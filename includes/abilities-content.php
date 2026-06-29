<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Rank Math content SEO abilities.
 */
function mcp_rankmath_register_content_abilities(): void {
	// =========================================================================
	// RANK MATH - Get SEO Meta
	// =========================================================================
	wp_register_ability(
		'rankmath/get-meta',
		array(
			'label'               => 'Get Rank Math SEO Meta',
			'description'         => 'Get Rank Math SEO meta data for a post or page. Returns title, description, focus keyword, SEO score, robots, and other SEO settings.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id'   => array(
						'type'        => 'integer',
						'description' => 'Post or page ID.',
					),
					'type' => array(
						'type'        => 'string',
						'enum'        => array( 'post', 'page', 'any' ),
						'default'     => 'any',
						'description' => 'Post type to query (default: any).',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'       => array( 'type' => 'boolean' ),
					'id'            => array( 'type' => 'integer' ),
					'title'         => array( 'type' => 'string' ),
					'url'           => array( 'type' => 'string' ),
					'seo_title'     => array( 'type' => 'string' ),
					'seo_description' => array( 'type' => 'string' ),
					'focus_keyword' => array( 'type' => 'string' ),
					'seo_score'     => array(
						'type'        => array( 'integer', 'null' ),
						'description' => 'Stored Rank Math SEO score from rank_math_seo_score, or null when no score has been calculated.',
						'minimum'     => 0,
						'maximum'     => 100,
					),
					'robots'        => array( 'type' => 'array' ),
					'canonical_url' => array( 'type' => 'string' ),
					'is_pillar'     => array( 'type' => 'boolean' ),
					'is_cornerstone' => array( 'type' => 'boolean' ),
					'message'       => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input ): array {
				if ( ! mcp_rankmath_is_active() ) {
					return array(
						'success' => false,
						'message' => 'Rank Math SEO plugin is not active.',
					);
				}

				$post_id = (int) $input['id'];
				$result  = mcp_rankmath_get_post_or_error( $post_id, 'access' );
				if ( empty( $result['success'] ) ) {
					return $result;
				}
				$post = $result['post'];

				// Get all Rank Math meta.
				$seo_title     = get_post_meta( $post_id, 'rank_math_title', true );
				$seo_desc      = get_post_meta( $post_id, 'rank_math_description', true );
				$focus_keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
				$seo_score     = mcp_rankmath_get_seo_score( $post_id );
				$robots        = get_post_meta( $post_id, 'rank_math_robots', true );
				$canonical     = get_post_meta( $post_id, 'rank_math_canonical_url', true );
				$is_pillar     = get_post_meta( $post_id, 'rank_math_pillar_content', true );
				$is_cornerstone = get_post_meta( $post_id, 'rank_math_cornerstone_content', true );

				return array(
					'success'         => true,
					'id'              => $post_id,
					'title'           => $post->post_title,
					'url'             => get_permalink( $post_id ),
					'post_type'       => $post->post_type,
					'seo_title'       => $seo_title ?: '',
					'seo_description' => $seo_desc ?: '',
					'focus_keyword'   => $focus_keyword ?: '',
					'seo_score'       => $seo_score,
					'robots'          => is_array( $robots ) ? $robots : array(),
					'canonical_url'   => $canonical ?: '',
					'is_pillar'       => $is_pillar === 'on',
					'is_cornerstone'  => $is_cornerstone === 'on',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
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
	// RANK MATH - Update SEO Meta
	// =========================================================================
	wp_register_ability(
		'rankmath/update-meta',
		array(
			'label'               => 'Update Rank Math SEO Meta',
			'description'         => 'Update Rank Math SEO meta data for a post or page. Can update title, description, focus keyword, robots, canonical URL, and content flags. Also accepts title/description/keyword aliases for convenience.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id'              => array(
						'type'        => 'integer',
						'description' => 'Post or page ID.',
					),
					'seo_title'       => array(
						'type'        => 'string',
						'description' => 'Custom SEO title. Use variables like %title%, %sitename%, %sep%.',
					),
					'title'           => array(
						'type'        => 'string',
						'description' => 'Alias for seo_title.',
					),
					'seo_description' => array(
						'type'        => 'string',
						'description' => 'Meta description (recommended: 150-160 characters).',
					),
					'description'     => array(
						'type'        => 'string',
						'description' => 'Alias for seo_description.',
					),
					'focus_keyword'   => array(
						'type'        => 'string',
						'description' => 'Focus keyword(s). Separate multiple with commas.',
					),
					'keyword'         => array(
						'type'        => 'string',
						'description' => 'Alias for focus_keyword.',
					),
					'robots'          => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Robot meta tags: index, noindex, follow, nofollow, etc.',
					),
					'canonical_url'   => array(
						'type'        => 'string',
						'description' => 'Custom canonical URL (leave empty to use default).',
					),
					'is_pillar'       => array(
						'type'        => 'boolean',
						'description' => 'Mark as pillar content.',
					),
					'is_cornerstone'  => array(
						'type'        => 'boolean',
						'description' => 'Mark as cornerstone content.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'id'       => array( 'type' => 'integer' ),
					'updated'  => array( 'type' => 'array' ),
					'url'      => array( 'type' => 'string' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input ): array {
				if ( ! mcp_rankmath_is_active() ) {
					return array(
						'success' => false,
						'message' => 'Rank Math SEO plugin is not active.',
					);
				}

				$post_id = (int) $input['id'];
				$result  = mcp_rankmath_get_post_or_error( $post_id, 'edit' );
				if ( empty( $result['success'] ) ) {
					return $result;
				}

				$updated         = array();
				$seo_title_input = $input['seo_title'] ?? $input['title'] ?? null;
				$seo_desc_input  = $input['seo_description'] ?? $input['description'] ?? null;
				$focus_input     = $input['focus_keyword'] ?? $input['keyword'] ?? null;

				// Update SEO title.
				if ( null !== $seo_title_input ) {
					update_post_meta( $post_id, 'rank_math_title', sanitize_text_field( $seo_title_input ) );
					$updated[] = 'seo_title';
				}

				// Update SEO description.
				if ( null !== $seo_desc_input ) {
					update_post_meta( $post_id, 'rank_math_description', sanitize_textarea_field( $seo_desc_input ) );
					$updated[] = 'seo_description';
				}

				// Update focus keyword.
				if ( null !== $focus_input ) {
					update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $focus_input ) );
					$updated[] = 'focus_keyword';
				}

				// Update robots.
				if ( isset( $input['robots'] ) && is_array( $input['robots'] ) ) {
					$allowed_robots = array( 'index', 'noindex', 'follow', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
					$robots = array_filter( $input['robots'], function( $r ) use ( $allowed_robots ) {
						return in_array( $r, $allowed_robots, true );
					});
					update_post_meta( $post_id, 'rank_math_robots', $robots );
					$updated[] = 'robots';
				}

				// Update canonical URL.
				if ( isset( $input['canonical_url'] ) ) {
					$canonical = esc_url_raw( $input['canonical_url'] );
					if ( empty( $input['canonical_url'] ) ) {
						delete_post_meta( $post_id, 'rank_math_canonical_url' );
					} else {
						update_post_meta( $post_id, 'rank_math_canonical_url', $canonical );
					}
					$updated[] = 'canonical_url';
				}

				// Update pillar content flag.
				if ( isset( $input['is_pillar'] ) ) {
					if ( $input['is_pillar'] ) {
						update_post_meta( $post_id, 'rank_math_pillar_content', 'on' );
					} else {
						delete_post_meta( $post_id, 'rank_math_pillar_content' );
					}
					$updated[] = 'is_pillar';
				}

				// Update cornerstone content flag.
				if ( isset( $input['is_cornerstone'] ) ) {
					if ( $input['is_cornerstone'] ) {
						update_post_meta( $post_id, 'rank_math_cornerstone_content', 'on' );
					} else {
						delete_post_meta( $post_id, 'rank_math_cornerstone_content' );
					}
					$updated[] = 'is_cornerstone';
				}

				if ( empty( $updated ) ) {
					return array(
						'success' => false,
						'message' => 'No fields provided to update.',
					);
				}

				return array(
					'success' => true,
					'id'      => $post_id,
					'updated' => $updated,
					'url'     => get_permalink( $post_id ),
					'message' => 'Updated ' . count( $updated ) . ' field(s): ' . implode( ', ', $updated ),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
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
	// RANK MATH - Bulk Get SEO Meta
	// =========================================================================
	wp_register_ability(
		'rankmath/bulk-get-meta',
		array(
			'label'               => 'Bulk Get Rank Math SEO Meta',
			'description'         => 'Get Rank Math SEO meta for multiple posts/pages, including stored Rank Math SEO scores. Useful for auditing SEO across content.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'post_type'    => array(
						'type'        => 'string',
						'default'     => 'any',
						'description' => 'Filter by post type: post, page, or any.',
					),
					'per_page'     => array(
						'type'        => 'integer',
						'default'     => 20,
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => 'Number of items per page (max 100).',
					),
					'page'         => array(
						'type'        => 'integer',
						'default'     => 1,
						'minimum'     => 1,
						'description' => 'Page number.',
					),
					'missing_desc' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Only return posts missing meta description.',
					),
					'search'       => array(
						'type'        => 'string',
						'description' => 'Search in post titles.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'items'   => array( 'type' => 'array' ),
					'total'   => array( 'type' => 'integer' ),
					'page'    => array( 'type' => 'integer' ),
					'pages'   => array( 'type' => 'integer' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				if ( ! mcp_rankmath_is_active() ) {
					return array(
						'success' => false,
						'message' => 'Rank Math SEO plugin is not active.',
					);
				}

				$per_page     = isset( $input['per_page'] ) ? min( 100, max( 1, (int) $input['per_page'] ) ) : 20;
				$page         = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
				$post_type    = isset( $input['post_type'] ) && $input['post_type'] !== 'any' ? $input['post_type'] : array( 'post', 'page' );
				$missing_desc = ! empty( $input['missing_desc'] );

				// When filtering for missing descriptions, fetch more and filter in PHP.
				$fetch_limit = $missing_desc ? $per_page * 5 : $per_page;

				$args = array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => $fetch_limit,
					'paged'          => $missing_desc ? 1 : $page,
					'orderby'        => 'modified',
					'order'          => 'DESC',
				);

				if ( ! current_user_can( 'edit_others_posts' ) ) {
					$current_user = wp_get_current_user();
					$args['author'] = $current_user->ID;
				}

				if ( ! empty( $input['search'] ) ) {
					$args['s'] = sanitize_text_field( $input['search'] );
				}

				$query = new WP_Query( $args );
				$items = array();

				foreach ( $query->posts as $post ) {
					if ( ! current_user_can( 'edit_post', $post->ID ) ) {
						continue;
					}

					$seo_desc = get_post_meta( $post->ID, 'rank_math_description', true );

					// Filter for missing descriptions if requested.
					if ( $missing_desc && ! empty( $seo_desc ) ) {
						continue;
					}

					$items[] = array(
						'id'              => $post->ID,
						'title'           => $post->post_title,
						'post_type'       => $post->post_type,
						'url'             => get_permalink( $post->ID ),
						'seo_title'       => get_post_meta( $post->ID, 'rank_math_title', true ) ?: '',
						'seo_description' => $seo_desc ?: '',
						'focus_keyword'   => get_post_meta( $post->ID, 'rank_math_focus_keyword', true ) ?: '',
						'seo_score'       => mcp_rankmath_get_seo_score( $post->ID ),
					);

					// Stop when we have enough items.
					if ( count( $items ) >= $per_page ) {
						break;
					}
				}

				$total = $missing_desc ? count( $items ) : (int) $query->found_posts;
				$pages = $missing_desc ? 1 : (int) $query->max_num_pages;

				return array(
					'success' => true,
					'items'   => $items,
					'total'   => $total,
					'page'    => $missing_desc ? 1 : $page,
					'pages'   => $pages,
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
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
	// RANK MATH - Get Inbound Links
	// =========================================================================
	wp_register_ability(
		'rankmath/get-inbound-links',
		array(
			'label'               => 'Get Inbound Links',
			'description'         => 'Build an internal inbound-link report from WordPress content and navigation menus. Use target_post_id or target_url to inspect one page, or omit both to list linked internal targets.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'target_post_id'  => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Optional target post/page ID to inspect.',
					),
					'target_url'      => array(
						'type'        => 'string',
						'description' => 'Optional internal target URL or path to inspect.',
					),
					'post_types'      => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Optional source/target post types to scan. Defaults to public post types plus common reusable/template post types.',
					),
					'post_statuses'   => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Optional post statuses to scan. Defaults to publish.',
					),
					'include_sources' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Include source objects for each inbound link target.',
					),
					'include_menus'   => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Include WordPress navigation menu items as inbound link sources.',
					),
					'min_count'       => array(
						'type'        => 'integer',
						'default'     => 1,
						'minimum'     => 0,
						'description' => 'Minimum inbound count when listing all linked targets.',
					),
					'limit'           => array(
						'type'        => 'integer',
						'default'     => 100,
						'minimum'     => 1,
						'maximum'     => 500,
						'description' => 'Maximum targets to return.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'         => array( 'type' => 'boolean' ),
					'items'           => array( 'type' => 'array' ),
					'count'           => array( 'type' => 'integer' ),
					'scanned_sources' => array( 'type' => 'integer' ),
					'post_types'      => array( 'type' => 'array' ),
					'post_statuses'   => array( 'type' => 'array' ),
					'target_paths'    => array( 'type' => 'array' ),
					'message'         => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				return mcp_rankmath_build_inbound_link_graph( $input );
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
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
	// RANK MATH - Audit Content SEO
	// =========================================================================
	wp_register_ability(
		'rankmath/audit-content-seo',
		array(
			'label'               => 'Audit Rank Math Content SEO',
			'description'         => 'Find published content with missing Rank Math SEO fields, noindex robots, low stored SEO scores, missing schema, or weak internal inbound-link coverage.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'post_types'      => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Post types to audit. Defaults to post and page.',
					),
					'post_statuses'   => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Post statuses to audit. Defaults to publish.',
					),
					'per_page'        => array( 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200 ),
					'page'            => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
					'search'          => array( 'type' => 'string' ),
					'score_below'     => array( 'type' => 'integer', 'default' => 70, 'minimum' => 0, 'maximum' => 100 ),
					'include_schema'  => array( 'type' => 'boolean', 'default' => true ),
					'include_inbound' => array( 'type' => 'boolean', 'default' => false ),
					'only_issues'     => array( 'type' => 'boolean', 'default' => true ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'items'   => array( 'type' => 'array' ),
					'total'   => array( 'type' => 'integer' ),
					'page'    => array( 'type' => 'integer' ),
					'pages'   => array( 'type' => 'integer' ),
					'counts'  => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				if ( ! mcp_rankmath_is_active() ) {
					return array( 'success' => false, 'message' => 'Rank Math SEO plugin is not active.' );
				}

				$post_types      = mcp_rankmath_get_content_audit_post_types( $input['post_types'] ?? array() );
				$post_statuses   = mcp_rankmath_get_content_audit_statuses( $input['post_statuses'] ?? array() );
				$per_page        = min( 200, max( 1, (int) ( $input['per_page'] ?? 50 ) ) );
				$page            = max( 1, (int) ( $input['page'] ?? 1 ) );
				$score_below     = min( 100, max( 0, (int) ( $input['score_below'] ?? 70 ) ) );
				$include_schema  = array_key_exists( 'include_schema', $input ) ? (bool) $input['include_schema'] : true;
				$include_inbound = ! empty( $input['include_inbound'] );
				$only_issues     = array_key_exists( 'only_issues', $input ) ? (bool) $input['only_issues'] : true;

				$args = array(
					'post_type'              => $post_types,
					'post_status'            => $post_statuses,
					'posts_per_page'         => $per_page,
					'paged'                  => $page,
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'update_post_meta_cache' => true,
					'update_post_term_cache' => false,
				);

				if ( ! current_user_can( 'edit_others_posts' ) ) {
					$current_user   = wp_get_current_user();
					$args['author'] = $current_user->ID;
				}

				if ( ! empty( $input['search'] ) ) {
					$args['s'] = sanitize_text_field( (string) $input['search'] );
				}

				$query  = new WP_Query( $args );
				$inbound_counts = $include_inbound ? mcp_rankmath_get_inbound_counts_for_posts( array_map( 'absint', wp_list_pluck( $query->posts, 'ID' ) ) ) : array();
				$items  = array();
				$counts = array(
					'missing_seo_title'       => 0,
					'missing_seo_description' => 0,
					'missing_focus_keyword'   => 0,
					'noindex'                 => 0,
					'low_score'               => 0,
					'missing_schema'          => 0,
					'no_inbound_links'        => 0,
				);

				foreach ( $query->posts as $post ) {
					if ( ! current_user_can( 'edit_post', $post->ID ) ) {
						continue;
					}

					$issues          = array();
					$seo_title       = (string) get_post_meta( $post->ID, 'rank_math_title', true );
					$seo_description = (string) get_post_meta( $post->ID, 'rank_math_description', true );
					$focus_keyword   = (string) get_post_meta( $post->ID, 'rank_math_focus_keyword', true );
					$robots          = get_post_meta( $post->ID, 'rank_math_robots', true );
					$robots          = is_array( $robots ) ? array_values( $robots ) : array();
					$seo_score       = mcp_rankmath_get_seo_score( $post->ID );
					$schema          = $include_schema ? mcp_rankmath_get_post_schema_meta( $post->ID ) : array();
					$inbound_count   = $include_inbound ? (int) ( $inbound_counts[ $post->ID ] ?? 0 ) : null;

					if ( '' === trim( $seo_title ) ) {
						$issues[] = 'missing_seo_title';
						++$counts['missing_seo_title'];
					}
					if ( '' === trim( $seo_description ) ) {
						$issues[] = 'missing_seo_description';
						++$counts['missing_seo_description'];
					}
					if ( '' === trim( $focus_keyword ) ) {
						$issues[] = 'missing_focus_keyword';
						++$counts['missing_focus_keyword'];
					}
					if ( in_array( 'noindex', $robots, true ) ) {
						$issues[] = 'noindex';
						++$counts['noindex'];
					}
					if ( null !== $seo_score && $seo_score < $score_below ) {
						$issues[] = 'low_score';
						++$counts['low_score'];
					}
					if ( $include_schema && empty( $schema ) ) {
						$issues[] = 'missing_schema';
						++$counts['missing_schema'];
					}
					if ( $include_inbound && 0 === $inbound_count ) {
						$issues[] = 'no_inbound_links';
						++$counts['no_inbound_links'];
					}

					if ( $only_issues && empty( $issues ) ) {
						continue;
					}

					$items[] = array(
						'id'              => (int) $post->ID,
						'title'           => get_the_title( $post ),
						'post_type'       => $post->post_type,
						'post_status'     => $post->post_status,
						'url'             => get_permalink( $post ),
						'modified_gmt'    => $post->post_modified_gmt,
						'seo_title'       => $seo_title,
						'seo_description' => $seo_description,
						'focus_keyword'   => $focus_keyword,
						'seo_score'       => $seo_score,
						'robots'          => $robots,
						'has_schema'      => ! empty( $schema ),
						'inbound_count'   => $inbound_count,
						'issues'          => $issues,
					);
				}

				return array(
					'success' => true,
					'items'   => $items,
					'total'   => (int) $query->found_posts,
					'page'    => $page,
					'pages'   => (int) $query->max_num_pages,
					'counts'  => $counts,
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
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
	// RANK MATH - Get/Update Post Schema
	// =========================================================================
	wp_register_ability(
		'rankmath/get-post-schema',
		array(
			'label'               => 'Get Rank Math Post Schema',
			'description'         => 'Read Rank Math schema-related post meta for one post or page.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id' => array( 'type' => 'integer', 'minimum' => 1 ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'id'      => array( 'type' => 'integer' ),
					'title'   => array( 'type' => 'string' ),
					'url'     => array( 'type' => 'string' ),
					'schema'  => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input ): array {
				$post_id = absint( $input['id'] ?? 0 );
				$result  = mcp_rankmath_get_post_or_error( $post_id, 'access' );
				if ( empty( $result['success'] ) ) {
					return $result;
				}
				$post = $result['post'];

				return array(
					'success' => true,
					'id'      => $post_id,
					'title'   => $post->post_title,
					'url'     => get_permalink( $post_id ),
					'schema'  => mcp_rankmath_get_post_schema_meta( $post_id ),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
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

	wp_register_ability(
		'rankmath/update-post-schema',
		array(
			'label'               => 'Update Rank Math Post Schema',
			'description'         => 'Update or delete Rank Math schema meta keys for one post. Only rank_math_schema_* keys can be written through this ability.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id'             => array( 'type' => 'integer', 'minimum' => 1 ),
					'schemas'        => array(
						'type'                 => 'object',
						'description'          => 'Object keyed by rank_math_schema_* meta key. Values may be objects, arrays, JSON strings, or strings.',
						'additionalProperties' => true,
					),
					'delete_keys'    => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
					'confirm_delete' => array( 'type' => 'boolean', 'default' => false ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'id'      => array( 'type' => 'integer' ),
					'updated' => array( 'type' => 'array' ),
					'deleted' => array( 'type' => 'array' ),
					'schema'  => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input ): array {
				$post_id = absint( $input['id'] ?? 0 );
				$result  = mcp_rankmath_get_post_or_error( $post_id, 'edit' );
				if ( empty( $result['success'] ) ) {
					return $result;
				}

				$updated = array();
				$deleted = array();

				if ( isset( $input['schemas'] ) && is_array( $input['schemas'] ) ) {
					foreach ( $input['schemas'] as $key => $value ) {
						$key = trim( (string) $key );
						if ( ! mcp_rankmath_is_schema_meta_key( $key ) ) {
							return array( 'success' => false, 'message' => 'Schema meta key must match rank_math_schema_* and contain only letters, numbers, underscores, or hyphens.' );
						}
						update_post_meta( $post_id, $key, mcp_rankmath_sanitize_schema_value( $value ) );
						$updated[] = $key;
					}
				}

				if ( isset( $input['delete_keys'] ) && is_array( $input['delete_keys'] ) ) {
					if ( empty( $input['confirm_delete'] ) ) {
						return array( 'success' => false, 'message' => 'confirm_delete must be true when deleting schema keys.' );
					}
					foreach ( $input['delete_keys'] as $key ) {
						$key = trim( (string) $key );
						if ( ! mcp_rankmath_is_schema_meta_key( $key ) ) {
							return array( 'success' => false, 'message' => 'Only valid rank_math_schema_* keys can be deleted.' );
						}
						delete_post_meta( $post_id, $key );
						$deleted[] = $key;
					}
				}

				if ( empty( $updated ) && empty( $deleted ) ) {
					return array( 'success' => false, 'message' => 'No schema changes provided.' );
				}

				return array(
					'success' => true,
					'id'      => $post_id,
					'updated' => $updated,
					'deleted' => $deleted,
					'schema'  => mcp_rankmath_get_post_schema_meta( $post_id ),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		)
	);

	// =========================================================================
	// RANK MATH - Primary Terms
	// =========================================================================
	wp_register_ability(
		'rankmath/get-primary-term',
		array(
			'label'               => 'Get Rank Math Primary Term',
			'description'         => 'Read the Rank Math primary term for a post and taxonomy, plus the assigned term list.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id'       => array( 'type' => 'integer', 'minimum' => 1 ),
					'taxonomy' => array( 'type' => 'string', 'default' => 'category' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'         => array( 'type' => 'boolean' ),
					'post_id'         => array( 'type' => 'integer' ),
					'taxonomy'        => array( 'type' => 'string' ),
					'primary_term_id' => array( 'type' => 'integer' ),
					'primary_term'    => array( 'type' => array( 'object', 'null' ) ),
					'terms'           => array( 'type' => 'array' ),
					'message'         => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input ): array {
				$post_id = absint( $input['id'] ?? 0 );
				$result  = mcp_rankmath_get_post_or_error( $post_id, 'access' );
				if ( empty( $result['success'] ) ) {
					return $result;
				}

				return mcp_rankmath_get_primary_term_data( $post_id, (string) ( $input['taxonomy'] ?? 'category' ) );
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
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

	wp_register_ability(
		'rankmath/update-primary-term',
		array(
			'label'               => 'Update Rank Math Primary Term',
			'description'         => 'Set or clear the Rank Math primary term for a post and taxonomy.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id', 'taxonomy' ),
				'properties'           => array(
					'id'       => array( 'type' => 'integer', 'minimum' => 1 ),
					'taxonomy' => array( 'type' => 'string' ),
					'term_id'  => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Set 0 to clear the primary term.' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'updated' => array( 'type' => 'boolean' ),
					'status'  => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input ): array {
				$post_id  = absint( $input['id'] ?? 0 );
				$taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
				$term_id  = isset( $input['term_id'] ) ? absint( $input['term_id'] ) : 0;
				$result   = mcp_rankmath_get_post_or_error( $post_id, 'edit' );
				if ( empty( $result['success'] ) ) {
					return $result;
				}

				$status = mcp_rankmath_get_primary_term_data( $post_id, $taxonomy );
				if ( empty( $status['success'] ) ) {
					return $status;
				}

				$meta_key = 'rank_math_primary_' . $taxonomy;
				if ( 0 === $term_id ) {
					delete_post_meta( $post_id, $meta_key );
					return array(
						'success' => true,
						'updated' => true,
						'status'  => mcp_rankmath_get_primary_term_data( $post_id, $taxonomy ),
					);
				}

				$assigned_ids = array_map(
					static function ( array $term ): int {
						return (int) $term['id'];
					},
					$status['terms']
				);

				if ( ! in_array( $term_id, $assigned_ids, true ) ) {
					return array( 'success' => false, 'message' => 'Term is not assigned to this post.' );
				}

				update_post_meta( $post_id, $meta_key, (string) $term_id );

				return array(
					'success' => true,
					'updated' => true,
					'status'  => mcp_rankmath_get_primary_term_data( $post_id, $taxonomy ),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
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
	// RANK MATH - Sitemap URLs and Redirect Match
	// =========================================================================
	wp_register_ability(
		'rankmath/list-sitemap-urls',
		array(
			'label'               => 'List Rank Math Sitemap URLs',
			'description'         => 'Fetch the Rank Math sitemap index and optionally child sitemap URLs for inspection.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'sitemap_path'     => array( 'type' => 'string', 'default' => '/sitemap_index.xml' ),
					'include_children' => array( 'type' => 'boolean', 'default' => true ),
					'limit'            => array( 'type' => 'integer', 'default' => 250, 'minimum' => 1, 'maximum' => 1000 ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'index_url' => array( 'type' => 'string' ),
					'sitemaps'  => array( 'type' => 'array' ),
					'urls'      => array( 'type' => 'array' ),
					'count'     => array( 'type' => 'integer' ),
					'message'   => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$path             = isset( $input['sitemap_path'] ) ? (string) $input['sitemap_path'] : '/sitemap_index.xml';
				$path             = '/' . ltrim( $path, '/' );
				$include_children = array_key_exists( 'include_children', $input ) ? (bool) $input['include_children'] : true;
				$limit            = min( 1000, max( 1, (int) ( $input['limit'] ?? 250 ) ) );
				$index_url        = home_url( $path );
				$response         = wp_remote_get( $index_url, array( 'timeout' => 20, 'redirection' => 3 ) );

				if ( is_wp_error( $response ) ) {
					return array( 'success' => false, 'message' => $response->get_error_message(), 'index_url' => $index_url );
				}

				$body     = (string) wp_remote_retrieve_body( $response );
				$sitemaps = mcp_rankmath_parse_sitemap_locs( $body );
				$urls     = array();

				if ( ! $include_children ) {
					$urls = $sitemaps;
				} else {
					foreach ( $sitemaps as $sitemap_url ) {
						if ( count( $urls ) >= $limit ) {
							break;
						}

						$child_host = wp_parse_url( $sitemap_url, PHP_URL_HOST );
						$home_host  = wp_parse_url( home_url(), PHP_URL_HOST );
						if ( $child_host && $home_host && strtolower( preg_replace( '/^www\./', '', $child_host ) ) !== strtolower( preg_replace( '/^www\./', '', $home_host ) ) ) {
							continue;
						}

						$child = wp_remote_get( $sitemap_url, array( 'timeout' => 20, 'redirection' => 3 ) );
						if ( is_wp_error( $child ) ) {
							continue;
						}
						foreach ( mcp_rankmath_parse_sitemap_locs( (string) wp_remote_retrieve_body( $child ) ) as $url ) {
							$urls[] = $url;
							if ( count( $urls ) >= $limit ) {
								break 2;
							}
						}
					}
				}

				$urls = array_values( array_unique( $urls ) );

				return array(
					'success'  => true,
					'index_url' => $index_url,
					'sitemaps'  => $sitemaps,
					'urls'      => $urls,
					'count'     => count( $urls ),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
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
