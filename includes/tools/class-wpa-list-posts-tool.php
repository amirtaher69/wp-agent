<?php
/**
 * List Posts Tool — read-only listing of posts/pages/custom types with
 * status, search and ordering filters. Returns excerpts, never full content.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_List_Posts_Tool extends WPA_Abstract_Tool {

    public function get_name() {
        return 'list_posts';
    }

    public function get_title() {
        return 'فهرست نوشته‌ها';
    }

    public function get_description() {
        return 'List posts of any post type with optional status filter, keyword search and ordering. '
            . 'Returns id, title, status, dates, author, permalink and a short excerpt for each post, plus the total '
            . 'number of matching posts. Use get_post afterwards to read the full content of a specific post.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'post_type' => [
                    'type'        => 'string',
                    'description' => 'Post type slug, e.g. "post" or "page". Default "post". Use get_site_info to discover available types.',
                ],
                'status'    => [
                    'type'        => 'string',
                    'enum'        => [ 'publish', 'draft', 'pending', 'future', 'private', 'any' ],
                    'description' => 'Post status filter. Default "any".',
                ],
                'search'    => [
                    'type'        => 'string',
                    'description' => 'Keyword to search in title and content.',
                ],
                'count'     => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 50,
                    'description' => 'Number of posts to return. Default 10.',
                ],
                'orderby'   => [
                    'type'        => 'string',
                    'enum'        => [ 'date', 'modified', 'title', 'comment_count' ],
                    'description' => 'Sort field. Default "date".',
                ],
                'order'     => [
                    'type'        => 'string',
                    'enum'        => [ 'asc', 'desc' ],
                    'description' => 'Sort direction. Default "desc".',
                ],
            ],
        ];
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function execute( array $args ) {
        $post_type = ! empty( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post';

        if ( ! post_type_exists( $post_type ) ) {
            return new WP_Error(
                'wpa_tool_error',
                sprintf(
                    'نوع محتوای «%s» وجود ندارد. انواع موجود: %s',
                    $post_type,
                    implode( '، ', get_post_types( [ 'public' => true ] ) )
                )
            );
        }

        $query = new WP_Query(
            [
                'post_type'      => $post_type,
                'post_status'    => ! empty( $args['status'] ) ? $args['status'] : 'any',
                's'              => ! empty( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '',
                'posts_per_page' => ! empty( $args['count'] ) ? (int) $args['count'] : 10,
                'orderby'        => ! empty( $args['orderby'] ) ? $args['orderby'] : 'date',
                'order'          => ( isset( $args['order'] ) && 'asc' === $args['order'] ) ? 'ASC' : 'DESC',
                'perm'           => 'readable',
                'no_found_rows'  => false,
            ]
        );

        $posts = [];

        foreach ( $query->posts as $post ) {
            $posts[] = [
                'id'       => $post->ID,
                'title'    => get_the_title( $post ),
                'status'   => $post->post_status,
                'date'     => get_the_date( 'Y-m-d H:i', $post ),
                'modified' => get_the_modified_date( 'Y-m-d H:i', $post ),
                'author'   => get_the_author_meta( 'display_name', $post->post_author ),
                'link'     => get_permalink( $post ),
                'excerpt'  => wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
            ];
        }

        return [
            'total_found' => (int) $query->found_posts,
            'returned'    => count( $posts ),
            'posts'       => $posts,
        ];
    }
}
