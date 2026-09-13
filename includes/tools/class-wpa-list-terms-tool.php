<?php
/**
 * List Terms Tool — read-only listing of taxonomy terms (categories, tags
 * and any custom taxonomy).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_List_Terms_Tool extends WPA_Abstract_Tool {

    public function get_name() {
        return 'list_terms';
    }

    public function get_title() {
        return 'فهرست ترم‌ها';
    }

    public function get_description() {
        return 'List the terms of a taxonomy (e.g. "category", "post_tag" or a custom taxonomy) with optional keyword '
            . 'search. Returns id, name, slug, post count, parent id and a short description for each term. '
            . 'Use get_site_info to discover available taxonomy slugs, and get_term for full details of one term.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'taxonomy'   => [
                    'type'        => 'string',
                    'description' => 'Taxonomy slug, e.g. "category" or "post_tag".',
                ],
                'search'     => [
                    'type'        => 'string',
                    'description' => 'Keyword to search in term names.',
                ],
                'count'      => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 100,
                    'description' => 'Number of terms to return. Default 20.',
                ],
                'hide_empty' => [
                    'type'        => 'boolean',
                    'description' => 'Only return terms that have at least one post. Default false.',
                ],
            ],
            'required'   => [ 'taxonomy' ],
        ];
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function execute( array $args ) {
        $taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : '';

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error(
                'wpa_tool_error',
                sprintf(
                    'تاکسونومی «%s» وجود ندارد. تاکسونومی‌های موجود: %s',
                    $taxonomy,
                    implode( '، ', get_taxonomies( [ 'public' => true ] ) )
                )
            );
        }

        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'number'     => ! empty( $args['count'] ) ? (int) $args['count'] : 20,
                'search'     => ! empty( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '',
                'hide_empty' => ! empty( $args['hide_empty'] ),
            ]
        );

        if ( is_wp_error( $terms ) ) {
            return new WP_Error( 'wpa_tool_error', 'خطا در دریافت ترم‌ها: ' . $terms->get_error_message() );
        }

        $items = [];

        foreach ( $terms as $term ) {
            $items[] = [
                'id'          => (int) $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'count'       => (int) $term->count,
                'parent'      => (int) $term->parent,
                'description' => wp_trim_words( wp_strip_all_tags( $term->description ), 30 ),
            ];
        }

        $taxonomy_object = get_taxonomy( $taxonomy );

        return [
            'taxonomy'       => $taxonomy,
            'taxonomy_label' => $taxonomy_object ? $taxonomy_object->label : $taxonomy,
            'total_terms'    => (int) wp_count_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] ),
            'returned'       => count( $items ),
            'terms'          => $items,
        ];
    }
}
