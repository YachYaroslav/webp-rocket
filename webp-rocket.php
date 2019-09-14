<?php
/**
 * Plugin Name: WebP Rocket
 *
 * Author: Yaroslav Yachmenov
 *
 * Author URI: https://www.linkedin.com/in/yaroslav-yachmenov-797945121/
 *
 * Description: Adds support of WebP images, operations over WebP images (rotation, slicing and others) on the image page and more.
 *
 * Version: 1.0
 *
 * Text Domain: webp-rocket
 *
 * Domain Path: /languages
 *
 * License: GPL v3
 */
defined( 'ABSPATH' ) || exit;


/**
 * Current plugin version
 */
define( 'WEBP_ROCKET_PLUGIN_VERSION', '1.0' );
/**
 * Path to the plugin
 */
define( 'WEBP_ROCKET_PATH', plugin_dir_path( __FILE__ ) );
/**
 * Path to the plugin PHP files
 */
define( 'WEBP_ROCKET_PHP', WEBP_ROCKET_PATH . 'includes' );
/**
 * URL to the plugin
 */
define( 'WEBP_ROCKET_URL', plugin_dir_url( __FILE__ ) );
/**
 * URL to the assets of plugin
 */
define( 'WEBP_ROCKET_ASSETS_URL', WEBP_ROCKET_URL . 'assets' );
/**
 * Plugin basename
 */
define( 'WEBP_ROCKET_BASENAME', plugin_basename( __FILE__ ) );

add_action( 'plugins_loaded', 'webp_rocket_load_plugin_textdomain' );

/**
 * Loads textdomain for WebP Rocket plugin
 */
function webp_rocket_load_plugin_textdomain() {
	load_plugin_textdomain( 'webp-rocket', false, WEBP_ROCKET_BASENAME . '/languages' );
}

add_action( 'init', 'webp_rocket_plugin_init' );

/**
 * Entry point of WebP Rocket plugin
 */
function webp_rocket_plugin_init() {

	require_once WEBP_ROCKET_PHP . '/general.php';

	add_action( 'admin_init', 'webp_rocket_admin_plugin_init' );

	function webp_rocket_admin_plugin_init() {
		require_once WEBP_ROCKET_PHP . '/admin.php';
	}
}