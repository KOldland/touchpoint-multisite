<?php

namespace KH\Planner\Agents;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ExportPDFHelper
 *
 * Shared PDF generation class for editorial planner exports.
 * Uses DomPDF (same as KHM PDFService) for reliable HTML-to-PDF conversion.
 */
class ExportPDFHelper {

    /**
     * Generate PDF from HTML content
     *
     * @param string $html The HTML content to convert
     * @param string $filename The filename for the PDF (without extension)
     * @return array|false Array with file_url, file_path, filename, or false on failure
     */
    public function generate_pdf( $html, $filename ) {
        // Try to use DomPDF from khm-plugin first
        if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
            // Try to load from khm-plugin vendor
            $khm_vendor = WP_PLUGIN_DIR . '/khm-plugin/vendor/autoload.php';
            if ( file_exists( $khm_vendor ) ) {
                require_once $khm_vendor;
            }
        }

        if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
            error_log( 'ExportPDFHelper: DomPDF not available' );
            return false;
        }

        try {
            $options = new \Dompdf\Options();
            $options->set( 'defaultFont', 'DejaVu Sans' );
            $options->set( 'isHtml5ParserEnabled', true );
            $options->set( 'isPhpEnabled', true );
            $options->set( 'isRemoteEnabled', true );

            $dompdf = new \Dompdf\Dompdf( $options );
            $dompdf->loadHtml( $html );
            $dompdf->setPaper( 'A4', 'portrait' );
            $dompdf->render();

            $pdf_content = $dompdf->output();

            // Save to export directory
            $export_dir = $this->get_export_path( $filename . '.pdf' );
            file_put_contents( $export_dir, $pdf_content );

            $file_url = $this->get_export_url( $filename . '.pdf' );

            return [
                'file_url'  => $file_url,
                'file_path' => $export_dir,
                'filename'  => $filename . '.pdf',
                'size'      => strlen( $pdf_content ),
            ];

        } catch ( \Exception $e ) {
            error_log( 'ExportPDFHelper: PDF generation failed - ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Get export file path
     *
     * @param string $filename
     * @return string
     */
    private function get_export_path( $filename ) {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/editorial_exports';
        if ( ! file_exists( $export_dir ) ) {
            wp_mkdir_p( $export_dir );
        }
        return $export_dir . '/' . $filename;
    }

    /**
     * Get export file URL
     *
     * @param string $filename
     * @return string
     */
    private function get_export_url( $filename ) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/editorial_exports/' . $filename;
    }
}