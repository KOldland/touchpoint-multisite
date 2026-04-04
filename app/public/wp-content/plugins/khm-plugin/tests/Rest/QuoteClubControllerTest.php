<?php
/**
 * Tests for Quote Club REST controller.
 *
 * @package KHM\Tests\Rest
 */

namespace KHM\Tests\Rest;

use KHM\Rest\QuoteClubController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

class QuoteClubControllerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['khm_test_current_user_id'] = 1001;
        $GLOBALS['khm_test_current_user_caps'] = [
            'manage_options' => true,
            'edit_posts' => true,
        ];
        $GLOBALS['khm_test_transients'] = [];
        $GLOBALS['khm_test_options'] = [];
        $GLOBALS['khm_test_rest_routes'] = [];
        $GLOBALS['khm_test_actions_fired'] = [];
    }

    protected function tearDown(): void {
        $GLOBALS['khm_test_current_user_id'] = 0;
        $GLOBALS['khm_test_current_user_caps'] = [];
        $GLOBALS['khm_test_transients'] = [];
        $GLOBALS['khm_test_actions_fired'] = [];
        parent::tearDown();
    }

    public function test_tokenize_keywords_supports_and_and_or_modes(): void {
        $controller = new QuoteClubController();
        $ref = new \ReflectionClass( $controller );
        $method = $ref->getMethod( 'tokenize_keywords' );

        $andTokens = $method->invoke( $controller, 'Field Service Management AND Aviation, Manufacturing', 'AND' );
        $orTokens = $method->invoke( $controller, 'Field Service Management OR Aviation, Manufacturing', 'OR' );

        $this->assertSame( [ 'field service management', 'aviation', 'manufacturing' ], $andTokens );
        $this->assertSame( [ 'field service management', 'aviation', 'manufacturing' ], $orTokens );
    }

    public function test_normalize_list_meta_supports_json_and_csv(): void {
        $controller = new QuoteClubController();
        $ref = new \ReflectionClass( $controller );
        $method = $ref->getMethod( 'normalize_list_meta' );

        $jsonResult = $method->invoke( $controller, '["Aviation","Manufacturing"]' );
        $csvResult = $method->invoke( $controller, 'Field Service, Logistics' );

        $this->assertSame( [ 'Aviation', 'Manufacturing' ], $jsonResult );
        $this->assertSame( [ 'Field Service', 'Logistics' ], $csvResult );
    }

    public function test_submit_commentary_returns_402_when_editorial_credits_insufficient(): void {
        $controller = new class extends QuoteClubController {
            protected function consume_editorial_credits(int $user_id, int $credits_needed, string $session_id): bool {
                return false;
            }
        };

        $request = new WP_REST_Request( 'POST', '/khm/v1/portal/quoteclub/commentary' );
        $request->set_param( 'session_id', 'ep-abc123' );
        $request->set_param( 'question_id', 'q2' );
        $request->set_param( 'commentary_text', str_repeat( 'word ', 250 ) );
        $request->set_param( 'is_press_release', false );

        $response = $controller->submit_commentary( $request );

        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertSame( 402, $response->get_status() );
        $payload = $response->get_data();
        $this->assertSame( false, $payload['success'] );
        $this->assertSame( 'insufficient_editorial_credits', $payload['error'] );
    }

    public function test_submit_commentary_returns_402_when_press_release_credit_insufficient(): void {
        $controller = new class extends QuoteClubController {
            protected function consume_press_release_credit(int $user_id, string $session_id): bool {
                return false;
            }
        };

        $request = new WP_REST_Request( 'POST', '/khm/v1/portal/quoteclub/commentary' );
        $request->set_param( 'session_id', 'ep-press-1' );
        $request->set_param( 'question_id', 'q-pr' );
        $request->set_param( 'commentary_text', 'Press release commentary text.' );
        $request->set_param( 'is_press_release', true );

        $response = $controller->submit_commentary( $request );

        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertSame( 402, $response->get_status() );
        $payload = $response->get_data();
        $this->assertSame( false, $payload['success'] );
        $this->assertSame( 'insufficient_press_release_credits', $payload['error'] );
    }

    public function test_accept_team_invite_returns_410_when_invite_expired(): void {
        $GLOBALS['khm_test_current_user_id'] = 0;
        $token = 'expired_token_123';
        $GLOBALS['khm_test_options']['khm_sponsor_pending_invites'] = [
            [
                'sponsor_id' => 9,
                'email' => 'invitee@example.com',
                'first_name' => 'Test',
                'last_name' => 'User',
                'job_title' => 'VP Marketing',
                'membership_level' => 'sponsor',
                'token' => $token,
                'created_at' => gmdate('Y-m-d H:i:s', time() - 72 * HOUR_IN_SECONDS),
                'expires_at' => gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS),
            ],
        ];

        $controller = new QuoteClubController();
        $request = new WP_REST_Request( 'POST', '/khm/v1/sponsor/invite/accept' );
        $request->set_param( 'token', $token );
        $request->set_param( 'email', 'invitee@example.com' );

        $response = $controller->accept_team_invite( $request );
        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertSame( 410, $response->get_status() );
        $payload = $response->get_data();
        $this->assertSame( false, $payload['success'] );
        $this->assertSame( 'invite_expired', $payload['error'] );
    }

    public function test_accept_team_invite_success_then_reuse_returns_404(): void {
        $GLOBALS['khm_test_current_user_id'] = 0;
        $token = 'valid_token_abc';
        $invite = [
            'sponsor_id' => 22,
            'email' => 'invitee@example.com',
            'first_name' => 'Valid',
            'last_name' => 'Invitee',
            'job_title' => 'Director',
            'membership_level' => 'sponsor',
            'token' => $token,
            'created_at' => current_time('mysql'),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 24 * HOUR_IN_SECONDS),
        ];

        $GLOBALS['khm_test_options']['khm_sponsor_pending_invites'] = [ $invite ];
        $GLOBALS['khm_test_users_by']['email']['invitee@example.com'] = (object) [ 'ID' => 707 ];

        $controller = new class extends QuoteClubController {
            protected function add_user_to_sponsor_team( int $sponsor_id, int $user_id, array $invite, string $email ): bool {
                return true;
            }
        };

        $request = new WP_REST_Request( 'POST', '/khm/v1/sponsor/invite/accept' );
        $request->set_param( 'token', $token );
        $request->set_param( 'email', 'invitee@example.com' );

        $first = $controller->accept_team_invite( $request );
        $this->assertInstanceOf( WP_REST_Response::class, $first );
        $this->assertSame( 200, $first->get_status() );
        $firstPayload = $first->get_data();
        $this->assertSame( true, $firstPayload['success'] );
        $this->assertSame( 707, (int) $firstPayload['user_id'] );
        $this->assertSame( 22, (int) $firstPayload['sponsor_id'] );

        $this->assertIsArray( $GLOBALS['khm_test_actions_fired'] );
        $telemetryEvents = array_filter(
            $GLOBALS['khm_test_actions_fired'],
            static function ( $entry ) {
                return is_array( $entry ) && ( $entry['hook'] ?? '' ) === 'khm_quoteclub_invite_accepted';
            }
        );
        $this->assertCount( 1, $telemetryEvents );

        $pendingAfterFirst = get_option( 'khm_sponsor_pending_invites', [] );
        $this->assertIsArray( $pendingAfterFirst );
        $this->assertCount( 0, $pendingAfterFirst );

        $second = $controller->accept_team_invite( $request );
        $this->assertInstanceOf( WP_REST_Response::class, $second );
        $this->assertSame( 404, $second->get_status() );
        $secondPayload = $second->get_data();
        $this->assertSame( false, $secondPayload['success'] );
        $this->assertSame( 'invite_not_found', $secondPayload['error'] );
    }

    public function test_accept_team_invite_returns_409_when_lock_already_active(): void {
        $GLOBALS['khm_test_current_user_id'] = 0;
        $token = 'locked_token_123';
        $GLOBALS['khm_test_options']['khm_sponsor_pending_invites'] = [
            [
                'sponsor_id' => 22,
                'email' => 'invitee@example.com',
                'first_name' => 'Valid',
                'last_name' => 'Invitee',
                'job_title' => 'Director',
                'membership_level' => 'sponsor',
                'token' => $token,
                'created_at' => current_time('mysql'),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 24 * HOUR_IN_SECONDS),
            ],
        ];
        set_transient( 'khm_qc_invite_accept_lock_' . md5( $token ), 1, MINUTE_IN_SECONDS );

        $controller = new QuoteClubController();
        $request = new WP_REST_Request( 'POST', '/khm/v1/sponsor/invite/accept' );
        $request->set_param( 'token', $token );
        $request->set_param( 'email', 'invitee@example.com' );

        $response = $controller->accept_team_invite( $request );
        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertSame( 409, $response->get_status() );
        $payload = $response->get_data();
        $this->assertSame( false, $payload['success'] );
        $this->assertSame( 'invite_in_progress', $payload['error'] );
    }

    public function test_accept_team_invite_returns_403_on_email_mismatch(): void {
        $GLOBALS['khm_test_current_user_id'] = 0;
        $token = 'mismatch_token_42';
        $GLOBALS['khm_test_options']['khm_sponsor_pending_invites'] = [
            [
                'sponsor_id' => 22,
                'email' => 'invitee@example.com',
                'first_name' => 'Valid',
                'last_name' => 'Invitee',
                'job_title' => 'Director',
                'membership_level' => 'sponsor',
                'token' => $token,
                'created_at' => current_time('mysql'),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 24 * HOUR_IN_SECONDS),
            ],
        ];

        $controller = new QuoteClubController();
        $request = new WP_REST_Request( 'POST', '/khm/v1/sponsor/invite/accept' );
        $request->set_param( 'token', $token );
        $request->set_param( 'email', 'other@example.com' );

        $response = $controller->accept_team_invite( $request );
        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertSame( 403, $response->get_status() );
        $payload = $response->get_data();
        $this->assertSame( false, $payload['success'] );
        $this->assertSame( 'email_mismatch', $payload['error'] );
    }

    public function test_accept_team_invite_returns_403_on_sponsor_id_mismatch(): void {
        $GLOBALS['khm_test_current_user_id'] = 0;
        $token = 'sponsor_mismatch_1';
        $GLOBALS['khm_test_options']['khm_sponsor_pending_invites'] = [
            [
                'sponsor_id' => 22,
                'email' => 'invitee@example.com',
                'first_name' => 'Valid',
                'last_name' => 'Invitee',
                'job_title' => 'Director',
                'membership_level' => 'sponsor',
                'token' => $token,
                'created_at' => current_time('mysql'),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 24 * HOUR_IN_SECONDS),
            ],
        ];

        $controller = new QuoteClubController();
        $request = new WP_REST_Request( 'POST', '/khm/v1/sponsor/999/invite/accept' );
        $request->set_param( 'sponsor_id', 999 );
        $request->set_param( 'token', $token );
        $request->set_param( 'email', 'invitee@example.com' );

        $response = $controller->accept_team_invite( $request );
        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertSame( 403, $response->get_status() );
        $payload = $response->get_data();
        $this->assertSame( false, $payload['success'] );
        $this->assertSame( 'sponsor_mismatch', $payload['error'] );
    }

    public function test_update_commentary_status_returns_403_without_editorial_capability(): void {
        $controller = new QuoteClubController();
        $GLOBALS['khm_test_current_user_caps']['edit_posts'] = false;

        $request = new WP_REST_Request( 'PATCH', '/khm/v1/portal/quoteclub/commentary/19' );
        $request->set_param( 'id', 19 );
        $request->set_param( 'status', 'approved' );

        $response = $controller->update_commentary_status( $request );
        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertSame( 403, $response->get_status() );
        $payload = $response->get_data();
        $this->assertSame( false, $payload['success'] );
        $this->assertSame( 'forbidden', $payload['error'] );
    }

    public function test_update_commentary_status_returns_200_for_editorial_user(): void {
        $controller = new QuoteClubController();
        $GLOBALS['khm_test_current_user_caps']['edit_posts'] = true;

        $request = new WP_REST_Request( 'PATCH', '/khm/v1/portal/quoteclub/commentary/21' );
        $request->set_param( 'id', 21 );
        $request->set_param( 'status', 'approved' );

        $response = $controller->update_commentary_status( $request );
        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertSame( 200, $response->get_status() );
        $payload = $response->get_data();
        $this->assertSame( true, $payload['success'] );
        $this->assertSame( 21, (int) $payload['id'] );
        $this->assertSame( 'approved', $payload['status'] );
    }

    public function test_get_commentary_detail_returns_404_when_missing(): void {
        $controller = new QuoteClubController();
        $request = new WP_REST_Request( 'GET', '/khm/v1/portal/quoteclub/commentary/999' );
        $request->set_param( 'id', 999 );

        $response = $controller->get_commentary_detail( $request );
        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertSame( 404, $response->get_status() );
        $payload = $response->get_data();
        $this->assertSame( false, $payload['success'] );
        $this->assertSame( 'not_found', $payload['error'] );
    }

    public function test_approve_commentary_is_idempotent_and_fires_action_once(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_sponsor_commentary';
        $wpdb->insert( $table, [
            'id' => 31,
            'post_id' => 88,
            'user_id' => 1001,
            'commentary_text' => 'Great sponsor insight.',
            'status' => 'pending_editorial',
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ] );

        $controller = new QuoteClubController();

        $request = new WP_REST_Request( 'POST', '/khm/v1/portal/quoteclub/commentary/31/approve' );
        $request->set_param( 'id', 31 );
        $request->set_param( 'insert', false );

        $first = $controller->approve_commentary( $request );
        $this->assertSame( 200, $first->get_status() );
        $firstPayload = $first->get_data();
        $this->assertSame( true, $firstPayload['success'] );
        $this->assertSame( false, $firstPayload['already_approved'] );

        $second = $controller->approve_commentary( $request );
        $this->assertSame( 200, $second->get_status() );
        $secondPayload = $second->get_data();
        $this->assertSame( true, $secondPayload['success'] );
        $this->assertSame( true, $secondPayload['already_approved'] );

        $events = array_filter(
            $GLOBALS['khm_test_actions_fired'],
            static function ( $entry ) {
                return is_array( $entry ) && ( $entry['hook'] ?? '' ) === 'khm_quoteclub_commentary_approved';
            }
        );
        $this->assertCount( 1, $events );
    }

    public function test_reject_commentary_is_idempotent(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_sponsor_commentary';
        $wpdb->insert( $table, [
            'id' => 41,
            'post_id' => 77,
            'user_id' => 1001,
            'commentary_text' => 'Needs more detail.',
            'status' => 'pending_editorial',
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ] );

        $controller = new QuoteClubController();
        $request = new WP_REST_Request( 'POST', '/khm/v1/portal/quoteclub/commentary/41/reject' );
        $request->set_param( 'id', 41 );

        $first = $controller->reject_commentary( $request );
        $this->assertSame( 200, $first->get_status() );
        $firstPayload = $first->get_data();
        $this->assertSame( true, $firstPayload['success'] );
        $this->assertSame( false, $firstPayload['already_rejected'] );

        $second = $controller->reject_commentary( $request );
        $this->assertSame( 200, $second->get_status() );
        $secondPayload = $second->get_data();
        $this->assertSame( true, $secondPayload['success'] );
        $this->assertSame( true, $secondPayload['already_rejected'] );
    }

    public function test_approve_commentary_insert_is_idempotent(): void {
        $controller = new class extends QuoteClubController {
            public int $insertCount = 0;
            private bool $inserted = false;

            protected function fetch_commentary(int $id): ?array {
                if ($id !== 51) {
                    return null;
                }

                return [
                    'id' => 51,
                    'post_id' => 901,
                    'user_id' => 1001,
                    'commentary_text' => 'Insert me once',
                    'status' => 'pending_editorial',
                ];
            }

            protected function persist_commentary_status(int $id, string $status): bool {
                return true;
            }

            protected function maybe_insert_commentary_content(array $commentary, string $target): bool {
                if ($this->inserted) {
                    return false;
                }

                $this->inserted = true;
                $this->insertCount++;
                return true;
            }
        };

        $request = new WP_REST_Request( 'POST', '/khm/v1/portal/quoteclub/commentary/51/approve' );
        $request->set_param( 'id', 51 );
        $request->set_param( 'insert', true );
        $request->set_param( 'insert_target', 'framework' );

        $first = $controller->approve_commentary( $request );
        $this->assertSame( 200, $first->get_status() );
        $this->assertTrue( (bool) ($first->get_data()['inserted'] ?? false) );

        $second = $controller->approve_commentary( $request );
        $this->assertSame( 200, $second->get_status() );
        $this->assertFalse( (bool) ($second->get_data()['inserted'] ?? true) );
        $this->assertSame( 1, $controller->insertCount );
    }

    public function test_registered_patch_route_permission_callback_requires_editorial_auth(): void {
        $controller = new QuoteClubController();
        $controller->register();

        $routeKey = '/khm/v1/portal/quoteclub/commentary/(?P<id>\d+)';
        $this->assertArrayHasKey( $routeKey, $GLOBALS['khm_test_rest_routes'] );

        $routeConfig = $GLOBALS['khm_test_rest_routes'][ $routeKey ]['args'];
        $permissionCallback = $routeConfig['permission_callback'] ?? null;
        $this->assertIsCallable( $permissionCallback );

        $request = new WP_REST_Request( 'PATCH', '/khm/v1/portal/quoteclub/commentary/11' );

        $GLOBALS['khm_test_current_user_caps']['edit_posts'] = false;
        $this->assertFalse( (bool) call_user_func( $permissionCallback, $request ) );

        $GLOBALS['khm_test_current_user_caps']['edit_posts'] = true;
        $this->assertTrue( (bool) call_user_func( $permissionCallback, $request ) );
    }

    public function test_registers_approve_reject_and_detail_routes(): void {
        $controller = new QuoteClubController();
        $controller->register();

        $this->assertArrayHasKey( '/khm/v1/portal/quoteclub/commentary/(?P<id>\d+)', $GLOBALS['khm_test_rest_routes'] );
        $this->assertArrayHasKey( '/khm/v1/portal/quoteclub/commentary/(?P<id>\d+)/approve', $GLOBALS['khm_test_rest_routes'] );
        $this->assertArrayHasKey( '/khm/v1/portal/quoteclub/commentary/(?P<id>\d+)/reject', $GLOBALS['khm_test_rest_routes'] );
        $this->assertArrayHasKey( '/khm/v1/sponsor/(?P<sponsor_id>\d+)/invite/accept', $GLOBALS['khm_test_rest_routes'] );
    }
}
