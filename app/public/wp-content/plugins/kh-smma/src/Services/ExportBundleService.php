<?php
declare( strict_types=1 );

namespace KH_SMMA\Services;

use RuntimeException;

use function basename;
use function file_exists;
use function file_put_contents;
use function filesize;
use function get_option;
use function gmdate;
use function glob;
use function is_array;
use function is_dir;
use function json_encode;
use function max;
use function mkdir;
use function rtrim;
use function sanitize_file_name;
use function sprintf;
use function strtotime;
use function time;
use function unlink;
use function update_option;
use function wp_generate_uuid4;
use function wp_json_encode;
use function wp_mkdir_p;
use function wp_upload_dir;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ExportBundleService {
    private const OPTION_PREFIX = 'kh_smma_export_bundle_';

    private string $base_dir;
    private int $ttl_seconds;

    public function __construct( ?string $base_dir = null, int $ttl_seconds = 86400 ) {
        $this->ttl_seconds = $ttl_seconds;
        $this->base_dir = $base_dir ?? $this->resolve_base_dir();
    }

    public function create_bundle( array $schedule, array $variant = array(), array $assets = array() ): array {
        $schedule_id = (string) ( $schedule['schedule_id'] ?? '' );
        if ( '' === $schedule_id ) {
            throw new RuntimeException( 'schedule_id is required to create export bundle.' );
        }

        $variant_id = (string) ( $schedule['variant_id'] ?? ( $variant['variant_id'] ?? '' ) );
        $post_text = (string) ( $variant['linkedIn']['text'] ?? $variant['text'] ?? '' );
        $estimate = $this->estimate_spend( $schedule );
        $manifest = array(
            'schedule_id' => $schedule_id,
            'variant_id' => $variant_id,
            'platform' => 'manual',
            'post_text' => $post_text,
            'estimated_spend' => $estimate['estimated_spend'],
            'estimated_ops' => $estimate['estimated_ops'],
            'compliance_status' => (string) ( $schedule['compliance_status'] ?? 'OK' ),
            'approval_status' => (string) ( $schedule['approval_status'] ?? 'approved' ),
            'created_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
        );

        $this->cleanup_expired_files();
        $work_dir = $this->trail( $this->base_dir ) . 'tmp_' . $schedule_id . '_' . wp_generate_uuid4();
        $assets_dir = $this->trail( $work_dir ) . 'assets';
        $this->mkdir_recursive( $assets_dir );

        $this->write_file( $this->trail( $work_dir ) . 'manifest.json', $this->encode_json( $manifest ) );
        $this->write_file( $this->trail( $work_dir ) . 'variant_text.txt', $post_text );
        foreach ( $assets as $asset ) {
            if ( ! is_array( $asset ) ) {
                continue;
            }
            $name = (string) ( $asset['filename'] ?? '' );
            $content = (string) ( $asset['content'] ?? '' );
            if ( '' === $name ) {
                continue;
            }
            $safe = $this->safe_filename( $name );
            $this->write_file( $this->trail( $assets_dir ) . $safe, $content );
        }

        $zip_name = 'schedule_export_' . $schedule_id . '.zip';
        $zip_path = $this->trail( $this->base_dir ) . $zip_name;
        $this->zip_directory( $work_dir, $zip_path );

        $bundle = array(
            'schedule_id' => $schedule_id,
            'variant_id' => $variant_id,
            'manifest' => $manifest,
            'file_name' => $zip_name,
            'file_path' => $zip_path,
            'bundle_size' => file_exists( $zip_path ) ? (int) filesize( $zip_path ) : 0,
            'created_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
        );

        update_option( self::OPTION_PREFIX . $schedule_id, $bundle, false );
        return $bundle;
    }

    public function get_bundle( string $schedule_id ): array {
        $bundle = get_option( self::OPTION_PREFIX . $schedule_id );
        return is_array( $bundle ) ? $bundle : array();
    }

    public function estimate_spend( array $schedule ): array {
        $boost_options = (array) ( $schedule['boost_options'] ?? array() );
        $budget_cents = max( 0, (int) ( $boost_options['budget_cents'] ?? 0 ) );
        $channels = (array) ( $boost_options['channels'] ?? array( 'linkedin' ) );
        $estimated_ops = max( 1, count( $channels ) );

        $schedule_time = (string) ( $schedule['schedule_time'] ?? '' );
        $days = 1;
        if ( '' !== $schedule_time ) {
            $ts = strtotime( $schedule_time );
            if ( false !== $ts && $ts > time() ) {
                $days = max( 1, (int) ceil( ( $ts - time() ) / 86400 ) );
            }
        }

        return array(
            'estimated_spend' => round( ( $budget_cents / 100 ) * $days, 2 ),
            'estimated_ops' => $estimated_ops,
        );
    }

    private function resolve_base_dir(): string {
        $uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : array();
        $base = (string) ( $uploads['basedir'] ?? sys_get_temp_dir() );
        $dir = $this->trail( $base ) . 'smma_exports';
        $this->mkdir_recursive( $dir );
        return $dir;
    }

    private function cleanup_expired_files(): void {
        $pattern = $this->trail( $this->base_dir ) . 'schedule_export_*.zip';
        foreach ( glob( $pattern ) ?: array() as $file ) {
            $mtime = @filemtime( $file );
            if ( false !== $mtime && ( time() - $mtime ) > $this->ttl_seconds ) {
                @unlink( $file );
            }
        }
    }

    private function zip_directory( string $source_dir, string $zip_path ): void {
        if ( ! class_exists( '\ZipArchive' ) ) {
            throw new RuntimeException( 'ZipArchive extension is required for manual export.' );
        }

        $zip = new \ZipArchive();
        if ( true !== $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
            throw new RuntimeException( 'Unable to create export zip archive.' );
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $source_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ( $files as $file ) {
            if ( $file->isDir() ) {
                continue;
            }
            $path = (string) $file->getRealPath();
            $local = ltrim( (string) str_replace( $source_dir, '', $path ), DIRECTORY_SEPARATOR );
            $zip->addFile( $path, $local );
        }
        $zip->close();
    }

    private function mkdir_recursive( string $dir ): void {
        if ( is_dir( $dir ) ) {
            return;
        }
        if ( function_exists( 'wp_mkdir_p' ) ) {
            wp_mkdir_p( $dir );
            return;
        }
        mkdir( $dir, 0775, true );
    }

    private function write_file( string $path, string $content ): void {
        $dir = rtrim( (string) dirname( $path ), DIRECTORY_SEPARATOR );
        $this->mkdir_recursive( $dir );
        file_put_contents( $path, $content );
    }

    private function encode_json( array $payload ): string {
        if ( function_exists( 'wp_json_encode' ) ) {
            return (string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        }
        return (string) json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    }

    private function safe_filename( string $name ): string {
        if ( function_exists( 'sanitize_file_name' ) ) {
            $safe = sanitize_file_name( $name );
            return '' !== $safe ? $safe : basename( $name );
        }
        return preg_replace( '/[^A-Za-z0-9._-]/', '_', $name ) ?: basename( $name );
    }

    private function trail( string $path ): string {
        if ( function_exists( 'trailingslashit' ) ) {
            return trailingslashit( $path );
        }
        return rtrim( $path, '/\\' ) . DIRECTORY_SEPARATOR;
    }
}
