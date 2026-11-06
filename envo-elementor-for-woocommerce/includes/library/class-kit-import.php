<?php
/**
 * Import of a whole design (kit). The library page imports the pages one by one (one request per page, so nothing
 * runs into a time limit) and then calls finish(): links between the demo pages point to the new pages, the home
 * page can become the front page and a menu of the new pages can be created (it is not assigned to a theme location).
 *
 * @package ETWW
 */

namespace ETWW\Library;

defined( 'ABSPATH' ) || exit;

class Kit_Import {

	/**
	 * @param array $pages   Results of the page imports (pages or posts): { id, demo_url, home }.
	 * @param array $options { front: bool, menu: bool, menu_name: string }.
	 * @return array|\WP_Error { front, menu, menu_url, updated }
	 */
	public static function finish( array $pages, array $options ) {
		$list = array();
		foreach ( $pages as $page ) {
			$id = isset( $page['id'] ) ? absint( $page['id'] ) : 0;
			if ( $id && in_array( get_post_type( $id ), array( 'page', 'post' ), true ) && get_post_meta( $id, Importer::META, true ) && current_user_can( 'edit_post', $id ) ) {
				$list[] = array(
					'id'       => $id,
					'demo_url' => isset( $page['demo_url'] ) ? esc_url_raw( (string) $page['demo_url'] ) : '',
					'home'     => ! empty( $page['home'] ),
				);
			}
		}
		if ( ! $list ) {
			return new \WP_Error( 'etww_import', __( 'No imported pages to finish.', 'envo-elementor-for-woocommerce' ), array( 'status' => 400 ) );
		}
		$result = array(
			'front'    => 0,
			'home'     => '',
			'menu'     => 0,
			'menu_url' => '',
			'updated'  => 0,
		);
		$home   = null;
		foreach ( $list as $page ) {
			if ( $page['home'] ) {
				$home = $page;
				break;
			}
		}

		// The home page of the design as the front page of the site.
		if ( ! empty( $options['front'] ) && $home && 'page' === get_post_type( $home['id'] ) && current_user_can( 'manage_options' ) ) {
			if ( 'publish' !== get_post_status( $home['id'] ) && current_user_can( 'publish_post', $home['id'] ) ) {
				wp_update_post(
					array(
						'ID'          => $home['id'],
						'post_status' => 'publish',
					)
				);
			}
			if ( 'publish' === get_post_status( $home['id'] ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $home['id'] );
				$result['front'] = $home['id'];
				$result['home']  = home_url( '/' );
			}
		}

		// Demo URL → URL of the new page.
		$map = array();
		foreach ( $list as $page ) {
			if ( $page['demo_url'] ) {
				$map[ untrailingslashit( $page['demo_url'] ) ] = $result['front'] === $page['id'] ? home_url( '/' ) : get_permalink( $page['id'] );
			}
		}
		// Links to the home of the demo site go to the new home page.
		$index = Remote::index();
		if ( $home && ! is_wp_error( $index ) && $index['home'] ) {
			$demo_home = untrailingslashit( $index['home'] );
			if ( ! isset( $map[ $demo_home ] ) ) {
				$map[ $demo_home ] = $result['front'] ? home_url( '/' ) : get_permalink( $home['id'] );
			}
		}
		// Longest first, so that a shorter URL never takes the start of a longer one.
		uksort(
			$map,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		if ( $map ) {
			foreach ( $list as $page ) {
				if ( self::relink( $page['id'], $map ) ) {
					++$result['updated'];
				}
			}
		}

		// Menu of the new pages.
		if ( ! empty( $options['menu'] ) && current_user_can( 'edit_theme_options' ) ) {
			$name = sanitize_text_field( isset( $options['menu_name'] ) ? (string) $options['menu_name'] : '' );
			$name = '' !== $name ? $name : __( 'Imported design', 'envo-elementor-for-woocommerce' );
			if ( wp_get_nav_menu_object( $name ) ) {
				$name .= ' ' . wp_date( 'Y-m-d H:i' );
			}
			$menu_id = wp_create_nav_menu( $name );
			if ( ! is_wp_error( $menu_id ) ) {
				foreach ( $list as $page ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-object-id' => $page['id'],
							'menu-item-object'    => get_post_type( $page['id'] ),
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
						)
					);
				}
				$result['menu']     = (int) $menu_id;
				$result['menu_url'] = admin_url( 'nav-menus.php?action=edit&menu=' . (int) $menu_id );
			}
		}
		return $result;
	}

	/**
	 * Replaces the demo URLs in the settings of all elements of a page.
	 *
	 * @return bool Changed.
	 */
	private static function relink( $post_id, array $map ) {
		$document = \Elementor\Plugin::$instance->documents->get( $post_id, false );
		if ( ! $document ) {
			return false;
		}
		$changed  = false;
		$elements = self::walk( $document->get_elements_data(), $map, $changed );
		if ( $changed ) {
			$document->save( array( 'elements' => $elements ) );
		}
		return $changed;
	}

	private static function walk( array $elements, array $map, &$changed ) {
		foreach ( $elements as $i => $element ) {
			if ( ! empty( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$element['settings'] = self::replace( $element['settings'], $map, $changed );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::walk( $element['elements'], $map, $changed );
			}
			$elements[ $i ] = $element;
		}
		return $elements;
	}

	/**
	 * Only whole URLs are replaced: the demo URL with or without the closing slash, followed by the end of the text,
	 * a quote, "#", "?", whitespace or "<" – never the start of a longer URL (e.g. a product of the demo shop).
	 *
	 * @param mixed $value Setting value.
	 */
	private static function replace( $value, array $map, &$changed ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::replace( $item, $map, $changed );
			}
			return $value;
		}
		if ( ! is_string( $value ) || false === strpos( $value, '//' ) ) {
			return $value;
		}
		$new = $value;
		foreach ( $map as $from => $to ) {
			$pattern = '#' . preg_quote( $from, '#' ) . '/?(?=$|["\'\#?\s<])#';
			$new     = preg_replace_callback(
				$pattern,
				static function () use ( $to ) {
					return $to;
				},
				$new
			);
		}
		if ( $new !== $value ) {
			$changed = true;
		}
		return $new;
	}
}
