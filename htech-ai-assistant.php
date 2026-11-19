<?php
/**
 * Plugin Name:       Free Website Chatbot AI by HTECH
 * Plugin URI:        https://htechdigital.com/
 * Description:       Self-hosted, private, browser-AI chatbot with a business knowledge base, document retrieval and lead capture. No paid APIs required.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            HTECH Digital Solutions
 * Author URI:        https://htechdigital.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       htech-ai-assistant
 * Domain Path:       /languages
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

define( 'HTECH_AI_VERSION', '1.0.1' );
define( 'HTECH_AI_FILE', __FILE__ );
define( 'HTECH_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'HTECH_AI_URL', plugin_dir_url( __FILE__ ) );
define( 'HTECH_AI_BASENAME', plugin_basename( __FILE__ ) );
define( 'HTECH_AI_SLUG', 'htech-ai-assistant' );
define( 'HTECH_AI_MIN_PHP', '7.4' );

/**
 * Abort activation on unsupported PHP versions instead of fatal-erroring.
 */
if ( version_compare( PHP_VERSION, HTECH_AI_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__( 'Free Website Chatbot AI by HTECH requires PHP %1$s or newer. This server runs PHP %2$s, so the plugin has not been loaded.', 'htech-ai-assistant' ),
						HTECH_AI_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

/**
 * Autoload plugin classes from includes/.
 *
 * @param string $class_name Class name.
 * @return void
 */
function htech_ai_autoload( $class_name ) {
	if ( 0 !== strpos( $class_name, 'HTECH_AI_' ) ) {
		return;
	}

	$map = array(
		'HTECH_AI_Plugin'           => 'class-plugin.php',
		'HTECH_AI_Database'         => 'class-database.php',
		'HTECH_AI_Settings'         => 'class-settings.php',
		'HTECH_AI_Security'         => 'class-security.php',
		'HTECH_AI_Admin'            => 'class-admin.php',
		'HTECH_AI_Chatbot'          => 'class-chatbot.php',
		'HTECH_AI_REST_API'         => 'class-rest-api.php',
		'HTECH_AI_Knowledge_Base'   => 'class-knowledge-base.php',
		'HTECH_AI_Document_Manager' => 'class-document-manager.php',
		'HTECH_AI_Text_Extractor'   => 'class-text-extractor.php',
		'HTECH_AI_Retrieval'        => 'class-retrieval.php',
		'HTECH_AI_Shortcode'        => 'class-shortcode.php',
		'HTECH_AI_Embed'            => 'class-embed.php',
		'HTECH_AI_Limits'           => 'class-limits.php',
		'HTECH_AI_Chunker'          => 'class-chunker.php',
		'HTECH_AI_Upgrader'         => 'class-upgrader.php',
	);

	if ( ! isset( $map[ $class_name ] ) ) {
		return;
	}

	$file = HTECH_AI_DIR . 'includes/' . $map[ $class_name ];
	if ( file_exists( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'htech_ai_autoload' );

/* Explicitly require the plugin bootstrap so it is always available. */
require_once HTECH_AI_DIR . 'includes/class-plugin.php';

/**
 * Main plugin instance accessor.
 *
 * @return HTECH_AI_Plugin
 */
function htech_ai() {
	return HTECH_AI_Plugin::instance();
}

/**
 * Activation callback.
 *
 * @return void
 */
function htech_ai_activate() {
	require_once HTECH_AI_DIR . 'includes/class-database.php';
	require_once HTECH_AI_DIR . 'includes/class-settings.php';
	require_once HTECH_AI_DIR . 'includes/class-limits.php';
	require_once HTECH_AI_DIR . 'includes/class-upgrader.php';

	HTECH_AI_Database::install();
	HTECH_AI_Settings::install_defaults();
	HTECH_AI_Limits::create_tables();
	HTECH_AI_Database::protect_uploads();

	// First run only: give the administrator obvious placeholders to replace so
	// the widget never starts life refusing to answer everything.
	HTECH_AI_Plugin::instance()->knowledge->seed_sample_content();

	update_option( 'htech_ai_version', HTECH_AI_VERSION, false );
	set_transient( 'htech_ai_activation_redirect', 1, 60 );
}
register_activation_hook( __FILE__, 'htech_ai_activate' );

/**
 * Deactivation callback. Never deletes business data.
 *
 * @return void
 */
function htech_ai_deactivate() {
	wp_clear_scheduled_hook( 'htech_ai_retention_cleanup' );
	wp_clear_scheduled_hook( 'htech_ai_warm_cache' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'htech_ai_deactivate' );

/* Boot the plugin. */
htech_ai();
