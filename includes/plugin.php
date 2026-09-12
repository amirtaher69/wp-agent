<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Main plugin class. Wires up hooks, dependencies, features and assets.
 */
class WPA_Plugin {

    public function __construct() {
        $this->register_hooks();
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
    }

    /**
     * Register all frontend assets. Registered here, enqueued per-feature.
     */
    public function register_assets() {
        // No assets yet. Register per-feature styles/scripts here, e.g.:
        // wp_register_style( 'wpa-{feature}', WPA_URL . 'assets/css/wpa-{feature}.css', [], WPA_VERSION );
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
        // No features yet. Attach each register_{feature}() method to its hook here.
    }
}
