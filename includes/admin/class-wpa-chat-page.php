<?php
/**
 * Chat Page — the main admin screen where the user talks to the agent.
 *
 * Owns the top-level "WP Agent" menu; the settings screen registers itself
 * as a submenu under this page's slug.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Chat_Page {

    const MENU_SLUG = 'wp-agent';

    /**
     * Hook suffix of the chat screen, set when the menu is registered.
     */
    private $hook_suffix = '';

    /**
     * Register the top-level menu; clicking it opens the chat screen.
     */
    public function register_menu() {
        $this->hook_suffix = add_menu_page(
            'WP Agent',
            'WP Agent',
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_page' ],
            'dashicons-format-chat',
            66
        );

        // Rename the auto-created first submenu item to a Persian label.
        add_submenu_page(
            self::MENU_SLUG,
            'گفتگو با دستیار',
            'گفتگو',
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    /**
     * Render the chat screen, or a setup notice when no API key is stored.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = $this->get_settings();
        ?>
        <div class="wrap wpa-chat-wrap">
            <h1>
                گفتگو با دستیار
                <?php if ( '' !== $settings['api_key'] ) : ?>
                    <span class="wpa-chat-model">مدل: <?php echo esc_html( $settings['model'] ); ?></span>
                <?php endif; ?>
            </h1>

            <?php if ( '' === $settings['api_key'] ) : ?>
                <div class="notice notice-warning">
                    <p>
                        برای شروع گفتگو، ابتدا کلید API را در
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agent-settings' ) ); ?>">تنظیمات WP Agent</a>
                        ذخیره کنید.
                    </p>
                </div>
            <?php else : ?>
                <div id="wpa-chat" class="wpa-chat">
                    <div id="wpa-chat-messages" class="wpa-chat-messages">
                        <div class="wpa-chat-empty" id="wpa-chat-empty">سوال یا دستور خود را بنویسید تا شروع کنیم.</div>
                    </div>
                    <form id="wpa-chat-form" class="wpa-chat-form">
                        <textarea
                            id="wpa-chat-input"
                            rows="3"
                            placeholder="پیام خود را بنویسید… (Enter برای ارسال، Shift+Enter برای خط جدید)"
                        ></textarea>
                        <div class="wpa-chat-actions">
                            <button type="submit" class="button button-primary" id="wpa-chat-send">ارسال</button>
                            <button type="button" class="button" id="wpa-chat-reset">گفتگوی جدید</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Enqueue chat assets only on this screen.
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( $hook_suffix !== $this->hook_suffix ) {
            return;
        }

        wp_enqueue_style( 'wpa-chat' );
        wp_enqueue_script( 'wpa-chat' );

        wp_localize_script(
            'wpa-chat',
            'wpaChat',
            [
                'chatUrl'     => esc_url_raw( rest_url( 'wpa/v1/chat' ) ),
                'nonce'       => wp_create_nonce( 'wp_rest' ),
                'maxMessages' => 20,
                'i18n'        => [
                    'thinking'     => 'در حال فکر کردن…',
                    'networkError' => 'خطا در ارتباط با سایت. اتصال خود را بررسی کنید.',
                    'genericError' => 'خطای نامشخصی رخ داد.',
                    'emptyState'   => 'سوال یا دستور خود را بنویسید تا شروع کنیم.',
                ],
            ]
        );
    }

    /**
     * Read plugin settings through the settings page accessor.
     */
    private function get_settings() {
        if ( ! class_exists( 'WPA_Settings_Page' ) ) {
            require_once WPA_PATH . 'includes/admin/class-wpa-settings-page.php';
        }

        return WPA_Settings_Page::get_settings();
    }
}
