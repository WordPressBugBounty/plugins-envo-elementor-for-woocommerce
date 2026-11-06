<?php
/**
 * The template library on envothemes.com: the published list of designs (kits) and pages, and the page files.
 *
 * The list is kept in a site option: loaded once a day, a manual refresh at most every 6 hours, after a failure
 * a new attempt after an hour, and the last good copy stays in use while the server cannot be reached. Every
 * downloaded file must match the sha256 in the list. No address of this site is sent – only the licence headers
 * of the PRO plugin for PRO files.
 *
 * @package ETWW
 */

namespace ETWW\Library;

use ETWW\Pro;

defined( 'ABSPATH' ) || exit;

class Remote {

	const OPTION   = 'etww_library';
	const SCHEMA   = 'etww-library/v1';
	const AUTO     = DAY_IN_SECONDS;
	const MANUAL   = 6 * HOUR_IN_SECONDS;
	const RETRY    = HOUR_IN_SECONDS;
	const MAX_FILE = 4194304; // 4 MB.

	public static function base() {
		return untrailingslashit( (string) apply_filters( 'etww/library_url', ETWW_LIBRARY_URL ) );
	}

	private static function get( $url, $timeout, array $headers = array() ) {
		return wp_safe_remote_get(
			$url,
			array(
				'timeout'    => $timeout,
				'user-agent' => 'ETWW/' . ETWW_VERSION,
				'headers'    => array_merge( array( 'Accept' => 'application/json' ), $headers ),
			)
		);
	}

	private static function state() {
		$state = get_site_option( self::OPTION );
		return array_merge(
			array(
				'index'  => null,
				'loaded' => 0,
				'tried'  => 0,
				'base'   => '',
				'news'   => array(),
			),
			is_array( $state ) ? $state : array()
		);
		// A copy from another library address (changed filter or constant) is not used.
		if ( $state['base'] !== self::base() ) {
			$state['index']  = null;
			$state['loaded'] = 0;
			$state['tried']  = 0;
			$state['news']   = array();
		}
		return $state;
	}

	/**
	 * The stored list and its state, without any request (for the menu count and the notice).
	 */
	public static function stored() {
		return self::state();
	}

	/**
	 * Time of the next allowed manual refresh, 0 when it is allowed now.
	 */
	public static function next_manual() {
		$next = (int) self::state()['loaded'] + self::MANUAL;
		return $next > time() ? $next : 0;
	}

	/**
	 * The library list.
	 *
	 * @param bool $manual Refresh requested by the user.
	 * @return array|\WP_Error
	 */
	public static function index( $manual = false ) {
		$state = self::state();
		if ( $manual && self::next_manual() ) {
			return new \WP_Error(
				'etww_library_wait',
				/* translators: %s: time */
				sprintf( __( 'The library was refreshed a short while ago. You can refresh it again at %s.', 'envo-elementor-for-woocommerce' ), wp_date( get_option( 'time_format' ), self::next_manual() ) ),
				array( 'status' => 429 )
			);
		}
		$stale     = time() - (int) $state['loaded'] > self::AUTO;
		$may_retry = time() - (int) $state['tried'] > self::RETRY;
		if ( $manual || ( $stale && $may_retry ) || ( ! $state['index'] && $may_retry ) ) {
			$loaded = self::load();
			if ( ! is_wp_error( $loaded ) || ! $state['index'] || $manual ) {
				return $loaded;
			}
		}
		return $state['index'] ? $state['index'] : self::unavailable();
	}

	private static function unavailable() {
		return new \WP_Error( 'etww_library', __( 'The template library is not available right now. Please try again later.', 'envo-elementor-for-woocommerce' ), array( 'status' => 503 ) );
	}

	private static function load() {
		$state          = self::state();
		$state['tried'] = time();
		$state['base']  = self::base();
		update_site_option( self::OPTION, $state );

		$response = self::get( self::base() . '/list', 15 );
		$data     = ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) ? json_decode( (string) wp_remote_retrieve_body( $response ), true ) : null;
		if ( ! is_array( $data ) || ! isset( $data['schema'] ) || self::SCHEMA !== $data['schema'] || empty( $data['hashes'] ) ) {
			return self::unavailable();
		}

