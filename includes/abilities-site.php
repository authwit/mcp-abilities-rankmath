<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Rank Math site-level configuration abilities.
 */
function mcp_rankmath_register_site_abilities(): void {
	// =========================================================================
	// RANK MATH - Get Schema Status
	// =========================================================================
	wp_register_ability(
		'rankmath/get-schema-status',
		array(
			'label'               => 'Get Rank Math Schema Status',
			'description'         => 'Return effective global publisher/schema settings including publisher type, website name, logo, social profiles, and local SEO contact fields.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => new stdClass(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'status'  => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function (): array {
				if ( ! mcp_rankmath_is_active() ) {
					return array( 'success' => false, 'message' => 'Rank Math SEO plugin is not active.' );
				}

				return array(
					'success' => true,
					'status'  => mcp_rankmath_get_schema_status_data(),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// =========================================================================
	// RANK MATH - List Modules
	// =========================================================================
	wp_register_ability(
		'rankmath/list-modules',
		array(
			'label'               => 'List Rank Math Modules',
			'description'         => 'List Rank Math modules with active, disabled, and upgrade status.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => new stdClass(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'modules' => array( 'type' => 'array' ),
					'count'   => array( 'type' => 'integer' ),
				),
			),
			'execute_callback'    => function (): array {
				if ( ! mcp_rankmath_is_active() ) {
					return array( 'success' => false, 'message' => 'Rank Math SEO plugin is not active.' );
				}

				$modules = mcp_rankmath_get_module_records();
				return array(
					'success' => true,
					'modules' => $modules,
					'count'   => count( $modules ),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// =========================================================================
	// RANK MATH - Update Modules
	// =========================================================================
	wp_register_ability(
		'rankmath/update-modules',
		array(
			'label'               => 'Update Rank Math Modules',
			'description'         => 'Enable or disable Rank Math modules by slug.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'enable'  => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
					'disable' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'updated' => array( 'type' => 'object' ),
					'modules' => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				if ( ! mcp_rankmath_is_active() || ! class_exists( '\\RankMath\\Helper' ) ) {
					return array( 'success' => false, 'message' => 'Rank Math SEO plugin is not active.' );
				}

				$enable  = isset( $input['enable'] ) && is_array( $input['enable'] ) ? array_map( 'sanitize_key', $input['enable'] ) : array();
				$disable = isset( $input['disable'] ) && is_array( $input['disable'] ) ? array_map( 'sanitize_key', $input['disable'] ) : array();

				if ( empty( $enable ) && empty( $disable ) ) {
					return array( 'success' => false, 'message' => 'No module changes provided.' );
				}

				$changes = array();
				foreach ( $enable as $module ) {
					if ( '' !== $module ) {
						$changes[ $module ] = 'on';
					}
				}
				foreach ( $disable as $module ) {
					if ( '' !== $module ) {
						$changes[ $module ] = 'off';
					}
				}

				\RankMath\Helper::update_modules( $changes );

				$route_modules = array_intersect( array_keys( $changes ), array( 'llms-txt', 'sitemap' ) );
				if ( ! empty( $route_modules ) && function_exists( 'flush_rewrite_rules' ) ) {
					flush_rewrite_rules( false );
				}

				if ( method_exists( '\\RankMath\\Helper', 'clear_cache' ) ) {
					\RankMath\Helper::clear_cache( 'mcp-rankmath-update-modules' );
				}

				return array(
					'success' => true,
					'updated' => array(
						'enable'  => array_values( $enable ),
						'disable' => array_values( $disable ),
					),
					'modules' => mcp_rankmath_get_module_records(),
					'message' => 'Rank Math modules updated.',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		)
	);
	// =========================================================================
	// RANK MATH - Update Publisher Profile
	// =========================================================================
	wp_register_ability(
		'rankmath/update-publisher-profile',
		array(
			'label'               => 'Update Rank Math Publisher Profile',
			'description'         => 'Safely update the global publisher/entity fields used by Rank Math schema and local SEO.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'knowledgegraph_type'    => array( 'type' => 'string', 'enum' => array( 'company', 'person' ) ),
					'knowledgegraph_name'    => array( 'type' => 'string' ),
					'website_name'           => array( 'type' => 'string' ),
					'organization_description' => array( 'type' => 'string' ),
					'url'                    => array( 'type' => 'string' ),
					'knowledgegraph_logo'    => array( 'type' => 'string' ),
					'knowledgegraph_logo_id' => array( 'type' => 'integer' ),
					'email'                  => array( 'type' => 'string' ),
					'phone'                  => array( 'type' => 'string' ),
					'local_address'          => array( 'type' => 'object' ),
					'local_address_format'   => array( 'type' => 'string' ),
					'local_seo'              => array( 'type' => 'boolean' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'updated' => array( 'type' => 'array' ),
					'status'  => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$titles  = mcp_rankmath_get_titles_settings();
				$updated = array();

				$scalar_fields = array(
					'knowledgegraph_type',
					'knowledgegraph_name',
					'website_name',
					'organization_description',
					'local_address_format',
					'phone',
				);
				foreach ( $scalar_fields as $field ) {
					if ( array_key_exists( $field, $input ) ) {
						$titles[ $field ] = sanitize_text_field( (string) $input[ $field ] );
						$updated[]        = $field;
					}
				}

				if ( array_key_exists( 'url', $input ) ) {
					$titles['url'] = esc_url_raw( (string) $input['url'] );
					$updated[]     = 'url';
				}

				if ( array_key_exists( 'knowledgegraph_logo', $input ) ) {
					$titles['knowledgegraph_logo'] = esc_url_raw( (string) $input['knowledgegraph_logo'] );
					$updated[]                     = 'knowledgegraph_logo';
				}

				if ( array_key_exists( 'knowledgegraph_logo_id', $input ) ) {
					$titles['knowledgegraph_logo_id'] = absint( $input['knowledgegraph_logo_id'] );
					$updated[]                        = 'knowledgegraph_logo_id';
				}

				if ( array_key_exists( 'email', $input ) ) {
					$titles['email'] = sanitize_email( (string) $input['email'] );
					$updated[]       = 'email';
				}

				if ( array_key_exists( 'local_seo', $input ) ) {
					$titles['local_seo'] = ! empty( $input['local_seo'] ) ? 'on' : 'off';
					$updated[]           = 'local_seo';
				}

				if ( array_key_exists( 'local_address', $input ) && is_array( $input['local_address'] ) ) {
					$address = array();
					foreach ( $input['local_address'] as $key => $value ) {
						$address[ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
					}
					$titles['local_address'] = $address;
					$updated[]               = 'local_address';
				}

				if ( empty( $updated ) ) {
					return array( 'success' => false, 'message' => 'No publisher profile fields provided.' );
				}

				update_option( 'rank-math-options-titles', $titles );

				return array(
					'success' => true,
					'updated' => $updated,
					'status'  => mcp_rankmath_get_schema_status_data(),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// =========================================================================
	// RANK MATH - Get Social Profiles
	// =========================================================================
	wp_register_ability(
		'rankmath/get-social-profiles',
		array(
			'label'               => 'Get Rank Math Social Profiles',
			'description'         => 'Return the global social profile fields that feed Rank Math sameAs output.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => new stdClass(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'profiles' => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function (): array {
				$titles = mcp_rankmath_get_titles_settings();
				return array(
					'success'  => true,
					'profiles' => array(
						'facebook_url'        => isset( $titles['social_url_facebook'] ) ? (string) $titles['social_url_facebook'] : '',
						'twitter_handle'      => isset( $titles['twitter_author_names'] ) ? ltrim( (string) $titles['twitter_author_names'], '@' ) : '',
						'additional_profiles' => isset( $titles['social_additional_profiles'] ) ? preg_split( '/\r\n|\r|\n/', (string) $titles['social_additional_profiles'] ) : array(),
						'effective_same_as'   => mcp_rankmath_get_social_profiles_from_titles( $titles ),
					),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// =========================================================================
	// RANK MATH - Update Social Profiles
	// =========================================================================
	wp_register_ability(
		'rankmath/update-social-profiles',
		array(
			'label'               => 'Update Rank Math Social Profiles',
			'description'         => 'Update the global social profile fields that feed Rank Math sameAs output.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'facebook_url'        => array( 'type' => 'string' ),
					'twitter_handle'      => array( 'type' => 'string' ),
					'additional_profiles' => array(
						'oneOf' => array(
							array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							array( 'type' => 'string' ),
						),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'updated' => array( 'type' => 'array' ),
					'profiles' => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$titles  = mcp_rankmath_get_titles_settings();
				$updated = array();

				if ( array_key_exists( 'facebook_url', $input ) ) {
					$titles['social_url_facebook'] = esc_url_raw( (string) $input['facebook_url'] );
					$updated[]                     = 'facebook_url';
				}

				if ( array_key_exists( 'twitter_handle', $input ) ) {
					$titles['twitter_author_names'] = ltrim( sanitize_text_field( (string) $input['twitter_handle'] ), '@' );
					$updated[]                      = 'twitter_handle';
				}

				if ( array_key_exists( 'additional_profiles', $input ) ) {
					$titles['social_additional_profiles'] = mcp_rankmath_normalize_additional_profiles( $input['additional_profiles'] );
					$updated[]                            = 'additional_profiles';
				}

				if ( empty( $updated ) ) {
					return array( 'success' => false, 'message' => 'No social profile changes provided.' );
				}

				update_option( 'rank-math-options-titles', $titles );

				return array(
					'success' => true,
					'updated' => $updated,
					'profiles' => array(
						'facebook_url'        => isset( $titles['social_url_facebook'] ) ? (string) $titles['social_url_facebook'] : '',
						'twitter_handle'      => isset( $titles['twitter_author_names'] ) ? ltrim( (string) $titles['twitter_author_names'], '@' ) : '',
						'additional_profiles' => isset( $titles['social_additional_profiles'] ) ? preg_split( '/\r\n|\r|\n/', (string) $titles['social_additional_profiles'] ) : array(),
						'effective_same_as'   => mcp_rankmath_get_social_profiles_from_titles( $titles ),
					),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// =========================================================================
	// RANK MATH - Get Sitemap Status
	// =========================================================================
	wp_register_ability(
		'rankmath/get-sitemap-status',
		array(
			'label'               => 'Get Rank Math Sitemap Status',
			'description'         => 'Return sitemap module state, enabled object types, rewrite status, and a live sitemap index check.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => new stdClass(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'status'  => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function (): array {
				if ( ! mcp_rankmath_is_active() ) {
					return array( 'success' => false, 'message' => 'Rank Math SEO plugin is not active.' );
				}

				$sitemap         = mcp_rankmath_get_sitemap_settings();
				$enabled_objects = mcp_rankmath_get_sitemap_enabled_items();
				$preview         = mcp_rankmath_fetch_local_preview( '/sitemap_index.xml', 8 );

				return array(
					'success' => true,
					'status'  => array(
						'module_active'     => class_exists( '\\RankMath\\Helper' ) && \RankMath\Helper::is_module_active( 'sitemap' ),
						'route_url'         => home_url( '/sitemap_index.xml' ),
						'rewrite'           => mcp_rankmath_get_rewrite_status( 'sitemap_index.xml' ),
						'include_images'    => ! empty( $sitemap['include_images'] ),
						'links_per_sitemap' => isset( $sitemap['links_per_sitemap'] ) ? (int) $sitemap['links_per_sitemap'] : 0,
						'post_types'        => $enabled_objects['post_types'],
						'taxonomies'        => $enabled_objects['taxonomies'],
						'live_preview'      => $preview,
					),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);
}
