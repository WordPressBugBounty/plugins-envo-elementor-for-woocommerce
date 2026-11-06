<?php
/**
 * Envo Templates in the Elementor editor: a button in the "add section" area opens the library, and a page is
 * inserted into the open document through Elementor's own import command (Ctrl+Z takes it back).
 *
 * @package ETWW
 */

namespace ETWW\Editor;

use ETWW\Admin\Admin;
use ETWW\Admin\Rest;

defined( 'ABSPATH' ) || exit;

class Editor_Library {

	public static function init() {
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'scripts' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'styles' ) );
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'preview_styles' ) );
	}

	private static function enabled() {
		return current_user_can( 'edit_posts' ) && (bool) apply_filters( 'etww/editor_library', true );
	}

	public static function scripts() {
		if ( ! self::enabled() ) {
			return;
		}
		wp_enqueue_script( 'etww-editor-library', ETWW_URL . 'assets/editor/library.js', array( 'jquery', 'backbone', 'wp-i18n' ), \ETWW\Plugin::asset_version( 'assets/editor/library.js' ), true );
		wp_set_script_translations( 'etww-editor-library', 'envo-elementor-for-woocommerce', ETWW_PATH . 'languages' );
		wp_add_inline_script(
			'etww-editor-library',
			'window.etwwEditorLibrary = ' . wp_json_encode(
				array(
					'rest'       => esc_url_raw( rest_url( Rest::NS . '/' ) ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'libraryUrl' => Admin::url(),
					'logo'       => Admin::icon(),
				)
			) . ';',
			'before'
		);
	}

	public static function styles() {
		if ( self::enabled() ) {
			wp_enqueue_style( 'etww-editor-library', ETWW_URL . 'assets/editor/library.css', array(), \ETWW\Plugin::asset_version( 'assets/editor/library.css' ) );
		}
	}

	public static function preview_styles() {
		if ( self::enabled() ) {
			wp_enqueue_style( 'etww-editor-library-preview', ETWW_URL . 'assets/editor/preview.css', array(), \ETWW\Plugin::asset_version( 'assets/editor/preview.css' ) );
		}
	}
}
