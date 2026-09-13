<?php
/**
 * Set Post Terms Tool — assign taxonomy terms to a post (replace or append).
 * Destructive: requires user confirmation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Set_Post_Terms_Tool extends WPA_Abstract_Tool {

    public function get_name() {
        return 'set_post_terms';
    }

    public function get_title() {
        return 'تخصیص ترم به نوشته';
    }

    public function get_description() {
        return 'Assign taxonomy terms (categories, tags or custom taxonomy terms) to a post by term names. '
            . 'Missing terms are created automatically. Set "append" true to keep existing terms, false to replace '
            . 'them. This action requires user confirmation in the UI.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id'  => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'ID of the target post.',
                ],
                'taxonomy' => [
                    'type'        => 'string',
                    'description' => 'Taxonomy slug, e.g. "category" or "post_tag".',
                ],
                'terms'    => [
                    'type'        => 'array',
                    'items'       => [ 'type' => 'string' ],
                    'description' => 'Term names to assign.',
                ],
                'append'   => [
                    'type'        => 'boolean',
                    'description' => 'true = add to existing terms, false = replace them. Default false.',
                ],
            ],
            'required'   => [ 'post_id', 'taxonomy', 'terms' ],
        ];
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function is_destructive() {
        return true;
    }

    public function summarize( array $args ) {
        $post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
        $post    = get_post( $post_id );

        return [
            'نوشته: ' . ( $post ? sprintf( '«%s» (شناسه %d)', get_the_title( $post ), $post_id ) : 'شناسه ' . $post_id ),
            'تاکسونومی: ' . ( isset( $args['taxonomy'] ) ? $args['taxonomy'] : '—' ),
            'ترم‌ها: ' . ( ! empty( $args['terms'] ) ? implode( '، ', (array) $args['terms'] ) : '—' ),
            'روش: ' . ( ! empty( $args['append'] ) ? 'افزودن به ترم‌های فعلی' : 'جایگزینی ترم‌های فعلی' ),
        ];
    }

    public function execute( array $args ) {
        $post_id  = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
        $taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : '';

        if ( ! get_post( $post_id ) ) {
            return new WP_Error( 'wpa_tool_error', sprintf( 'نوشته‌ای با شناسه %d یافت نشد.', $post_id ) );
        }

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error(
                'wpa_tool_error',
                sprintf( 'تاکسونومی «%s» وجود ندارد. تاکسونومی‌های موجود: %s', $taxonomy, implode( '، ', get_taxonomies( [ 'public' => true ] ) ) )
            );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'wpa_tool_error', 'شما دسترسی ویرایش این نوشته را ندارید.' );
        }

        $taxonomy_object = get_taxonomy( $taxonomy );

        if ( ! current_user_can( $taxonomy_object->cap->assign_terms ) ) {
            return new WP_Error( 'wpa_tool_error', 'شما دسترسی تخصیص ترم در این تاکسونومی را ندارید.' );
        }

        $names  = array_map( 'sanitize_text_field', (array) $args['terms'] );
        $result = wp_set_object_terms( $post_id, $names, $taxonomy, ! empty( $args['append'] ) );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'wpa_tool_error', 'خطا در تخصیص ترم‌ها: ' . $result->get_error_message() );
        }

        $current = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );

        return [
            'post_id'       => $post_id,
            'taxonomy'      => $taxonomy,
            'current_terms' => is_wp_error( $current ) ? [] : $current,
        ];
    }
}
