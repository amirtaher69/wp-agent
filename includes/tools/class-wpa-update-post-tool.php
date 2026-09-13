<?php
/**
 * Update Post Tool — edit title/content/excerpt/status/slug of an existing
 * post. Destructive: runs only after explicit user confirmation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Update_Post_Tool extends WPA_Abstract_Tool {

    public function get_name() {
        return 'update_post';
    }

    public function get_title() {
        return 'ویرایش نوشته';
    }

    public function get_description() {
        return 'Update an existing post, page or custom post type item by ID. Only the provided fields change; '
            . 'omitted fields stay untouched. WordPress keeps revisions of previous content. '
            . 'This action requires user confirmation in the UI before it runs.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'ID of the post to update.',
                ],
                'title'   => [
                    'type'        => 'string',
                    'description' => 'New title.',
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'New content (replaces the current content entirely). WordPress-safe HTML allowed.',
                ],
                'excerpt' => [
                    'type'        => 'string',
                    'description' => 'New excerpt.',
                ],
                'status'  => [
                    'type'        => 'string',
                    'enum'        => [ 'draft', 'pending', 'publish', 'private' ],
                    'description' => 'New status.',
                ],
                'slug'    => [
                    'type'        => 'string',
                    'description' => 'New URL slug.',
                ],
            ],
            'required'   => [ 'post_id' ],
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

        $lines   = [];
        $lines[] = 'نوشته: ' . ( $post ? sprintf( '«%s» (شناسه %d)', get_the_title( $post ), $post_id ) : 'شناسه ' . $post_id );

        if ( isset( $args['title'] ) ) {
            $lines[] = 'عنوان جدید: ' . $args['title'];
        }

        if ( isset( $args['status'] ) ) {
            $lines[] = 'وضعیت جدید: ' . $this->get_status_label( $args['status'] );
        }

        if ( isset( $args['content'] ) ) {
            $lines[] = 'محتوای جدید: ' . wp_trim_words( wp_strip_all_tags( $args['content'] ), 25 );
        }

        if ( isset( $args['excerpt'] ) ) {
            $lines[] = 'چکیده جدید: ' . wp_trim_words( $args['excerpt'], 15 );
        }

        if ( isset( $args['slug'] ) ) {
            $lines[] = 'اسلاگ جدید: ' . $args['slug'];
        }

        return $lines;
    }

    public function execute( array $args ) {
        $post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return new WP_Error( 'wpa_tool_error', sprintf( 'نوشته‌ای با شناسه %d یافت نشد.', $post_id ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'wpa_tool_error', 'شما دسترسی ویرایش این نوشته را ندارید.' );
        }

        $update  = [ 'ID' => $post_id ];
        $changed = [];

        if ( isset( $args['title'] ) ) {
            $update['post_title'] = sanitize_text_field( $args['title'] );
            $changed[]            = 'title';
        }

        if ( isset( $args['content'] ) ) {
            $update['post_content'] = wp_kses_post( $args['content'] );
            $changed[]              = 'content';
        }

        if ( isset( $args['excerpt'] ) ) {
            $update['post_excerpt'] = sanitize_textarea_field( $args['excerpt'] );
            $changed[]              = 'excerpt';
        }

        if ( isset( $args['slug'] ) ) {
            $update['post_name'] = sanitize_title( $args['slug'] );
            $changed[]           = 'slug';
        }

        if ( isset( $args['status'] ) ) {
            $post_type_object = get_post_type_object( $post->post_type );

            if ( in_array( $args['status'], [ 'publish', 'private' ], true )
                && 'publish' !== $post->post_status
                && ( ! $post_type_object || ! current_user_can( $post_type_object->cap->publish_posts ) ) ) {
                return new WP_Error( 'wpa_tool_error', 'شما دسترسی انتشار این نوشته را ندارید.' );
            }

            $update['post_status'] = $args['status'];
            $changed[]             = 'status';
        }

        if ( 1 === count( $update ) ) {
            return new WP_Error( 'wpa_tool_error', 'هیچ فیلدی برای تغییر مشخص نشده است.' );
        }

        $result = wp_update_post( wp_slash( $update ), true );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'wpa_tool_error', 'خطا در ویرایش نوشته: ' . $result->get_error_message() );
        }

        return [
            'id'             => $post_id,
            'title'          => get_the_title( $post_id ),
            'status'         => get_post_status( $post_id ),
            'link'           => get_permalink( $post_id ),
            'updated_fields' => $changed,
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
