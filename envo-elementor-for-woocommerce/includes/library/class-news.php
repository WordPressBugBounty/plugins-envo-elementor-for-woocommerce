<?php
/**
 * New designs and pages in the library: what a refreshed list adds compared to the previous one stays "new" for
 * 30 days, until the user opens the library or dismisses the notice. The list is checked once a day in the
 * background, so the notice appears even when nobody opens the library.
 *
 * The first list a site loads has nothing new. Which items a user has seen is kept in user meta.
 *
 * @package ETWW
 */

namespace ETWW\Library;

defined( 'ABSPATH' ) || exit;

class News {

	const KEEP  = 30 * DAY_IN_SECONDS;
	const META  = 'etww_library_seen';
	const CHECK = 'etww_library_check';

	public static function init() {
		add_action( self::CHECK, array( __CLASS__, 'check' ) );
		/**
		 * Whether the library list is checked for new designs once a day in the background.
		 *
		 * @param bool $check Check.
		 */
		if ( apply_filters( 'etww/library/check_new', true ) ) {
			if ( ! wp_next_scheduled( self::CHECK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CHECK );
			}
		} elseif ( wp_next_scheduled( self::CHECK ) ) {
			wp_clear_scheduled_hook( self::CHECK );
		}
	}

	/**
	 * Cron: loads the list when the copy is older than a day (Remote::index decides).
	 */
	public static function check() {
		Remote::index();
	}

	/**
	 * Items of a list: "kit:<id>" / "page:<id>" => title.
	 */
	private static function keys( $index ) {
		$keys = array();
		if ( ! is_array( $index ) ) {
			return $keys;
		}
		foreach ( $index['kits'] as $kit ) {
			$keys[ 'kit:' . $kit['id'] ] = $kit['title'];
		}
		foreach ( $index['pages'] as $page ) {
			$keys[ 'page:' . $page['id'] ] = $page['title'];
		}
		return $keys;
	}

	/**
	 * The new items after a refresh of the list.
	 *
	 * @param array|null $old   Previous list (null: the first one, nothing is new).
	 * @param array      $index New list.
	 * @param array      $news  Earlier new items: key => time.
	 * @return array key => time
	 */
	public static function track( $old, array $index, array $news ) {
		if ( ! is_array( $old ) ) {
			return array();
		}
		$now  = time();
		$keys = self::keys( $index );
		$was  = self::keys( $old );
		foreach ( $news as $key => $time ) {
			if ( ! isset( $keys[ $key ] ) || $now - (int) $time > self::KEEP ) {
				unset( $news[ $key ] );
			}
		}
		foreach ( $keys as $key => $title ) {
			if ( ! isset( $was[ $key ] ) && ! isset( $news[ $key ] ) ) {
				$news[ $key ] = $now;
			}
		}
		return $news;
	}

	/**
	 * New items the user has not seen, newest first. Only the stored list is read (no request).
	 *
	 * @return array key => title
	 */
	public static function unseen( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		$state   = Remote::stored();
		if ( ! $user_id || empty( $state['news'] ) ) {
			return array();
		}
		$keys = self::keys( $state['index'] );
		$seen = (array) get_user_meta( $user_id, self::META, true );
		$news = $state['news'];
		arsort( $news );
		$list = array();
		foreach ( $news as $key => $time ) {
			if ( isset( $keys[ $key ] ) && time() - (int) $time <= self::KEEP && ! in_array( $key, $seen, true ) ) {
				$list[ $key ] = $keys[ $key ];
			}
		}
		return $list;
	}

	/**
	 * The first new items for the notice: title, PRO, number of pages of a design and the preview image.
	 *
	 * @return array key => { title, pro, pages, image }
	 */
	public static function preview( $limit, $user_id = 0 ) {
		$state = Remote::stored();
		$items = array();
		if ( is_array( $state['index'] ) ) {
			foreach ( $state['index']['kits'] as $kit ) {
				$items[ 'kit:' . $kit['id'] ] = $kit;
			}
			foreach ( $state['index']['pages'] as $page ) {
				$items[ 'page:' . $page['id'] ] = $page;
			}
		}
		$list = array();
		foreach ( array_slice( array_keys( self::unseen( $user_id ) ), 0, $limit ) as $key ) {
			$item         = $items[ $key ];
			$list[ $key ] = array(
				'title' => $item['title'],
				'pro'   => $item['pro'],
				'pages' => isset( $item['pages'] ) ? count( $item['pages'] ) : 0,
				'image' => Previews::data( $item['image'], $state['index']['previews_url'] ),
			);
		}
		return $list;
	}

	/**
	 * Marks the new items as seen by the user. Only keys that are still new are kept.
	 */
	public static function seen( array $keys, $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		$state   = Remote::stored();
		$news    = isset( $state['news'] ) ? (array) $state['news'] : array();
		$seen    = array_merge( (array) get_user_meta( $user_id, self::META, true ), array_map( 'strval', $keys ) );
		$seen    = array_values( array_unique( array_intersect( $seen, array_keys( $news ) ) ) );
		if ( $seen ) {
			update_user_meta( $user_id, self::META, $seen );
		} else {
			delete_user_meta( $user_id, self::META );
		}
		return count( self::unseen( $user_id ) );
	}
}
