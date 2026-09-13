<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Main plugin class. Wires up hooks, dependencies, features and assets.
 */
class WPA_Plugin {

    /**
     * Lazily-created feature instances, shared between hook callbacks.
     */
    private $settings_page = null;
    private $chat_page     = null;

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
        wp_register_style( 'wpa-chat', WPA_URL . 'assets/css/wpa-chat.css', [], WPA_VERSION );
        wp_register_script( 'wpa-chat', WPA_URL . 'assets/js/wpa-chat.js', [], WPA_VERSION, true );

        $this->get_chat_page()->enqueue_assets( $hook_suffix );
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
        // The chat page owns the top-level menu, so it must register first.
        add_action( 'admin_menu', [ $this, 'register_chat_page' ] );
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    /**
     * Register the top-level menu and the chat screen.
     */
    public function register_chat_page() {
        $this->get_chat_page()->register_menu();
    }

    /**
     * Register the settings screen as a submenu of the chat page.
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
     * Register the plugin REST routes under the wpa/v1 namespace.
     */
    public function register_rest_routes() {
        require_once WPA_PATH . 'includes/api/class-wpa-rest-controller.php';

        $controller = new WPA_REST_Controller();
        $controller->register_routes();
    }

    /**
     * Lazy-load and share a single chat page instance.
     */
    private function get_chat_page() {
        if ( null === $this->chat_page ) {
            require_once WPA_PATH . 'includes/admin/class-wpa-chat-page.php';
            $this->chat_page = new WPA_Chat_Page();
        }

        return $this->chat_page;
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
