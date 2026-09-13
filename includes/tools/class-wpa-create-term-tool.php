<?php
/**
 * Create Term Tool — create a new taxonomy term with optional parent.
 * Destructive: requires user confirmation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Create_Term_Tool extends WPA_Abstract_Tool {

    public function get_name() {
        return 'create_term';
    }

    public function get_title() {
        return 'ساخت ترم';
    }

    public function get_description() {
        return 'Create a new term (category, tag or custom taxonomy term) with optional slug, description and '
            . 'parent term (hierarchical taxonomies only). This action requires user confirmation in the UI.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'taxonomy'    => [
                    'type'        => 'string',
                    'description' => 'Taxonomy slug, e.g. "category" or "post_tag".',
                ],
                'name'        => [
                    'type'        => 'string',
                    'description' => 'Name of the new term.',
                ],
                'slug'        => [
                    'type'        => 'string',
                    'description' => 'Optional URL slug.',
                ],
                'description' => [
                    'type'        => 'string',
                    'description' => 'Optional description.',
                ],
                'parent_id'   => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'Optional parent term ID (hierarchical taxonomies only).',
                ],
            ],
            'required'   => [ 'taxonomy', 'name' ],
        ];
    }

    public function get_required_capability() {
        return 'manage_categories';
    }

    public function is_destructive() {
        return true;
    }

    public function summarize( array $args ) {
        $lines   = [];
        $lines[] = 'نام: ' . ( isset( $args['name'] ) ? $args['name'] : '—' );
        $lines[] = 'تاکسونومی: ' . ( isset( $args['taxonomy'] ) ? $args['taxonomy'] : '—' );

        if ( ! empty( $args['parent_id'] ) ) {
            $parent  = get_term( absint( $args['parent_id'] ) );
            $lines[] = 'والد: ' . ( ( $parent && ! is_wp_error( $parent ) ) ? $parent->name : 'شناسه ' . $args['parent_id'] );
        }

        if ( ! empty( $args['description'] ) ) {
            $lines[] = 'توضیح: ' . wp_trim_words( $args['description'], 15 );
        }

        return $lines;
    }

    public function execute( array $args ) {
        $taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : '';
        $name     = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error(
                'wpa_tool_error',
                sprintf( 'تاکسونومی «%s» وجود ندارد. تاکسونومی‌های موجود: %s', $taxonomy, implode( '، ', get_taxonomies( [ 'public' => true ] ) ) )
            );
        }

        $term_args = [];

        if ( ! empty( $args['slug'] ) ) {
            $term_args['slug'] = sanitize_title( $args['slug'] );
        }

        if ( ! empty( $args['description'] ) ) {
            $term_args['description'] = sanitize_textarea_field( $args['description'] );
        }

        if ( ! empty( $args['parent_id'] ) ) {
            if ( ! is_taxonomy_hierarchical( $taxonomy ) ) {
                return new WP_Error( 'wpa_tool_error', sprintf( 'تاکسونومی «%s» سلسله‌مراتبی نیست و ترم والد نمی‌پذیرد.', $taxonomy ) );
            }

            $parent = get_term( absint( $args['parent_id'] ), $taxonomy );

            if ( ! $parent || is_wp_error( $parent ) ) {
                return new WP_Error( 'wpa_tool_error', sprintf( 'ترم والد با شناسه %d در این تاکسونومی یافت نشد.', absint( $args['parent_id'] ) ) );
            }

            $term_args['parent'] = $parent->term_id;
        }

        $result = wp_insert_term( $name, $taxonomy, $term_args );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'wpa_tool_error', 'خطا در ساخت ترم: ' . $result->get_error_message() );
        }

        $term = get_term( $result['term_id'], $taxonomy );
        $link = get_term_link( $term );

        return [
            'id'       => (int) $term->term_id,
            'name'     => $term->name,
            'slug'     => $term->slug,
            'taxonomy' => $taxonomy,
            'link'     => is_wp_error( $link ) ? null : $link,
        ];
    }
}
