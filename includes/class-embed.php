<?php
/**
 * JavaScript embed mode for non-WordPress sites that point at this install.
 *
 * This is NOT a SaaS relay: the snippet only loads the widget bundle from this
 * WordPress site and talks to this site's own REST API. Every client still
 * runs their own independent installation; nothing is centralised.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Embed
 */
class HTECH_AI_Embed {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Serve the embeddable loader script.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'htech-ai/v1',
			'/standalone-config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'serve_standalone_config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'htech-ai/v1',
			'/embed.js',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'serve_embed_script' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Serve a read-only knowledge snapshot for standalone (non-WordPress)
	 * deployments of the widget.
	 *
	 * Security note: this deliberately exposes only what a visitor can already
	 * see in the chat (published business facts and document snippets) plus a
	 * short-lived bundle token. It never exposes settings, conversations, leads
	 * or file paths. Administrators who do not want this endpoint can disable it
	 * with the `htech_ai_enable_standalone_config` filter.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function serve_standalone_config() {
		/**
		 * Filter whether the standalone config endpoint is available.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'htech_ai_enable_standalone_config', true ) ) {
			return new WP_Error( 'htech_disabled', __( 'The standalone configuration endpoint is disabled on this site.', 'htech-ai-assistant' ), array( 'status' => 404 ) );
		}

		$limit = HTECH_AI_Limits::consume( 'standalone-config', 30, HOUR_IN_SECONDS );
		if ( ! $limit['allowed'] ) {
			return new WP_Error( 'htech_rate_limited', __( 'Too many requests. Try again later.', 'htech-ai-assistant' ), array( 'status' => 429 ) );
		}

		// A token tied to the request origin lets the standalone bundle write to
		// the public endpoints without shipping a reusable credential.
		$token = wp_generate_password( 32, false, false );
		set_transient( 'htech_ai_bundle_' . md5( $token ), HTECH_AI_Security::client_ip(), 12 * HOUR_IN_SECONDS );

		$config = HTECH_AI_Settings::public_config();
		unset( $config['nonce'] );

		$config['nonce']     = wp_create_nonce( HTECH_AI_Security::NONCE_ACTION );
		$config['bundleToken'] = $token;
		$config['generated'] = gmdate( 'c' );
		$config['knowledge'] = $this->export_knowledge_snapshot();

		$response = rest_ensure_response( $config );
		$response->header( 'Cache-Control', 'public, max-age=300' );

		return $response;
	}

	/**
	 * Flatten the searchable knowledge into a portable JSON snapshot.
	 *
	 * @param int $max_chunks Safety cap on payload size.
	 * @return array
	 */
	public function export_knowledge_snapshot( $max_chunks = 3000 ) {
		global $wpdb;

		$chunks_table = HTECH_AI_Database::chunks_table();
		$docs_table   = HTECH_AI_Database::documents_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT k.source_type, k.source_label, k.content, k.term_data, k.chunk_index
				 FROM {$chunks_table} k
				 LEFT JOIN {$docs_table} d ON d.id = k.document_id
				 WHERE k.source_type = 'manual' OR ( k.source_type = 'document' AND d.is_active = 1 )
				 ORDER BY k.id ASC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $max_chunks
			),
			ARRAY_A
		);

		$items = array();
		foreach ( (array) $rows as $row ) {
			$terms = json_decode( (string) $row['term_data'], true );

			$items[] = array(
				's' => 'document' === $row['source_type'] ? 'doc' : 'kb',
				'l' => (string) $row['source_label'],
				't' => (string) $row['content'],
				// Term frequencies make client-side BM25 possible without a model.
				'f' => is_array( $terms['terms'] ?? null ) ? $terms['terms'] : new stdClass(),
				'n' => (int) ( $terms['len'] ?? 0 ),
			);
		}

		return array(
			'version'  => 1,
			'items'    => $items,
			'faqs'     => array_values(
				array_filter(
					(array) ( htech_ai()->knowledge->all()['faqs'] ?? array() ),
					static function ( $faq ) {
						return ! empty( $faq['active'] );
					}
				)
			),
			'sections' => array_map(
				static function ( $section ) {
					return array( 'content' => $section['content'], 'active' => $section['active'] );
				},
				(array) htech_ai()->knowledge->all()['sections']
			),
			'count'    => count( $items ),
			'truncated' => count( $items ) >= $max_chunks,
		);
	}

	/**
	 * Build the loader JS.
	 *
	 * CORS: the widget calls this same origin, so no cross-origin API access is
	 * needed. The script itself is public and cacheable.
	 *
	 * @return WP_REST_Response
	 */
	public function serve_embed_script() {
		$config = HTECH_AI_Settings::public_config();
		unset( $config['nonce'] ); // Nonces are per-session and fetched at runtime.

		$script = "(function(){\n"
			. "  if (window.HTECH_AI_EMBED_LOADED) { return; }\n"
			. "  window.HTECH_AI_EMBED_LOADED = true;\n"
			. "  var CFG = " . wp_json_encode( $config ) . ";\n"
			. "  var css = document.createElement('link');\n"
			. "  css.rel = 'stylesheet'; css.href = " . wp_json_encode( HTECH_AI_URL . 'public/chatbot-widget.css?v=' . HTECH_AI_VERSION ) . ";\n"
			. "  document.head.appendChild(css);\n"
			. "  var s = document.createElement('script');\n"
			. "  s.src = " . wp_json_encode( HTECH_AI_URL . 'public/chatbot-widget.js?v=' . HTECH_AI_VERSION ) . ";\n"
			. "  s.async = true;\n"
			. "  s.onload = function(){ if (window.HTECH_AI && window.HTECH_AI.mount) { window.HTECH_AI.mount(CFG); } };\n"
			. "  document.body.appendChild(s);\n"
			. "  var mount = document.createElement('div');\n"
			. "  mount.id = 'htech-ai-launcher'; mount.className = 'htech-ai-embed-launcher';\n"
			. "  var panel = document.createElement('div');\n"
			. "  panel.id = 'htech-ai-panel'; panel.className = 'htech-ai-embed-mount'; panel.setAttribute('data-htech-mode','floating');\n"
			. "  document.addEventListener('DOMContentLoaded', function(){\n"
			. "    document.body.appendChild(panel); document.body.appendChild(mount);\n"
			. "  });\n"
			. "})();\n";

		$response = new WP_REST_Response( $script, 200 );
		$response->header( 'Content-Type', 'application/javascript; charset=utf-8' );
		$response->header( 'Cache-Control', 'public, max-age=3600' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );

		return $response;
	}

	/**
	 * Snippet for the admin Installation screen.
	 *
	 * Uses the REST embed endpoint so the snippet stays one line even when the
	 * plugin path changes.
	 *
	 * @return string
	 */
	public static function embed_snippet() {
		return '<script src="' . esc_url( rest_url( 'htech-ai/v1/embed.js' ) ) . '" async></script>';
	}
}
