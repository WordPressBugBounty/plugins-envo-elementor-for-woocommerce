<?php
/**
 * Plugins that library pages need besides Elementor (wordpress.org slugs from the list, e.g. WooCommerce):
 * their state on this site, and install / activate from wordpress.org before an import.
 *
 * @package ETWW
 */

namespace ETWW\Library;

defined( 'ABSPATH' ) || exit;

class Plugins {

	const NAMES = array(
		'woocommerce'    => 'WooCommerce',
		'contact-form-7' => 'Contact Form 7',
	);

	private static function load_admin() {
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Main file of an installed plugin by its folder (slug), '' when it is not installed.
	 */
	public static function file( $slug ) {
		self::load_admin();
		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( 0 === strpos( $file, $slug . '/' ) ) {
				return $file;
			}
		}
		return '';
	}

	/**
	 * @return string active|installed|missing
	 */
	public static function state( $slug ) {
		self::load_admin();
		$file = self::file( $slug );
		if ( ! $file ) {
			return 'missing';
		}
		return is_plugin_active( $file ) ? 'active' : 'installed';
	}

	public static function name( $slug ) {
		if ( isset( self::NAMES[ $slug ] ) ) {
			return self::NAMES[ $slug ];
		}
		$file = self::file( $slug );
		return $file ? get_plugins()[ $file ]['Name'] : ucwords( str_replace( '-', ' ', $slug ) );
	}

	/**
	 * @param string[] $slugs Slugs.
	 * @return array[] { slug, name, state }
	 */
	public static function states( array $slugs ) {
		$list = array();
		foreach ( array_unique( $slugs ) as $slug ) {
			$list[] = array(
				'slug'  => $slug,
				'name'  => self::name( $slug ),
				'state' => self::state( $slug ),
			);
		}
		return $list;
	}

	/**
	 * @param string[] $slugs Slugs.
	 * @return string[] Slugs that are not active.
	 */
	public static function missing( array $slugs ) {
		return array_values(
			array_filter(
				$slugs,
				static function ( $slug ) {
					return 'active' !== self::state( $slug );
				}
			)
		);
	}

	public static function missing_error( array $slugs ) {
		return new \WP_Error(
			'etww_needs_plugins',
			/* translators: %s: plugin names */
			sprintf( __( 'This page needs these plugins: %s. Install and activate them, then import it again.', 'envo-elementor-for-woocommerce' ), implode( ', ', array_map( array( __CLASS__, 'name' ), $slugs ) ) ),
			array(
				'status'  => 409,
				'plugins' => self::states( $slugs ),
			)
		);
	}

	/**
	 * Slugs that the library asks for (only these can be installed through the library).
	 *
	 * @return string[]
	 */
	public static function known() {
		$index = Remote::index();
		if ( is_wp_error( $index ) ) {
			return array();
		}
		$slugs = array();
		foreach ( $index['kits'] as $kit ) {
			$slugs = array_merge( $slugs, $kit['plugins'] );
			foreach ( $kit['pages'] as $page ) {
				$slugs = array_merge( $slugs, $page['plugins'] );
			}
		}
		foreach ( $index['pages'] as $page ) {
			$slugs = array_merge( $slugs, $page['plugins'] );
		}
		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Installs (when needed) and activates a plugin from wordpress.org.
	 *
	 * @return array|\WP_Error { slug, name, state }
	 */
	public static function install( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! in_array( $slug, self::known(), true ) ) {
			return new \WP_Error( 'etww_plugin', __( 'This plugin cannot be installed from the template library.', 'envo-elementor-for-woocommerce' ), array( 'status' => 400 ) );
		}
		self::load_admin();
		$file = self::file( $slug );

		if ( ! $file ) {
			if ( ! current_user_can( 'install_plugins' ) ) {
				return new \WP_Error( 'etww_plugin', __( 'You are not allowed to install plugins. Ask the administrator of the site.', 'envo-elementor-for-woocommerce' ), array( 'status' => 403 ) );
			}
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array( 'sections' => false ),
				)
			);
			if ( is_wp_error( $api ) ) {
				return $api;
			}
			$upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
			$result   = $upgrader->install( $api->download_link );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( ! $result ) {
				$errors = $upgrader->skin->get_errors();
				return is_wp_error( $errors ) && $errors->has_errors() ? $errors : new \WP_Error( 'etww_plugin', __( 'The plugin could not be installed.', 'envo-elementor-for-woocommerce' ) );
			}
			wp_clean_plugins_cache();
			$file = self::file( $slug );
			if ( ! $file ) {
				return new \WP_Error( 'etww_plugin', __( 'The plugin could not be installed.', 'envo-elementor-for-woocommerce' ) );
			}
		}

		if ( ! is_plugin_active( $file ) ) {
			if ( ! current_user_can( 'activate_plugin', $file ) ) {
				return new \WP_Error( 'etww_plugin', __( 'You are not allowed to activate plugins. Ask the administrator of the site.', 'envo-elementor-for-woocommerce' ), array( 'status' => 403 ) );
			}
			$activated = activate_plugin( $file, '', false, true );
			if ( is_wp_error( $activated ) ) {
				return $activated;
			}
		}
		return array(
			'slug'  => $slug,
			'name'  => self::name( $slug ),
			'state' => 'active',
		);
	}
}
