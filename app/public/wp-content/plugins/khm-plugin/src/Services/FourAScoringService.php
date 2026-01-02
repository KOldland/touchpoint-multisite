<?php

namespace KHM\Services;

/**
 * Applies the deterministic 4A scoring model and persists person/company rollups.
 */
class FourAScoringService {

	private const PERSON_LOOKBACK_DAYS   = 120;
	private const STAGE_LOOKBACK_DAYS    = 45;
	private const COMPANY_ENGAGE_DAYS    = 21;
	private const DEFAULT_MEDIAN_SCROLL = 80;
	private const DEFAULT_MEDIAN_DWELL  = 45;
	private const DEFAULT_MEDIAN_PCT    = 100;

	private \wpdb $wpdb;
	private string $events_table;
	private string $person_table;
	private string $company_table;
	private string $weights_table;

	/**
	 * @var array<string,array{base:float,category:string,stage:string}>
	 */
	private array $weights = array();

	/**
	 * Cache of actor topic preferences keyed by email.
	 *
	 * @var array<string,string[]>
	 */
	private array $actor_topics_cache = array();

	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;

		$this->wpdb          = $db ?: $wpdb;
		$this->events_table  = $this->resolve_table_name( 'cp_events' );
		$this->person_table  = $this->resolve_table_name( 'cp_scores_person' );
		$this->company_table = $this->resolve_table_name( 'cp_scores_company' );
		$this->weights_table = $this->resolve_table_name( 'cp_weights' );

