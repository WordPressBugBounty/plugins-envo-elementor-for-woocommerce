<?php
/**
 * The Elementor widgets of the plugin: registration, panel category, scripts and styles.
 *
 * The names, controls and HTML of the widgets are the same as in 1.x – pages built with them must not change.
 * Each widget asks for its own script and style (get_script_depends / get_style_depends), so they load only
 * on pages that use the widget.
 *
 * @package ETWW
 */

namespace ETWW\Widgets;

defined( 'ABSPATH' ) || exit;

class Manager {

	/**
	 * File in includes/widgets → class.
	 */
	const WIDGETS = array(
		'animated-heading' => 'AnimatedHeading',
		'blog-grid'        => 'Blog_Grid',
		'flip-box'         => 'FlipBox',
		'off-canvas'       => 'Off_Canvas',
		'pricing'          => 'Pricing',
		'search'           => 'Search',
		'tabs'             => 'Tabs',
		'contact-form'     => 'Contact_Form',
	);

	const WOO_WIDGETS = array(
		'woo-add-to-cart' => 'Woo_Add_To_Cart',
		'woo-products'    => 'Woo_Products',
		'woo-categories'  => 'Woo_Categories',
		'woo-slider'      => 'Woo_Slider',
	);

	/**
	 * Styles in assets/css/<name>/style.css, handle "etww-<name>".
	 */
	const STYLES = array( 'animated-heading', 'blog-grid', 'off-canvas', 'flip-box', 'pricing', 'tabs', 'search', 'woo-addtocart', 'woo-slider' );

