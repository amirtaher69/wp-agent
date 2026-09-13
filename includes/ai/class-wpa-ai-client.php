<?php
/**
 * AI Client — thin HTTP layer over the OpenAI Chat Completions API.
 *
 * Callers pass an array of messages (and later, tool definitions) and get
 * back a normalized array or a WP_Error with a Persian, display-ready message.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_AI_Client {

    /**
     * Plugin settings (api_key, base_url, model, timeout, ...).
     */
    private $settings;

    public function __construct() {
        // The settings page class is the single source of truth for defaults.
        if ( ! class_exists( 'WPA_Settings_Page' ) ) {
            require_once WPA_PATH . 'includes/admin/class-wpa-settings-page.php';
        }

        $this->settings = WPA_Settings_Page::get_settings();
    }

    /**
     * Send a conversation to the model and return a normalized response.
     *
     * @param array $messages Chat messages: [ [ 'role' => ..., 'content' => ... ], ... ].
     * @param array $tools    OpenAI tool definitions (unused until phase 3).
     * @return array|WP_Error Normalized response array or a WP_Error.
     */
    public function send( array $messages, array $tools = [] ) {
        if ( '' === $this->settings['api_key'] ) {
            return new WP_Error(
                'wpa_missing_api_key',
                'کلید API تنظیم نشده است. ابتدا از صفحه تنظیمات، کلید را ذخیره کنید.'
            );
        }

        $body = [
            'model'    => $this->settings['model'],
            'messages' => $messages,
        ];

        if ( ! empty( $tools ) ) {
            $body['tools'] = $tools;
        }

        $response = wp_remote_post(
            $this->settings['base_url'] . '/chat/completions',
            [
                'timeout' => (int) $this->settings['timeout'],
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->settings['api_key'],
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( $body ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'wpa_http_error',
                'ارتباط با سرور برقرار نشد. آدرس پایه API و دسترسی سرور به اینترنت را بررسی کنید.',
                [ 'original_error' => $response->get_error_message() ]
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $data        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $status_code ) {
            return $this->map_api_error( $status_code, $data );
        }

        return $this->normalize_response( $data );
    }

    /**
     * Convert a non-200 API response into a WP_Error with a Persian message.
     */
    private function map_api_error( $status_code, $data ) {
        $api_message = isset( $data['error']['message'] ) ? $data['error']['message'] : '';

        switch ( $status_code ) {
            case 401:
                $message = 'کلید API نامعتبر است. کلید را بررسی و دوباره ذخیره کنید.';
                break;
            case 404:
                $message = 'مدل انتخاب‌شده در دسترس نیست یا آدرس پایه API اشتباه است.';
                break;
            case 429:
                $message = 'محدودیت تعداد درخواست یا اتمام اعتبار حساب. کمی بعد دوباره تلاش کنید.';
                break;
            case 400:
                $message = 'درخواست ارسالی نامعتبر بود.';
                break;
            default:
                $message = sprintf( 'خطای غیرمنتظره از سرویس (کد %d).', $status_code );
                break;
        }

        // Append the raw API message to help with debugging.
        if ( '' !== $api_message ) {
            $message .= sprintf( ' — پیام سرویس: %s', $api_message );
        }

        return new WP_Error(
            'wpa_api_error_' . $status_code,
            $message,
            [ 'status' => $status_code, 'api_message' => $api_message ]
        );
    }

    /**
     * Normalize a successful API response so upper layers never touch
     * the raw OpenAI format.
     */
    private function normalize_response( $data ) {
        if ( ! is_array( $data ) || empty( $data['choices'][0]['message'] ) ) {
            return new WP_Error(
                'wpa_invalid_response',
                'پاسخ دریافتی از سرویس قابل پردازش نیست.',
                [ 'raw' => $data ]
            );
        }

        $message    = $data['choices'][0]['message'];
        $tool_calls = isset( $message['tool_calls'] ) ? $message['tool_calls'] : [];

        return [
            'type'       => empty( $tool_calls ) ? 'text' : 'tool_calls',
            'content'    => isset( $message['content'] ) ? $message['content'] : '',
            'tool_calls' => $tool_calls,
            'model'      => isset( $data['model'] ) ? $data['model'] : $this->settings['model'],
            'usage'      => isset( $data['usage'] ) ? $data['usage'] : [],
        ];
    }
}
