<?php
/**
 * The "Envo Templates" admin page (the library), links on the plugins screen and notices.
 *
 * @package ETWW
 */

namespace ETWW\Admin;

use ETWW\Library\News;
use ETWW\Pro;

defined( 'ABSPATH' ) || exit;

class Admin {

	const SLUG = 'etww-library';

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'scripts' ) );
		add_action( 'admin_init', array( __CLASS__, 'activation_redirect' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice_pro' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice_new' ) );
		add_filter( 'plugin_action_links_' . ETWW_PLUGIN_BASE, array( __CLASS__, 'action_links' ) );
	}

	public static function url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	public static function menu() {
		// The 1.x library was under WooCommerce › Templates Library.
		if ( isset( $_GET['page'] ) && 'etww_templates' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( self::url() );
			exit;
		}
		// Count of the new designs and pages, like the count of plugin updates.
		$new   = current_user_can( 'edit_pages' ) ? count( News::unseen() ) : 0;
		$title = __( 'Envo Templates', 'envo-elementor-for-woocommerce' );
		if ( $new ) {
			$title .= ' <span class="update-plugins count-' . $new . '"><span class="plugin-count">' . number_format_i18n( $new ) . '</span></span>';
		}
		self::$hook = add_menu_page(
			__( 'Envo Templates', 'envo-elementor-for-woocommerce' ),
			$title,
			'edit_pages',
			self::SLUG,
			array( __CLASS__, 'render' ),
			'data:image/svg+xml;base64,' . base64_encode( self::icon( '#a7aaad' ) ), // phpcs:ignore WordPress.PHP.DiscouragedFunctions.obfuscation_base64_encode
			58.7
		);
	}

	/**
	 * Logo: a stack of page layouts.
	 */
	public static function icon( $color = 'currentColor' ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="' . $color . '"><path d="M3 2h14a1 1 0 0 1 1 1v4H2V3a1 1 0 0 1 1-1Zm-1 7h7v9H3a1 1 0 0 1-1-1V9Zm9 0h7v3h-7V9Zm0 5h7v3a1 1 0 0 1-1 1h-6v-4Z"/></svg>';
	}

	public static function render() {
		echo '<div class="wrap etww-wrap"><h1 class="screen-reader-text">' . esc_html__( 'Envo Templates', 'envo-elementor-for-woocommerce' ) . '</h1><div id="etww-library" class="etww-lib"><p class="etww-lib__loading">' . esc_html__( 'Loading the library…', 'envo-elementor-for-woocommerce' ) . '</p></div></div>';
	}

	public static function scripts( $hook ) {
		if ( ! self::$hook || $hook !== self::$hook ) {
			return;
		}
		wp_enqueue_style( 'etww-library', ETWW_URL . 'assets/admin/library.css', array(), \ETWW\Plugin::asset_version( 'assets/admin/library.css' ) );
		wp_enqueue_script( 'etww-library', ETWW_URL . 'assets/admin/library.js', array( 'wp-api-fetch', 'wp-i18n' ), \ETWW\Plugin::asset_version( 'assets/admin/library.js' ), true );
		wp_set_script_translations( 'etww-library', 'envo-elementor-for-woocommerce', ETWW_PATH . 'languages' );
		wp_add_inline_script(
			'etww-library',
			'window.etwwLibrary = ' . wp_json_encode(
				array(
					'logo'       => self::icon(),
					'elementor'  => admin_url( 'edit.php?post_type=elementor_library&tabs_group=library' ),
					'pages'      => admin_url( 'edit.php?post_type=page' ),
					'posts'      => admin_url( 'edit.php' ),
					'siteName'   => get_bloginfo( 'name' ),
					'front'      => (int) get_option( 'page_on_front' ),
					'learnMore'  => 'https://envothemes.com/elementor-templates-for-woocommerce/',
				)
			) . ';',
			'before'
		);
	}

	/**
	 * After activation (not bulk) the library opens once.
	 */
	public static function activation_redirect() {
		if ( ! get_option( 'etww_do_activation_redirect' ) ) {
			return;
		}
		delete_option( 'etww_do_activation_redirect' );
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) || ! current_user_can( 'edit_pages' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Templates', 'envo-elementor-for-woocommerce' ) . '</a>' );
		if ( ! Pro::installed() ) {
			$links['etww_pro'] = '<a href="' . esc_url( Pro::buy_url() ) . '" target="_blank" rel="noopener" style="color:#39b54a;font-weight:bold;">' . esc_html__( 'Get PRO', 'envo-elementor-for-woocommerce' ) . '</a>';
		}
		return $links;
	}

	/**
	 * New designs in the library, on the dashboard, the plugins and themes screens and the lists of pages and
	 * Elementor templates. Dismissing it marks them seen; opening the library does the same.
	 */
	public static function notice_new() {
		$screen = get_current_screen();
		if ( ! $screen || is_network_admin() || ! current_user_can( 'edit_pages' ) || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'themes', 'edit-page', 'edit-elementor_library' ), true ) ) {
			return;
		}
		$news = News::unseen();
		if ( ! $news ) {
			return;
		}
		// Previews of the first new items; each opens its detail in the library.
		$cards = '';
		$shown = News::preview( 5 );
		foreach ( $shown as $key => $item ) {
			$meta = $item['pages']
				/* translators: %d: number of pages */
				? sprintf( _n( '%d page', '%d pages', $item['pages'], 'envo-elementor-for-woocommerce' ), $item['pages'] )
				: __( 'Single page', 'envo-elementor-for-woocommerce' );
			$img  = $item['image']
				? '<img src="' . esc_url( $item['image']['src'] ) . '" srcset="' . esc_attr( $item['image']['srcset'] ) . '" sizes="140px" alt="" loading="lazy"' . ( $item['image']['lqip'] ? ' style="background-image:url(' . esc_attr( $item['image']['lqip'] ) . ')"' : '' ) . '>'
				: '';
			$cards .= sprintf(
				'<a class="etww-news__item" href="%1$s"><span class="etww-news__shot">%2$s<span class="etww-news__badge%3$s">%4$s</span></span><strong>%5$s</strong><span>%6$s</span></a>',
				esc_url( self::url() . '#' . $key ),
				$img,
				$item['pro'] ? ' is-pro' : '',
				esc_html( $item['pro'] ? __( 'PRO', 'envo-elementor-for-woocommerce' ) : __( 'Free', 'envo-elementor-for-woocommerce' ) ),
				esc_html( $item['title'] ),
				esc_html( $meta )
			);
		}
		$more = count( $news ) - count( $shown );
		if ( $more ) {
			$cards .= '<a class="etww-news__item etww-news__more" href="' . esc_url( self::url() ) . '"><span class="etww-news__shot">+' . esc_html( number_format_i18n( $more ) ) . '</span></a>';
		}
		printf(
			'<div class="notice notice-info is-dismissible etww-news" data-keys="%1$s"><p><strong>%2$s</strong> %3$s</p><div class="etww-news__list">%4$s</div><p><a href="%5$s" class="button button-primary">%6$s</a></p></div>',
			esc_attr( wp_json_encode( array_keys( $news ) ) ),
			esc_html(
				/* translators: %d: number of new templates */
				sprintf( _n( '%d new template in Envo Templates', '%d new templates in Envo Templates', count( $news ), 'envo-elementor-for-woocommerce' ), count( $news ) )
			),
			esc_html__( '– ready to import with a few clicks.', 'envo-elementor-for-woocommerce' ),
			$cards, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			esc_url( self::url() ),
			esc_html__( 'View the templates', 'envo-elementor-for-woocommerce' )
		);
		echo '<style>' .
			'.etww-news__list{display:flex;flex-wrap:wrap;gap:12px;margin:4px 0 8px}' .
			'.etww-news a.etww-news__item{display:flex;flex-direction:column;gap:2px;width:140px;color:#1d2327;text-decoration:none}' .
			'.etww-news__item:hover strong,.etww-news__item:focus strong{color:#2271b1}' .
			'.etww-news__shot{position:relative;display:flex;align-items:center;justify-content:center;height:168px;margin-bottom:4px;overflow:hidden;border:1px solid #dcdcde;border-radius:6px;background:#f6f7f7;font-size:20px;font-weight:600;color:#50575e}' .
			'.etww-news__shot img{width:100%;height:100%;object-fit:cover;object-position:top center;background-size:cover;transition:object-position 2s ease}' .
			'.etww-news__item:hover .etww-news__shot img{object-position:bottom center}' .
			'.etww-news__item > span:last-child{color:#646970;font-size:12px}' .
			'.etww-news__badge{position:absolute;top:6px;right:6px;padding:2px 6px;border-radius:999px;background:#e9f7eb;color:#2a8a37;font-size:10px;font-weight:700;text-transform:uppercase}' .
			'.etww-news__badge.is-pro{background:#f68e13;color:#fff}' .
			'.etww-news__more .etww-news__shot{border-style:dashed}' .
			'</style>';
		// Dismissing (the X button of WordPress) marks the templates seen.
		wp_print_inline_script_tag(
			'document.addEventListener("click",function(e){var n=e.target.closest(".etww-news .notice-dismiss");if(!n){return;}' .
			'fetch(' . wp_json_encode( esc_url_raw( rest_url( 'etww/v1/seen' ) ) ) . ',{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":' . wp_json_encode( wp_create_nonce( 'wp_rest' ) ) . '},' .
			'body:JSON.stringify({keys:JSON.parse(n.closest(".etww-news").dataset.keys)})}).then(function(){var c=document.querySelector("#toplevel_page_' . self::SLUG . ' .update-plugins");if(c){c.remove();}});});'
		);
	}

	/**
	 * PRO 1.x cannot unlock the new library.
	 */
	public static function notice_pro() {
		if ( ! Pro::outdated() || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%1$s</p><p><a href="%2$s" class="button">%3$s</a> <a href="%4$s" target="_blank" rel="noopener">%5$s</a></p></div>',
			wp_kses(
				__( '<strong>Elementor Templates for WooCommerce PRO</strong> needs an update to version 2.0 to unlock the PRO designs of the new template library.', 'envo-elementor-for-woocommerce' ),
				array( 'strong' => array() )
			),
			esc_url( admin_url( 'update-core.php?force-check=1' ) ),
			esc_html__( 'Check for updates', 'envo-elementor-for-woocommerce' ),
			esc_url( 'https://envothemes.com/how-to-update-plugin/' ),
			esc_html__( 'How to update?', 'envo-elementor-for-woocommerce' )
		);
	}
}
