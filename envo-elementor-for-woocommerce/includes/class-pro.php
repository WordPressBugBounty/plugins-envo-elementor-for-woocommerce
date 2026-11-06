<?php
/**
 * What the free plugin knows about PRO. The licence itself lives only in the PRO plugin: it says through the filter
 * "etww/pro_active" that it is active and adds the licence headers for the library through "etww/library/headers".
 *
 * @package ETWW
 */

namespace ETWW;

defined( 'ABSPATH' ) || exit;

class Pro {

	const BUY_URL = 'https://envothemes.com/product/elementor-templates-woocommerce-pro/';

	public static function installed() {
		return defined( 'ETW_PRO_VERSION' );
	}

	/**
	 * PRO 1.x knows nothing about the new library.
	 */
	public static function outdated() {
		return self::installed() && version_compare( ETW_PRO_VERSION, '2.0.0', '<' );
	}

	public static function active() {
		return (bool) apply_filters( 'etww/pro_active', false );
	}

	public static function buy_url() {
		return (string) apply_filters( 'etww/pro_buy_url', self::BUY_URL );
	}

	public static function locked_error() {
		if ( self::outdated() ) {
			$message = __( 'This design is part of PRO. Update the PRO plugin to version 2.0 or newer to import it.', 'envo-elementor-for-woocommerce' );
		} elseif ( self::installed() ) {
			$message = __( 'This design is part of PRO. Activate your PRO licence to import it.', 'envo-elementor-for-woocommerce' );
		} else {
			$message = __( 'This design is part of PRO. Get the PRO plugin to import it.', 'envo-elementor-for-woocommerce' );
		}
		return new \WP_Error( 'etww_pro_locked', $message, array( 'status' => 403 ) );
	}
}
