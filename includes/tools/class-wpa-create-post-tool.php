<?php
/**
 * Create Post Tool — create a post/page/custom item. Destructive: runs only
 * after explicit user confirmation. Defaults to draft status.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Create_Post_Tool extends WPA_Abstract_Tool {

    public function get_name() {
        return 'create_post';
    }

    public function get_title() {
        return 'ساخت نوشته';
    }

    public function get_description() {
        return 'Create a new post, page or custom post type item. Status defaults to "draft"; only use "publish" '
            . 'when the user explicitly asked to publish. Content accepts WordPress-safe HTML. Terms can be '
            . 'assigned at creation via the "terms" object (taxonomy slug => array of term names; missing terms are created). '
            . 'This action requires user confirmation in the UI before it runs.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'title'     => [
                    'type'        => 'string',
                    'description' => 'Post title.',
                ],
                'content'   => [
                    'type'        => 'string',
                    'description' => 'Post content. WordPress-safe HTML allowed.',
                ],
                'post_type' => [
                    'type'        => 'string',
                    'description' => 'Post type slug. Default "post".',
                ],
                'status'    => [
                    'type'        => 'string',
                    'enum'        => [ 'draft', 'pending', 'publish', 'private' ],
                    'description' => 'Post status. Default "draft".',
                ],
                'excerpt'   => [
                    'type'        => 'string',
                    'description' => 'Optional excerpt.',
                ],
                'slug'      => [
                    'type'        => 'string',
                    'description' => 'Optional URL slug.',
                ],
                'terms'     => [
                    'type'                 => 'object',
                    'description'          => 'Terms to assign, keyed by taxonomy slug, e.g. {"category": ["آموزش"]}.',
                    'additionalProperties' => [
                        'type'  => 'array',
                        'items' => [ 'type' => 'string' ],
                    ],
                ],
            ],
            'required'   => [ 'title' ],
        ];
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function is_destructive() {
        return true;
    }

    public function summarize( array $args ) {
        $lines   = [];
        $lines[] = 'عنوان: ' . ( isset( $args['title'] ) ? $args['title'] : '—' );
        $lines[] = 'نوع: ' . ( ! empty( $args['post_type'] ) ? $args['post_type'] : 'post' );
        $lines[] = 'وضعیت: ' . $this->get_status_label( ! empty( $args['status'] ) ? $args['status'] : 'draft' );

        if ( ! empty( $args['content'] ) ) {
            $lines[] = 'محتوا: ' . wp_trim_words( wp_strip_all_tags( $args['content'] ), 25 );
        }

        if ( ! empty( $args['terms'] ) ) {
            $lines[] = 'ترم‌ها: ' . wp_json_encode( $args['terms'], JSON_UNESCAPED_UNICODE );
        }

        return $lines;
    }

    public function execute( array $args ) {
        $post_type = ! empty( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post';
        $status    = ! empty( $args['status'] ) ? $args['status'] : 'draft';

        $post_type_object = get_post_type_object( $post_type );

        if ( ! $post_type_object ) {
            return new WP_Error( 'wpa_tool_error', sprintf( 'نوع محتوای «%s» وجود ندارد.', $post_type ) );
        }

        if ( ! current_user_can( $post_type_object->cap->edit_posts ) ) {
            return new WP_Error( 'wpa_tool_error', 'شما دسترسی ساخت این نوع محتوا را ندارید.' );
        }

        // Publishing directly needs the dedicated capability.
        if ( in_array( $status, [ 'publish', 'private' ], true )
            && ! current_user_can( $post_type_object->cap->publish_posts ) ) {
            return new WP_Error( 'wpa_tool_error', 'شما دسترسی انتشار این نوع محتوا را ندارید. نوشته را به صورت پیش‌نویس بسازید.' );
        }

        $post_data = [
            'post_type'    => $post_type,
            'post_status'  => $status,
            'post_title'   => sanitize_text_field( isset( $args['title'] ) ? $args['title'] : '' ),
            'post_content' => wp_kses_post( isset( $args['content'] ) ? $args['content'] : '' ),
        ];

        if ( ! empty( $args['excerpt'] ) ) {
            $post_data['post_excerpt'] = sanitize_textarea_field( $args['excerpt'] );
        }

        if ( ! empty( $args['slug'] ) ) {
            $post_data['post_name'] = sanitize_title( $args['slug'] );
        }

        $post_id = wp_insert_post( wp_slash( $post_data ), true );

        if ( is_wp_error( $post_id ) ) {
            return new WP_Error( 'wpa_tool_error', 'خطا در ساخت نوشته: ' . $post_id->get_error_message() );
        }

        $assigned_terms = [];

        if ( ! empty( $args['terms'] ) && is_array( $args['terms'] ) ) {
            foreach ( $args['terms'] as $taxonomy => $names ) {
                $taxonomy = sanitize_key( $taxonomy );

                if ( ! taxonomy_exists( $taxonomy ) || ! is_array( $names ) ) {
                    continue;
                }

                $names  = array_map( 'sanitize_text_field', $names );
                $result = wp_set_object_terms( $post_id, $names, $taxonomy );

                if ( ! is_wp_error( $result ) ) {
                    $assigned_terms[ $taxonomy ] = $names;
                }
            }
        }

        return [
            'id'        => $post_id,
            'title'     => get_the_title( $post_id ),
            'type'      => $post_type,
            'status'    => get_post_status( $post_id ),
            'link'      => get_permalink( $post_id ),
            'edit_link' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
            'terms'     => $assigned_terms,
        ];
    }

    /**
     * Persian label of a post status for the confirmation card.
     */
    private function get_status_label( $status ) {
        $labels = [
            'draft'   => 'پیش‌نویس',
            'pending' => 'در انتظار بازبینی',
            'publish' => 'انتشار',
            'private' => 'خصوصی',
        ];

        return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
    }
}
