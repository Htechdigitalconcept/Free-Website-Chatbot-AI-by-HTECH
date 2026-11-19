<?php
/**
 * Database schema and data access helpers.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Database
 */
class HTECH_AI_Database {

	const SCHEMA_VERSION = '1.0.0';

	/**
	 * Conversations table name.
	 *
	 * @return string
	 */
	public static function conversations_table() {
		global $wpdb;
		return $wpdb->prefix . 'htech_conversations';
	}

	/**
	 * Messages table name.
	 *
	 * @return string
	 */
	public static function messages_table() {
		global $wpdb;
		return $wpdb->prefix . 'htech_messages';
	}

	/**
	 * Documents table name.
	 *
	 * @return string
	 */
	public static function documents_table() {
		global $wpdb;
		return $wpdb->prefix . 'htech_documents';
	}

	/**
	 * Chunks table name.
	 *
	 * @return string
	 */
	public static function chunks_table() {
		global $wpdb;
		return $wpdb->prefix . 'htech_chunks';
	}

	/**
	 * Leads table name.
	 *
	 * @return string
	 */
	public static function leads_table() {
		global $wpdb;
		return $wpdb->prefix . 'htech_leads';
	}

	/**
	 * Create/upgrade tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$c       = self::conversations_table();
		$m       = self::messages_table();
		$d       = self::documents_table();
		$k       = self::chunks_table();
		$l       = self::leads_table();

		$sql = array();

		$sql[] = "CREATE TABLE {$c} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_key VARCHAR(64) NOT NULL DEFAULT '',
			visitor_label VARCHAR(190) NOT NULL DEFAULT '',
			page_url VARCHAR(255) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			message_count INT UNSIGNED NOT NULL DEFAULT 0,
			lead_captured TINYINT(1) NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY session_key (session_key),
			KEY created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$m} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			role VARCHAR(16) NOT NULL DEFAULT 'user',
			content LONGTEXT NOT NULL,
			mode VARCHAR(24) NOT NULL DEFAULT '',
			sources LONGTEXT NULL,
			tokens INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$d} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(190) NOT NULL DEFAULT '',
			file_name VARCHAR(190) NOT NULL DEFAULT '',
			stored_path VARCHAR(255) NOT NULL DEFAULT '',
			file_type VARCHAR(20) NOT NULL DEFAULT '',
			file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
			sha256 CHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			status_message TEXT NULL,
			chunk_count INT UNSIGNED NOT NULL DEFAULT 0,
			char_count INT UNSIGNED NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			source_type VARCHAR(20) NOT NULL DEFAULT 'upload',
			extractor VARCHAR(32) NOT NULL DEFAULT '',
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			processed_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY is_active (is_active)
		) {$charset};";

		$sql[] = "CREATE TABLE {$k} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			document_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			kb_entry_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			source_type VARCHAR(20) NOT NULL DEFAULT 'document',
			source_label VARCHAR(190) NOT NULL DEFAULT '',
			chunk_index INT UNSIGNED NOT NULL DEFAULT 0,
			content LONGTEXT NOT NULL,
			token_count INT UNSIGNED NOT NULL DEFAULT 0,
			term_data LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY document_id (document_id),
			KEY kb_entry_id (kb_entry_id),
			KEY source_type (source_type)
		) {$charset};";

		$sql[] = "CREATE TABLE {$l} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			name VARCHAR(190) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			phone VARCHAR(60) NOT NULL DEFAULT '',
			service VARCHAR(190) NOT NULL DEFAULT '',
			description TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'htech_ai_schema_version', self::SCHEMA_VERSION, false );
	}

	/**
	 * Create and protect the private document folder.
	 *
	 * @return void
	 */
	public static function protect_uploads() {
		if ( class_exists( 'HTECH_AI_Document_Manager' ) ) {
			HTECH_AI_Document_Manager::ensure_dir();
		}
	}

