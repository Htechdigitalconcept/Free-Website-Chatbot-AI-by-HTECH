<?php
/**
 * Document library: secure upload, storage, extraction, chunking, lifecycle.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Document_Manager
 */
class HTECH_AI_Document_Manager {

	const DIR_NAME = 'htech-ai-assistant';

	/**
	 * Absolute path to the protected upload directory.
	 *
	 * @return string|WP_Error
	 */
	public static function uploads_dir() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'htech_uploads', $uploads['error'] );
		}

		$dir = trailingslashit( $uploads['basedir'] ) . self::DIR_NAME;

		if ( ! file_exists( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return new WP_Error(
					'htech_uploads',
					__( 'The plugin could not create its upload folder. Check that wp-content/uploads is writable.', 'htech-ai-assistant' )
				);
			}
		}

		self::protect_dir( $dir );

		return $dir;
	}

	/**
	 * Write deny rules so documents can never be fetched directly by URL.
	 *
	 * @param string $dir Directory.
	 * @return void
	 */
	public static function protect_dir( $dir ) {
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "# HTECH AI Assistant: private document store.\n"
				. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";
			file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		// Belt and braces for servers that ignore .htaccess: rename text files
		// inside the folder is not enough, so also drop a web.config for IIS.
		$web_config = $dir . '/web.config';
		if ( ! file_exists( $web_config ) ) {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n";
			file_put_contents( $web_config, $xml ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Ensure the directory exists (activation hook helper).
	 *
	 * @return void
	 */
	public static function ensure_dir() {
		self::uploads_dir();
	}

	/**
	 * Allowed extension => MIME map.
	 *
	 * @return array<string,string[]>
	 */
	public static function allowed_types() {
		return array(
			'pdf'  => array( 'application/pdf' ),
			'docx' => array(
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'application/zip',
				'application/octet-stream',
			),
			'txt'  => array( 'text/plain', 'application/octet-stream' ),
			'md'   => array( 'text/plain', 'text/markdown', 'application/octet-stream' ),
			'csv'  => array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream' ),
			'json' => array( 'application/json', 'text/plain', 'application/octet-stream' ),
		);
	}

	/**
	 * Extensions the administrator has enabled.
	 *
	 * @return string[]
	 */
	public static function enabled_extensions() {
		$configured = array_filter( array_map( 'trim', explode( ',', (string) HTECH_AI_Settings::get( 'allowed_extensions', 'pdf,docx,txt,csv' ) ) ) );
		$allowed    = array_keys( self::allowed_types() );
		return array_values( array_intersect( array_map( 'strtolower', $configured ), $allowed ) );
	}

	/**
	 * Maximum upload size in bytes.
	 *
	 * @return int
	 */
	public static function max_upload_bytes() {
		$mb       = max( 1, (int) HTECH_AI_Settings::get( 'max_file_size_mb', 8 ) );
		$php_max  = wp_max_upload_size();
		return (int) min( $mb * MB_IN_BYTES, $php_max > 0 ? $php_max : $mb * MB_IN_BYTES );
	}

	/**
	 * Handle an uploaded file (called from the admin REST endpoint).
	 *
	 * @param array  $file        Entry from $_FILES.
	 * @param string $client_text Optional text already extracted in the browser.
	 * @return array|WP_Error Document row data.
	 */
	public function handle_upload( array $file, $client_text = '' ) {
		if ( empty( $file['tmp_name'] ) || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'htech_upload', $this->upload_error_message( (int) ( $file['error'] ?? -1 ) ) );
		}

		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'htech_upload', __( 'The upload could not be verified as a genuine file transfer.', 'htech-ai-assistant' ) );
		}

		$size = (int) ( $file['size'] ?? 0 );
		if ( $size <= 0 ) {
			return new WP_Error( 'htech_upload', __( 'The uploaded file is empty.', 'htech-ai-assistant' ) );
		}
		if ( $size > self::max_upload_bytes() ) {
			return new WP_Error(
				'htech_upload',
				sprintf(
					/* translators: %s: max size in MB */
					__( 'The file is larger than the %s MB limit configured for this site.', 'htech-ai-assistant' ),
					number_format_i18n( self::max_upload_bytes() / MB_IN_BYTES, 1 )
				)
			);
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( ! $ext || ! in_array( $ext, self::enabled_extensions(), true ) ) {
			return new WP_Error(
				'htech_upload',
				sprintf(
					/* translators: 1: file extension, 2: allowed list */
					__( 'Files of type "%1$s" are not accepted. Allowed types: %2$s.', 'htech-ai-assistant' ),
					$ext ? $ext : __( 'unknown', 'htech-ai-assistant' ),
					implode( ', ', self::enabled_extensions() )
				)
			);
		}

		// Magic-number / MIME validation (never trust the client).
		$detected = $this->detect_type( $file['tmp_name'], $ext );
		if ( is_wp_error( $detected ) ) {
			return $detected;
		}

		$dir = self::uploads_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$hash   = hash_file( 'sha256', $file['tmp_name'] );
		$stored = $dir . '/' . $ext . '-' . substr( $hash, 0, 12 ) . '-' . gmdate( 'YmdHis' ) . '.' . $ext;

		// move_uploaded_file() keeps the file outside the media library and
		// out of any public URL structure.
		if ( ! @move_uploaded_file( $file['tmp_name'], $stored ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new WP_Error( 'htech_upload', __( 'The file could not be saved to the uploads directory. Check folder permissions.', 'htech-ai-assistant' ) );
		}

		@chmod( $stored, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		$document_id = $this->create_document(
			array(
				'title'        => $this->title_from_name( $name ),
				'file_name'    => $name,
				'stored_path'  => $stored,
				'file_type'    => $ext,
				'file_size'    => $size,
				'sha256'       => $hash,
				'source_type'  => 'upload',
				'status'       => 'processing',
				'created_by'   => get_current_user_id(),
			)
		);

		if ( is_wp_error( $document_id ) ) {
			@unlink( $stored ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return $document_id;
		}

		return $this->process_document( $document_id, $client_text );
	}

	/**
	 * Verify the real file signature matches the claimed extension.
	 *
	 * @param string $path Temporary path.
	 * @param string $ext  Extension.
	 * @return true|WP_Error
	 */
	private function detect_type( $path, $ext ) {
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$head   = $handle ? fread( $handle, 8 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		if ( $handle ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}

		$is_pdf = '%PDF' === substr( (string) $head, 0, 4 );
		// ZIP local-file header (also matches DOCX). Compare the first four
		// bytes only — $head holds eight.
		$is_zip = "PK\x03\x04" === substr( (string) $head, 0, 4 );

		switch ( $ext ) {
			case 'pdf':
				if ( ! $is_pdf ) {
					return new WP_Error( 'htech_upload', __( 'That file is not a valid PDF (its contents do not match a PDF signature).', 'htech-ai-assistant' ) );
				}
				break;

			case 'docx':
				if ( ! $is_zip ) {
					return new WP_Error( 'htech_upload', __( 'That file is not a valid DOCX document. Legacy .doc files are not supported — save as .docx.', 'htech-ai-assistant' ) );
				}
				if ( class_exists( 'ZipArchive' ) ) {
					$zip = new ZipArchive();
					if ( true === $zip->open( $path ) ) {
						$has_doc = false !== $zip->locateName( 'word/document.xml' );
						$zip->close();
						if ( ! $has_doc ) {
							return new WP_Error( 'htech_upload', __( 'That ZIP file is not a Word document (no word/document.xml inside).', 'htech-ai-assistant' ) );
						}
					}
				}
				break;

			default:
				// Text formats must not contain PHP tags – blocks polyglot uploads.
				$sample = file_get_contents( $path, false, null, 0, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( preg_match( '/<\?(php|=)/i', (string) $sample ) ) {
					return new WP_Error( 'htech_upload', __( 'The file contains PHP code and has been rejected for security reasons.', 'htech-ai-assistant' ) );
				}
				break;
		}

		return true;
	}

	/**
	 * Human-readable upload error.
	 *
	 * @param int $code PHP upload error code.
	 * @return string
	 */
	private function upload_error_message( $code ) {
		switch ( $code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'The file exceeds the maximum upload size allowed by this server (upload_max_filesize / post_max_size in php.ini).', 'htech-ai-assistant' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'The upload was interrupted. Please try again.', 'htech-ai-assistant' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was received.', 'htech-ai-assistant' );
			case UPLOAD_ERR_NO_TMP_DIR:
				return __( 'The server has no temporary folder for uploads. Contact your host.', 'htech-ai-assistant' );
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'The server could not write the file to disk. Contact your host.', 'htech-ai-assistant' );
			default:
				return __( 'The upload failed. Please try again.', 'htech-ai-assistant' );
		}
	}

	/**
	 * Turn a file name into a readable title.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	private function title_from_name( $name ) {
		$base = pathinfo( $name, PATHINFO_FILENAME );
		$base = str_replace( array( '-', '_' ), ' ', $base );
		$base = trim( preg_replace( '/\s+/', ' ', $base ) );
		return mb_substr( ucwords( $base ), 0, 190 ) ?: __( 'Untitled document', 'htech-ai-assistant' );
	}

	/**
	 * Insert a document row.
	 *
	 * @param array $data Data.
	 * @return int|WP_Error
	 */
	public function create_document( array $data ) {
		global $wpdb;

		$row = array(
			'title'        => mb_substr( (string) ( $data['title'] ?? '' ), 0, 190 ),
			'file_name'    => mb_substr( (string) ( $data['file_name'] ?? '' ), 0, 190 ),
			'stored_path'  => (string) ( $data['stored_path'] ?? '' ),
			'file_type'    => (string) ( $data['file_type'] ?? '' ),
			'file_size'    => (int) ( $data['file_size'] ?? 0 ),
			'sha256'       => (string) ( $data['sha256'] ?? '' ),
			'status'       => (string) ( $data['status'] ?? 'pending' ),
			'source_type'  => (string) ( $data['source_type'] ?? 'upload' ),
			'created_by'   => (int) ( $data['created_by'] ?? get_current_user_id() ),
			'created_at'   => current_time( 'mysql', true ),
			'is_active'    => 1,
			'chunk_count'  => 0,
		);

		$ok = $wpdb->insert( HTECH_AI_Database::documents_table(), $row );
		if ( ! $ok ) {
			return new WP_Error( 'htech_db', __( 'The document record could not be saved to the database.', 'htech-ai-assistant' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Extract + index a document using the server-side extractor.
	 *
	 * @param int    $document_id Document id.
	 * @param string $client_text Optional text already extracted in the browser.
	 * @return array Document row + flags.
	 */
	public function process_document( $document_id, $client_text = '' ) {
		$document = $this->get_document( $document_id );
		if ( ! $document ) {
			return array(
				'ok'      => false,
				'message' => __( 'Document not found.', 'htech-ai-assistant' ),
			);
		}

		$text   = '';
		$method = '';

		if ( '' !== trim( (string) $client_text ) ) {
			$text   = (string) $client_text;
			$method = 'browser';
		} else {
			$result = HTECH_AI_Text_Extractor::from_file( $document['stored_path'], $document['file_type'] );

			if ( ! $result['ok'] ) {
				$this->set_status( $document_id, 'needs_browser', $result['error'] );
				return array(
					'ok'                 => false,
					'needs_browser_parse' => true,
					'document_id'        => (int) $document_id,
					'message'            => $result['error'],
				);
			}

			$text   = $result['text'];
			$method = $result['method'];
		}

		$text = HTECH_AI_Chunker::clean( $text );

		// Hard cap: keeps indexing (and shared-host memory) predictable.
		if ( mb_strlen( $text ) > 2000000 ) {
			$text = mb_substr( $text, 0, 2000000 );
			$note = __( 'Only the first 2,000,000 characters were indexed.', 'htech-ai-assistant' );
		} else {
			$note = '';
		}

		if ( mb_strlen( $text ) < 20 ) {
			$this->set_status( $document_id, 'failed', __( 'No readable text was found in this file. If it is a scanned PDF, run OCR first or upload a text-based copy.', 'htech-ai-assistant' ) );
			return array(
				'ok'          => false,
				'document_id' => (int) $document_id,
				'message'     => __( 'No readable text was found in this file. If it is a scanned PDF, run OCR first or upload a text-based copy.', 'htech-ai-assistant' ),
			);
		}

		// CSV/JSON are row/tabular data: give every record its own chunk so a
		// question about one product retrieves just that row.
		$row_based = in_array( $document['file_type'], array( 'csv' ), true );
		$chunks    = HTECH_AI_Chunker::split( $text, null, null, $row_based );

		global $wpdb;
		$chunks_table = HTECH_AI_Database::chunks_table();
		$wpdb->delete( $chunks_table, array( 'document_id' => (int) $document_id ) );
		HTECH_AI_Retrieval::flush_corpus_cache();

		$label = $document['title'] ? $document['title'] : $document['file_name'];

		foreach ( $chunks as $index => $chunk ) {
			htech_ai()->knowledge->insert_chunk(
				array(
					'document_id'  => (int) $document_id,
					'kb_entry_id'  => 0,
					'source_type'  => 'document',
					'source_label' => $label,
					'chunk_index'  => (int) $index,
					'content'      => $chunk,
				)
			);
		}

		$wpdb->update(
			HTECH_AI_Database::documents_table(),
			array(
				'status'         => 'ready',
				'status_message' => $note,
				'chunk_count'    => count( $chunks ),
				'char_count'     => mb_strlen( $text ),
				'extractor'      => $method,
				'processed_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $document_id )
		);

		HTECH_AI_Retrieval::bump_version();

		return array(
			'ok'          => true,
			'document_id' => (int) $document_id,
			'chunks'      => count( $chunks ),
			'characters'  => mb_strlen( $text ),
			'extractor'   => $method,
			'message'     => sprintf(
				/* translators: 1: chunk count, 2: extractor */
				__( 'Processed into %1$d searchable chunks using the %2$s extractor.', 'htech-ai-assistant' ),
				count( $chunks ),
				$method
			),
		);
	}

	/**
	 * Update status/message.
	 *
	 * @param int    $id      Document id.
	 * @param string $status  Status.
	 * @param string $message Message.
	 * @return void
	 */
	public function set_status( $id, $status, $message = '' ) {
		global $wpdb;
		$wpdb->update(
			HTECH_AI_Database::documents_table(),
			array(
				'status'         => $status,
				'status_message' => mb_substr( wp_strip_all_tags( (string) $message ), 0, 2000 ),
			),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Fetch one document.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public function get_document( $id ) {
		global $wpdb;
		$table = HTECH_AI_Database::documents_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * List documents.
	 *
	 * @param array $args search/status/paged/per_page.
	 * @return array{items:array,total:int,pages:int}
	 */
	public function get_documents( array $args = array() ) {
		global $wpdb;
		$table = HTECH_AI_Database::documents_table();

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'status'   => '',
				'paged'    => 1,
				'per_page' => 20,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['search'] ) {
			$where[]  = '(title LIKE %s OR file_name LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		if ( '' !== $args['status'] ) {
			if ( 'active' === $args['status'] ) {
				$where[] = 'is_active = 1';
			} elseif ( 'inactive' === $args['status'] ) {
				$where[] = 'is_active = 0';
			} else {
				$where[]  = 'status = %s';
				$params[] = $args['status'];
			}
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = max( 0, ( (int) $args['paged'] - 1 ) * (int) $args['per_page'] );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

		$sql   = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$items = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( (int) $args['per_page'], $offset ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, (int) $args['per_page'] ) ),
		);
	}

	/**
	 * Toggle active flag and refresh retrieval availability.
	 *
	 * @param int $id Id.
	 * @return bool New state.
	 */
	public function toggle_active( $id ) {
		$document = $this->get_document( $id );
		if ( ! $document ) {
			return false;
		}

		global $wpdb;
		$new = empty( $document['is_active'] ) ? 1 : 0;

		$wpdb->update(
			HTECH_AI_Database::documents_table(),
			array( 'is_active' => $new ),
			array( 'id' => (int) $id )
		);

		HTECH_AI_Retrieval::bump_version();
		return (bool) $new;
	}

	/**
	 * Delete a document, its file and all its chunks.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public function delete_document( $id ) {
		$document = $this->get_document( $id );
		if ( ! $document ) {
			return false;
		}

		global $wpdb;
		$wpdb->delete( HTECH_AI_Database::chunks_table(), array( 'document_id' => (int) $id ) );
		HTECH_AI_Retrieval::flush_corpus_cache();
		$wpdb->delete( HTECH_AI_Database::documents_table(), array( 'id' => (int) $id ) );

		if ( ! empty( $document['stored_path'] ) && $this->is_inside_uploads( $document['stored_path'] ) ) {
			@unlink( $document['stored_path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		HTECH_AI_Retrieval::bump_version();
		return true;
	}

	/**
	 * Confirm a path is inside our private upload folder before touching it.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	public function is_inside_uploads( $path ) {
		$dir = self::uploads_dir();
		if ( is_wp_error( $dir ) ) {
			return false;
		}
		$real_dir  = realpath( $dir );
		$real_file = realpath( $path );
		if ( ! $real_dir || ! $real_file ) {
			return false;
		}
		return 0 === strpos( $real_file, $real_dir );
	}

	/**
	 * Replace a document's file, keeping its record id.
	 *
	 * @param int   $id   Document id.
	 * @param array $file Uploaded file.
	 * @return array|WP_Error
	 */
	public function replace_file( $id, array $file, $client_text = '' ) {
		$document = $this->get_document( $id );
		if ( ! $document ) {
			return new WP_Error( 'htech_doc', __( 'Document not found.', 'htech-ai-assistant' ) );
		}

		$result = $this->handle_upload( $file, $client_text );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// handle_upload created a new row – fold it into the existing one.
		$new_id   = (int) $result['document_id'];
		$new_doc  = $this->get_document( $new_id );

		if ( $new_doc ) {
			global $wpdb;
			$wpdb->update(
				HTECH_AI_Database::chunks_table(),
				array( 'document_id' => (int) $id ),
				array( 'document_id' => $new_id )
			);
			$wpdb->delete( HTECH_AI_Database::documents_table(), array( 'id' => $new_id ) );

			if ( ! empty( $document['stored_path'] ) && $this->is_inside_uploads( $document['stored_path'] ) ) {
				@unlink( $document['stored_path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}

			$wpdb->update(
				HTECH_AI_Database::documents_table(),
				array(
					'file_name'      => $new_doc['file_name'],
					'stored_path'    => $new_doc['stored_path'],
					'file_type'      => $new_doc['file_type'],
					'file_size'      => $new_doc['file_size'],
					'sha256'         => $new_doc['sha256'],
					'status'         => $new_doc['status'],
					'status_message' => $new_doc['status_message'],
					'chunk_count'    => $new_doc['chunk_count'],
					'char_count'     => $new_doc['char_count'],
					'extractor'      => $new_doc['extractor'],
					'processed_at'   => $new_doc['processed_at'],
				),
				array( 'id' => (int) $id )
			);
		}

		HTECH_AI_Retrieval::bump_version();

		return array(
			'ok'                  => ! empty( $result['ok'] ),
			'document_id'         => (int) $id,
			'needs_browser_parse' => ! empty( $result['needs_browser_parse'] ),
			'message'             => (string) ( $result['message'] ?? '' ),
			'chunks'              => (int) ( $result['chunks'] ?? 0 ),
		);
	}

	/**
	 * Delete every document and chunk (privacy tools).
	 *
	 * @param bool $keep_files Whether to keep files on disk.
	 * @return int Number of documents removed.
	 */
	public function delete_all( $keep_files = false ) {
		global $wpdb;

		$documents = $this->get_documents( array( 'per_page' => 10000 ) );

		foreach ( $documents['items'] as $document ) {
			if ( $keep_files ) {
				$wpdb->delete( HTECH_AI_Database::documents_table(), array( 'id' => (int) $document['id'] ) );
			} else {
				$this->delete_document( (int) $document['id'] );
			}
		}

		$wpdb->delete( HTECH_AI_Database::chunks_table(), array( 'source_type' => 'document' ) );
		HTECH_AI_Retrieval::flush_corpus_cache();
		HTECH_AI_Retrieval::bump_version();

		return count( $documents['items'] );
	}

	/**
	 * Total bytes used by stored documents.
	 *
	 * @return int
	 */
	public function total_size() {
		global $wpdb;
		$table = HTECH_AI_Database::documents_table();
		return (int) $wpdb->get_var( "SELECT COALESCE(SUM(file_size),0) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
