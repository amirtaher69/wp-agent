<?php
/**
 * Plugin Name: WP Agent
 * Description: افزونه WP Agent — پایه ماژولار برای امکانات سفارشی سایت.
 * Version: 1.0.0
 * Author: Amir taherkhani
 * Author URI: https://wikiteach.ir
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'WPA_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPA_URL', plugin_dir_url( __FILE__ ) );
define( 'WPA_VERSION', '1.0.0' );

require_once WPA_PATH . 'includes/plugin.php';
new WPA_Plugin();
