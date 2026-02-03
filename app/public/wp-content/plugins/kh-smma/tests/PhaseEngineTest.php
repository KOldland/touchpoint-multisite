<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/Services/PhaseEngine.php';

class PhaseEngineTest extends TestCase {
    public function test_import_event_catalog_replaces_rows() {
        $csv = sys_get_temp_dir() . '/kh-smma-event-catalog-test.csv';
        file_put_contents(
            $csv,
            "event_id,label,points,phase_tag,biases,default_decay_days\n" .
            "event_one,Event One,5,Attention,authority|relevance,10\n" .
            "event_two,Event Two,9,Anxiety,consistency,20\n"
        );

        $db = new PhaseEngineTestWpdb();
        $engine = new \KH_SMMA\Services\PhaseEngine( $db );

        $count = $engine->import_event_catalog( $csv );

        $this->assertSame( 2, $count );
        $this->assertCount( 2, $db->replaced );
        $this->assertSame( 'event_one', $db->replaced[0]['event_id'] );
        $this->assertSame( 5, $db->replaced[0]['points'] );
        $this->assertSame( 'event_two', $db->replaced[1]['event_id'] );

        unlink( $csv );
    }

    public function test_get_user_phase_returns_cached_row() {
        $db = new PhaseEngineTestWpdb();
        $db->rows['row'] = array(
            'user_id' => 55,
            'attention_points' => 12.5,
            'antagonistic_points' => 3.0,
            'anxiety_points' => 0.0,
            'acceptance_points' => 1.2,
            'assigned_phase' => 'Attention',
            'norm_scores' => wp_json_encode( array(
                'Attention' => 0.17,
                'Antagonistic' => 0.03,
                'Anxiety' => 0.0,
                'Acceptance' => 0.01,
            ) ),
            'top_events' => wp_json_encode( array(
                array(
                    'event_id' => 'portal_dashboard_view',
                    'points' => 2,
                    'decayed_points' => 1.9,
                    'phase_tag' => 'Attention',
                    'biases' => array( 'authority' ),
                    'ts' => '2026-02-01 00:00:00',
                ),
            ) ),
            'computed_at' => date( 'Y-m-d H:i:s' ),
        );

        $engine = new \KH_SMMA\Services\PhaseEngine( $db );
        $result = $engine->get_user_phase( 55 );

        $this->assertSame( 55, $result['user_id'] );
        $this->assertSame( 'Attention', $result['assigned_phase'] );
        $this->assertSame( 12.5, $result['scores']['Attention'] );
        $this->assertCount( 1, $result['top_events'] );
    }
}

class PhaseEngineTestWpdb {
    public $prefix = 'wp_';
    public $replaced = array();
    public $rows = array();

    public function get_charset_collate() {
        return '';
    }

    public function replace( $table, $data, $format = array() ) {
        $this->replaced[] = $data;
        return true;
    }

    public function get_var( $query ) {
        return $this->rows['var'] ?? null;
    }

    public function get_row( $query, $output = ARRAY_A ) {
        return $this->rows['row'] ?? null;
    }

    public function get_results( $query, $output = ARRAY_A ) {
        return $this->rows['results'] ?? array();
    }

    public function insert( $table, $data, $format = array() ) {
        return true;
    }

    public function update( $table, $data, $where ) {
        return true;
    }

    public function get_col( $query ) {
        return $this->rows['col'] ?? array();
    }

    public function prepare( $query, ...$args ) {
        return $query;
    }
}
