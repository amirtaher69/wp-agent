<?php
/**
 * Update Term Tool — edit name/slug/description/parent of an existing term.
 * Destructive: requires user confirmation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Update_Term_Tool extends WPA_Abstract_Tool {

    public function get_name() {
        return 'update_term';
    }

    public function get_title() {
        return 'ویرایش ترم';
    }

    public function get_description() {
        return 'Update an existing taxonomy term by ID: name, slug, description or parent. Only the provided '
            . 'fields change. This action requires user confirmation in the UI.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'term_id'     => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'ID of the term to update.',
                ],
                'taxonomy'    => [
                    'type'        => 'string',
                    'description' => 'Taxonomy slug of the term. Optional, but include it when known.',
                ],
                'name'        => [
                    'type'        => 'string',
                    'description' => 'New name.',
                ],
                'slug'        => [
                    'type'        => 'string',
                    'description' => 'New URL slug.',
                ],
                'description' => [
                    'type'        => 'string',
                    'description' => 'New description.',
                ],
                'parent_id'   => [
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'description' => 'New parent term ID; 0 removes the parent. Hierarchical taxonomies only.',
                ],
            ],
            'required'   => [ 'term_id' ],
        ];
    }

    public function get_required_capability() {
        return 'manage_categories';
    }

    public function is_destructive() {
        return true;
    }

    public function summarize( array $args ) {
        $term_id = isset( $args['term_id'] ) ? absint( $args['term_id'] ) : 0;
        $term    = get_term( $term_id );

        $lines   = [];
        $lines[] = 'ترم: ' . ( ( $term && ! is_wp_error( $term ) ) ? sprintf( '«%s» (شناسه %d)', $term->name, $term_id ) : 'شناسه ' . $term_id );

        if ( isset( $args['name'] ) ) {
            $lines[] = 'نام جدید: ' . $args['name'];
        }

        if ( isset( $args['slug'] ) ) {
            $lines[] = 'اسلاگ جدید: ' . $args['slug'];
        }

        if ( isset( $args['description'] ) ) {
            $lines[] = 'توضیح جدید: ' . wp_trim_words( $args['description'], 15 );
        }

        if ( isset( $args['parent_id'] ) ) {
            $lines[] = 'والد جدید: ' . ( empty( $args['parent_id'] ) ? 'بدون والد' : 'شناسه ' . $args['parent_id'] );
        }

        return $lines;
    }

    public function execute( array $args ) {
        $term_id  = isset( $args['term_id'] ) ? absint( $args['term_id'] ) : 0;
        $taxonomy = ! empty( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : '';

        $term = get_term( $term_id, $taxonomy );

        if ( ! $term || is_wp_error( $term ) ) {
            return new WP_Error( 'wpa_tool_error', sprintf( 'ترمی با شناسه %d یافت نشد.', $term_id ) );
        }

        $update = [];

        if ( isset( $args['name'] ) ) {
            $update['name'] = sanitize_text_field( $args['name'] );
        }

        if ( isset( $args['slug'] ) ) {
            $update['slug'] = sanitize_title( $args['slug'] );
        }

        if ( isset( $args['description'] ) ) {
            $update['description'] = sanitize_textarea_field( $args['description'] );
        }

        if ( isset( $args['parent_id'] ) ) {
            if ( ! is_taxonomy_hierarchical( $term->taxonomy ) ) {
                return new WP_Error( 'wpa_tool_error', sprintf( 'تاکسونومی «%s» سلسله‌مراتبی نیست و ترم والد نمی‌پذیرد.', $term->taxonomy ) );
            }

            $parent_id = absint( $args['parent_id'] );

            if ( $parent_id > 0 ) {
                $parent = get_term( $parent_id, $term->taxonomy );

                if ( ! $parent || is_wp_error( $parent ) ) {
                    return new WP_Error( 'wpa_tool_error', sprintf( 'ترم والد با شناسه %d در این تاکسونومی یافت نشد.', $parent_id ) );
                }
            }

            $update['parent'] = $parent_id;
        }

        if ( empty( $update ) ) {
            return new WP_Error( 'wpa_tool_error', 'هیچ فیلدی برای تغییر مشخص نشده است.' );
        }

        $result = wp_update_term( $term->term_id, $term->taxonomy, $update );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'wpa_tool_error', 'خطا در ویرایش ترم: ' . $result->get_error_message() );
        }

        $updated = get_term( $term->term_id, $term->taxonomy );

        return [
            'id'             => (int) $updated->term_id,
            'name'           => $updated->name,
            'slug'           => $updated->slug,
            'taxonomy'       => $updated->taxonomy,
            'description'    => $updated->description,
            'parent'         => (int) $updated->parent,
            'updated_fields' => array_keys( $update ),
        ];
    }
}
