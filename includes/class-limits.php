<?php
/**
 * Database-backed rate limiting.
 *
 * Object-cache/transient based limiting is unreliable on shared hosting
 * (transients are stored in the same database anyway), so a dedicated table
 * with an expiry column is used instead.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Limits
 */
class HTECH_AI_Limits {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'htech_rate_limits';
	}

	/**
	 * Create the rate-limit table.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				bucket VARCHAR(120) NOT NULL DEFAULT '',
				hits INT UNSIGNED NOT NULL DEFAULT 0,
				window_start DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
				expires_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY bucket (bucket),
				KEY expires_at (expires_at)
			) {$charset};"
		);
	}

	/**
	 * Consume one token from a bucket.
	 *
	 * @param string $action  Action name (chat, lead, upload...).
	 * @param int    $limit   Max hits per window.
	 * @param int    $window  Window length in seconds.
	 * @return array{allowed:bool,remaining:int,retry_after:int}
	 */
	public static function consume( $action, $limit, $window = 60 ) {
		global $wpdb;

		$limit  = max( 1, (int) $limit );
		$window = max( 10, (int) $window );
		$bucket = substr( $action . ':' . HTECH_AI_Security::ip_hash(), 0, 120 );
		$now    = time();
		$table  = self::table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE bucket = %s", $bucket ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$now_sql = current_time( 'mysql', true );

		if ( ! $row ) {
			$wpdb->insert(
				$table,
				array(
					'bucket'       => $bucket,
					'hits'         => 1,
					'window_start' => $now_sql,
					'expires_at'   => gmdate( 'Y-m-d H:i:s', $now + $window ),
				)
			);
			return array(
				'allowed'     => true,
				'remaining'   => $limit - 1,
				'retry_after' => 0,
			);
		}

		$expires_ts = strtotime( $row['expires_at'] . ' UTC' );

		if ( ! $expires_ts || $expires_ts <= $now ) {
			$wpdb->update(
				$table,
				array(
					'hits'         => 1,
					'window_start' => $now_sql,
					'expires_at'   => gmdate( 'Y-m-d H:i:s', $now + $window ),
				),
				array( 'bucket' => $bucket )
			);
			return array(
				'allowed'     => true,
				'remaining'   => $limit - 1,
				'retry_after' => 0,
			);
		}

		if ( (int) $row['hits'] >= $limit ) {
			return array(
				'allowed'     => false,
				'remaining'   => 0,
				'retry_after' => max( 1, $expires_ts - $now ),
			);
		}

		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "UPDATE {$table} SET hits = hits + 1 WHERE bucket = %s", $bucket )
		);

		return array(
			'allowed'     => true,
			'remaining'   => max( 0, $limit - (int) $row['hits'] - 1 ),
			'retry_after' => 0,
		);
	}

	/**
	 * Clear all buckets (used by admin tools).
	 *
	 * @return void
	 */
	public static function flush() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
