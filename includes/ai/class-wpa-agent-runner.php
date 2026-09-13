<?php
/**
 * Agent Runner — the multi-step loop: sends the conversation plus tool
 * definitions to the model and executes requested tool calls through the
 * registry until the model produces a final answer.
 *
 * Destructive tool calls pause the loop: the full state is stored in a
 * user-locked, single-use transient and the UI must confirm via resume().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Agent_Runner {

    const TRANSIENT_PREFIX = 'wpa_pending_';
    const PENDING_TTL      = 600; // 10 minutes.

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
     * Start the agent loop on a fresh conversation.
     *
     * @return array|WP_Error { status: done|pending, ... } or WP_Error.
     */
    public function run( array $messages ) {
        return $this->loop( $messages, [] );
    }

    /**
     * Resume a paused loop after the user approved or rejected the
     * pending destructive actions.
     */
    public function resume( $token, $approve ) {
        $key   = self::TRANSIENT_PREFIX . sanitize_key( $token );
        $state = get_transient( $key );

        if ( ! is_array( $state ) || empty( $state['pending'] ) ) {
            return new WP_Error( 'wpa_invalid_token', 'درخواست تایید یافت نشد یا منقضی شده است. لطفا دستور را دوباره مطرح کنید.' );
        }

        if ( (int) $state['user_id'] !== get_current_user_id() ) {
            return new WP_Error( 'wpa_token_forbidden', 'این درخواست تایید متعلق به کاربر دیگری است.' );
        }

        // Single use: burn the token before executing anything.
        delete_transient( $key );

        $messages = $state['messages'];
        $steps    = $state['steps'];

        foreach ( $state['pending'] as $call ) {
            if ( $approve ) {
                // The registry re-checks capability and schema at execution time.
                $result = $this->registry->execute( $call['name'], $call['args'] );
            } else {
                $result = [ 'error' => 'کاربر اجرای این عملیات را تایید نکرد. آن را دوباره تلاش نکن.' ];
            }

            $tool    = $this->registry->get( $call['name'] );
            $steps[] = [
                'tool'  => $call['name'],
                'title' => $tool ? $tool->get_title() : $call['name'],
                'ok'    => $approve && ! isset( $result['error'] ),
            ];

            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => $call['id'],
                'content'      => wp_json_encode( $result, JSON_UNESCAPED_UNICODE ),
            ];
        }

        return $this->loop( $messages, $steps );
    }

    /**
     * The shared loop body used by both run() and resume().
     */
    private function loop( array $messages, array $steps ) {
        $definitions = $this->registry->get_definitions();

        for ( $i = 0; $i < $this->max_steps; $i++ ) {
            $response = $this->client->send( $messages, $definitions );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            // A plain text answer ends the loop.
            if ( 'text' === $response['type'] ) {
                return [
                    'status' => 'done',
                    'reply'  => $response['content'],
                    'model'  => $response['model'],
                    'usage'  => $response['usage'],
                    'steps'  => $steps,
                ];
            }

            // Echo the assistant turn back exactly as the API expects it.
            $messages[] = [
                'role'       => 'assistant',
                'content'    => $response['content'],
                'tool_calls' => $response['tool_calls'],
            ];

            $pending = [];

            foreach ( $response['tool_calls'] as $tool_call ) {
                $name = isset( $tool_call['function']['name'] ) ? $tool_call['function']['name'] : '';
                $args = json_decode(
                    isset( $tool_call['function']['arguments'] ) ? $tool_call['function']['arguments'] : '{}',
                    true
                );
                $args = is_array( $args ) ? $args : [];
                $tool = $this->registry->get( $name );

                // Destructive calls are held for user confirmation.
                if ( $tool && $tool->is_destructive() ) {
                    $pending[] = [
                        'id'   => isset( $tool_call['id'] ) ? $tool_call['id'] : '',
                        'name' => $name,
                        'args' => $args,
                    ];
                    continue;
                }

                // Read-only calls run immediately.
                $result = $this->registry->execute( $name, $args );

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

            if ( ! empty( $pending ) ) {
                return $this->pause( $messages, $pending, $steps );
            }
        }

        return new WP_Error(
            'wpa_max_steps_exceeded',
            'عملیات از سقف مجاز گام‌های ایجنت عبور کرد. درخواست را ساده‌تر مطرح کنید یا سقف گام‌ها را در تنظیمات افزایش دهید.'
        );
    }

    /**
     * Store the loop state and describe the pending actions for the UI.
     */
    private function pause( array $messages, array $pending, array $steps ) {
        $token = strtolower( wp_generate_password( 24, false, false ) );

        set_transient(
            self::TRANSIENT_PREFIX . $token,
            [
                'user_id'  => get_current_user_id(),
                'messages' => $messages,
                'pending'  => $pending,
                'steps'    => $steps,
            ],
            self::PENDING_TTL
        );

        $actions = [];

        foreach ( $pending as $call ) {
            $tool = $this->registry->get( $call['name'] );

            $actions[] = [
                'tool'    => $call['name'],
                'title'   => $tool ? $tool->get_title() : $call['name'],
                'summary' => $tool ? $tool->summarize( $call['args'] ) : [],
            ];
        }

        return [
            'status'  => 'pending',
            'token'   => $token,
            'actions' => $actions,
            'steps'   => $steps,
        ];
    }
}
