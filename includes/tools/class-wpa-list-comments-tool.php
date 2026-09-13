<?php
/**
 * List Comments Tool — read-only listing of comments with status filter.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_List_Comments_Tool extends WPA_Abstract_Tool {

    public function get_name() {
        return 'list_comments';
    }

    public function get_title() {
        return 'فهرست دیدگاه‌ها';
    }

    public function get_description() {
        return 'List recent comments with optional filters: status (approved, pending, spam, trash), a specific post, '
            . 'or a search keyword. Returns id, post, author name, date, status and a shortened comment text.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'status'  => [
                    'type'        => 'string',
                    'enum'        => [ 'approve', 'hold', 'spam', 'trash', 'all' ],
                    'description' => 'Comment status filter: "approve" (approved), "hold" (pending moderation), "spam", "trash" or "all". Default "all".',
                ],
                'post_id' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'Only comments of this post.',
                ],
                'search'  => [
                    'type'        => 'string',
                    'description' => 'Keyword to search in comment text and author fields.',
                ],
                'count'   => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 50,
                    'description' => 'Number of comments to return. Default 10.',
                ],
            ],
        ];
    }

    public function get_required_capability() {
        return 'moderate_comments';
    }

    public function execute( array $args ) {
        $status = ! empty( $args['status'] ) ? $args['status'] : 'all';

        $comments = get_comments(
            [
                'status'  => ( 'all' === $status ) ? '' : $status,
                'number'  => ! empty( $args['count'] ) ? (int) $args['count'] : 10,
                'post_id' => ! empty( $args['post_id'] ) ? absint( $args['post_id'] ) : 0,
                'search'  => ! empty( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '',
            ]
        );

        $items = [];

        foreach ( $comments as $comment ) {
            $items[] = [
                'id'         => (int) $comment->comment_ID,
                'post_id'    => (int) $comment->comment_post_ID,
                'post_title' => get_the_title( (int) $comment->comment_post_ID ),
                'author'     => $comment->comment_author,
                'date'       => mysql2date( 'Y-m-d H:i', $comment->comment_date ),
                'status'     => wp_get_comment_status( $comment ),
                'content'    => wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 40 ),
            ];
        }

        return [
            'returned' => count( $items ),
            'comments' => $items,
        ];
    }
}
