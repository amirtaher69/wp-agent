<?php
/**
 * REST Controller — internal endpoints of the plugin under the wpa/v1 namespace.
 *
 * POST /wpa/v1/chat            — send the current conversation to the model.
 * POST /wpa/v1/test-connection — verify the stored API credentials.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_REST_Controller {

    const REST_NAMESPACE = 'wpa/v1';

    /**
     * Hard limits that keep token usage and abuse under control.
     */
    const MAX_MESSAGES       = 20;
    const MAX_MESSAGE_LENGTH = 8000;

    /**
     * Register all plugin routes.
     */
    public function register_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/chat',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_chat' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'messages' => [
                        'required'          => true,
                        'type'              => 'array',
                        'validate_callback' => [ $this, 'validate_messages' ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/test-connection',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_test_connection' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ]
        );
    }

    /**
     * All plugin endpoints are admin-only for now.
     */
    public function check_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Validate the incoming conversation before it reaches the handler.
     */
    public function validate_messages( $value ) {
        if ( ! is_array( $value ) || empty( $value ) ) {
            return new WP_Error( 'rest_invalid_param', 'لیست پیام‌ها خالی یا نامعتبر است.', [ 'status' => 400 ] );
        }

        foreach ( $value as $message ) {
            if ( ! is_array( $message ) || empty( $message['role'] ) || ! isset( $message['content'] ) ) {
                return new WP_Error( 'rest_invalid_param', 'ساختار یکی از پیام‌ها نامعتبر است.', [ 'status' => 400 ] );
            }

            // System messages are built server-side only; never accepted from the client.
            if ( ! in_array( $message['role'], [ 'user', 'assistant' ], true ) ) {
                return new WP_Error( 'rest_invalid_param', 'نقش یکی از پیام‌ها نامعتبر است.', [ 'status' => 400 ] );
            }

            if ( ! is_string( $message['content'] ) || '' === trim( $message['content'] ) ) {
                return new WP_Error( 'rest_invalid_param', 'متن یکی از پیام‌ها خالی است.', [ 'status' => 400 ] );
            }

            if ( mb_strlen( $message['content'] ) > self::MAX_MESSAGE_LENGTH ) {
                return new WP_Error( 'rest_invalid_param', 'طول یکی از پیام‌ها بیش از حد مجاز است.', [ 'status' => 400 ] );
            }
        }

        return true;
    }

    /**
     * POST /chat — relay the sanitized conversation to the model.
     */
    public function handle_chat( WP_REST_Request $request ) {
        $messages = [];

        foreach ( $request->get_param( 'messages' ) as $message ) {
            $messages[] = [
                'role'    => $message['role'],
                'content' => sanitize_textarea_field( $message['content'] ),
            ];
        }

        // Cap history length, then prepend the server-built system prompt.
        $messages = array_slice( $messages, -self::MAX_MESSAGES );
        array_unshift(
            $messages,
            [
                'role'    => 'system',
                'content' => $this->build_system_prompt(),
            ]
        );

        $result = $this->get_client()->send( $messages );

        if ( is_wp_error( $result ) ) {
            return $this->to_rest_error( $result );
        }

        return rest_ensure_response(
            [
                'reply' => $result['content'],
                'model' => $result['model'],
                'usage' => $result['usage'],
            ]
        );
    }

    /**
     * POST /test-connection — send a tiny ping through the AI client.
     */
    public function handle_test_connection() {
        $result = $this->get_client()->send(
            [
                [
                    'role'    => 'user',
                    'content' => 'Reply with the single word: OK',
                ],
            ]
        );

        if ( is_wp_error( $result ) ) {
            return $this->to_rest_error( $result );
        }

        return rest_ensure_response(
            [
                'message' => sprintf( 'اتصال با موفقیت برقرار شد — مدل: %s', $result['model'] ),
            ]
        );
    }

    /**
     * Identity and ground rules of the assistant, always built server-side.
     */
    private function build_system_prompt() {
        return sprintf(
            'You are the built-in AI assistant of the WordPress site "%s" (%s). ' .
            'Always answer in Persian (Farsi) unless the user explicitly asks for another language. ' .
            'Be concise, accurate and helpful. Today is %s.',
            get_bloginfo( 'name' ),
            home_url(),
            date_i18n( 'Y-m-d' )
        );
    }

    /**
     * Convert an AI client WP_Error into one carrying a proper HTTP status.
     */
    private function to_rest_error( WP_Error $error ) {
        $status = ( 'wpa_missing_api_key' === $error->get_error_code() ) ? 400 : 502;

        return new WP_Error( $error->get_error_code(), $error->get_error_message(), [ 'status' => $status ] );
    }

    /**
     * Lazy-load and create the AI client.
     */
    private function get_client() {
        require_once WPA_PATH . 'includes/ai/class-wpa-ai-client.php';

        return new WPA_AI_Client();
    }
}
