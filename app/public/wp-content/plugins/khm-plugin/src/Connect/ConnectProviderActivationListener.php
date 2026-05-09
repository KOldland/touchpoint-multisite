<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

use KHM\Services\SponsorService;

/**
 * Listens to khm_connect_site_subscriptions_activated and auto-creates placeholder
 * provider rows so sellers don't have to create them manually after subscribing.
 *
 * Row is created with status = 'inactive' so it is invisible in the directory until
 * the seller completes their provider profile via the portal.
 */
class ConnectProviderActivationListener {

	public function register(): void {
		add_action( 'khm_connect_site_subscriptions_activated', [ $this, 'on_activated' ], 10, 3 );
	}

	/**
	 * @param int    $user_id  WP user ID of the seller
	 * @param array  $sites    Array of site slugs activated (e.g. ['pricing', 'field-service'])
	 * @param string $scope    'portfolio' or individual site slug
	 */
	public function on_activated( int $user_id, array $sites, string $scope ): void {
		$sponsor = SponsorService::get_user_sponsor( $user_id );
		if ( ! $sponsor ) {
			return;
		}

		$sponsor_id   = (int) $sponsor['id'];
		$is_portfolio = $scope === 'portfolio';

		if ( $is_portfolio ) {
			// Portfolio providers are visible on all sites: blog_id = 0
			$this->ensure_provider_row( $sponsor_id, 0 );
		} else {
			// Create a row for each activated site's blog_id
			foreach ( $sites as $site_slug ) {
				$blog_ids = ConnectTaxonomy::blog_ids_for_site_slugs( [ $site_slug ] );
				foreach ( $blog_ids as $blog_id ) {
					$this->ensure_provider_row( $sponsor_id, $blog_id );
				}
			}
		}
	}

	/**
	 * Insert a placeholder provider row if none already exists for this sponsor + blog_id.
	 */
	private function ensure_provider_row( int $sponsor_id, int $blog_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'connect_providers';

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM `{$table}` WHERE sponsor_id = %d AND blog_id = %d LIMIT 1",
			$sponsor_id,
			$blog_id
		) );

		if ( $existing ) {
			return;
		}

		// Unique slug uses sponsor_id + blog_id + timestamp to avoid collisions
		$slug = sprintf( 'provider-%d-%d-%d', $sponsor_id, $blog_id, time() );

		$wpdb->insert(
			$table,
			[
				'blog_id'    => $blog_id,
				'sponsor_id' => $sponsor_id,
				'name'       => 'New Provider',
				'slug'       => $slug,
				'status'     => 'inactive',
				'created_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);
	}
}
