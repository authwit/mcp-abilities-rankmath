<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Rank Math log and redirection abilities.
 */
function mcp_rankmath_register_log_redirection_abilities(): void {
	wp_register_ability(
		'rankmath/find-redirection',
		array(
			'label'               => 'Find Rank Math Redirection',
			'description'         => 'Find Rank Math redirections whose source rules match one URL or path.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'url' ),
				'properties'           => array(
					'url'         => array( 'type' => 'string' ),
					'active_only' => array( 'type' => 'boolean', 'default' => true ),
					'limit'       => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'path'    => array( 'type' => 'string' ),
					'matches' => array( 'type' => 'array' ),
					'count'   => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input ): array {
				global $wpdb;
				$table = $wpdb->prefix . 'rank_math_redirections';
				if ( ! mcp_rankmath_table_exists( $table ) ) {
					return array( 'success' => false, 'message' => 'Rank Math redirections table not found.' );
				}

				$path = mcp_rankmath_normalized_redirection_path( (string) ( $input['url'] ?? '' ) );
				if ( '' === $path ) {
					return array( 'success' => false, 'message' => 'A valid URL path is required.' );
				}

				$active_only = array_key_exists( 'active_only', $input ) ? (bool) $input['active_only'] : true;
				$limit       = min( 100, max( 1, (int) ( $input['limit'] ?? 20 ) ) );

				if ( $active_only ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$rows = $wpdb->get_results(
						$wpdb->prepare(
							'SELECT * FROM `' . esc_sql( $table ) . '` WHERE status = %s ORDER BY id DESC LIMIT %d',
							'active',
							1000
						),
						ARRAY_A
					);
				} else {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$rows = $wpdb->get_results(
						$wpdb->prepare(
							'SELECT * FROM `' . esc_sql( $table ) . '` ORDER BY id DESC LIMIT %d',
							1000
						),
						ARRAY_A
					);
				}
				$matches = array();

				foreach ( $rows as $row ) {
					$sources = mcp_rankmath_decode_redirection_sources( $row['sources'] ?? '' );
					foreach ( $sources as $source ) {
						if ( is_array( $source ) && mcp_rankmath_redirection_source_matches_path( $source, $path ) ) {
							$matches[] = array(
								'id'          => (int) $row['id'],
								'source'      => $source,
								'destination' => (string) ( $row['url_to'] ?? '' ),
								'header_code' => (int) ( $row['header_code'] ?? 0 ),
								'status'      => (string) ( $row['status'] ?? '' ),
								'hits'        => (int) ( $row['hits'] ?? 0 ),
							);
							if ( count( $matches ) >= $limit ) {
								break 2;
							}
						}
					}
				}

				return array(
					'success' => true,
					'path'    => $path,
					'matches' => $matches,
					'count'   => count( $matches ),
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
	// RANK MATH - List 404 Logs
	// =========================================================================
	wp_register_ability(
		'rankmath/list-404-logs',
		array(
			'label'               => 'List Rank Math 404 Logs',
			'description'         => 'List recent Rank Math 404 log entries (read-only).',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'per_page' => array( 'type' => 'integer', 'default' => 50 ),
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'logs'    => array( 'type' => 'array' ),
					'total'   => array( 'type' => 'integer' ),
				),
			),
				'execute_callback'    => function ( array $input = array() ): array {
					global $wpdb;
					$table = $wpdb->prefix . 'rank_math_404_logs';

				if ( ! mcp_rankmath_table_exists( $table ) ) {
					return array( 'success' => false, 'message' => 'Rank Math 404 log table not found.' );
				}

				$per_page = min( 200, max( 1, (int) ( $input['per_page'] ?? 50 ) ) );
				$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
				$offset   = ( $page - 1 ) * $per_page;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$rows = $wpdb->get_results(
						$wpdb->prepare(
							'SELECT * FROM `' . esc_sql( $table ) . '` ORDER BY id DESC LIMIT %d OFFSET %d',
							$per_page,
							$offset
						),
						ARRAY_A
					);
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`' );

					return array(
						'success' => true,
						'logs'    => $rows,
						'total'   => $total,
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
	// RANK MATH - Delete 404 Logs
	// =========================================================================
	wp_register_ability(
		'rankmath/delete-404-logs',
		array(
			'label'               => 'Delete Rank Math 404 Logs',
			'description'         => 'Delete specific Rank Math 404 log entries by ID.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'ids' ),
				'properties'           => array(
					'ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Array of 404 log IDs to delete.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'deleted' => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input ): array {
				global $wpdb;
				$table = $wpdb->prefix . 'rank_math_404_logs';

				if ( ! mcp_rankmath_table_exists( $table ) ) {
					return array( 'success' => false, 'message' => 'Rank Math 404 log table not found.' );
				}

				$ids = array_filter( array_map( 'absint', $input['ids'] ?? array() ) );
				if ( empty( $ids ) ) {
					return array( 'success' => false, 'message' => 'No valid IDs provided.' );
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$deleted = $wpdb->query(
					$wpdb->prepare(
						'DELETE FROM `' . esc_sql( $table ) . '` WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
						$ids
					)
				);

				return array(
					'success' => true,
					'deleted' => (int) $deleted,
					'message' => 'Deleted ' . (int) $deleted . ' log(s).',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),
		)
	);

	// =========================================================================
	// RANK MATH - Clear 404 Logs
	// =========================================================================
	wp_register_ability(
		'rankmath/clear-404-logs',
		array(
			'label'               => 'Clear Rank Math 404 Logs',
			'description'         => 'Deletes all Rank Math 404 log entries.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'confirm' ),
				'properties'           => array(
					'confirm' => array(
						'type'        => 'boolean',
						'description' => 'Set true to confirm clearing all Rank Math 404 logs.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input ): array {
				global $wpdb;
				$table = $wpdb->prefix . 'rank_math_404_logs';

				if ( ! mcp_rankmath_table_exists( $table ) ) {
					return array( 'success' => false, 'message' => 'Rank Math 404 log table not found.' );
				}

				if ( empty( $input['confirm'] ) ) {
					return array( 'success' => false, 'message' => 'Confirmation required to clear 404 logs.' );
				}

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->query( 'DELETE FROM `' . esc_sql( $table ) . '`' );

				return array(
					'success' => true,
					'message' => 'Cleared Rank Math 404 logs.',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),
		)
	);

	// =========================================================================
	// RANK MATH - List Redirections
	// =========================================================================
	wp_register_ability(
		'rankmath/list-redirections',
		array(
			'label'               => 'List Rank Math Redirections',
			'description'         => 'List Rank Math redirections (read-only).',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'per_page' => array( 'type' => 'integer', 'default' => 50 ),
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'redirections' => array( 'type' => 'array' ),
					'total'       => array( 'type' => 'integer' ),
				),
			),
				'execute_callback'    => function ( array $input = array() ): array {
					global $wpdb;
					$table = $wpdb->prefix . 'rank_math_redirections';

				if ( ! mcp_rankmath_table_exists( $table ) ) {
					return array( 'success' => false, 'message' => 'Rank Math redirections table not found.' );
				}

				$per_page = min( 200, max( 1, (int) ( $input['per_page'] ?? 50 ) ) );
				$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
				$offset   = ( $page - 1 ) * $per_page;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$rows = $wpdb->get_results(
						$wpdb->prepare(
							'SELECT * FROM `' . esc_sql( $table ) . '` ORDER BY id DESC LIMIT %d OFFSET %d',
							$per_page,
							$offset
						),
						ARRAY_A
					);
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`' );

					return array(
						'success'      => true,
						'redirections' => $rows,
						'total'        => $total,
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
	// RANK MATH - Create Redirection
	// =========================================================================
	wp_register_ability(
		'rankmath/create-redirection',
		array(
			'label'               => 'Create Rank Math Redirection',
			'description'         => 'Create a Rank Math redirection with one or more sources.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'sources' ),
				'properties'           => array(
					'sources' => array(
						'type'        => 'array',
						'description' => 'Array of sources with pattern and comparison.',
						'items'       => array(
							'type'                 => 'object',
							'properties'           => array(
								'pattern'     => array( 'type' => 'string' ),
								'comparison'  => array(
									'type'    => 'string',
									'enum'    => mcp_rankmath_allowed_redirection_comparisons(),
									'default' => 'exact',
								),
								'ignore_case' => array( 'type' => 'boolean' ),
							),
							'required'             => array( 'pattern' ),
							'additionalProperties' => false,
						),
					),
					'destination' => array(
						'type'        => 'string',
						'description' => 'Target URL (relative or absolute). Optional for 410/451.',
					),
					'header_code' => array(
						'type'        => 'integer',
						'default'     => 301,
						'description' => 'HTTP status code (301, 302, 307, 308, 410, 451).',
					),
					'status'      => array(
						'type'        => 'string',
						'default'     => 'active',
						'description' => 'Status: active or inactive.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'id'      => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
					'code'    => array( 'type' => 'string' ),
					'conflicting_sources' => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( array $input ): array {
				if ( ! mcp_rankmath_is_active() ) {
					return array(
						'success' => false,
						'message' => 'Rank Math SEO plugin is not active.',
					);
				}

				if ( ! class_exists( 'RankMath\\Redirections\\Redirection' ) ) {
					return array(
						'success' => false,
						'message' => 'Rank Math redirections module is not available.',
					);
				}

				$header_code = isset( $input['header_code'] ) ? (int) $input['header_code'] : 301;
				if ( ! in_array( $header_code, mcp_rankmath_allowed_redirection_headers(), true ) ) {
					return array(
						'success' => false,
						'message' => 'Invalid header_code provided.',
					);
				}

				$status = isset( $input['status'] ) ? $input['status'] : 'active';
				if ( ! in_array( $status, mcp_rankmath_allowed_redirection_statuses(), true ) ) {
					return array(
						'success' => false,
						'message' => 'Invalid status provided.',
					);
				}

				$destination = isset( $input['destination'] ) ? (string) $input['destination'] : '';
				if ( empty( $destination ) && ! in_array( $header_code, array( 410, 451 ), true ) ) {
					return array(
						'success' => false,
						'message' => 'Destination is required for this header_code.',
					);
				}

				$sources_input = $input['sources'] ?? array();
				if ( empty( $sources_input ) || ! is_array( $sources_input ) ) {
					return array(
						'success' => false,
						'message' => 'At least one source is required.',
					);
				}

				$sources = array();
				foreach ( $sources_input as $source ) {
					if ( ! is_array( $source ) ) {
						continue;
					}
					$pattern = isset( $source['pattern'] ) ? trim( (string) $source['pattern'] ) : '';
					if ( '' === $pattern ) {
						continue;
					}
					$comparison = isset( $source['comparison'] ) ? $source['comparison'] : 'exact';
					if ( ! in_array( $comparison, mcp_rankmath_allowed_redirection_comparisons(), true ) ) {
						return array(
							'success' => false,
							'message' => 'Invalid comparison type: ' . $comparison,
						);
					}
					$sources[] = array(
						'pattern'    => $pattern,
						'comparison' => $comparison,
						'ignore'     => ! empty( $source['ignore_case'] ) ? 'case' : '',
					);
				}

				if ( empty( $sources ) ) {
					return array(
						'success' => false,
						'message' => 'No valid sources provided.',
					);
				}

				$self_redirect_sources = mcp_rankmath_self_redirect_sources( $sources, $destination );
				if ( ! empty( $self_redirect_sources ) && ! in_array( $header_code, array( 410, 451 ), true ) ) {
					return array(
						'success' => false,
						'message' => 'Redirection source must not normalize to the same path as its destination.',
						'code'    => 'rankmath_self_redirect_source',
						'conflicting_sources' => $self_redirect_sources,
					);
				}

				$redirection = \RankMath\Redirections\Redirection::from(
					array(
						'sources'     => $sources,
						'url_to'      => $destination,
						'header_code' => $header_code,
						'status'      => $status,
					)
				);

				if ( method_exists( $redirection, 'is_infinite_loop' ) && $redirection->is_infinite_loop() ) {
					return array(
						'success' => false,
						'message' => 'Redirection would create an infinite loop.',
					);
				}

				$redirection_id = $redirection->save();
				if ( empty( $redirection_id ) ) {
					return array(
						'success' => false,
						'message' => 'Failed to create redirection.',
					);
				}

				return array(
					'success' => true,
					'id'      => (int) $redirection_id,
					'message' => 'Redirection created.',
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
	// RANK MATH - Delete Redirections
	// =========================================================================
	wp_register_ability(
		'rankmath/delete-redirections',
		array(
			'label'               => 'Delete Rank Math Redirections',
			'description'         => 'Delete Rank Math redirections by ID.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'ids' ),
				'properties'           => array(
					'ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Array of redirection IDs to delete.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'deleted' => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input ): array {
				if ( ! mcp_rankmath_is_active() ) {
					return array(
						'success' => false,
						'message' => 'Rank Math SEO plugin is not active.',
					);
				}

				if ( ! class_exists( 'RankMath\\Redirections\\DB' ) ) {
					return array(
						'success' => false,
						'message' => 'Rank Math redirections module is not available.',
					);
				}

				$ids = array_filter( array_map( 'absint', $input['ids'] ?? array() ) );
				if ( empty( $ids ) ) {
					return array(
						'success' => false,
						'message' => 'No valid IDs provided.',
					);
				}

				$deleted = \RankMath\Redirections\DB::delete( $ids );

				return array(
					'success' => true,
					'deleted' => (int) $deleted,
					'message' => 'Deleted ' . (int) $deleted . ' redirection(s).',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),
		)
	);
}