	/**
	 * Handle → file in assets/js (without .js).
	 */
	const SCRIPTS = array(
		'isotope'         => 'isotope',
		'etww-blog-grid'  => 'blog-grid',
		'morphext'        => 'morphext',
		'etww-off-canvas' => 'off-canvas',
		'etww-search'     => 'search',
		'etww-tabs'       => 'tabs',
		'typed'           => 'typed',
		'etww-woo-slider' => 'woo-slider',
		'slick-q'         => 'slick',
	);

	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register' ) );
		add_action( 'elementor/controls/register', array( __CLASS__, 'controls' ) );

		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_scripts' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_styles' ) );
		add_action( 'elementor/frontend/after_enqueue_styles', array( __CLASS__, 'frontend_styles' ) );
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'preview_styles' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'editor_styles' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'editor_scripts' ) );

		// Select2 of the "etww-query-posts" control.
		add_action( 'wp_ajax_etww_get_posts_by_query', array( __CLASS__, 'ajax_posts_by_query' ) );
		add_action( 'wp_ajax_etww_get_posts_title_by_id', array( __CLASS__, 'ajax_posts_title_by_id' ) );

		if ( self::woocommerce() ) {
			// In the editor the WooCommerce frontend hooks are needed before the editor starts.
			add_action( 'admin_action_elementor', array( __CLASS__, 'wc_frontend' ), 9 );
			add_action( 'pre_get_posts', array( __CLASS__, 'fix_query_offset' ), 1 );
			add_filter( 'found_posts', array( __CLASS__, 'fix_query_found_posts' ), 1, 2 );
		}
	}

	private static function woocommerce() {
		return function_exists( 'etww_is_woocommerce_active' ) ? etww_is_woocommerce_active() : class_exists( 'WooCommerce' );
	}

	private static function suffix() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}

	public static function category( $elements_manager ) {
		$brand = function_exists( 'etww_theme_branding' ) ? etww_theme_branding() : 'ETWW';
		$elements_manager->add_category( 'etww-elements', array( 'title' => $brand . ' ' . esc_html__( 'Elements', 'envo-elementor-for-woocommerce' ) ), 1 );
	}

	public static function register( $widgets_manager ) {
		$widgets = self::WIDGETS;
		if ( self::woocommerce() ) {
			$widgets = array_merge( $widgets, self::WOO_WIDGETS );
		}
		foreach ( $widgets as $file => $class ) {
			require_once ETWW_PATH . 'includes/widgets/' . $file . '.php';
			$class = __NAMESPACE__ . '\\' . $class;
			$widgets_manager->register( new $class() );
		}
	}

	public static function controls( $controls_manager ) {
		$controls_manager->register( new Query_Control() );
	}

	public static function register_scripts() {
		foreach ( self::SCRIPTS as $handle => $file ) {
			wp_register_script( $handle, ETWW_URL . 'assets/js/' . $file . self::suffix() . '.js', array( 'jquery' ), ETWW_VERSION, true );
		}
	}

	public static function register_styles() {
		foreach ( self::STYLES as $name ) {
			wp_register_style( 'etww-' . $name, ETWW_URL . 'assets/css/' . $name . '/style' . self::suffix() . '.css', array(), ETWW_VERSION );
		}
	}

	/**
	 * Base styles for Elementor pages (buttons, pagination, Contact Form 7 labels), as in 1.x.
	 */
	public static function frontend_styles() {
		wp_enqueue_style( 'etww-frontend', ETWW_URL . 'assets/css/etww-frontend' . self::suffix() . '.css', array(), ETWW_VERSION );
		wp_register_style( 'font-awesome-5-all', ELEMENTOR_ASSETS_URL . 'lib/font-awesome/css/all.min.css', array(), ELEMENTOR_VERSION );
	}

	/**
	 * In the editor preview every widget can be added, so all styles are loaded.
	 */
	public static function preview_styles() {
		foreach ( self::STYLES as $name ) {
			wp_enqueue_style( 'etww-' . $name );
		}
		wp_enqueue_style( 'etww-elementor-preview', ETWW_URL . 'assets/css/elementor/preview' . self::suffix() . '.css', array(), ETWW_VERSION );
	}

	public static function editor_styles() {
		wp_enqueue_style( 'etww-elementor-editor', ETWW_URL . 'assets/css/elementor/editor' . self::suffix() . '.css', array(), ETWW_VERSION );
	}

	public static function editor_scripts() {
		wp_enqueue_script( 'preview-script-elmn', ETWW_URL . 'assets/js/elementor.js', array( 'elementor-editor' ), ETWW_VERSION, true );
	}

	public static function wc_frontend() {
		if ( function_exists( 'WC' ) ) {
			WC()->frontend_includes();
		}
	}

	/**
	 * The product widgets keep an offset over all pages of the pagination.
	 */
	public static function fix_query_offset( &$query ) {
		if ( ! empty( $query->query_vars['offset_to_fix'] ) ) {
			if ( $query->is_paged ) {
				$query->query_vars['offset'] = $query->query_vars['offset_to_fix'] + ( ( $query->query_vars['paged'] - 1 ) * $query->query_vars['posts_per_page'] );
			} else {
				$query->query_vars['offset'] = $query->query_vars['offset_to_fix'];
			}
		}
	}

	public static function fix_query_found_posts( $found_posts, $query ) {
		$offset_to_fix = $query->get( 'offset_to_fix' );
		if ( $offset_to_fix ) {
			$found_posts -= $offset_to_fix;
		}
		return $found_posts;
	}

	/**
	 * Post types the query control may list: public ones (never private or internal content).
	 *
	 * @return string[]
	 */
	private static function query_post_types() {
		return array_values( get_post_types( array( 'public' => true ) ) );
	}

	public static function ajax_posts_title_by_id() {
		if ( ! current_user_can( 'edit_posts' ) || ! check_ajax_referer( 'etww_query_posts', 'security', false ) ) {
			wp_send_json( array() );
		}
		$ids     = isset( $_POST['id'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['id'] ) ) : array();
		$results = array();
		if ( $ids ) {
			$query = new \WP_Query(
				array(
					'post_type'      => self::query_post_types(),
					'post_status'    => 'publish',
					'post__in'       => $ids,
					'posts_per_page' => -1,
				)
			);
			foreach ( $query->posts as $post ) {
				$results[ $post->ID ] = $post->post_title;
			}
		}
		wp_send_json( $results );
	}

	public static function ajax_posts_by_query() {
		if ( ! current_user_can( 'edit_posts' ) || ! check_ajax_referer( 'etww_query_posts', 'security', false ) ) {
			wp_send_json( array() );
		}
		$search    = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'all';
		if ( 'all' !== $post_type && ! in_array( $post_type, self::query_post_types(), true ) ) {
			wp_send_json( array() );
		}

		if ( 'all' === $post_type ) {
			$post_types          = get_post_types(
				array(
					'public'   => true,
					'_builtin' => false,
				),
				'names',
				'and'
			);
			$post_types['Posts'] = 'post';
			$post_types['Pages'] = 'page';
		} else {
			$post_types = array( $post_type => $post_type );
		}

		$result = array();
		add_filter( 'posts_search', array( __CLASS__, 'search_only_titles' ), 10, 2 );
		foreach ( $post_types as $label => $type ) {
			$data  = array();
			$query = new \WP_Query(
				array(
					's'              => $search,
					'post_type'      => $type,
					'post_status'    => 'publish',
					'posts_per_page' => 50,
				)
			);
			foreach ( $query->posts as $post ) {
				$title  = get_the_title( $post );
				$title .= $post->post_parent ? ' (' . get_the_title( $post->post_parent ) . ')' : '';
				$data[] = array(
					'id'   => $post->ID,
					'text' => $title,
				);
			}
			if ( $data ) {
				$result[] = array(
					'text'     => $label,
					'children' => $data,
				);
			}
		}
		remove_filter( 'posts_search', array( __CLASS__, 'search_only_titles' ), 10 );
		wp_send_json( $result );
	}

	public static function search_only_titles( $search, $wp_query ) {
		if ( ! empty( $search ) && ! empty( $wp_query->query_vars['search_terms'] ) ) {
			global $wpdb;
			$q      = $wp_query->query_vars;
			$n      = ! empty( $q['exact'] ) ? '' : '%';
			$search = array();
			foreach ( (array) $q['search_terms'] as $term ) {
				$search[] = $wpdb->prepare( "$wpdb->posts.post_title LIKE %s", $n . $wpdb->esc_like( $term ) . $n ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			if ( ! is_user_logged_in() ) {
				$search[] = "$wpdb->posts.post_password = ''";
			}
			$search = ' AND ' . implode( ' AND ', $search );
		}
		return $search;
	}
}
