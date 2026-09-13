<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Main plugin class. Wires up hooks, dependencies, features and assets.
 */
class WPA_Plugin {

    /**
     * Lazily-created settings page instance, shared between hook callbacks.
     */
    private $settings_page = null;

    public function __construct() {
        $this->register_hooks();
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'register_admin_assets' ] );
    }

    /**
     * Register all frontend assets. Registered here, enqueued per-feature.
     */
    public function register_assets() {
        // No frontend assets yet.
    }

    /**
     * Register all admin assets, then let each feature enqueue on its own screen.
     */
    public function register_admin_assets( $hook_suffix ) {
        wp_register_style( 'wpa-settings', WPA_URL . 'assets/css/wpa-settings.css', [], WPA_VERSION );
        wp_register_script( 'wpa-settings', WPA_URL . 'assets/js/wpa-settings.js', [], WPA_VERSION, true );

        $this->get_settings_page()->enqueue_assets( $hook_suffix );
    }

    private function register_hooks() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    /**
     * Bootstrap the plugin after all plugins are loaded.
     */
    public function init() {
        $this->init_features();
    }

    /**
     * Attach each feature to its registration hook.
     */
    private function init_features() {
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_wpa_test_connection', [ $this, 'handle_test_connection' ] );
    }

    /**
     * Register the admin menu and the settings screen.
     */
    public function register_settings_page() {
        $this->get_settings_page()->register_menu();
    }

    /**
     * Register the plugin option, sections and fields.
     */
    public function register_settings() {
        $this->get_settings_page()->register_settings();
    }

    /**
     * AJAX: test the connection to the OpenAI API.
     */
    public function handle_test_connection() {
        $this->get_settings_page()->handle_test_connection();
    }

    /**
     * Lazy-load and share a single settings page instance.
     */
    private function get_settings_page() {
        if ( null === $this->settings_page ) {
            require_once WPA_PATH . 'includes/admin/class-wpa-settings-page.php';
            $this->settings_page = new WPA_Settings_Page();
        }

        return $this->settings_page;
    }
}
