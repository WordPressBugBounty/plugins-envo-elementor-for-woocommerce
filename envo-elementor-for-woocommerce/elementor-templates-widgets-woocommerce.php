<?php
/**
 * Plugin Name: Envo's Templates & Widgets for Elementor and WooCommerce
 * Description: Ready-made Elementor designs for shops (home pages with their subpages) and widgets for Elementor and WooCommerce.
 * Plugin URI:  https://envothemes.com/elementor-templates-for-woocommerce/
 * Version:     2.0.0
 * Author:      EnvoThemes
 * Author URI:  https://envothemes.com/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: envo-elementor-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: elementor
 * WC tested up to: 11.2
 * Elementor tested up to: 4.3.0
 *
 * @package ETWW
 */

defined( 'ABSPATH' ) || exit;

define( 'ETWW_VERSION', '2.0.0' );
define( 'ETWW_ROOT', __FILE__ );
define( 'ETWW_URL', plugins_url( '/', ETWW_ROOT ) );
define( 'ETWW_PATH', plugin_dir_path( ETWW_ROOT ) );
define( 'ETWW_DIR_URL', plugin_dir_url( ETWW_ROOT ) );
define( 'ETWW_PLUGIN_BASE', plugin_basename( ETWW_ROOT ) );

// The template library on envothemes.com (can be changed with the filter "etww/library_url").
if ( ! defined( 'ETWW_LIBRARY_URL' ) ) {
	define( 'ETWW_LIBRARY_URL', 'https://envothemes.com/elementor-templates-for-woocommerce/wp-json/etww-library/v1' );
}

require_once ETWW_PATH . 'includes/class-plugin.php';
\ETWW\Plugin::init();
