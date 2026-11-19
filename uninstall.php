<?php
/**
 * Uninstall routine.
 *
 * Data is only removed when the administrator explicitly enabled
 * "Delete all data when the plugin is uninstalled" in General Settings.
 * Ordinary deactivation, updates and re-installs never touch business data.
 *
 * @package HTECH_AI_Assistant
 */

// Only run in the uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! current_user_can( 'activate_plugins' ) ) {
	exit;
}

$settings = get_option( 'htech_ai_settings', array() );
$delete   = is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] );

if ( ! $delete ) {
	// Respect the administrator's choice: leave everything in place.
	return;
}

global $wpdb;

/* 1. Remove uploaded documents. */
$uploads = wp_upload_dir( null, false );
if ( empty( $uploads['error'] ) ) {
	$dir = trailingslashit( $uploads['basedir'] ) . 'htech-ai-assistant';

	if ( is_dir( $dir ) ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			// Never follow symlinks out of the folder.
			if ( $item->isLink() ) {
				continue;
			}
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			} else {
				@unlink( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}

		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}

/* 2. Drop plugin tables. */
$tables = array(
	$wpdb->prefix . 'htech_messages',
	$wpdb->prefix . 'htech_conversations',
	$wpdb->prefix . 'htech_chunks',
	$wpdb->prefix . 'htech_documents',
	$wpdb->prefix . 'htech_leads',
	$wpdb->prefix . 'htech_rate_limits',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
}

/* 3. Delete options and transients. */
$options = array(
	'htech_ai_settings',
	'htech_ai_kb',
	'htech_ai_version',
	'htech_ai_schema_version',
	'htech_ai_index_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_htech\\_ai\\_%' OR option_name LIKE '\\_transient\\_timeout\\_htech\\_ai\\_%'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
);

/* 4. Clear scheduled events. */
wp_clear_scheduled_hook( 'htech_ai_retention_cleanup' );
wp_clear_scheduled_hook( 'htech_ai_warm_cache' );
