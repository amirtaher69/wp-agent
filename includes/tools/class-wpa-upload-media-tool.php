<?php
/**
 * Upload Media Tool — sideload a file from a public URL into the media
 * library. Uses WordPress-safe HTTP (blocks local/private hosts) and the
 * standard file type whitelist. Requires user confirmation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Upload_Media_Tool extends WPA_Abstract_Tool {

    public function get_name() {
        return 'upload_media';
    }

    public function get_title() {
        return 'آپلود در رسانه';
    }

    public function get_description() {
        return 'Download a file from a direct public URL (http/https) and add it to the media library. Only file '
            . 'types allowed by WordPress are accepted. Optionally set a title, alt text, and attach the file to a '
            . 'post. This action requires user confirmation in the UI.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'file_url'          => [
                    'type'        => 'string',
                    'description' => 'Direct public URL of the file, e.g. https://example.com/image.jpg',
                ],
                'title'             => [
                    'type'        => 'string',
                    'description' => 'Optional title for the media item.',
                ],
                'alt_text'          => [
                    'type'        => 'string',
                    'description' => 'Optional alt text (images only).',
                ],
                'attach_to_post_id' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'Optional post ID to attach the file to.',
                ],
            ],
            'required'   => [ 'file_url' ],
        ];
    }

    public function get_required_capability() {
        return 'upload_files';
    }

    public function is_destructive() {
        return true;
    }

    public function summarize( array $args ) {
        $lines   = [];
        $lines[] = 'فایل: ' . ( isset( $args['file_url'] ) ? $args['file_url'] : '—' );

        if ( ! empty( $args['title'] ) ) {
            $lines[] = 'عنوان: ' . $args['title'];
        }

        if ( ! empty( $args['attach_to_post_id'] ) ) {
            $post    = get_post( absint( $args['attach_to_post_id'] ) );
            $lines[] = 'پیوست به: ' . ( $post ? sprintf( '«%s»', get_the_title( $post ) ) : 'شناسه ' . $args['attach_to_post_id'] );
        }

        return $lines;
    }

    public function execute( array $args ) {
        $url = isset( $args['file_url'] ) ? esc_url_raw( $args['file_url'] ) : '';

        if ( '' === $url || ! wp_http_validate_url( $url ) ) {
            return new WP_Error( 'wpa_tool_error', 'آدرس فایل نامعتبر است. فقط آدرس‌های مستقیم http/https پذیرفته می‌شوند.' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $url, 60 );

        if ( is_wp_error( $tmp ) ) {
            return new WP_Error( 'wpa_tool_error', 'دانلود فایل ناموفق بود: ' . $tmp->get_error_message() );
        }

        $path = wp_parse_url( $url, PHP_URL_PATH );
        $name = $path ? wp_basename( $path ) : 'file';

        $file_array = [
            'name'     => sanitize_file_name( $name ),
            'tmp_name' => $tmp,
        ];

        $parent_id     = ! empty( $args['attach_to_post_id'] ) ? absint( $args['attach_to_post_id'] ) : 0;
        $attachment_id = media_handle_sideload( $file_array, $parent_id );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

            return new WP_Error(
                'wpa_tool_error',
                'افزودن فایل به رسانه ناموفق بود: ' . $attachment_id->get_error_message()
            );
        }

        if ( ! empty( $args['title'] ) ) {
            wp_update_post(
                [
                    'ID'         => $attachment_id,
                    'post_title' => sanitize_text_field( $args['title'] ),
                ]
            );
        }

        if ( ! empty( $args['alt_text'] ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt_text'] ) );
        }

        return [
            'id'    => $attachment_id,
            'url'   => wp_get_attachment_url( $attachment_id ),
            'mime'  => get_post_mime_type( $attachment_id ),
            'title' => get_the_title( $attachment_id ),
        ];
    }
}
