<?php
/**
 * REST API endpoints (public widget + admin).
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_REST_API
 */
class HTECH_AI_REST_API {

	const NAMESPACE_V1 = 'htech-ai/v1';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$public = array( $this, 'public_permission' );
		$admin  = array( $this, 'admin_permission' );

		/* ---------------- Public (visitor) ---------------------------- */
		register_rest_route(
			self::NAMESPACE_V1,
			'/bootstrap',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'bootstrap' ),
				// Public by design: it returns only visitor-safe values plus a
				// nonce for anonymous visitors, and lets embed/standalone
				// installs bootstrap without server-rendered inline config.
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/chat/message',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'chat_message' ),
				'permission_callback' => $public,
				'args'                => array(
					'message'         => array( 'required' => true, 'type' => 'string' ),
					'conversation_id' => array( 'type' => 'integer', 'default' => 0 ),
					'ai_ready'        => array( 'type' => 'boolean', 'default' => false ),
					'page_url'        => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/chat/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'chat_complete' ),
				'permission_callback' => $public,
				'args'                => array(
					'conversation_id' => array( 'required' => true, 'type' => 'integer' ),
					'answer'          => array( 'required' => true, 'type' => 'string' ),
					'context'         => array( 'type' => 'string', 'default' => '' ),
					'sources'         => array( 'type' => 'array', 'default' => array() ),
					'latency_ms'      => array( 'type' => 'integer', 'default' => 0 ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/chat/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'chat_reset' ),
				'permission_callback' => $public,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit_lead' ),
				'permission_callback' => $public,
				'args'                => array(
					'name'            => array( 'type' => 'string', 'default' => '' ),
					'email'           => array( 'type' => 'string', 'default' => '' ),
					'phone'           => array( 'type' => 'string', 'default' => '' ),
					'service'         => array( 'type' => 'string', 'default' => '' ),
					'description'     => array( 'type' => 'string', 'default' => '' ),
					'conversation_id' => array( 'type' => 'integer', 'default' => 0 ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health' ),
				'permission_callback' => '__return_true',
			)
		);

		/* ---------------- Admin --------------------------------------- */
		$admin_routes = array(
			'/admin/documents/upload'      => array( 'POST', 'admin_upload_document' ),
			'/admin/documents/process'     => array( 'POST', 'admin_process_document' ),
			'/admin/documents/toggle'      => array( 'POST', 'admin_toggle_document' ),
			'/admin/documents/delete'      => array( 'POST', 'admin_delete_document' ),
			'/admin/documents/reprocess'   => array( 'POST', 'admin_reprocess_document' ),
			'/admin/kb/section'            => array( 'POST', 'admin_save_section' ),
			'/admin/kb/faq'                => array( 'POST', 'admin_save_faq' ),
			'/admin/kb/faq/delete'         => array( 'POST', 'admin_delete_faq' ),
			'/admin/kb/custom'             => array( 'POST', 'admin_save_custom' ),
			'/admin/kb/custom/delete'      => array( 'POST', 'admin_delete_custom' ),
			'/admin/kb/reindex'            => array( 'POST', 'admin_reindex' ),
			'/admin/kb/import'             => array( 'POST', 'admin_import_kb' ),
			'/admin/importer/site'         => array( 'POST', 'admin_import_site' ),
			'/admin/tools/flush-limits'    => array( 'POST', 'admin_flush_limits' ),
			'/admin/tools/reset-settings'  => array( 'POST', 'admin_reset_settings' ),
			'/admin/tools/purge-data'      => array( 'POST', 'admin_purge_data' ),
			'/admin/conversations/delete'  => array( 'POST', 'admin_delete_conversation' ),
			'/admin/leads/delete'          => array( 'POST', 'admin_delete_lead' ),
			'/admin/test-retrieval'        => array( 'POST', 'admin_test_retrieval' ),
		);

		foreach ( $admin_routes as $route => $config ) {
			register_rest_route(
				self::NAMESPACE_V1,
				$route,
				array(
					'methods'             => $config[0],
					'callback'            => array( $this, $config[1] ),
					'permission_callback' => $admin,
				)
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Permissions
	 * ------------------------------------------------------------------ */

	/**
	 * Public write endpoints: a valid nonce issued by /bootstrap or inlined with
	 * the widget payload. For anonymous visitors WordPress derives the nonce
	 * from the empty session token, so this is a lightweight origin check
	 * rather than a CSRF boundary — the real protections are the per-IP rate
	 * limiter, strict input validation and the server-side answer guards.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function public_permission( $request ) {
		/*
		 * Deliberately NOT X-WP-Nonce: WordPress core treats that header as a
		 * logged-in cookie assertion and returns rest_cookie_invalid_nonce for
		 * anonymous visitors before this callback ever runs. A plugin-specific
		 * header keeps the check working for logged-out visitors.
		 */
		$nonce = $request->get_header( 'X-Htech-Nonce' );
		if ( ! $nonce ) {
			$nonce = (string) $request->get_param( '_nonce' );
		}

		if ( ! HTECH_AI_Security::verify_public_nonce( $nonce ) ) {
			return new WP_Error(
				'htech_nonce',
				__( 'Your session expired. Please refresh the page and try again.', 'htech-ai-assistant' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Admin endpoints: capability + nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function admin_permission( $request ) {
		if ( ! HTECH_AI_Security::can_manage() ) {
			return new WP_Error( 'htech_forbidden', __( 'You are not allowed to manage this plugin.', 'htech-ai-assistant' ), array( 'status' => 403 ) );
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'htech_nonce', __( 'Security check failed. Reload the page and try again.', 'htech-ai-assistant' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Public handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Everything the widget needs to render itself.
	 *
	 * Only visitor-safe values are exposed; secrets and admin-only settings
	 * never leave the server.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function bootstrap( $request ) {
		return rest_ensure_response( HTECH_AI_Settings::public_config() );
	}

	/**
	 * Visitor message.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function chat_message( $request ) {
		$limit = HTECH_AI_Limits::consume( 'chat', (int) HTECH_AI_Settings::get( 'rate_limit_per_minute', 20 ), MINUTE_IN_SECONDS );

		if ( ! $limit['allowed'] ) {
			return new WP_Error(
				'htech_rate_limited',
				sprintf(
					/* translators: %d: seconds */
					__( 'You are sending messages too quickly. Please wait %d seconds and try again.', 'htech-ai-assistant' ),
					(int) $limit['retry_after']
				),
				array(
					'status'      => 429,
					'retry_after' => (int) $limit['retry_after'],
				)
			);
		}

		$message = (string) $request->get_param( 'message' );

		$result = htech_ai()->chatbot->handle_message(
			$message,
			(int) $request->get_param( 'conversation_id' ),
			array(
				'ai_ready' => (bool) $request->get_param( 'ai_ready' ),
				'page_url' => esc_url_raw( (string) $request->get_param( 'page_url' ) ),
			)
		);

		if ( empty( $result['ok'] ) ) {
			return new WP_Error( 'htech_chat', (string) ( $result['message'] ?? __( 'The message could not be processed.', 'htech-ai-assistant' ) ), array( 'status' => 400 ) );
		}

		$response = rest_ensure_response( $result );

		if ( isset( $limit['remaining'] ) ) {
			$response->header( 'X-RateLimit-Remaining', (string) $limit['remaining'] );
		}

		return $response;
	}

	/**
	 * Store a model-generated answer after server-side validation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function chat_complete( $request ) {
		$limit = HTECH_AI_Limits::consume( 'complete', (int) HTECH_AI_Settings::get( 'rate_limit_per_minute', 20 ), MINUTE_IN_SECONDS );
		if ( ! $limit['allowed'] ) {
			return new WP_Error( 'htech_rate_limited', __( 'Too many requests. Please slow down.', 'htech-ai-assistant' ), array( 'status' => 429 ) );
		}

		$conversation_id = (int) $request->get_param( 'conversation_id' );
		if ( $conversation_id > 0 && ! HTECH_AI_Settings::is_enabled( 'store_conversations' ) ) {
			$conversation_id = 0;
		}

		$sources = array();
		foreach ( (array) $request->get_param( 'sources' ) as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$sources[] = array(
				'label' => sanitize_text_field( (string) ( $source['label'] ?? '' ) ),
				'type'  => sanitize_key( (string) ( $source['type'] ?? '' ) ),
				'score' => (float) ( $source['score'] ?? 0 ),
				'id'    => (int) ( $source['id'] ?? 0 ),
			);
		}

		$result = htech_ai()->chatbot->handle_ai_completion(
			$conversation_id,
			(string) $request->get_param( 'answer' ),
			(string) $request->get_param( 'context' ),
			$sources,
			(int) $request->get_param( 'latency_ms' )
		);

		return rest_ensure_response( $result );
	}

	/**
	 * Start a fresh conversation (new session row).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function chat_reset( $request ) {
		$id = 0;

		if ( HTECH_AI_Settings::is_enabled( 'store_conversations' ) ) {
			$id = HTECH_AI_Database::create_conversation(
				array(
					'session_key'   => HTECH_AI_Security::session_key(),
					'visitor_label' => __( 'New visitor', 'htech-ai-assistant' ),
					'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
					'ip_hash'       => HTECH_AI_Security::ip_hash(),
				)
			);
		}

		return rest_ensure_response(
			array(
				'ok'               => true,
				'conversation_id'  => (int) $id,
				'welcome_message'  => (string) HTECH_AI_Settings::get( 'welcome_message' ),
				'suggestions'      => htech_ai()->knowledge->suggestions( 4 ),
			)
		);
	}

	/**
	 * Lead submission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_lead( $request ) {
		$limit = HTECH_AI_Limits::consume( 'lead', 5, HOUR_IN_SECONDS );
		if ( ! $limit['allowed'] ) {
			return new WP_Error( 'htech_rate_limited', __( 'Too many submissions from this connection. Please try later.', 'htech-ai-assistant' ), array( 'status' => 429 ) );
		}

		$result = htech_ai()->chatbot->save_lead(
			array(
				'name'        => (string) $request->get_param( 'name' ),
				'email'       => (string) $request->get_param( 'email' ),
				'phone'       => (string) $request->get_param( 'phone' ),
				'service'     => (string) $request->get_param( 'service' ),
				'description' => (string) $request->get_param( 'description' ),
			),
			(int) $request->get_param( 'conversation_id' )
		);

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Lightweight health probe (no secrets).
	 *
	 * @return WP_REST_Response
	 */
	public function health() {
		return rest_ensure_response(
			array(
				'ok'      => true,
				'version' => HTECH_AI_VERSION,
				'storage' => HTECH_AI_Settings::is_enabled( 'store_conversations' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin handlers: documents
	 * ------------------------------------------------------------------ */

	/**
	 * Upload one document.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_upload_document( $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'htech_upload', __( 'No file was received by the server.', 'htech-ai-assistant' ), array( 'status' => 400 ) );
		}

		$limit = HTECH_AI_Limits::consume( 'upload', (int) HTECH_AI_Settings::get( 'max_files_per_upload', 5 ) * 4, MINUTE_IN_SECONDS );
		if ( ! $limit['allowed'] ) {
			return new WP_Error( 'htech_rate_limited', __( 'Upload limit reached for this minute. Please wait a moment.', 'htech-ai-assistant' ), array( 'status' => 429 ) );
		}

		$client_text = (string) $request->get_param( 'client_text' );

		// Stores the original file in the private folder, validates it and
		// indexes either the browser-extracted text or a server-side parse.
		$result = htech_ai()->documents->handle_upload( $files['file'], $client_text );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Attach browser-extracted text to an existing document and index it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_process_document( $request ) {
		$document_id = (int) $request->get_param( 'document_id' );
		$text        = (string) $request->get_param( 'text' );

		if ( $document_id <= 0 ) {
			return new WP_Error( 'htech_doc', __( 'Missing document id.', 'htech-ai-assistant' ), array( 'status' => 400 ) );
		}

		if ( mb_strlen( $text ) < 20 ) {
			return new WP_Error( 'htech_doc', __( 'The browser could not extract readable text from this file.', 'htech-ai-assistant' ), array( 'status' => 400 ) );
		}

		$result = htech_ai()->documents->process_document( $document_id, $text );
		return rest_ensure_response( $result );
	}

	/**
	 * Toggle active.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function admin_toggle_document( $request ) {
		$id    = (int) $request->get_param( 'document_id' );
		$state = htech_ai()->documents->toggle_active( $id );

		return rest_ensure_response(
			array(
				'ok'        => true,
				'is_active' => $state ? 1 : 0,
			)
		);
	}

	/**
	 * Delete.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function admin_delete_document( $request ) {
		$id = (int) $request->get_param( 'document_id' );

		return rest_ensure_response(
			array(
				'ok'      => htech_ai()->documents->delete_document( $id ),
				'message' => __( 'Document and its indexed content were deleted.', 'htech-ai-assistant' ),
			)
		);
	}

	/**
	 * Re-run extraction for a document (e.g. after changing chunk settings).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_reprocess_document( $request ) {
		$id = (int) $request->get_param( 'document_id' );
		$document = htech_ai()->documents->get_document( $id );

		if ( ! $document ) {
			return new WP_Error( 'htech_doc', __( 'Document not found.', 'htech-ai-assistant' ), array( 'status' => 404 ) );
		}

		if ( empty( $document['stored_path'] ) || ! file_exists( $document['stored_path'] ) ) {
			return rest_ensure_response(
				array(
					'ok'                  => false,
					'needs_browser_parse' => true,
					'message'             => __( 'The original file is not stored on this server. Re-upload it to reprocess, or paste its text into a knowledge entry.', 'htech-ai-assistant' ),
				)
			);
		}

		htech_ai()->documents->set_status( $id, 'processing' );
		return rest_ensure_response( htech_ai()->documents->process_document( $id ) );
	}

	/* ---------------------------------------------------------------------
	 * Admin handlers: knowledge base
	 * ------------------------------------------------------------------ */

	/**
	 * Save a manual section.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function admin_save_section( $request ) {
		$key     = sanitize_key( (string) $request->get_param( 'section' ) );
		$content = (string) $request->get_param( 'content' );
		$active  = (bool) $request->get_param( 'active' );

		htech_ai()->knowledge->save_section( $key, $content, $active );

		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => __( 'Section saved and re-indexed. The chatbot can use it immediately.', 'htech-ai-assistant' ),
				'coverage' => htech_ai()->knowledge->coverage(),
			)
		);
	}

	/**
	 * Create/update a FAQ.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_save_faq( $request ) {
		$id = htech_ai()->knowledge->save_faq(
			array(
				'id'       => (int) $request->get_param( 'id' ),
				'question' => (string) $request->get_param( 'question' ),
				'answer'   => (string) $request->get_param( 'answer' ),
				'active'   => (bool) $request->get_param( 'active' ),
			)
		);

		if ( ! $id ) {
			return new WP_Error( 'htech_kb', __( 'Both a question and an answer are required.', 'htech-ai-assistant' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'ok'      => true,
				'id'      => $id,
				'message' => __( 'FAQ saved.', 'htech-ai-assistant' ),
			)
		);
	}

	/**
	 * Delete a FAQ.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function admin_delete_faq( $request ) {
		htech_ai()->knowledge->delete_faq( (int) $request->get_param( 'id' ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Create/update a custom entry.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_save_custom( $request ) {
		$id = htech_ai()->knowledge->save_custom(
			array(
				'id'      => (int) $request->get_param( 'id' ),
				'title'   => (string) $request->get_param( 'title' ),
				'content' => (string) $request->get_param( 'content' ),
				'active'  => (bool) $request->get_param( 'active' ),
			)
		);

		if ( ! $id ) {
			return new WP_Error( 'htech_kb', __( 'A title and content are required.', 'htech-ai-assistant' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'ok'      => true,
				'id'      => $id,
				'message' => __( 'Knowledge entry saved.', 'htech-ai-assistant' ),
			)
		);
	}

	/**
	 * Delete a custom entry.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function admin_delete_custom( $request ) {
		htech_ai()->knowledge->delete_custom( (int) $request->get_param( 'id' ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Force a full re-index.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function admin_reindex( $request ) {
		$count = htech_ai()->knowledge->reindex();

		return rest_ensure_response(
			array(
				'ok'        => true,
				'message'   => sprintf(
					/* translators: %d: chunk count */
					__( 'Re-index complete: %d manual knowledge chunks are now searchable.', 'htech-ai-assistant' ),
					$count
				),
				'coverage'  => htech_ai()->knowledge->coverage(),
			)
		);
	}

	/**
	 * Import knowledge JSON.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_import_kb( $request ) {
		$result = htech_ai()->knowledge->import_json( (string) $request->get_param( 'json' ) );

		if ( empty( $result['ok'] ) ) {
			return new WP_Error( 'htech_kb', $result['message'], array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => $result['message'],
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin handlers: website importer
	 * ------------------------------------------------------------------ */

	/**
	 * Fetch a public page and return extracted text for review (does not save).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_import_site( $request ) {
		$url = esc_url_raw( (string) $request->get_param( 'url' ) );

		if ( ! $url ) {
			return new WP_Error( 'htech_import', __( 'Enter a valid public URL, including https://.', 'htech-ai-assistant' ), array( 'status' => 400 ) );
		}

		$parsed = wp_parse_url( $url );
		$scheme = strtolower( (string) ( $parsed['scheme'] ?? '' ) );
		$host   = strtolower( (string) ( $parsed['host'] ?? '' ) );

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return new WP_Error( 'htech_import', __( 'Only public http(s) URLs can be imported.', 'htech-ai-assistant' ), array( 'status' => 400 ) );
		}

		// Refuse private/loopback targets (SSRF protection).
		$ip = gethostbyname( $host );
		if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
			return new WP_Error( 'htech_import', __( 'That address resolves to a private or reserved network and cannot be imported.', 'htech-ai-assistant' ), array( 'status' => 400 ) );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 12,
				'redirection' => 3,
				'user-agent'  => 'HTECH-AI-Assistant/' . HTECH_AI_VERSION . '; ' . home_url(),
				'headers'     => array( 'Accept' => 'text/html,application/xhtml+xml' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'htech_import',
				sprintf(
					/* translators: %s: error */
					__( 'The page could not be fetched: %s', 'htech-ai-assistant' ),
					$response->get_error_message()
				),
				array( 'status' => 400 )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'htech_import',
				sprintf(
					/* translators: %d: HTTP status */
					__( 'The server returned HTTP %d. The page may be private or blocked. Add the information manually instead.', 'htech-ai-assistant' ),
					$code
				),
				array( 'status' => 400 )
			);
		}

		$html = (string) wp_remote_retrieve_body( $response );
		if ( mb_strlen( $html ) > 3000000 ) {
			$html = mb_substr( $html, 0, 3000000 );
		}

		$text  = HTECH_AI_Text_Extractor::from_html( $html );
		$title = '';
		if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
			$title = trim( wp_strip_all_tags( $m[1] ) );
		}

		if ( mb_strlen( $text ) < 50 ) {
			return new WP_Error( 'htech_import', __( 'Very little readable text was found on that page. It is probably rendered by JavaScript. Copy the content into a manual knowledge entry instead.', 'htech-ai-assistant' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'ok'        => true,
				'url'       => $url,
				'title'     => $title,
				'text'      => $text,
				'characters' => mb_strlen( $text ),
				'notice'    => __( 'Review this text before saving. Remove anything not intended for visitors.', 'htech-ai-assistant' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin handlers: tools, conversations, leads, diagnostics
	 * ------------------------------------------------------------------ */

	/**
	 * Clear rate-limit buckets.
	 *
	 * @return WP_REST_Response
	 */
	public function admin_flush_limits() {
		HTECH_AI_Limits::flush();
		return rest_ensure_response( array( 'ok' => true, 'message' => __( 'Rate limits cleared.', 'htech-ai-assistant' ) ) );
	}

	/**
	 * Reset appearance/settings to defaults.
	 *
	 * @return WP_REST_Response
	 */
	public function admin_reset_settings() {
		HTECH_AI_Settings::reset();
		return rest_ensure_response( array( 'ok' => true, 'message' => __( 'Settings restored to defaults.', 'htech-ai-assistant' ) ) );
	}

	/**
	 * Delete data by scope (privacy tools).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_purge_data( $request ) {
		$scope = sanitize_key( (string) $request->get_param( 'scope' ) );

		switch ( $scope ) {
			case 'conversations':
				HTECH_AI_Database::delete_all_conversations();
				break;
			case 'documents':
				htech_ai()->documents->delete_all();
				break;
			case 'knowledge':
				update_option( HTECH_AI_Knowledge_Base::OPTION, array(), 'no' );
				htech_ai()->knowledge->reindex();
				break;
			case 'leads':
				global $wpdb;
				$wpdb->query( 'DELETE FROM ' . HTECH_AI_Database::leads_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
				break;
			default:
				return new WP_Error( 'htech_purge', __( 'Unknown data scope.', 'htech-ai-assistant' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => __( 'Selected data deleted.', 'htech-ai-assistant' ),
			)
		);
	}

	/**
	 * Delete one conversation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function admin_delete_conversation( $request ) {
		HTECH_AI_Database::delete_conversation( (int) $request->get_param( 'id' ) );
		return rest_ensure_response( array( 'ok' => true, 'message' => __( 'Conversation deleted.', 'htech-ai-assistant' ) ) );
	}

	/**
	 * Delete one lead.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function admin_delete_lead( $request ) {
		global $wpdb;
		$wpdb->delete( HTECH_AI_Database::leads_table(), array( 'id' => (int) $request->get_param( 'id' ) ) );
		return rest_ensure_response( array( 'ok' => true, 'message' => __( 'Lead deleted.', 'htech-ai-assistant' ) ) );
	}

	/**
	 * Run a retrieval test from the admin UI and return ranked chunks.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function admin_test_retrieval( $request ) {
		$query   = (string) $request->get_param( 'query' );
		$results = htech_ai()->retrieval->search( $query );

		$clean = array();
		foreach ( $results as $result ) {
			$clean[] = array(
				'source'  => $result['source_label'],
				'type'    => $result['source_type'],
				'score'   => $result['score'],
				'snippet' => mb_substr( preg_replace( '/\s+/', ' ', $result['content'] ), 0, 400 ),
			);
		}

		$extractive = htech_ai()->chatbot->compose_extractive_answer( $query, $results );

		return rest_ensure_response(
			array(
				'ok'         => true,
				'query'      => $query,
				'count'      => count( $clean ),
				'results'    => $clean,
				'answer'     => $extractive ? $extractive : HTECH_AI_Chatbot::unknown_answer(),
				'fallback'   => true,
			)
		);
	}
}
