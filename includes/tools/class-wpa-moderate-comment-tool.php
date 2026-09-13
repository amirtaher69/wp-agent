<?php
/**
 * Moderate Comment Tool — change a comment's status (approve/hold/spam/trash).
 * Permanent deletion is intentionally not available. Requires confirmation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Moderate_Comment_Tool extends WPA_Abstract_Tool {

    public function get_name() {
        return 'moderate_comment';
    }

    public function get_title() {
        return 'مدیریت دیدگاه';
    }

    public function get_description() {
        return 'Change the status of a comment by ID: "approve", "hold" (pending), "spam" or "trash". Trash and '
            . 'spam are recoverable; permanent deletion is not available. This action requires user confirmation in the UI.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'comment_id' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'ID of the comment.',
                ],
                'action'     => [
                    'type'        => 'string',
                    'enum'        => [ 'approve', 'hold', 'spam', 'trash' ],
                    'description' => 'New status for the comment.',
                ],
            ],
            'required'   => [ 'comment_id', 'action' ],
        ];
    }

    public function get_required_capability() {
        return 'moderate_comments';
    }

    public function is_destructive() {
        return true;
    }

    public function summarize( array $args ) {
        $comment_id = isset( $args['comment_id'] ) ? absint( $args['comment_id'] ) : 0;
        $comment    = get_comment( $comment_id );

        $labels = [
            'approve' => 'تایید',
            'hold'    => 'در انتظار بازبینی',
            'spam'    => 'اسپم',
            'trash'   => 'انتقال به زباله‌دان',
        ];

        $action = isset( $args['action'] ) ? $args['action'] : '';

        $lines   = [];
        $lines[] = 'دیدگاه: ' . ( $comment ? sprintf( 'شماره %d از «%s»', $comment_id, $comment->comment_author ) : 'شناسه ' . $comment_id );

        if ( $comment ) {
            $lines[] = 'متن: ' . wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 15 );
        }

        $lines[] = 'عملیات: ' . ( isset( $labels[ $action ] ) ? $labels[ $action ] : $action );

        return $lines;
    }

    public function execute( array $args ) {
        $comment_id = isset( $args['comment_id'] ) ? absint( $args['comment_id'] ) : 0;
        $action     = isset( $args['action'] ) ? $args['action'] : '';
        $comment    = get_comment( $comment_id );

        if ( ! $comment ) {
            return new WP_Error( 'wpa_tool_error', sprintf( 'دیدگاهی با شناسه %d یافت نشد.', $comment_id ) );
        }

        if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
            return new WP_Error( 'wpa_tool_error', 'شما دسترسی مدیریت این دیدگاه را ندارید.' );
        }

        switch ( $action ) {
            case 'spam':
                $result = wp_spam_comment( $comment_id );
                break;
            case 'trash':
                $result = wp_trash_comment( $comment_id );
                break;
            default:
                $result = wp_set_comment_status( $comment_id, $action );
                break;
        }

        if ( ! $result || is_wp_error( $result ) ) {
            return new WP_Error( 'wpa_tool_error', 'تغییر وضعیت دیدگاه ناموفق بود.' );
        }

        return [
            'id'         => $comment_id,
            'author'     => $comment->comment_author,
            'new_status' => wp_get_comment_status( $comment_id ),
        ];
    }
}
