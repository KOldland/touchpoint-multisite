<?php

	function kh_suggested_reading_get_posts( $post_id = null, $limit = 6, $offset = 0 ) {

	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	$cache_key = 'kh_sr_' . $post_id . '_' . $limit . '_' . $offset;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$suggested = [];
	$seen_ids  = [ $post_id ];

	// 1. Manual override
	$manual = get_field( 'featured_posts_override', $post_id );
	
	if ( ! empty( $manual ) && is_array( $manual ) ) {
		$result = array_slice( $manual, 0, $limit );
		set_transient( $cache_key, $result, MINUTE_IN_SECONDS * 10 );
		return $result;
	}

	// 2. On/off toggle
	$enabled = get_field( 'onoff_toggle', $post_id );
	if ( $enabled === false ) {
		return [];
	}

	// 3. Tag override
	$tag = get_field( 'override_by_tag', $post_id );
	if ( ! empty( $tag ) && is_object( $tag ) ) {
		$result = get_posts( [
			'tag__in'        => [ $tag->term_id ],
			'post__not_in'   => $seen_ids,
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_status'    => 'publish',
			'post_type'      => 'post',
		] );
		set_transient( $cache_key, $result, MINUTE_IN_SECONDS * 10 );
		return $result;
	}

	// 4. Lead category fallback
	$lead_term = get_field( 'override_lead_category', $post_id );
	$lead_ids  = [];

	if ( ! empty( $lead_term ) && is_object( $lead_term ) ) {
		$lead_ids[] = $lead_term->term_id;
	} else {
		$post_cats = wp_get_post_categories( $post_id );
		if ( ! empty( $post_cats ) ) {
			$lead_ids = array_slice( $post_cats, 0, 1 );
		}
	}

	$lead_posts = [];
	if ( ! empty( $lead_ids ) ) {
		$lead_posts = get_posts( [
			'category__in'    => $lead_ids,
			'post__not_in'    => $seen_ids,
			'posts_per_page'  => 3,
			'orderby'         => 'date',
			'order'           => 'DESC',
			'post_status'     => 'publish',
			'post_type'       => 'post',
		] );

		$seen_ids = array_merge( $seen_ids, wp_list_pluck( $lead_posts, 'ID' ) );
	}

	$non_lead_posts = get_posts( [
		'category__not_in' => $lead_ids,
		'post__not_in'     => $seen_ids,
		'posts_per_page'   => 3,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'post_status'      => 'publish',
		'post_type'        => 'post',
	] );

	$seen_ids = array_merge( $seen_ids, wp_list_pluck( $non_lead_posts, 'ID' ) );

	// Interleave lead + non-lead
	for ( $i = 0; $i < 3; $i++ ) {
		if ( isset( $lead_posts[ $i ] ) ) {
			$suggested[] = $lead_posts[ $i ];
		}
		if ( isset( $non_lead_posts[ $i ] ) ) {
			$suggested[] = $non_lead_posts[ $i ];
		}
	}

	// Fill remaining slots
	if ( count( $suggested ) < $limit ) {
		$fillers = get_posts( [
			'post__not_in'   => $seen_ids,
			'posts_per_page' => $limit - count( $suggested ),
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_status'    => 'publish',
			'post_type'      => 'post',
		] );

		$suggested = array_merge( $suggested, $fillers );
	}

	set_transient( $cache_key, $suggested, MINUTE_IN_SECONDS * 10 );
	return $suggested;
}
	
