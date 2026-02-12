<?php

namespace KHM\Elementor\Tags;

use Elementor\Modules\DynamicTags\Module as DynamicTagsModule;

class KhmDynamicTags {
	public function register(): void {
		add_action( 'elementor/dynamic_tags/register', [ $this, 'register_tags' ] );
	}

	public function register_tags( $dynamic_tags ): void {
		if ( ! $dynamic_tags instanceof DynamicTagsModule ) {
			return;
		}

		// Register a custom group.
		if ( method_exists( $dynamic_tags, 'register_group' ) ) {
			$dynamic_tags->register_group( 'khm', [
				'title' => __( 'KHM', 'khm-membership' ),
			] );
		}

		$dynamic_tags->register_tag( LevelMetaTag::class );
	}
}
