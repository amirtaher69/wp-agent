<?php
/**
 * Get Post Tool — read the full details and content of a single post by ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Get_Post_Tool extends WPA_Abstract_Tool {

    /**
     * Content is capped so a huge post cannot blow up the token budget.
     */
    const MAX_CONTENT_LENGTH = 12000;

    public function get_name() {
        return 'get_post';
    }

    public function get_title() {
        return 'دریافت نوشته';
    }

    public function get_description() {
        return 'Get the full details of a single post, page or custom post type item by its ID: title, status, dates, '
            . 'author, permalink, assigned taxonomy terms and the full content (truncated when very long). '
            . 'Use list_posts first when you only know the title, not the ID.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'ID of the post to fetch.',
                ],
            ],
            'required'   => [ 'post_id' ],
        ];
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function execute( array $args ) {
        $post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return new WP_Error( 'wpa_tool_error', sprintf( 'نوشته‌ای با شناسه %d یافت نشد.', $post_id ) );
        }

        // Per-item permission on top of the tool-level capability.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'wpa_tool_error', 'شما دسترسی لازم برای مشاهده این نوشته را ندارید.' );
        }

        $content   = $post->post_content;
        $truncated = false;

        if ( mb_strlen( $content ) > self::MAX_CONTENT_LENGTH ) {
            $content   = mb_substr( $content, 0, self::MAX_CONTENT_LENGTH );
            $truncated = true;
        }

        $terms = [];
        foreach ( get_object_taxonomies( $post ) as $taxonomy ) {
            $names = wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'names' ] );
            if ( ! is_wp_error( $names ) && ! empty( $names ) ) {
                $terms[ $taxonomy ] = $names;
            }
        }

        return [
            'id'                => $post->ID,
            'title'             => get_the_title( $post ),
            'type'              => $post->post_type,
            'status'            => $post->post_status,
            'date'              => get_the_date( 'Y-m-d H:i', $post ),
            'modified'          => get_the_modified_date( 'Y-m-d H:i', $post ),
            'author'            => get_the_author_meta( 'display_name', $post->post_author ),
            'link'              => get_permalink( $post ),
            'terms'             => $terms,
            'comment_count'     => (int) $post->comment_count,
            'content'           => $content,
            'content_truncated' => $truncated,
        ];
    }
}
