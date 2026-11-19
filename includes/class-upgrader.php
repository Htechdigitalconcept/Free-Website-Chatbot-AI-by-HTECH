<?php
/**
 * Schema/version upgrades.
 *
 * Runs on every load but does nothing unless the stored version differs from
 * the code version, so it costs one option read in the common case.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Upgrader
 */
class HTECH_AI_Upgrader {

	/**
	 * Compare versions and run migrations.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$stored = (string) get_option( 'htech_ai_version', '0' );

		if ( version_compare( $stored, HTECH_AI_VERSION, '>=' ) ) {
			return;
		}

		// Tables use dbDelta, so re-running install() is safe and idempotent.
		HTECH_AI_Database::install();
		HTECH_AI_Limits::create_tables();
		HTECH_AI_Settings::install_defaults();

		// Give an empty knowledge base something to work with (first run only;
		// seed_sample_content() never overwrites existing content).
		htech_ai()->knowledge->seed_sample_content();

		// Rebuild the manual index so new fields are picked up.
		htech_ai()->knowledge->reindex();

		update_option( 'htech_ai_version', HTECH_AI_VERSION, false );
	}
}
