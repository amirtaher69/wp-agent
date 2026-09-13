<?php
/**
 * Trash Post Tool — move a post to trash (reversible; permanent deletion is
 * intentionally not available). Destructive: requires user confirmation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Trash_Post_Tool extends WPA_Abstract_Tool {

    public function get_name() {
        return 'trash_post';
    }

    public function get_title() {
        return 'انتقال نوشته به زباله‌دان';
    }

    public function get_description() {
        return 'Move a post, page or custom post type item to the trash by ID. The item is recoverable from the '
            . 'trash; permanent deletion is not available. This action requires user confirmation in the UI.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'ID of the post to trash.',
                ],
            ],
            'required'   => [ 'post_id' ],
        ];
    }

    public function get_required_capability() {
        return 'delete_posts';
    }

    public function is_destructive() {
        return true;
    }

    public function summarize( array $args ) {
        $post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
        $post    = get_post( $post_id );

        return [
            'نوشته: ' . ( $post ? sprintf( '«%s» (شناسه %d)', get_the_title( $post ), $post_id ) : 'شناسه ' . $post_id ),
            'عملیات: انتقال به زباله‌دان (قابل بازیابی)',
        ];
    }

    public function execute( array $args ) {
        $post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return new WP_Error( 'wpa_tool_error', sprintf( 'نوشته‌ای با شناسه %d یافت نشد.', $post_id ) );
        }

        if ( 'trash' === $post->post_status ) {
            return new WP_Error( 'wpa_tool_error', 'این نوشته همین حالا هم در زباله‌دان است.' );
        }

        if ( ! current_user_can( 'delete_post', $post_id ) ) {
            return new WP_Error( 'wpa_tool_error', 'شما دسترسی حذف این نوشته را ندارید.' );
        }

        $title  = get_the_title( $post );
        $result = wp_trash_post( $post_id );

        if ( ! $result ) {
            return new WP_Error( 'wpa_tool_error', 'انتقال نوشته به زباله‌دان ناموفق بود.' );
        }

        return [
            'id'     => $post_id,
            'title'  => $title,
            'status' => 'trash',
        ];
    }
}
