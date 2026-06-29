<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Rank Math route and llms.txt abilities.
 */
function mcp_rankmath_register_route_abilities(): void {
	// =========================================================================
	// RANK MATH - Refresh LLMS Route
	// =========================================================================
	wp_register_ability(
		'rankmath/refresh-llms-route',
		array(
			'label'               => 'Refresh Rank Math llms.txt Route',
			'description'         => 'Checks whether the Rank Math llms.txt rewrite rule is present and flushes rewrite rules when needed.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'force_flush' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Flush rewrite rules even if the llms.txt rule already appears to exist.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'            => array( 'type' => 'boolean' ),
					'module_active'      => array( 'type' => 'boolean' ),
					'permalink_structure' => array( 'type' => 'string' ),
					'before'             => array( 'type' => 'object' ),
					'after'              => array( 'type' => 'object' ),
					'flushed'            => array( 'type' => 'boolean' ),
					'message'            => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				if ( ! mcp_rankmath_is_active() ) {
					return array(
						'success' => false,
						'message' => 'Rank Math SEO plugin is not active.',
					);
				}

				if ( ! function_exists( 'flush_rewrite_rules' ) ) {
					return array(
						'success' => false,
						'message' => 'WordPress rewrite functions are unavailable.',
					);
				}

				$force_flush         = ! empty( $input['force_flush'] );
				$module_active       = class_exists( '\\RankMath\\Helper' ) && \RankMath\Helper::is_module_active( 'llms-txt' );
				$permalink_structure = (string) get_option( 'permalink_structure', '' );
				$before              = mcp_rankmath_get_llms_rewrite_status();
				$flushed             = false;

				if ( ! $module_active ) {
					return array(
						'success'             => false,
						'module_active'       => false,
						'permalink_structure' => $permalink_structure,
						'before'              => $before,
						'after'               => $before,
						'flushed'             => false,
						'message'             => 'Rank Math llms-txt module is not active.',
					);
				}

				if ( $force_flush || empty( $before['rule_present'] ) ) {
					flush_rewrite_rules( false );
					$flushed = true;
				}

				$after = mcp_rankmath_get_llms_rewrite_status();

				return array(
					'success'             => ! empty( $after['rule_present'] ),
					'module_active'       => true,
					'permalink_structure' => $permalink_structure,
					'before'              => $before,
					'after'               => $after,
					'flushed'             => $flushed,
					'message'             => ! empty( $after['rule_present'] ) ? 'llms.txt rewrite rule is available.' : 'llms.txt rewrite rule is still missing after refresh.',
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
	// RANK MATH - Get Rewrite Status
	// =========================================================================
	wp_register_ability(
		'rankmath/get-rewrite-status',
		array(
			'label'               => 'Get Rank Math Rewrite Status',
			'description'         => 'Inspect stored rewrite rules for llms.txt, sitemap_index.xml, or a custom regex.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'endpoint'     => array(
						'type'        => 'string',
						'enum'        => array( 'llms.txt', 'sitemap_index.xml', 'custom' ),
						'default'     => 'llms.txt',
					),
					'custom_regex' => array(
						'type'        => 'string',
						'description' => 'Used only when endpoint=custom.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'status'  => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$endpoint     = isset( $input['endpoint'] ) ? sanitize_text_field( (string) $input['endpoint'] ) : 'llms.txt';
				$custom_regex = isset( $input['custom_regex'] ) ? sanitize_text_field( (string) $input['custom_regex'] ) : '';

				return array(
					'success' => true,
					'status'  => mcp_rankmath_get_rewrite_status( $endpoint, $custom_regex ),
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
	// RANK MATH - Get LLMS Status
	// =========================================================================
	wp_register_ability(
		'rankmath/get-llms-status',
		array(
			'label'               => 'Get Rank Math llms.txt Status',
			'description'         => 'Return Rank Math llms.txt module state, settings, rewrite status, and a live preview.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'preview_lines' => array(
						'type'    => 'integer',
						'default' => 12,
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'status'  => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				if ( ! mcp_rankmath_is_active() ) {
					return array( 'success' => false, 'message' => 'Rank Math SEO plugin is not active.' );
				}

				$preview_lines = isset( $input['preview_lines'] ) ? max( 1, min( 50, (int) $input['preview_lines'] ) ) : 12;

				return array(
					'success' => true,
					'status'  => mcp_rankmath_get_llms_status_data( $preview_lines ),
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
	// RANK MATH - Preview LLMS
	// =========================================================================
	wp_register_ability(
		'rankmath/preview-llms',
		array(
			'label'               => 'Preview Rank Math llms.txt',
			'description'         => 'Fetch the live llms.txt output and return the first lines for inspection.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'max_lines' => array(
						'type'    => 'integer',
						'default' => 40,
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'preview' => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$max_lines = isset( $input['max_lines'] ) ? max( 1, min( 200, (int) $input['max_lines'] ) ) : 40;

				return array(
					'success' => true,
					'preview' => mcp_rankmath_fetch_local_preview( '/llms.txt', $max_lines ),
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
