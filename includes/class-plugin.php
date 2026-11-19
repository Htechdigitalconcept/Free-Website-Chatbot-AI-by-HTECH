<?php
/**
 * Plugin bootstrap / service container.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Plugin
 */
final class HTECH_AI_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var HTECH_AI_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings service.
	 *
	 * @var HTECH_AI_Settings
	 */
	public $settings;

	/**
	 * Retrieval engine.
	 *
	 * @var HTECH_AI_Retrieval
	 */
	public $retrieval;

	/**
	 * Knowledge base service.
	 *
	 * @var HTECH_AI_Knowledge_Base
	 */
	public $knowledge;

	/**
	 * Document manager.
	 *
	 * @var HTECH_AI_Document_Manager
	 */
	public $documents;

	/**
	 * Chatbot service.
	 *
	 * @var HTECH_AI_Chatbot
	 */
	public $chatbot;

	/**
	 * Get the instance.
	 *
	 * @return HTECH_AI_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: wire everything up.
	 */
	private function __construct() {
		$this->settings  = new HTECH_AI_Settings();
		$this->knowledge = new HTECH_AI_Knowledge_Base();
		$this->documents = new HTECH_AI_Document_Manager();
		$this->retrieval = new HTECH_AI_Retrieval();
		$this->chatbot   = new HTECH_AI_Chatbot();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( 'HTECH_AI_Upgrader', 'maybe_upgrade' ) );
		add_action( 'init', array( $this, 'init' ) );

		new HTECH_AI_REST_API();
		new HTECH_AI_Shortcode();
		new HTECH_AI_Embed();

		if ( is_admin() ) {
			new HTECH_AI_Admin();
		}

		add_action( 'htech_ai_retention_cleanup', array( $this, 'run_retention_cleanup' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_compat_notice' ) );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'htech-ai-assistant', false, dirname( HTECH_AI_BASENAME ) . '/languages' );
	}

	/**
	 * Init hook: schedule housekeeping.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! wp_next_scheduled( 'htech_ai_retention_cleanup' ) ) {
			wp_schedule_event( time() + 3600, 'daily', 'htech_ai_retention_cleanup' );
		}
	}

	/**
	 * Delete conversations / leads older than the retention window.
	 *
	 * @return void
	 */
	public function run_retention_cleanup() {
		HTECH_AI_Database::purge_expired();
	}

	/**
	 * Warn administrators if the uploads directory is not writable.
	 *
	 * @return void
	 */
	public function maybe_show_compat_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'htech-ai' ) ) {
			return;
		}
		$uploads = HTECH_AI_Document_Manager::uploads_dir();
		if ( is_wp_error( $uploads ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'Free Website Chatbot AI by HTECH:', 'htech-ai-assistant' ),
				esc_html( $uploads->get_error_message() )
			);
		}
	}
}
