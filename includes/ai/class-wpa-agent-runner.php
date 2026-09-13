<?php
/**
 * Agent Runner — the multi-step loop: sends the conversation plus tool
 * definitions to the model, executes requested tool calls through the
 * registry and feeds results back until the model produces a final answer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Agent_Runner {

    private $client;
    private $registry;
    private $max_steps;

    public function __construct() {
        require_once WPA_PATH . 'includes/ai/class-wpa-ai-client.php';
        require_once WPA_PATH . 'includes/tools/class-wpa-tool-registry.php';

        $this->client   = new WPA_AI_Client();
        $this->registry = new WPA_Tool_Registry();

        $settings        = WPA_Settings_Page::get_settings();
        $this->max_steps = max( 1, (int) $settings['max_steps'] );
    }

    /**
     * Run the agent loop on a conversation.
     *
     * @param array $messages Conversation including the system message.
     * @return array|WP_Error { reply, model, usage, steps } or WP_Error.
     */
    public function run( array $messages ) {
        $steps       = [];
        $definitions = $this->registry->get_definitions();

        for ( $i = 0; $i < $this->max_steps; $i++ ) {
            $response = $this->client->send( $messages, $definitions );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            // A plain text answer ends the loop.
            if ( 'text' === $response['type'] ) {
                return [
                    'reply' => $response['content'],
                    'model' => $response['model'],
                    'usage' => $response['usage'],
                    'steps' => $steps,
                ];
            }

            // Echo the assistant turn back exactly as the API expects it.
            $messages[] = [
                'role'       => 'assistant',
                'content'    => $response['content'],
                'tool_calls' => $response['tool_calls'],
            ];

            foreach ( $response['tool_calls'] as $tool_call ) {
                $name = isset( $tool_call['function']['name'] ) ? $tool_call['function']['name'] : '';
                $args = json_decode(
                    isset( $tool_call['function']['arguments'] ) ? $tool_call['function']['arguments'] : '{}',
                    true
                );

                $result = $this->registry->execute( $name, is_array( $args ) ? $args : [] );

                // Transparency report shown under the answer in the UI.
                $tool    = $this->registry->get( $name );
                $steps[] = [
                    'tool'  => $name,
                    'title' => $tool ? $tool->get_title() : $name,
                    'ok'    => ! isset( $result['error'] ),
                ];

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => isset( $tool_call['id'] ) ? $tool_call['id'] : '',
                    'content'      => wp_json_encode( $result, JSON_UNESCAPED_UNICODE ),
                ];
            }
        }

        return new WP_Error(
            'wpa_max_steps_exceeded',
            'عملیات از سقف مجاز گام‌های ایجنت عبور کرد. درخواست را ساده‌تر مطرح کنید یا سقف گام‌ها را در تنظیمات افزایش دهید.'
        );
    }
}
