<?php
/**
 * Settings Page — admin menu, settings fields and the connection test handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Settings_Page {

    const OPTION_NAME    = 'wpa_settings';
    const OPTION_GROUP   = 'wpa_settings_group';
    const MENU_SLUG      = 'wp-agent';
    const API_SECTION_ID = 'wpa_api_section';

    /**
     * Hook suffix of the settings screen, set when the menu is registered.
     */
    private $hook_suffix = '';

    /**
     * Default values for every setting.
     */
    public static function get_defaults() {
        return [
            'api_key'   => '',
            'base_url'  => 'https://api.openai.com/v1',
            'model'     => 'gpt-4o-mini',
            'timeout'   => 60,
            'max_steps' => 10,
        ];
    }

    /**
     * Whitelist of selectable OpenAI models (value => label).
     */
    public static function get_model_options() {
        return [
            'gpt-5'        => 'GPT-5',
            'gpt-5-mini'   => 'GPT-5 mini',
            'gpt-5-nano'   => 'GPT-5 nano',
            'gpt-4.1'      => 'GPT-4.1',
            'gpt-4.1-mini' => 'GPT-4.1 mini',
            'gpt-4o'       => 'GPT-4o',
            'gpt-4o-mini'  => 'GPT-4o mini',
        ];
    }

    /**
     * Current settings merged with defaults. The wp-config constant,
     * when defined, always overrides the stored API key.
     */
    public static function get_settings() {
        $settings = wp_parse_args( (array) get_option( self::OPTION_NAME, [] ), self::get_defaults() );

        if ( self::is_api_key_constant_defined() ) {
            $settings['api_key'] = WPA_OPENAI_API_KEY;
        }

        return $settings;
    }

    /**
     * Whether the API key is forced via wp-config.php.
     */
    public static function is_api_key_constant_defined() {
        return defined( 'WPA_OPENAI_API_KEY' ) && '' !== WPA_OPENAI_API_KEY;
    }

    /**
     * Register the top-level menu and the settings submenu.
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
            'تنظیمات WP Agent',
            'تنظیمات',
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    /**
     * Register the option, its section and all fields.
     */
    public function register_settings() {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ]
        );

        add_settings_section(
            self::API_SECTION_ID,
            'اتصال به OpenAI',
            [ $this, 'render_api_section_intro' ],
            self::MENU_SLUG
        );

        $fields = [
            'api_key'   => 'کلید API',
            'base_url'  => 'آدرس پایه API',
            'model'     => 'مدل هوش مصنوعی',
            'timeout'   => 'مهلت درخواست (ثانیه)',
            'max_steps' => 'حداکثر گام‌های ایجنت',
        ];

        foreach ( $fields as $key => $label ) {
            add_settings_field(
                'wpa_field_' . $key,
                $label,
                [ $this, 'render_' . $key . '_field' ],
                self::MENU_SLUG,
                self::API_SECTION_ID,
                [ 'label_for' => 'wpa-field-' . $key ]
            );
        }
    }

    /**
     * Validate and normalize submitted settings.
     */
    public function sanitize_settings( $input ) {
        $input    = is_array( $input ) ? $input : [];
        $old      = wp_parse_args( (array) get_option( self::OPTION_NAME, [] ), self::get_defaults() );
        $defaults = self::get_defaults();
        $clean    = [];

        // Keep the stored key when the masked field is submitted empty.
        $api_key          = isset( $input['api_key'] ) ? trim( sanitize_text_field( $input['api_key'] ) ) : '';
        $clean['api_key'] = ( '' !== $api_key ) ? $api_key : $old['api_key'];

        $base_url          = isset( $input['base_url'] ) ? esc_url_raw( trim( $input['base_url'] ) ) : '';
        $clean['base_url'] = ( '' !== $base_url ) ? untrailingslashit( $base_url ) : $defaults['base_url'];

        // Only whitelisted models are accepted.
        $model          = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : '';
        $clean['model'] = array_key_exists( $model, self::get_model_options() ) ? $model : $defaults['model'];

        $clean['timeout']   = $this->clamp_int( isset( $input['timeout'] ) ? $input['timeout'] : 0, 5, 300, $defaults['timeout'] );
        $clean['max_steps'] = $this->clamp_int( isset( $input['max_steps'] ) ? $input['max_steps'] : 0, 1, 30, $defaults['max_steps'] );

        return $clean;
    }

    public function render_api_section_intro() {
        echo '<p>مشخصات اتصال به سرویس OpenAI (یا هر سرویس واسطه سازگار با OpenAI) را وارد کنید.</p>';
    }

    public function render_api_key_field() {
        if ( self::is_api_key_constant_defined() ) {
            ?>
            <input type="password" id="wpa-field-api_key" class="regular-text" value="********" disabled>
            <p class="description">
                کلید API از طریق ثابت <code>WPA_OPENAI_API_KEY</code> در فایل <code>wp-config.php</code> تنظیم شده و از اینجا قابل تغییر نیست.
            </p>
            <?php
            return;
        }

        $settings    = self::get_settings();
        $has_key     = '' !== $settings['api_key'];
        $placeholder = $has_key ? '•••••••••••••••• (کلید ذخیره شده است)' : 'sk-...';
        ?>
        <input
            type="password"
            id="wpa-field-api_key"
            class="regular-text"
            name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key]"
            value=""
            placeholder="<?php echo esc_attr( $placeholder ); ?>"
            autocomplete="new-password"
        >
        <?php if ( $has_key ) : ?>
            <p class="description">برای حفظ کلید فعلی، این فیلد را خالی بگذارید و برای تغییر، کلید جدید را وارد کنید.</p>
        <?php else : ?>
            <p class="description">کلید API حساب OpenAI خود را وارد کنید.</p>
        <?php endif; ?>
        <?php
    }

    public function render_base_url_field() {
        $settings = self::get_settings();
        ?>
        <input
            type="url"
            id="wpa-field-base_url"
            class="regular-text code"
            dir="ltr"
            name="<?php echo esc_attr( self::OPTION_NAME ); ?>[base_url]"
            value="<?php echo esc_attr( $settings['base_url'] ); ?>"
        >
        <p class="description">در صورت استفاده از سرویس واسطه سازگار با OpenAI، آدرس پایه آن را وارد کنید. پیش‌فرض: <code>https://api.openai.com/v1</code></p>
        <?php
    }

    public function render_model_field() {
        $settings = self::get_settings();
        ?>
        <select id="wpa-field-model" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[model]">
            <?php foreach ( self::get_model_options() as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['model'], $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">مدلی که پرامپت‌های شما با آن پردازش می‌شود.</p>
        <?php
    }

    public function render_timeout_field() {
        $settings = self::get_settings();
        ?>
        <input
            type="number"
            id="wpa-field-timeout"
            class="small-text"
            name="<?php echo esc_attr( self::OPTION_NAME ); ?>[timeout]"
            value="<?php echo esc_attr( $settings['timeout'] ); ?>"
            min="5"
            max="300"
        >
        <p class="description">حداکثر زمان انتظار برای پاسخ سرویس، بین ۵ تا ۳۰۰ ثانیه.</p>
        <?php
    }

    public function render_max_steps_field() {
        $settings = self::get_settings();
        ?>
        <input
            type="number"
            id="wpa-field-max_steps"
            class="small-text"
            name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_steps]"
            value="<?php echo esc_attr( $settings['max_steps'] ); ?>"
            min="1"
            max="30"
        >
        <p class="description">سقف تعداد گام‌های حلقه ایجنت در هر درخواست (در فازهای بعدی استفاده می‌شود).</p>
        <?php
    }

    /**
     * Render the settings screen with the connection-test box.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>تنظیمات WP Agent</h1>

            <form action="options.php" method="post">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::MENU_SLUG );
                submit_button( 'ذخیره تنظیمات' );
                ?>
            </form>

            <hr>

            <h2>تست اتصال</h2>
            <p>تست با آخرین تنظیمات <strong>ذخیره‌شده</strong> انجام می‌شود؛ اگر تغییری داده‌اید، ابتدا آن را ذخیره کنید.</p>
            <p>
                <button type="button" class="button" id="wpa-test-connection">تست اتصال</button>
                <span id="wpa-test-result" class="wpa-test-result" aria-live="polite"></span>
            </p>
        </div>
        <?php
    }

    /**
     * Enqueue settings assets only on this screen.
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( $hook_suffix !== $this->hook_suffix ) {
            return;
        }

        wp_enqueue_style( 'wpa-settings' );
        wp_enqueue_script( 'wpa-settings' );

        wp_localize_script(
            'wpa-settings',
            'wpaSettings',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'wpa_test_connection' ),
                'i18n'    => [
                    'testing'      => 'در حال بررسی اتصال…',
                    'networkError' => 'خطا در ارتباط با سایت. اتصال خود را بررسی کنید.',
                    'genericError' => 'خطای نامشخصی رخ داد.',
                ],
            ]
        );
    }

    /**
     * AJAX handler: send a tiny ping message through the AI client.
     */
    public function handle_test_connection() {
        check_ajax_referer( 'wpa_test_connection', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'شما دسترسی لازم برای این کار را ندارید.' ], 403 );
        }

        require_once WPA_PATH . 'includes/ai/class-wpa-ai-client.php';

        $client = new WPA_AI_Client();
        $result = $client->send(
            [
                [
                    'role'    => 'user',
                    'content' => 'Reply with the single word: OK',
                ],
            ]
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success(
            [
                'message' => sprintf( 'اتصال با موفقیت برقرار شد — مدل: %s', $result['model'] ),
            ]
        );
    }

    /**
     * Clamp a value to an integer range, falling back on invalid input.
     */
    private function clamp_int( $value, $min, $max, $fallback ) {
        $value = absint( $value );

        if ( $value < $min || $value > $max ) {
            return ( 0 === $value ) ? $fallback : min( max( $value, $min ), $max );
        }

        return $value;
    }
}
