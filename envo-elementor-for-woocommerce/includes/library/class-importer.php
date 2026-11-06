<?php
/**
 * Import of one library page: as a template in the Elementor library, as a new page, or as content for the
 * open Elementor document (inserted by the editor, so Ctrl+Z works).
 *
 * @package ETWW
 */

namespace ETWW\Library;

use ETWW\Pro;

defined( 'ABSPATH' ) || exit;

class Importer {

	const META = '_etww_library_page';

	/**
	 * Page settings of the demo that must not be copied (they belong to the post, not to the design).
	 */
	const SKIP_SETTINGS = array( 'post_title', 'post_status', 'post_excerpt', 'post_featured_image', 'template', 'menu_order' );

	const TEMPLATES = array( 'default', 'elementor_canvas', 'elementor_header_footer' );

	/**
	 * Library entry, downloaded file and its content prepared by Elementor (new IDs, images in the media library).
	 *
	 * @return array|\WP_Error { entry, data, content, settings }
	 */
	private static function prepare( $page_id ) {
		$entry = Remote::find_page( $page_id );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}
		if ( $entry['pro'] && ! Pro::active() ) {
			return Pro::locked_error();
		}
		$needs = Plugins::missing( $entry['plugins'] );
		if ( $needs ) {
			return Plugins::missing_error( $needs );
		}
		$data = Remote::file( $entry['file'] );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$missing = self::missing_widgets( $data['content'] );
		if ( $missing ) {
			return new \WP_Error(
				'etww_widgets_missing',
				/* translators: %s: widget names */
				sprintf( __( 'This page uses widgets that are not available on this site: %s. Activate the plugin that adds them and import it again.', 'envo-elementor-for-woocommerce' ), implode( ', ', $missing ) ),
				array( 'status' => 409 )
			);
		}

		$source   = new Elementor_Source();
		$settings = isset( $data['page_settings'] ) && is_array( $data['page_settings'] ) ? $data['page_settings'] : array();
		foreach ( self::SKIP_SETTINGS as $key ) {
			unset( $settings[ $key ] );
		}
		$title = sanitize_text_field( isset( $data['title'] ) ? (string) $data['title'] : '' );
		return array(
			'entry'    => $entry,
			'title'    => '' !== $title ? $title : $entry['title'],
			'template' => isset( $data['template'] ) && in_array( $data['template'], self::TEMPLATES, true ) ? $data['template'] : 'default',
			'demo_url' => esc_url_raw( ! empty( $data['url'] ) ? (string) $data['url'] : $entry['preview'] ),
			'content'  => $source->prepare( $data['content'] ),
			'settings' => $settings,
		);
	}

	private static function widget_types( array $elements ) {
		$types = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( ! empty( $element['widgetType'] ) ) {
				$types[] = (string) $element['widgetType'];
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$types = array_merge( $types, self::widget_types( $element['elements'] ) );
			}
		}
		return array_values( array_unique( $types ) );
	}

	/**
	 * Widget types of the content that Elementor does not know on this site.
	 *
	 * @return string[]
	 */
	private static function missing_widgets( array $content ) {
		$manager = \Elementor\Plugin::$instance->widgets_manager;
		return array_values(
			array_filter(
				self::widget_types( $content ),
				static function ( $type ) use ( $manager ) {
					return ! $manager->get_widget_types( $type );
				}
			)
		);
	}

	/**
	 * Imports the page into the Elementor template library ("My Templates").
	 *
	 * @return array|\WP_Error
	 */
	public static function to_library( $page_id ) {
		$source = \Elementor\Plugin::$instance->templates_manager->get_source( 'local' );
		if ( ! $source ) {
			return new \WP_Error( 'etww_import', __( 'The Elementor template library is not available.', 'envo-elementor-for-woocommerce' ) );
		}
		$prepared = self::prepare( $page_id );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		$id = $source->save_item(
			array(
				'title'         => $prepared['title'],
				'type'          => 'page',
				'content'       => $prepared['content'],
				'page_settings' => $prepared['settings'],
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, self::META, absint( $page_id ) );
		return array(
			'id'       => (int) $id,
			'title'    => get_the_title( $id ),
			'edit_url' => \Elementor\Plugin::$instance->documents->get( $id )->get_edit_url(),
			'url'      => admin_url( 'edit.php?post_type=elementor_library&tabs_group=library' ),
		);
	}

	/**
	 * Imports the page as a new page or post of the site.
	 *
	 * @param int    $page_id   Library page.
	 * @param string $status    draft|publish.
	 * @param string $post_type page|post.
	 * @return array|\WP_Error
	 */
	public static function to_page( $page_id, $status = 'draft', $post_type = 'page' ) {
		$post_type = 'post' === $post_type ? 'post' : 'page';
		$type      = get_post_type_object( $post_type );
		if ( ! $type || ! current_user_can( $type->cap->create_posts ) ) {
			return new \WP_Error( 'etww_forbidden', __( 'You are not allowed to add this kind of content.', 'envo-elementor-for-woocommerce' ), array( 'status' => 403 ) );
		}
		$prepared = self::prepare( $page_id );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		$id = wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_title'  => $prepared['title'],
				'post_status' => 'publish' === $status && current_user_can( $type->cap->publish_posts ) ? 'publish' : 'draft',
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'post' === $post_type ? 'wp-post' : 'wp-page' );
		update_post_meta( $id, '_wp_page_template', $prepared['template'] );

		$document = \Elementor\Plugin::$instance->documents->get( $id, false );
		if ( ! $document ) {
			wp_delete_post( $id, true );
			return new \WP_Error( 'etww_import', __( 'Elementor could not open the new page.', 'envo-elementor-for-woocommerce' ) );
		}
		$document->save(
			array(
				'elements' => $prepared['content'],
				'settings' => $prepared['settings'],
			)
		);
		update_post_meta( $id, self::META, absint( $page_id ) );

		return array(
			'id'       => (int) $id,
			'title'    => get_the_title( $id ),
			'home'     => ! empty( $prepared['entry']['home'] ),
			'demo_url' => $prepared['demo_url'],
			'url'      => get_permalink( $id ),
			'edit_url' => $document->get_edit_url(),
		);
	}

	/**
	 * Content for the open Elementor document; the editor inserts it.
	 *
	 * @return array|\WP_Error
	 */
	public static function content( $page_id ) {
		$prepared = self::prepare( $page_id );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		return array(
			'title'   => $prepared['title'],
			'content' => array_values( $prepared['content'] ),
		);
	}
}
