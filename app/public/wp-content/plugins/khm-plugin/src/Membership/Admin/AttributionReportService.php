<?php

namespace KHM\Membership\Admin;

class AttributionReportService {
	private string $tableName;
	private string $postsTable;
	private string $sponsorsTable;
	private bool $hasPostsTable;
	private bool $hasSponsorsTable;
	private int $exportTtlSeconds = 86400;

	public function __construct() {
		global $wpdb;

		$this->tableName      = $wpdb->prefix . 'promotion_attribution';
		$this->postsTable     = isset( $wpdb->posts ) && is_string( $wpdb->posts ) && $wpdb->posts !== '' ? $wpdb->posts : $wpdb->prefix . 'posts';
		$this->sponsorsTable  = $wpdb->prefix . 'khm_sponsors';
		$this->hasPostsTable    = $this->table_exists( $this->postsTable );
		$this->hasSponsorsTable = $this->table_exists( $this->sponsorsTable );
	}

	/**
	 * @param array<string,mixed> $filters
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
	 */
	public function query( array $filters, int $page, int $per_page ): array {
		global $wpdb;

		$page     = max( 1, $page );
		$per_page = max( 1, min( 200, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where = [];
		$values = [];
		$this->append_filter_where_sql( $filters, $where, $values );
		$where_sql = empty( $where ) ? '' : 'WHERE ' . implode( ' AND ', $where );

		$select = [
			'p.id',
			'p.schedule_id',
			'p.sponsor_id',
			'p.user_id',
			'p.user_email',
			'p.utm_source',
			'p.utm_medium',
			'p.utm_campaign',
			'p.phase_at_click',
			'p.conversion_type',
			'p.reference_metadata',
			'p.created_at',
		];

		if ( $this->hasPostsTable ) {
			$select[] = 'sp.post_title AS schedule_title';
		} else {
			$select[] = 'NULL AS schedule_title';
		}

		if ( $this->hasSponsorsTable ) {
			$select[] = 's.name AS sponsor_name';
		} else {
			$select[] = 'NULL AS sponsor_name';
		}

		$sql = 'SELECT ' . implode( ', ', $select ) . " FROM {$this->tableName} p";

		if ( $this->hasPostsTable ) {
			$sql .= " LEFT JOIN {$this->postsTable} sp ON sp.ID = p.schedule_id";
		}

		if ( $this->hasSponsorsTable ) {
			$sql .= " LEFT JOIN {$this->sponsorsTable} s ON s.id = p.sponsor_id";
		}

		$sql .= " {$where_sql} ORDER BY p.created_at DESC, p.id DESC LIMIT %d OFFSET %d";

		$items = $wpdb->get_results(
			$wpdb->prepare( $sql, array_merge( $values, [ $per_page, $offset ] ) ),
			ARRAY_A
		);

		$count_sql = "SELECT COUNT(*) FROM {$this->tableName} p {$where_sql}";
		$total = (int) $wpdb->get_var( empty( $values ) ? $count_sql : $wpdb->prepare( $count_sql, $values ) );

		return [
			'items' => $items ?: [],
			'total' => $total,
			'page' => $page,
			'per_page' => $per_page,
			'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
		];
	}

	/**
	 * @param array<string,mixed> $filters
	 * @return array{total:int,paid:int,signup:int,no_consent:int,unique_users:int}
	 */
	public function get_kpis( array $filters ): array {
		global $wpdb;

		$where = [];
		$values = [];
		$this->append_filter_where_sql( $filters, $where, $values );
		$where_sql = empty( $where ) ? '' : 'WHERE ' . implode( ' AND ', $where );

		$sql = "SELECT
			COUNT(*) AS total,
			SUM(CASE WHEN p.conversion_type = 'paid' THEN 1 ELSE 0 END) AS paid,
			SUM(CASE WHEN p.conversion_type = 'signup' THEN 1 ELSE 0 END) AS signup,
			SUM(CASE WHEN p.conversion_type LIKE '%no_consent%' THEN 1 ELSE 0 END) AS no_consent,
			COUNT(DISTINCT p.user_id) AS unique_users
			FROM {$this->tableName} p
			{$where_sql}";

		$row = $wpdb->get_row( empty( $values ) ? $sql : $wpdb->prepare( $sql, $values ), ARRAY_A );
		$row = is_array( $row ) ? $row : [];

		return [
			'total' => (int) ( $row['total'] ?? 0 ),
			'paid' => (int) ( $row['paid'] ?? 0 ),
			'signup' => (int) ( $row['signup'] ?? 0 ),
			'no_consent' => (int) ( $row['no_consent'] ?? 0 ),
			'unique_users' => (int) ( $row['unique_users'] ?? 0 ),
		];
	}

	/**
	 * @param array<string,mixed> $filters
	 * @return array{file:string,filename:string,checksum:string,rows:int}
	 */
	public function create_csv_export( array $filters ): array {
		$all = $this->query( $filters, 1, 2000 );
		$rows = $all['items'];

		$dir = $this->get_export_dir();
		$this->cleanup_old_exports( $dir );

		$timestamp = gmdate( 'Ymd-His' );
		$filename  = 'membership-attribution-' . $timestamp . '.csv';
		$file_path = trailingslashit( $dir ) . $filename;

		$handle = fopen( $file_path, 'w' );
		if ( false === $handle ) {
			throw new \RuntimeException( 'Unable to create export file.' );
		}

		fputcsv( $handle, [ 'id', 'schedule_id', 'schedule_title', 'sponsor_id', 'sponsor_name', 'user_id', 'user_email', 'utm_source', 'utm_medium', 'utm_campaign', 'phase_at_click', 'conversion_type', 'created_at' ] );

		$written = 0;
		foreach ( $rows as $row ) {
			$clean = $this->apply_consent_redaction( $row );
			fputcsv(
				$handle,
				[
					(string) ( $clean['id'] ?? '' ),
					(string) ( $clean['schedule_id'] ?? '' ),
					(string) ( $clean['schedule_title'] ?? '' ),
					(string) ( $clean['sponsor_id'] ?? '' ),
					(string) ( $clean['sponsor_name'] ?? '' ),
					(string) ( $clean['user_id'] ?? '' ),
					(string) ( $clean['user_email'] ?? '' ),
					(string) ( $clean['utm_source'] ?? '' ),
					(string) ( $clean['utm_medium'] ?? '' ),
					(string) ( $clean['utm_campaign'] ?? '' ),
					(string) ( $clean['phase_at_click'] ?? '' ),
					(string) ( $clean['conversion_type'] ?? '' ),
					(string) ( $clean['created_at'] ?? '' ),
				]
			);
			$written++;
		}

		fclose( $handle );

		$checksum = hash_file( 'sha256', $file_path ) ?: '';

		return [
			'file' => $file_path,
			'filename' => $filename,
			'checksum' => $checksum,
			'rows' => $written,
		];
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	public function apply_consent_redaction( array $row ): array {
		$conversion_type = isset( $row['conversion_type'] ) ? (string) $row['conversion_type'] : '';
		$metadata_raw = isset( $row['reference_metadata'] ) ? (string) $row['reference_metadata'] : '';
		$metadata = json_decode( $metadata_raw, true );
		$metadata = is_array( $metadata ) ? $metadata : [];

		$has_consent = true;
		if ( strpos( $conversion_type, 'no_consent' ) !== false ) {
			$has_consent = false;
		}
		if ( array_key_exists( 'consent', $metadata ) ) {
			$has_consent = ! empty( $metadata['consent'] );
		}

		if ( ! $has_consent ) {
			$row['user_id'] = '';
			$row['user_email'] = '';
			$row['utm_source'] = '';
			$row['utm_medium'] = '';
			$row['utm_campaign'] = '';
		}

		return $row;
	}

	/**
	 * @param array<string,mixed> $filters
	 * @param array<int,string> $where
	 * @param array<int,mixed> $values
	 */
	private function append_filter_where_sql( array $filters, array &$where, array &$values ): void {
		global $wpdb;

		$schedule_id = isset( $filters['schedule_id'] ) ? absint( $filters['schedule_id'] ) : 0;
		if ( $schedule_id > 0 ) {
			$where[] = 'p.schedule_id = %d';
			$values[] = $schedule_id;
		}

		$sponsor_id = isset( $filters['sponsor_id'] ) ? absint( $filters['sponsor_id'] ) : 0;
		if ( $sponsor_id > 0 ) {
			$where[] = 'p.sponsor_id = %d';
			$values[] = $sponsor_id;
		}

		$conversion_type = isset( $filters['conversion_type'] ) ? sanitize_key( (string) $filters['conversion_type'] ) : '';
		if ( $conversion_type !== '' ) {
			$where[] = 'p.conversion_type = %s';
			$values[] = $conversion_type;
		}

		$user_id = isset( $filters['user_id'] ) ? absint( $filters['user_id'] ) : 0;
		if ( $user_id > 0 ) {
			$where[] = 'p.user_id = %d';
			$values[] = $user_id;
		}

		$date_from = isset( $filters['date_from'] ) ? sanitize_text_field( (string) $filters['date_from'] ) : '';
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$where[] = 'DATE(p.created_at) >= %s';
			$values[] = $date_from;
		}

		$date_to = isset( $filters['date_to'] ) ? sanitize_text_field( (string) $filters['date_to'] ) : '';
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$where[] = 'DATE(p.created_at) <= %s';
			$values[] = $date_to;
		}

		$q = isset( $filters['q'] ) ? sanitize_text_field( (string) $filters['q'] ) : '';
		if ( $q !== '' ) {
			$like = '%' . $wpdb->esc_like( $q ) . '%';
			$where[] = '(p.user_email LIKE %s OR p.utm_source LIKE %s OR p.utm_campaign LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}
	}

	private function get_export_dir(): string {
		$uploads = wp_upload_dir();
		$base = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : sys_get_temp_dir();
		$dir = trailingslashit( $base ) . 'khm-membership-exports';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	private function cleanup_old_exports( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$cutoff = time() - $this->exportTtlSeconds;
		$files = glob( trailingslashit( $dir ) . '*.csv' );
		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			$mtime = @filemtime( $file );
			if ( false === $mtime || $mtime > $cutoff ) {
				continue;
			}
			@unlink( $file );
		}
	}

	private function table_exists( string $table ): bool {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}
}
