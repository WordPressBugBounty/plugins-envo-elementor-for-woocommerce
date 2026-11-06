<?php
/**
 * Local copies of the preview images of the library (uploads/etww-previews of the main site), so that the library
 * page does not load images from envothemes.com again and again. The page shows the remote image the first time
 * and asks to keep a copy; the folder has a size limit and is cleaned weekly of images no longer in the list.
 *
 * @package ETWW
 */

namespace ETWW\Library;

defined( 'ABSPATH' ) || exit;

class Previews {

	const FOLDER      = 'etww-previews';
	const PER_REQUEST = 30;
	const MAX_FOLDER  = 52428800; // 50 MB.
	const MAX_IMAGE   = 1048576;  // 1 MB.
	const PRUNE       = 'etww_prune_previews';

	public static function init() {
		add_action( self::PRUNE, array( __CLASS__, 'prune' ) );
	}

	public static function schedule_prune() {
		if ( ! wp_next_scheduled( self::PRUNE ) ) {
			wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', self::PRUNE );
		}
	}

	/**
	 * @return array{dir:string,url:string} Empty strings when uploads are not writable.
	 */
	private static function location() {
		$switch = is_multisite() && ! is_main_site();
		if ( $switch ) {
			switch_to_blog( get_main_site_id() );
		}
		$uploads = wp_get_upload_dir();
		if ( $switch ) {
			restore_current_blog();
		}
		if ( ! empty( $uploads['error'] ) ) {
			return array(
				'dir' => '',
				'url' => '',
			);
		}
		return array(
			'dir' => trailingslashit( $uploads['basedir'] ) . self::FOLDER . '/',
			'url' => trailingslashit( $uploads['baseurl'] ) . self::FOLDER . '/',
		);
	}

	/**
	 * Image data for the library page: the local copy when there is one, otherwise the remote image.
	 *
	 * @param array|null $image Image of the list.
	 * @param string     $base  URL of the remote previews.
	 */
	public static function data( $image, $base ) {
		if ( ! $image ) {
			return null;
		}
		$location = self::location();
		$srcset   = array();
		$remote   = false;
		$src      = '';
		foreach ( $image['files'] as $width => $name ) {
			if ( '' !== $location['dir'] && is_file( $location['dir'] . $name ) ) {
				$url = $location['url'] . $name;
			} else {
				$url    = $base . $name;
				$remote = true;
			}
			$srcset[] = esc_url_raw( $url ) . ' ' . (int) $width . 'w';
			$src      = esc_url_raw( $url );
		}
		return array(
			'key'    => $image['key'],
			'src'    => $src,
			'srcset' => implode( ', ', $srcset ),
			'lqip'   => $image['lqip'],
			'ratio'  => $image['w'] && $image['h'] ? $image['w'] . ' / ' . $image['h'] : '',
			'remote' => $remote,
		);
	}

	private static function images( array $index ) {
		$images = array();
		foreach ( $index['kits'] as $kit ) {
			$list = array_merge( array( $kit['image'] ), wp_list_pluck( $kit['pages'], 'image' ) );
			foreach ( array_filter( $list ) as $image ) {
				$images[ $image['key'] ] = $image;
			}
		}
		foreach ( array_filter( wp_list_pluck( $index['pages'], 'image' ) ) as $image ) {
			$images[ $image['key'] ] = $image;
		}
		return $images;
	}

	private static function folder_size( $dir ) {
		$size = 0;
		foreach ( (array) glob( $dir . '*.webp' ) as $file ) {
			$size += (int) filesize( $file );
		}
		return $size;
	}

	/**
	 * Downloads the images with these keys (at most PER_REQUEST).
	 *
	 * @param string[] $keys Image keys.
	 * @return string[] Keys that are now stored locally.
	 */
	public static function keep( array $keys ) {
		$index    = Remote::index();
		$location = self::location();
		if ( is_wp_error( $index ) || '' === $location['dir'] || ! wp_mkdir_p( $location['dir'] ) || self::folder_size( $location['dir'] ) > self::MAX_FOLDER ) {
			return array();
		}
		$images = self::images( $index );
		$keys   = array_slice( array_unique( array_map( 'sanitize_key', $keys ) ), 0, self::PER_REQUEST );
		$kept   = array();
		foreach ( $keys as $key ) {
			if ( ! isset( $images[ $key ] ) ) {
				continue;
			}
			$ok = true;
			foreach ( $images[ $key ]['files'] as $name ) {
				if ( ! is_file( $location['dir'] . $name ) ) {
					$ok = self::download( $index['previews_url'] . $name, $location['dir'] . $name ) && $ok;
				}
			}
			if ( $ok ) {
				$kept[] = $key;
			}
		}
		self::schedule_prune();
		return $kept;
	}

	private static function download( $url, $target ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'ETWW/' . ETWW_VERSION,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$body = (string) wp_remote_retrieve_body( $response );
		// Only real WebP images.
		if ( strlen( $body ) < 12 || strlen( $body ) > self::MAX_IMAGE || 'RIFF' !== substr( $body, 0, 4 ) || 'WEBP' !== substr( $body, 8, 4 ) ) {
			return false;
		}
		return false !== file_put_contents( $target, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Weekly: removes images that are no longer in the library.
	 */
	public static function prune() {
		$location = self::location();
		$index    = Remote::index();
		if ( '' === $location['dir'] || ! is_dir( $location['dir'] ) || is_wp_error( $index ) ) {
			return;
		}
		$names = array();
		foreach ( self::images( $index ) as $image ) {
			$names = array_merge( $names, array_values( $image['files'] ) );
		}
		foreach ( (array) glob( $location['dir'] . '*.webp' ) as $file ) {
			if ( ! in_array( basename( $file ), $names, true ) ) {
				wp_delete_file( $file );
			}
		}
	}
}
