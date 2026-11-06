<?php
/**
 * Bootstrap: autoloader, requirements, the parts of the plugin and the update from 1.x.
 *
 * @package ETWW
 */

namespace ETWW;

defined( 'ABSPATH' ) || exit;

class Plugin {

	const PRO_FILE = 'envo-elementor-for-woocommerce-pro/elementor-templates-woocommerce-pro.php';

	public static function init() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );

		register_activation_hook( ETWW_ROOT, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( ETWW_ROOT, array( __CLASS__, 'deactivate' ) );

		add_action( 'before_woocommerce_init', array( __CLASS__, 'hpos' ) );
		add_action( 'init', array( __CLASS__, 'i18n' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'load' ) );
	}

	/**
	 * ETWW\Library\Remote → includes/library/class-remote.php
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
			return;
		}
		$parts = explode( '\\', substr( $class, strlen( __NAMESPACE__ ) + 1 ) );
		$name  = array_pop( $parts );
		$dir   = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$file  = ETWW_PATH . 'includes/' . $dir . 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}

	/**
	 * Version of a plugin file for its URL: the plugin version plus the file time, so a changed file is never
	 * served from the browser cache.
	 */
	public static function asset_version( $file ) {
		$time = @filemtime( ETWW_PATH . $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $time ? ETWW_VERSION . '.' . $time : ETWW_VERSION;
	}

	public static function hpos() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ETWW_ROOT, true );
		}
	}

	public static function i18n() {
		load_plugin_textdomain( 'envo-elementor-for-woocommerce', false, dirname( ETWW_PLUGIN_BASE ) . '/languages/' );
	}

	public static function load() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_elementor' ) );
			return;
		}
		self::upgrade();

		require_once ETWW_PATH . 'includes/helpers.php';
		Widgets\Manager::init();

		Library\Previews::init();
		Library\News::init();
		Admin\Rest::init();
		Editor\Editor_Library::init();
		if ( is_admin() ) {
			Admin\Admin::init();
		}
	}

	/**
	 * Clean-up after 1.x: the old library kept its list in a transient.
	 */
	private static function upgrade() {
		$version = get_option( 'etww_version' );
		if ( ETWW_VERSION === $version ) {
			return;
		}
		if ( ! $version || version_compare( $version, '2.0.0', '<' ) ) {
			delete_transient( 'etww_template_info' );
			delete_metadata( 'user', 0, 'etww_pro_promo_message', '', true );
		}
		update_option( 'etww_version', ETWW_VERSION );
	}

	public static function activate() {
		delete_transient( 'etww_template_info' );
		add_option( 'etww_do_activation_redirect', 1 );
	}

	public static function deactivate() {
		delete_transient( 'etww_template_info' );
		delete_option( 'etww_do_activation_redirect' );
		wp_clear_scheduled_hook( Library\Previews::PRUNE );
		wp_clear_scheduled_hook( Library\News::CHECK );
	}

	public static function notice_elementor() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$file = 'elementor/elementor.php';
		if ( isset( get_plugins()[ $file ] ) ) {
			$url    = wp_nonce_url( 'plugins.php?action=activate&plugin=' . $file . '&plugin_status=all&paged=1', 'activate-plugin_' . $file );
			$button = __( 'Activate Elementor', 'envo-elementor-for-woocommerce' );
		} else {
			$url    = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 'install-plugin_elementor' );
			$button = __( 'Install Elementor', 'envo-elementor-for-woocommerce' );
		}
		printf(
			'<div class="notice notice-error"><p>%1$s</p><p><a href="%2$s" class="button-primary">%3$s</a></p></div>',
			/* translators: %s: plugin name */
			sprintf( esc_html__( '%s needs the Elementor plugin.', 'envo-elementor-for-woocommerce' ), '<strong>' . esc_html__( 'Envo\'s Templates & Widgets for Elementor and WooCommerce', 'envo-elementor-for-woocommerce' ) . '</strong>' ),
			esc_url( $url ),
			esc_html( $button )
		);
	}
}
