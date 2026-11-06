<?php
/**
 * REST API of the library page and the editor (etww/v1, logged-in users with a REST nonce only).
 *
 * @package ETWW
 */

namespace ETWW\Admin;

use ETWW\Library\Importer;
use ETWW\Library\Kit_Import;
use ETWW\Library\News;
use ETWW\Library\Plugins;
use ETWW\Library\Previews;
use ETWW\Library\Remote;
use ETWW\Pro;

defined( 'ABSPATH' ) || exit;

class Rest {

	const NS = 'etww/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function can_view() {
		return current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' );
	}

	public static function can_import_pages() {
		return current_user_can( 'edit_pages' );
	}

	public static function can_import_library() {
		$type = get_post_type_object( 'elementor_library' );
		return $type && current_user_can( $type->cap->edit_posts );
	}

	public static function routes() {
		$id = array(
			'type'     => 'integer',
			'required' => true,
			'minimum'  => 1,
		);
		register_rest_route(
			self::NS,
			'/library',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'library' ),
				'permission_callback' => array( __CLASS__, 'can_view' ),
				'args'                => array(
					'refresh' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/previews',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'previews' ),
				'permission_callback' => array( __CLASS__, 'can_view' ),
				'args'                => array(
					'keys' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/seen',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'seen' ),
				'permission_callback' => array( __CLASS__, 'can_view' ),
				'args'                => array(
					'keys' => array(
						'type'     => 'array',
						'items'    => array(
							'type'    => 'string',
							'pattern' => '^(kit|page):[0-9]+$',
						),
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/plugin',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'plugin' ),
				'permission_callback' => static function () {
					return current_user_can( 'install_plugins' ) || current_user_can( 'activate_plugins' );
				},
				'args'                => array(
					'slug' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'import' ),
				'permission_callback' => array( __CLASS__, 'can_view' ),
				'args'                => array(
					'page'   => $id,
					'to'     => array(
						'type'    => 'string',
						'enum'    => array( 'page', 'post', 'library' ),
						'default' => 'page',
					),
					'status' => array(
						'type'    => 'string',
						'enum'    => array( 'draft', 'publish' ),
						'default' => 'draft',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/finish',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'finish' ),
				'permission_callback' => array( __CLASS__, 'can_view' ),
				'args'                => array(
					'pages'     => array(
						'type'     => 'array',
						'required' => true,
					),
					'front'     => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'menu'      => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'menu_name' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/content',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'content' ),
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array( 'page' => $id ),
			)
		);
	}

	/**
	 * The library for the admin page and the editor: preview images resolved, file paths left out.
	 */
	public static function library( \WP_REST_Request $request ) {
		$index = Remote::index( (bool) $request['refresh'] );
		if ( is_wp_error( $index ) ) {
			return $index;
		}
		$base  = $index['previews_url'];
		$slugs = array();
		$page  = static function ( array $page ) use ( $base, &$slugs ) {
			$page['image'] = Previews::data( $page['image'], $base );
			$slugs         = array_merge( $slugs, $page['plugins'] );
			unset( $page['file'] );
			return $page;
		};
		$kits = array();
		foreach ( $index['kits'] as $kit ) {
			$kit['image'] = Previews::data( $kit['image'], $base );
			$kit['pages'] = array_map( $page, $kit['pages'] );
			$slugs        = array_merge( $slugs, $kit['plugins'] );
			$kits[]       = $kit;
		}
		$pages = array_map( $page, $index['pages'] );
		// New items the user has not seen yet (the library page marks them seen).
		$news = array(
			'kits'  => array(),
			'pages' => array(),
		);
		foreach ( array_keys( News::unseen() ) as $key ) {
			list( $kind, $item ) = explode( ':', $key );
			$news[ 'kit' === $kind ? 'kits' : 'pages' ][] = (int) $item;
		}

		return array_merge(
			array(
				'kits'      => $kits,
				'pages'     => $pages,
				'plugins'   => Plugins::states( array_values( array_unique( $slugs ) ) ),
				'published' => $index['published'],
				'demo'      => $index['home'],
				'news'      => $news,
				'pro'       => array(
					'active'    => Pro::active(),
					'installed' => Pro::installed(),
					'outdated'  => Pro::outdated(),
					'buy'       => Pro::buy_url(),
				),
				'can'       => array(
					'pages'        => self::can_import_pages(),
					'publish'      => current_user_can( 'publish_pages' ),
					'posts'        => current_user_can( 'edit_posts' ),
					'publishPosts' => current_user_can( 'publish_posts' ),
					'library'      => self::can_import_library(),
					'front'        => current_user_can( 'manage_options' ),
					'menu'         => current_user_can( 'edit_theme_options' ),
					'install'      => current_user_can( 'install_plugins' ),
					'activate'     => current_user_can( 'activate_plugins' ),
				),
			),
			Remote::status()
		);
	}

	public static function previews( \WP_REST_Request $request ) {
		return array( 'kept' => Previews::keep( (array) $request['keys'] ) );
	}

	public static function seen( \WP_REST_Request $request ) {
		return array( 'unseen' => News::seen( (array) $request['keys'] ) );
	}

	public static function plugin( \WP_REST_Request $request ) {
		return Plugins::install( (string) $request['slug'] );
	}

	public static function import( \WP_REST_Request $request ) {
		if ( 'library' === $request['to'] ) {
			if ( ! self::can_import_library() ) {
				return new \WP_Error( 'etww_forbidden', __( 'You are not allowed to add Elementor templates.', 'envo-elementor-for-woocommerce' ), array( 'status' => 403 ) );
			}
			return Importer::to_library( (int) $request['page'] );
		}
		return Importer::to_page( (int) $request['page'], (string) $request['status'], (string) $request['to'] );
	}

	public static function finish( \WP_REST_Request $request ) {
		return Kit_Import::finish(
			(array) $request['pages'],
			array(
				'front'     => (bool) $request['front'],
				'menu'      => (bool) $request['menu'],
				'menu_name' => (string) $request['menu_name'],
			)
		);
	}

	public static function content( \WP_REST_Request $request ) {
		return Importer::content( (int) $request['page'] );
	}
}