		$this->weights = $this->load_weights();
	}

	/**
	 * Execute the recompute cycle.
	 *
	 * @param int $window_seconds Only actors/companies with events ingested within this window are recalculated.
	 * @return array{actors:int,companies:int}
	 */
	public function run( int $window_seconds = 7200 ): array {
		$now          = time();
		$window_start = gmdate( 'Y-m-d H:i:s', $now - $window_seconds );

		$actor_emails   = $this->get_candidate_actors( $window_start );
		$company_domains = $this->get_candidate_companies( $window_start );

		foreach ( $actor_emails as $email ) {
			$this->process_actor( $email, $now );
		}

		foreach ( $company_domains as $domain ) {
			$this->process_company( $domain, $now );
		}

		return array(
			'actors'   => count( $actor_emails ),
			'companies'=> count( $company_domains ),
		);
	}

	private function process_actor( string $email, int $now ): void {
		$events = $this->get_events_for_actor( $email );
		if ( empty( $events ) ) {
			return;
		}

		$events_30d = array_filter(
			$events,
			function ( $event ) use ( $now ) {
				return strtotime( (string) $event['occurred_at'] ) >= $now - ( 30 * DAY_IN_SECONDS );
			}
		);

		$event_count_30d = count( $events_30d );
		$actor_topics    = $this->get_actor_topics( $email );

		$total_score = 0.0;
		$scored_events = array();

		foreach ( $events as $event ) {
			$score = $this->calculate_event_score( $event, $event_count_30d, $actor_topics, $now );
			$total_score += $score;
			$event['score'] = $score;
			$scored_events[] = $event;
		}

		$stage = $this->infer_stage( $scored_events );

		$last_touch = $this->get_last_touch( $scored_events );

		$mql_flag = ( $total_score >= 30 ) && in_array( $stage, array( 'diagnosis', 'solution' ), true );
		$sql_flag = ( $total_score >= 60 ) || $this->has_recent_pos_event( $scored_events );

		$this->upsert_person_score(
			array(
				'actor_email'  => strtolower( $email ),
				'score_date'   => gmdate( 'Y-m-d' ),
				'person_score' => round( $total_score, 2 ),
				'stage'        => $stage,
				'last_touch'   => $last_touch['touchpoint'] ?? null,
				'last_touch_at'=> $last_touch['occurred_at'] ?? null,
				'mql_flag'     => $mql_flag ? 1 : 0,
				'sql_flag'     => $sql_flag ? 1 : 0,
			)
		);
	}

	private function process_company( string $domain, int $now ): void {
		$events = $this->get_events_for_company( $domain );
		if ( empty( $events ) ) {
			return;
		}

		$total_score = 0.0;
		$contacts_21d = array();
		$events_30d_count = $this->count_events_in_window( $events, $now - ( 30 * DAY_IN_SECONDS ) );

		foreach ( $events as $event ) {
			$event_score = $this->calculate_event_score(
				$event,
				$events_30d_count,
				array(), // company does not leverage actor topics
				$now
			);
			$total_score += $event_score;
			$event['score'] = $event_score;
			if ( ! empty( $event['actor_email'] ) && strtotime( (string) $event['occurred_at'] ) >= $now - ( self::COMPANY_ENGAGE_DAYS * DAY_IN_SECONDS ) ) {
				$contacts_21d[ strtolower( $event['actor_email'] ) ] = true;
			}
		}

		$stage_mode = $this->infer_stage( $events );

		$engaged_contacts = count( $contacts_21d );
		$hot_flag = ( $total_score >= 120 ) && $engaged_contacts >= 3;

		$this->upsert_company_score(
			array(
				'company_domain' => strtolower( $domain ),
				'score_date'     => gmdate( 'Y-m-d' ),
				'company_score'  => round( $total_score, 2 ),
				'stage_mode'     => $stage_mode,
				'engaged_contacts' => $engaged_contacts,
				'hot_flag'       => $hot_flag ? 1 : 0,
				'hot_since'      => $hot_flag ? gmdate( 'Y-m-d' ) : null,
			)
		);
	}

	private function calculate_event_score( array $event, int $events_30d, array $actor_topics, int $now ): float {
		$touchpoint = sanitize_key( $event['touchpoint'] ?? 'unknown' );
		$weight_row = $this->weights[ $touchpoint ] ?? array(
			'base'    => 8.0,
			'stage'   => 'attention',
			'category'=> 'low',
		);

		$base  = (float) $weight_row['base'];
		$freq  = 1 + log1p( max( 0, $events_30d ) );
		$depth = $this->calculate_depth_multiplier( $event );
		$topic = $this->calculate_topic_multiplier( $event, $actor_topics );
		$decay = $this->calculate_decay_multiplier( $event, $now );

		return $base * $freq * $depth * $topic * $decay;
	}

	private function calculate_depth_multiplier( array $event ): float {
		$scroll = isset( $event['depth_scroll'] ) ? (float) $event['depth_scroll'] : 0.0;
		$dwell  = isset( $event['depth_dwell_sec'] ) ? (float) $event['depth_dwell_sec'] : 0.0;
		$pct    = isset( $event['depth_pct_complete'] ) ? (float) $event['depth_pct_complete'] : 0.0;

		$score =
			0.25 * $this->ratio( $scroll, self::DEFAULT_MEDIAN_SCROLL ) +
			0.25 * $this->ratio( $dwell, self::DEFAULT_MEDIAN_DWELL ) +
			0.50 * $this->ratio( $pct, self::DEFAULT_MEDIAN_PCT );

		return min( 1.2, max( 0.5, $score ) );
	}

	private function calculate_topic_multiplier( array $event, array $actor_topics ): float {
		if ( empty( $actor_topics ) ) {
			return 1.0;
		}

		$event_topics = $event['topic_tax'] ?? null;
		if ( is_string( $event_topics ) ) {
			$decoded = json_decode( $event_topics, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$event_topics = $decoded;
			}
		}

		if ( empty( $event_topics ) || ! is_array( $event_topics ) ) {
			return 1.0;
		}

		foreach ( $event_topics as $topic ) {
			$topic = strtolower( sanitize_text_field( (string) $topic ) );
			if ( in_array( $topic, $actor_topics, true ) ) {
				return 1.2;
			}
		}

		return 1.0;
	}

	private function calculate_decay_multiplier( array $event, int $now ): float {
		$occurred = strtotime( (string) $event['occurred_at'] );
		if ( ! $occurred ) {
			$occurred = $now;
		}

		$days_since = max( 0, ( $now - $occurred ) / DAY_IN_SECONDS );

		return pow( 0.9, $days_since / 7 );
	}

	private function infer_stage( array $events ): string {
		$cutoff = time() - ( self::STAGE_LOOKBACK_DAYS * DAY_IN_SECONDS );
		$count  = array();
		$acceptance = false;

		foreach ( $events as $event ) {
			$occurred = strtotime( (string) $event['occurred_at'] );
			if ( $occurred < $cutoff ) {
				continue;
			}

			$touchpoint = sanitize_key( $event['touchpoint'] ?? 'unknown' );
			$weight_row = $this->weights[ $touchpoint ] ?? array(
				'stage'   => 'attention',
				'category'=> 'low',
			);

			if ( isset( $weight_row['category'] ) && 'pos' === $weight_row['category'] ) {
				$acceptance = true;
			}

			$stage = strtolower( $event['stage_hint'] ?? $weight_row['stage'] ?? 'attention' );
			$count[ $stage ] = ( $count[ $stage ] ?? 0 ) + 1;
		}

		if ( $acceptance ) {
			return 'acceptance';
		}

		if ( empty( $count ) ) {
			return 'attention';
		}

		arsort( $count );
		return (string) array_key_first( $count );
	}

	private function has_recent_pos_event( array $events ): bool {
		$cutoff = time() - ( 14 * DAY_IN_SECONDS );
		foreach ( $events as $event ) {
			$touchpoint = sanitize_key( $event['touchpoint'] ?? 'unknown' );
			$weight_row = $this->weights[ $touchpoint ] ?? null;
			if ( $weight_row && 'pos' === $weight_row['category'] && strtotime( (string) $event['occurred_at'] ) >= $cutoff ) {
				return true;
			}
		}
		return false;
	}

	private function get_last_touch( array $events ): ?array {
		if ( empty( $events ) ) {
			return null;
		}

		usort(
			$events,
			function ( $a, $b ) {
				return strtotime( (string) $b['occurred_at'] ) <=> strtotime( (string) $a['occurred_at'] );
			}
		);

		return $events[0];
	}

	private function upsert_person_score( array $data ): void {
		$sql = $this->wpdb->prepare(
			"INSERT INTO {$this->person_table}
			(actor_email, score_date, person_score, stage, last_touch, last_touch_at, mql_flag, sql_flag, nba_recommendation, created_at, updated_at)
			VALUES (%s,%s,%f,%s,%s,%s,%d,%d,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP())
			ON DUPLICATE KEY UPDATE
				person_score = VALUES(person_score),
				stage = VALUES(stage),
				last_touch = VALUES(last_touch),
				last_touch_at = VALUES(last_touch_at),
				mql_flag = VALUES(mql_flag),
				sql_flag = VALUES(sql_flag),
				nba_recommendation = VALUES(nba_recommendation),
				updated_at = VALUES(updated_at)",
			$data['actor_email'],
			$data['score_date'],
			$data['person_score'],
			$data['stage'],
			$data['last_touch'],
			$data['last_touch_at'],
			(int) $data['mql_flag'],
			(int) $data['sql_flag'],
			'[]'
		);

		$this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function upsert_company_score( array $data ): void {
		$sql = $this->wpdb->prepare(
			"INSERT INTO {$this->company_table}
			(company_domain, score_date, company_score, stage_mode, engaged_contacts, hot_flag, hot_since, nba_recommendation, created_at, updated_at)
			VALUES (%s,%s,%f,%s,%d,%d,%s,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP())
			ON DUPLICATE KEY UPDATE
				company_score = VALUES(company_score),
				stage_mode = VALUES(stage_mode),
				engaged_contacts = VALUES(engaged_contacts),
				hot_flag = VALUES(hot_flag),
				hot_since = VALUES(hot_since),
				nba_recommendation = VALUES(nba_recommendation),
				updated_at = VALUES(updated_at)",
			$data['company_domain'],
			$data['score_date'],
			$data['company_score'],
			$data['stage_mode'],
			(int) $data['engaged_contacts'],
			(int) $data['hot_flag'],
			$data['hot_since'],
			'[]'
		);

		$this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function get_candidate_actors( string $window_start ): array {
		return $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT DISTINCT actor_email FROM {$this->events_table} WHERE actor_email IS NOT NULL AND ingested_at >= %s",
				$window_start
			)
		);
	}

	private function get_candidate_companies( string $window_start ): array {
		return $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT DISTINCT company_domain FROM {$this->events_table} WHERE company_domain IS NOT NULL AND ingested_at >= %s",
				$window_start
			)
		);
	}

	private function get_events_for_actor( string $email ): array {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::PERSON_LOOKBACK_DAYS * DAY_IN_SECONDS ) );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->events_table} WHERE actor_email = %s AND occurred_at >= %s ORDER BY occurred_at ASC",
				$email,
				$cutoff
			),
			ARRAY_A
		);
	}

	private function get_events_for_company( string $domain ): array {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::PERSON_LOOKBACK_DAYS * DAY_IN_SECONDS ) );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->events_table} WHERE company_domain = %s AND occurred_at >= %s ORDER BY occurred_at ASC",
				$domain,
				$cutoff
			),
			ARRAY_A
		);
	}

	private function load_weights(): array {
		$rows = $this->wpdb->get_results(
			"SELECT touchpoint, base_weight, stage_default, category FROM {$this->weights_table} WHERE is_active = 1",
			ARRAY_A
		);

		$map = array();
		if ( empty( $rows ) ) {
			return $map;
		}

		foreach ( $rows as $row ) {
			$map[ sanitize_key( $row['touchpoint'] ) ] = array(
				'base'     => (float) $row['base_weight'],
				'stage'    => strtolower( $row['stage_default'] ?? 'attention' ),
				'category' => strtolower( $row['category'] ?? 'low' ),
			);
		}

		return $map;
	}

	private function get_actor_topics( string $email ): array {
		$key = strtolower( $email );
		if ( isset( $this->actor_topics_cache[ $key ] ) ) {
			return $this->actor_topics_cache[ $key ];
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return $this->actor_topics_cache[ $key ] = array();
		}

		$raw = get_user_meta( $user->ID, 'khm_topic_interests', true );
		if ( empty( $raw ) ) {
			return $this->actor_topics_cache[ $key ] = array();
		}

		if ( is_string( $raw ) && $this->looks_like_json( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				$topics = array_map(
					function ( $topic ) {
						return strtolower( sanitize_text_field( (string) $topic ) );
					},
					$decoded
				);
				return $this->actor_topics_cache[ $key ] = array_filter( $topics );
			}
		}

		if ( is_string( $raw ) ) {
			$parts = array_map( 'trim', explode( ',', $raw ) );
			$topics = array_map(
				function ( $topic ) {
					return strtolower( sanitize_text_field( (string) $topic ) );
				},
				$parts
			);
			return $this->actor_topics_cache[ $key ] = array_filter( $topics );
		}

		return $this->actor_topics_cache[ $key ] = array();
	}

	private function resolve_table_name( string $base ): string {
		$prefixed = $this->wpdb->prefix . $base;
		$found    = $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $prefixed ) );
		if ( $found === $prefixed ) {
			return $prefixed;
		}

		$bare = $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $base ) );
		return ( $bare === $base ) ? $base : $prefixed;
	}

	private function ratio( float $value, float $median ): float {
		if ( $median <= 0 ) {
			return 0.0;
		}
		return $value / $median;
	}

	private function looks_like_json( string $value ): bool {
		return 0 === strpos( trim( $value ), '[' ) || 0 === strpos( trim( $value ), '{' );
	}

	private function count_events_in_window( array $events, int $cutoff_ts ): int {
		$count = 0;
		foreach ( $events as $event ) {
			if ( strtotime( (string) $event['occurred_at'] ) >= $cutoff_ts ) {
				$count++;
			}
		}
		return $count;
	}
}
