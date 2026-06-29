<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Rank Math option abilities.
 */
function mcp_rankmath_register_option_abilities(): void {
	// =========================================================================
	// RANK MATH - List Options
	// =========================================================================
	wp_register_ability(
		'rankmath/list-options',
		array(
			'label'               => 'List Rank Math Options',
			'description'         => 'List Rank Math option names stored in wp_options.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'limit'  => array( 'type' => 'integer', 'default' => 200 ),
					'offset' => array( 'type' => 'integer', 'default' => 0 ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'options' => array( 'type' => 'array' ),
					'count'   => array( 'type' => 'integer' ),
				),
			),
				'execute_callback'    => function ( array $input = array() ): array {
				global $wpdb;
				$limit  = min( 500, max( 1, (int) ( $input['limit'] ?? 200 ) ) );
				$offset = max( 0, (int) ( $input['offset'] ?? 0 ) );

				$like_patterns = mcp_rankmath_allowed_option_like_patterns();
				$legacy_like   = $like_patterns[0] ?? 'rank_math_%';
				$modern_like   = $like_patterns[1] ?? 'rank-math-%';

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s OR option_name LIKE %s ORDER BY option_name ASC LIMIT %d OFFSET %d',
						$legacy_like,
						$modern_like,
						$limit,
						$offset
					),
					ARRAY_A
				);

				$options = array_map( static function ( $row ) {
					return $row['option_name'];
				}, $rows ?: array() );

				return array(
					'success' => true,
					'options' => $options,
					'count'   => count( $options ),
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
	// RANK MATH - Get Options
	// =========================================================================
	wp_register_ability(
		'rankmath/get-options',
		array(
			'label'               => 'Get Rank Math Options',
			'description'         => 'Get Rank Math option values by name.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'options' ),
				'properties'           => array(
					'options' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Option names to fetch.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'options' => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$names = isset( $input['options'] ) && is_array( $input['options'] ) ? $input['options'] : array();
				if ( empty( $names ) ) {
					return array( 'success' => false, 'message' => 'No option names provided.' );
				}

				$values = array();
				foreach ( $names as $name ) {
					$name = sanitize_text_field( $name );
					if ( ! mcp_rankmath_is_allowed_option_name( $name ) ) {
						continue;
					}
					$values[ $name ] = get_option( $name, null );
				}

					return array( 'success' => true, 'options' => $values );
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
	// RANK MATH - Update Options
	// =========================================================================
	wp_register_ability(
		'rankmath/update-options',
		array(
			'label'               => 'Update Rank Math Options',
			'description'         => 'Update Rank Math option values by name.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'options' ),
				'properties'           => array(
					'options' => array(
						'type'        => 'object',
						'description' => 'Map of option names to values.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'updated' => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
				'execute_callback'    => function ( array $input = array() ): array {
				$options = isset( $input['options'] ) && is_array( $input['options'] ) ? $input['options'] : array();
				if ( empty( $options ) ) {
					return array( 'success' => false, 'message' => 'No options provided.' );
				}

				$updated = array();
				foreach ( $options as $name => $value ) {
					$name = sanitize_text_field( $name );
					if ( ! mcp_rankmath_is_allowed_option_name( $name ) ) {
						continue;
					}
					update_option( $name, $value );
					$updated[] = $name;
				}

					return array(
						'success' => true,
						'updated' => $updated,
						'message' => 'Options updated.',
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
}