	/**
	 * Drop every plugin table (uninstall only).
	 *
	 * @return void
	 */
	public static function drop_tables() {
		global $wpdb;
		$tables = array(
			self::conversations_table(),
			self::messages_table(),
			self::documents_table(),
			self::chunks_table(),
			self::leads_table(),
			$wpdb->prefix . 'htech_rate_limits',
		);
		foreach ( $tables as $table ) {
			// Table names are built from $wpdb->prefix only, never user input.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/* ---------------------------------------------------------------------
	 * Conversations
	 * ------------------------------------------------------------------ */

	/**
	 * Create a conversation row.
	 *
	 * @param array $data Conversation data.
	 * @return int Inserted id (0 on failure).
	 */
	public static function create_conversation( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql', true );

		$row = array(
			'session_key'   => isset( $data['session_key'] ) ? substr( (string) $data['session_key'], 0, 64 ) : '',
			'visitor_label' => isset( $data['visitor_label'] ) ? substr( (string) $data['visitor_label'], 0, 190 ) : '',
			'page_url'      => isset( $data['page_url'] ) ? substr( (string) $data['page_url'], 0, 255 ) : '',
			'user_agent'    => isset( $data['user_agent'] ) ? substr( (string) $data['user_agent'], 0, 255 ) : '',
			'ip_hash'       => isset( $data['ip_hash'] ) ? substr( (string) $data['ip_hash'], 0, 64 ) : '',
			'created_at'    => $now,
			'updated_at'    => $now,
		);

		$wpdb->insert( self::conversations_table(), $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch a conversation.
	 *
	 * @param int $id Conversation id.
	 * @return array|null
	 */
	public static function get_conversation( $id ) {
		global $wpdb;
		$table = self::conversations_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * Fetch a conversation by its session key.
	 *
	 * @param string $session_key Session key.
	 * @return array|null
	 */
	public static function get_conversation_by_session( $session_key ) {
		global $wpdb;
		$table = self::conversations_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE session_key = %s ORDER BY id DESC LIMIT 1", $session_key ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * Bump conversation counters.
	 *
	 * @param int $id Conversation id.
	 * @return void
	 */
	public static function touch_conversation( $id ) {
		global $wpdb;
		$table = self::conversations_table();
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$table} SET message_count = message_count + 1, updated_at = %s WHERE id = %d",
				current_time( 'mysql', true ),
				$id
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Messages
	 * ------------------------------------------------------------------ */

	/**
	 * Store a message.
	 *
	 * @param int    $conversation_id Conversation id.
	 * @param string $role            user|assistant.
	 * @param string $content         Message text.
	 * @param array  $args            Optional mode, sources, tokens.
	 * @return int
	 */
	public static function add_message( $conversation_id, $role, $content, array $args = array() ) {
		global $wpdb;

		$wpdb->insert(
			self::messages_table(),
			array(
				'conversation_id' => (int) $conversation_id,
				'role'            => in_array( $role, array( 'user', 'assistant', 'system' ), true ) ? $role : 'user',
				'content'         => wp_kses_post( $content ),
				'mode'            => isset( $args['mode'] ) ? substr( (string) $args['mode'], 0, 24 ) : '',
				'sources'         => isset( $args['sources'] ) ? wp_json_encode( $args['sources'] ) : null,
				'tokens'          => isset( $args['tokens'] ) ? (int) $args['tokens'] : 0,
				'created_at'      => current_time( 'mysql', true ),
			)
		);

		self::touch_conversation( $conversation_id );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Get messages for a conversation.
	 *
	 * @param int $conversation_id Conversation id.
	 * @param int $limit           Max messages.
	 * @return array
	 */
	public static function get_messages( $conversation_id, $limit = 500 ) {
		global $wpdb;
		$table = self::messages_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$conversation_id,
				(int) $limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Conversation list for the admin screen.
	 *
	 * @param array $args search / paged / per_page.
	 * @return array
	 */
	public static function get_conversations( array $args = array() ) {
		global $wpdb;
		$table = self::conversations_table();
		$msg   = self::messages_table();

		$defaults = array(
			'search'   => '',
			'paged'    => 1,
			'per_page' => 20,
			'from'     => '',
			'to'       => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = "(c.visitor_label LIKE %s OR c.session_key LIKE %s OR EXISTS (SELECT 1 FROM {$msg} sm WHERE sm.conversation_id = c.id AND sm.content LIKE %s))";
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( '' !== $args['from'] ) {
			$where[]  = 'c.created_at >= %s';
			$params[] = $args['from'] . ' 00:00:00';
		}
		if ( '' !== $args['to'] ) {
			$where[]  = 'c.created_at <= %s';
			$params[] = $args['to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = max( 0, ( (int) $args['paged'] - 1 ) * (int) $args['per_page'] );

		$count_sql = "SELECT COUNT(*) FROM {$table} c WHERE {$where_sql}";
		$total     = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

		$sql          = "SELECT c.* FROM {$table} c WHERE {$where_sql} ORDER BY c.updated_at DESC, c.id DESC LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( (int) $args['per_page'], $offset ) );

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, (int) $args['per_page'] ) ),
		);
	}

	/**
	 * Delete a single conversation and its messages.
	 *
	 * @param int $id Id.
	 * @return void
	 */
	public static function delete_conversation( $id ) {
		global $wpdb;
		$wpdb->delete( self::messages_table(), array( 'conversation_id' => (int) $id ) );
		$wpdb->delete( self::conversations_table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Delete all conversations & messages.
	 *
	 * @return void
	 */
	public static function delete_all_conversations() {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . self::messages_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'DELETE FROM ' . self::conversations_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Retention purge of conversations and leads.
	 *
	 * @return void
	 */
	public static function purge_expired() {
		global $wpdb;

		$days = (int) HTECH_AI_Settings::get( 'retention_days', 90 );
		if ( $days > 0 ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
			$table  = self::conversations_table();
			$msg    = self::messages_table();

			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $ids ) {
				foreach ( $ids as $id ) {
					$wpdb->delete( $msg, array( 'conversation_id' => (int) $id ) );
					$wpdb->delete( $table, array( 'id' => (int) $id ) );
				}
			}
		}

		$lead_days = (int) HTECH_AI_Settings::get( 'lead_retention_days', 365 );
		if ( $lead_days > 0 ) {
			$lead_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $lead_days * DAY_IN_SECONDS ) );
			$leads       = self::leads_table();
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$leads} WHERE created_at < %s", $lead_cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$rates = $wpdb->prefix . 'htech_rate_limits';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$rates} WHERE expires_at < %s", current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/* ---------------------------------------------------------------------
	 * Statistics (real data only, never hardcoded)
	 * ------------------------------------------------------------------ */

	/**
	 * Dashboard counters.
	 *
	 * @return array
	 */
	public static function get_stats() {
		global $wpdb;

		$conv   = self::conversations_table();
		$msg    = self::messages_table();
		$doc    = self::documents_table();
		$chunk  = self::chunks_table();
		$lead   = self::leads_table();
		$week   = gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) );
		$today  = current_time( 'Y-m-d' ) . ' 00:00:00';

		$stats = array(
			'conversations_total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$conv}" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			'conversations_week'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$conv} WHERE created_at >= %s", $week ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'conversations_today' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$conv} WHERE created_at >= %s", $today ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'messages_total'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$msg}" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			'leads_total'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$lead}" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			'leads_week'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$lead} WHERE created_at >= %s", $week ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'documents_total'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$doc}" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			'documents_ready'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$doc} WHERE status = %s", 'ready' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'chunks_total'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$chunk}" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			'kb_manual_chunks'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$chunk} WHERE source_type = %s", 'manual' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'fallback_answers'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$msg} WHERE mode = %s", 'fallback' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'ai_answers'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$msg} WHERE mode IN (%s, %s)", 'ai', 'ai-rag' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'unanswered'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$msg} WHERE mode = %s", 'no-answer' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day, COUNT(*) AS total FROM {$conv} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d', time() - ( 13 * DAY_IN_SECONDS ) ) . ' 00:00:00'
			),
			ARRAY_A
		);

		/*
		 * Fill every day in the window, not just the days that have rows.
		 * Returning only non-zero days made a single busy day render as one
		 * full-width block, which read as a charting bug rather than "2 today".
		 */
		$by_day = array();

		foreach ( (array) $daily as $row ) {
			$by_day[ (string) $row['day'] ] = (int) $row['total'];
		}

		$stats['daily'] = array();

		for ( $i = 13; $i >= 0; $i-- ) {
			$day = gmdate( 'Y-m-d', time() - ( $i * DAY_IN_SECONDS ) );

			$stats['daily'][] = array(
				'day'   => $day,
				'total' => $by_day[ $day ] ?? 0,
			);
		}

		$top = $wpdb->get_results(
			"SELECT content FROM {$msg} WHERE role = 'user' ORDER BY id DESC LIMIT 200", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			ARRAY_A
		);
		$stats['recent_questions'] = is_array( $top ) ? array_slice( $top, 0, 8 ) : array();

		return $stats;
	}
}