		$index           = self::sanitize( $data );
		$state['news']   = News::track( $state['index'], $index, (array) $state['news'] );
		$state['index']  = $index;
		$state['loaded'] = time();
		update_site_option( self::OPTION, $state );
		Previews::schedule_prune();
		return $state['index'];
	}

	public static function status() {
		$state = self::state();
		return array(
			'loaded'      => $state['loaded'] ? gmdate( 'c', (int) $state['loaded'] ) : '',
			'next_manual' => self::next_manual() ? gmdate( 'c', self::next_manual() ) : '',
		);
	}

	private static function text( $item, $key ) {
		return sanitize_text_field( isset( $item[ $key ] ) ? (string) $item[ $key ] : '' );
	}

	private static function url( $item, $key ) {
		return esc_url_raw( isset( $item[ $key ] ) ? (string) $item[ $key ] : '' );
	}

	private static function file_path( $value ) {
		$value = (string) $value;
		return 1 === preg_match( '#^pages/[0-9]{1,10}\.json$#', $value ) ? $value : '';
	}

	private static function slugs( $item, $key ) {
		$list = isset( $item[ $key ] ) ? (array) $item[ $key ] : array();
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $list ) ) ) );
	}

	private static function image( $image ) {
		if ( ! is_array( $image ) || empty( $image['key'] ) || empty( $image['files'] ) ) {
			return null;
		}
		$files = array();
		foreach ( (array) $image['files'] as $width => $name ) {
			if ( 1 === preg_match( '/^[a-z0-9-]{1,60}\.[a-f0-9]{8}-[0-9]{2,4}\.webp$/', (string) $name ) ) {
				$files[ (int) $width ] = (string) $name;
			}
		}
		if ( ! $files ) {
			return null;
		}
		ksort( $files );
		$lqip = isset( $image['lqip'] ) ? (string) $image['lqip'] : '';
		return array(
			'key'   => sanitize_key( $image['key'] ),
			'w'     => absint( isset( $image['w'] ) ? $image['w'] : 0 ),
			'h'     => absint( isset( $image['h'] ) ? $image['h'] : 0 ),
			'lqip'  => 0 === strpos( $lqip, 'data:image/webp;base64,' ) && strlen( $lqip ) < 4000 ? $lqip : '',
			'files' => $files,
		);
	}

	private static function page( $page ) {
		$file = self::file_path( isset( $page['file'] ) ? $page['file'] : '' );
		if ( empty( $page['id'] ) || ! $file ) {
			return null;
		}
		return array(
			'id'      => absint( $page['id'] ),
			'title'   => self::text( $page, 'title' ),
			'slug'    => sanitize_title( isset( $page['slug'] ) ? $page['slug'] : '' ),
			'preview' => self::url( $page, 'preview' ),
			'file'    => $file,
			'image'   => self::image( isset( $page['image'] ) ? $page['image'] : null ),
			'widgets' => self::slugs( $page, 'widgets' ),
			'plugins' => self::slugs( $page, 'plugins' ),
		);
	}

	private static function meta( $item ) {
		return array(
			'description' => self::text( $item, 'description' ),
			'category'    => self::text( $item, 'category' ),
			'tags'        => array_values( array_filter( array_map( 'sanitize_text_field', isset( $item['tags'] ) ? (array) $item['tags'] : array() ) ) ),
			'pro'         => ! empty( $item['pro'] ),
			'added'       => self::text( $item, 'added' ),
		);
	}

	private static function sanitize( array $data ) {
		$kits = array();
		foreach ( isset( $data['kits'] ) ? (array) $data['kits'] : array() as $kit ) {
			$pages = array();
			foreach ( isset( $kit['pages'] ) ? (array) $kit['pages'] : array() as $page ) {
				$clean = self::page( $page );
				if ( $clean ) {
					$clean['home'] = ! empty( $page['home'] );
					$pages[]       = $clean;
				}
			}
			if ( empty( $kit['id'] ) || ! $pages ) {
				continue;
			}
			$kits[] = array_merge(
				array(
					'id'      => absint( $kit['id'] ),
					'title'   => self::text( $kit, 'title' ),
					'preview' => self::url( $kit, 'preview' ),
					'image'   => self::image( isset( $kit['image'] ) ? $kit['image'] : null ),
					'plugins' => self::slugs( $kit, 'plugins' ),
					'pages'   => $pages,
				),
				self::meta( $kit )
			);
		}

		$pages = array();
		foreach ( isset( $data['pages'] ) ? (array) $data['pages'] : array() as $page ) {
			$clean = self::page( $page );
			if ( $clean ) {
				$pages[] = array_merge( $clean, self::meta( $page ) );
			}
		}

		$hashes = array();
		foreach ( (array) $data['hashes'] as $rel => $hash ) {
			if ( self::file_path( $rel ) && 1 === preg_match( '/^[a-f0-9]{64}$/', (string) $hash ) ) {
				$hashes[ $rel ] = (string) $hash;
			}
		}

		return array(
			'version'      => self::text( $data, 'version' ),
			'published'    => self::text( $data, 'published' ),
			'home'         => self::url( $data, 'home' ),
			'previews_url' => trailingslashit( self::url( $data, 'previews_url' ) ),
			'kits'         => $kits,
			'pages'        => $pages,
			'hashes'       => $hashes,
		);
	}

	/**
	 * A page of the library by its id, from a kit or the standalone pages. A page is PRO only when no free kit or
	 * free standalone entry contains it (the server decides the same way).
	 *
	 * @return array|\WP_Error Page entry with "pro" and "kit" (id of the first kit with it, 0 for none).
	 */
	public static function find_page( $id ) {
		$index = self::index();
		if ( is_wp_error( $index ) ) {
			return $index;
		}
		$id    = absint( $id );
		$found = null;
		$free  = false;
		foreach ( $index['kits'] as $kit ) {
			foreach ( $kit['pages'] as $page ) {
				if ( $page['id'] === $id ) {
					if ( ! $found ) {
						$found        = $page;
						$found['kit'] = $kit['id'];
					}
					$free = $free || ! $kit['pro'];
				}
			}
		}
		foreach ( $index['pages'] as $page ) {
			if ( $page['id'] === $id ) {
				if ( ! $found ) {
					$found        = $page;
					$found['kit'] = 0;
				}
				$free = $free || ! $page['pro'];
			}
		}
		if ( ! $found ) {
			return new \WP_Error( 'etww_library_missing', __( 'This page is not in the library any more. Refresh the library.', 'envo-elementor-for-woocommerce' ), array( 'status' => 404 ) );
		}
		$found['pro'] = ! $free;
		return $found;
	}

	/**
	 * Content of a page file.
	 *
	 * @param string $rel Path from the list ("pages/<id>.json").
	 * @return array|\WP_Error
	 */
	public static function file( $rel ) {
		$rel = self::file_path( $rel );
		if ( ! $rel ) {
			return new \WP_Error( 'etww_library', __( 'Unknown library file.', 'envo-elementor-for-woocommerce' ) );
		}
		$index = self::index();
		$hash  = is_array( $index ) && isset( $index['hashes'][ $rel ] ) ? $index['hashes'][ $rel ] : '';
		if ( '' === $hash ) {
			return new \WP_Error( 'etww_library_missing', __( 'This page is not in the library any more. Refresh the library.', 'envo-elementor-for-woocommerce' ), array( 'status' => 404 ) );
		}
		// The checksum is part of the key: a page published again is never served from an old copy.
		$cache = 'etww_library_' . md5( $rel . $hash );
		$body  = get_site_transient( $cache );

		if ( ! is_string( $body ) || '' === $body ) {
			/**
			 * Headers for the file request. The PRO plugin adds the licence key and domain here.
			 *
			 * @param array  $headers Headers.
			 * @param string $rel     File path.
			 */
			$headers  = (array) apply_filters( 'etww/library/headers', array(), $rel );
			$response = self::get( add_query_arg( 'path', rawurlencode( $rel ), self::base() . '/file' ), 30, $headers );
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( 401 === $code || 403 === $code ) {
				if ( ! Pro::active() ) {
					return Pro::locked_error();
				}
				return new \WP_Error(
					'etww_library_license',
					401 === $code
						? __( 'The PRO plugin did not send a licence key. Enter your licence key in the PRO plugin and try again.', 'envo-elementor-for-woocommerce' )
						: __( 'The library did not accept your PRO licence (it is not active for this site or it has expired). Check the licence in the PRO plugin and try again.', 'envo-elementor-for-woocommerce' ),
					array( 'status' => 403 )
				);
			}
			if ( 200 !== $code ) {
				return new \WP_Error( 'etww_library', __( 'The page could not be downloaded from envothemes.com. Please try again later.', 'envo-elementor-for-woocommerce' ), array( 'status' => 502 ) );
			}
			$body = (string) wp_remote_retrieve_body( $response );
			if ( strlen( $body ) > self::MAX_FILE ) {
				return new \WP_Error( 'etww_library', __( 'The downloaded file is too large.', 'envo-elementor-for-woocommerce' ), array( 'status' => 502 ) );
			}
		}

		if ( ! hash_equals( $hash, hash( 'sha256', $body ) ) ) {
			delete_site_transient( $cache );
			return new \WP_Error( 'etww_library_checksum', __( 'The downloaded page does not match the library list (the library was probably updated). Refresh the library and try again.', 'envo-elementor-for-woocommerce' ), array( 'status' => 502 ) );
		}
		// PRO files are not kept: the licence is checked again for the next import.
		$pro = self::find_page( (int) basename( $rel, '.json' ) );
		if ( ! is_wp_error( $pro ) && ! $pro['pro'] ) {
			set_site_transient( $cache, $body, HOUR_IN_SECONDS );
		}

		$data = json_decode( $body, true, 256 );
		if ( ! is_array( $data ) || ! isset( $data['content'] ) || ! is_array( $data['content'] ) ) {
			return new \WP_Error( 'etww_library', __( 'This page cannot be imported.', 'envo-elementor-for-woocommerce' ) );
		}
		return $data;
	}
}
