<?php
/**
 * Admin controller: menus, assets, form handling, exports.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Admin
 */
class HTECH_AI_Admin {

	/**
	 * Capability needed for the dashboard.
	 *
	 * @var string
	 */
	private $cap;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		$this->cap = HTECH_AI_Security::capability();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ) );
		add_action( 'admin_post_htech_ai_export_leads', array( $this, 'export_leads' ) );
		add_action( 'admin_post_htech_ai_export_conversation', array( $this, 'export_conversation' ) );
		add_action( 'admin_post_htech_ai_lead_status', array( $this, 'update_lead_status' ) );
		add_action( 'admin_post_htech_ai_kb_export', array( $this, 'export_knowledge' ) );
		add_action( 'admin_footer', array( $this, 'render_toast_root' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
	}

	/* ---------------------------------------------------------------------
	 * Menu
	 * ------------------------------------------------------------------ */

	/**
	 * Register the admin menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu() {
		$pages = array(
			'htech-ai-assistant'   => array( __( 'Overview', 'htech-ai-assistant' ), 'view-overview' ),
			'htech-ai-settings'    => array( __( 'Chatbot Settings', 'htech-ai-assistant' ), 'view-settings' ),
			'htech-ai-knowledge'   => array( __( 'Knowledge Base', 'htech-ai-assistant' ), 'view-knowledge' ),
			'htech-ai-documents'   => array( __( 'Documents', 'htech-ai-assistant' ), 'view-documents' ),
			'htech-ai-appearance'  => array( __( 'Appearance & Branding', 'htech-ai-assistant' ), 'view-appearance' ),
			'htech-ai-conversations' => array( __( 'Conversations', 'htech-ai-assistant' ), 'view-conversations' ),
			'htech-ai-leads'       => array( __( 'Leads', 'htech-ai-assistant' ), 'view-leads' ),
			'htech-ai-installation' => array( __( 'Installation', 'htech-ai-assistant' ), 'view-installation' ),
			'htech-ai-general'     => array( __( 'General Settings', 'htech-ai-assistant' ), 'view-general' ),
		);

		add_menu_page(
			__( 'Free Website Chatbot AI by HTECH', 'htech-ai-assistant' ),
			// Short label for the WordPress admin sidebar; the long product name
			// wraps to three cramped lines there.
			__( 'Website Chatbot AI', 'htech-ai-assistant' ),
			$this->cap,
			'htech-ai-assistant',
			array( $this, 'render_page' ),
			'dashicons-format-chat',
			58
		);

		foreach ( $pages as $slug => $config ) {
			add_submenu_page(
				'htech-ai-assistant',
				$config[0],
				$config[0],
				$this->cap,
				$slug,
				array( $this, 'render_page' )
			);
		}

		// Keeps the first submenu label meaningful instead of repeating the parent.
		global $submenu;
		if ( isset( $submenu['htech-ai-assistant'][0] ) ) {
			$submenu['htech-ai-assistant'][0][0] = __( 'Overview', 'htech-ai-assistant' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
	}

	/**
	 * Render the current admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		HTECH_AI_Security::require_manage();

		$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'htech-ai-assistant'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$views = array(
			'htech-ai-assistant'     => 'view-overview',
			'htech-ai-settings'      => 'view-settings',
			'htech-ai-knowledge'     => 'view-knowledge',
			'htech-ai-documents'     => 'view-documents',
			'htech-ai-appearance'    => 'view-appearance',
			'htech-ai-conversations' => 'view-conversations',
			'htech-ai-leads'         => 'view-leads',
			'htech-ai-installation'  => 'view-installation',
			'htech-ai-general'       => 'view-general',
		);

		$view = isset( $views[ $page ] ) ? $views[ $page ] : 'view-overview';
		$file = HTECH_AI_DIR . 'admin/views/' . $view . '.php';

		echo '<div class="wrap htech-ai-wrap" id="htech-ai-app">';

		if ( file_exists( $file ) ) {
			include $file;
		} else {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'That screen could not be loaded.', 'htech-ai-assistant' ) );
		}

		echo '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Enqueue admin assets only on plugin screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'htech-ai' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_style(
			'htech-ai-admin',
			HTECH_AI_URL . 'admin/assets/css/admin.css',
			array( 'wp-color-picker' ),
			HTECH_AI_VERSION
		);

		wp_enqueue_script(
			'htech-ai-admin',
			HTECH_AI_URL . 'admin/assets/js/admin.js',
			array( 'jquery', 'wp-color-picker', 'wp-i18n' ),
			HTECH_AI_VERSION,
			true
		);

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$data = array(
			'restUrl'      => esc_url_raw( rest_url( 'htech-ai/v1' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'page'         => $page,
			'vendorUrl'    => esc_url_raw( HTECH_AI_URL . 'vendor/' ),
			'maxUpload'    => HTECH_AI_Document_Manager::max_upload_bytes(),
			'maxFiles'     => (int) HTECH_AI_Settings::get( 'max_files_per_upload', 5 ),
			'extensions'   => HTECH_AI_Document_Manager::enabled_extensions(),
			'chunkSize'    => (int) HTECH_AI_Settings::get( 'chunk_size', 900 ),
			'adminUrl'     => esc_url_raw( admin_url( 'admin.php' ) ),
			'i18n'         => array(
				'uploading'     => __( 'Uploading…', 'htech-ai-assistant' ),
				'extracting'    => __( 'Extracting text in your browser…', 'htech-ai-assistant' ),
				'indexing'      => __( 'Indexing…', 'htech-ai-assistant' ),
				'done'          => __( 'Done', 'htech-ai-assistant' ),
				'failed'        => __( 'Failed', 'htech-ai-assistant' ),
				'saved'         => __( 'Saved', 'htech-ai-assistant' ),
				'error'         => __( 'Something went wrong.', 'htech-ai-assistant' ),
				'confirmDelete' => __( 'Delete this permanently? This cannot be undone.', 'htech-ai-assistant' ),
				'noResults'     => __( 'No matching business knowledge was found for that question.', 'htech-ai-assistant' ),
				'searching'     => __( 'Searching…', 'htech-ai-assistant' ),
				'copied'        => __( 'Copied to clipboard.', 'htech-ai-assistant' ),
				'selectLogo'    => __( 'Select logo', 'htech-ai-assistant' ),
				'useLogo'       => __( 'Use this image', 'htech-ai-assistant' ),
				'pdfWorker'     => __( 'Loading PDF parser…', 'htech-ai-assistant' ),
				'tooManyFiles'  => __( 'Too many files selected for one upload.', 'htech-ai-assistant' ),
				'fileTooLarge'  => __( 'That file is larger than the configured limit.', 'htech-ai-assistant' ),
			),
		);

		wp_localize_script( 'htech-ai-admin', 'HTECH_AI_ADMIN', $data );

		if ( 'htech-ai-documents' === $page ) {
			wp_enqueue_script(
				'htech-ai-processor',
				HTECH_AI_URL . 'admin/assets/js/htech-processor.js',
				array( 'htech-ai-admin' ),
				HTECH_AI_VERSION,
				true
			);
		}
	}

	/**
	 * Redirect to the Overview screen once, right after activation.
	 *
	 * @return void
	 */
	public function maybe_redirect_after_activation() {
		if ( ! get_transient( 'htech_ai_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'htech_ai_activation_redirect' );

		if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) || ! current_user_can( $this->cap ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=htech-ai-assistant&welcome=1' ) );
		exit;
	}

	/**
	 * Queue a one-shot admin notice for the current user.
	 *
	 * @param string $message Message text.
	 * @param string $type    success|error|warning|info.
	 * @return void
	 */
	private function flash( $message, $type = 'success' ) {
		$key      = 'htech_ai_flash_' . get_current_user_id();
		$messages = get_transient( $key );

		if ( ! is_array( $messages ) ) {
			$messages = array();
		}

		$messages[] = array(
			'message' => (string) $message,
			'type'    => in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info',
		);

		set_transient( $key, $messages, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Print and clear queued notices on this plugin's screens only.
	 *
	 * @return void
	 */
	public function render_notices() {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}

		$key      = 'htech_ai_flash_' . get_current_user_id();
		$messages = get_transient( $key );

		if ( ! is_array( $messages ) || ! $messages ) {
			return;
		}

		delete_transient( $key );

		foreach ( $messages as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['message'] )
			);
		}
	}

	/**
	 * Whether the current request is one of this plugin's admin screens.
	 *
	 * @return bool
	 */
	private function is_plugin_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return 0 === strpos( $page, 'htech-ai' );
	}

	/**
	 * Container for JS toasts.
	 *
	 * @return void
	 */
	public function render_toast_root() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'htech-ai' ) ) {
			return;
		}
		echo '<div id="htech-ai-toasts" class="htech-ai-toasts" aria-live="polite"></div>';
	}

	/* ---------------------------------------------------------------------
	 * Exports & form actions
	 * ------------------------------------------------------------------ */

	/**
	 * Verify an admin_post request (nonce + capability).
	 *
	 * @param string $action Action name.
	 * @return void
	 */
	private function verify_action( $action ) {
		HTECH_AI_Security::require_manage();
		check_admin_referer( $action );
	}

	/**
	 * Stream leads as CSV.
	 *
	 * @return void
	 */
	public function export_leads() {
		$this->verify_action( 'htech_ai_export_leads' );

		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . HTECH_AI_Database::leads_table() . ' ORDER BY created_at DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$filename = 'htech-ai-leads-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		/*
		 * Neutralise spreadsheet formulas.
		 *
		 * A visitor can type anything into the lead form, including
		 * "=HYPERLINK(...)" or "=cmd|...". Excel and Sheets execute those on
		 * open, so exported values are prefixed with an apostrophe whenever they
		 * start with a formula character. This is the standard CSV-injection
		 * mitigation and it does not change the stored data.
		 */
		$safe = static function ( $value ) {
			$value = (string) $value;

			if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
				return "'" . $value;
			}

			return $value;
		};

		fputcsv( $out, array( 'ID', 'Created (UTC)', 'Name', 'Email', 'Phone', 'Service', 'Description', 'Status', 'Conversation' ) );

		foreach ( (array) $rows as $row ) {
			fputcsv(
				$out,
				array(
					(int) $row['id'],
					$safe( $row['created_at'] ),
					$safe( $row['name'] ),
					$safe( $row['email'] ),
					$safe( $row['phone'] ),
					$safe( $row['service'] ),
					$safe( $row['description'] ),
					$safe( $row['status'] ),
					(int) $row['conversation_id'],
				)
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Export one conversation as plain text.
	 *
	 * @return void
	 */
	public function export_conversation() {
		$this->verify_action( 'htech_ai_export_conversation' );

		$id           = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$conversation = HTECH_AI_Database::get_conversation( $id );

		if ( ! $conversation ) {
			wp_die( esc_html__( 'Conversation not found.', 'htech-ai-assistant' ) );
		}

		$messages = HTECH_AI_Database::get_messages( $id );

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=htech-ai-conversation-' . $id . '.txt' );

		echo esc_html(
			sprintf(
				/* translators: 1: conversation id, 2: created date */
				__( "Free Website Chatbot AI by HTECH — conversation #%1\$d\nCreated: %2\$s\n\n", 'htech-ai-assistant' ),
				$id,
				$conversation['created_at']
			)
		);

		foreach ( $messages as $message ) {
			echo esc_html( strtoupper( $message['role'] ) . ' [' . $message['created_at'] . ']' ) . "\n";
			echo esc_html( $message['content'] ) . "\n\n";
		}

		exit;
	}

	/**
	 * Update a lead's status (new / contacted / closed).
	 *
	 * @return void
	 */
	public function update_lead_status() {
		$this->verify_action( 'htech_ai_lead_status' );

		global $wpdb;
		$id     = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'new';

		if ( ! $id || ! in_array( $status, array( 'new', 'contacted', 'closed' ), true ) ) {
			$this->flash( __( 'That lead status is not valid.', 'htech-ai-assistant' ), 'error' );
		} else {
			$updated = $wpdb->update( HTECH_AI_Database::leads_table(), array( 'status' => $status ), array( 'id' => $id ) );

			if ( false === $updated ) {
				$this->flash( __( 'The lead could not be updated.', 'htech-ai-assistant' ), 'error' );
			} else {
				$this->flash( __( 'Lead status updated.', 'htech-ai-assistant' ), 'success' );
			}
		}

		wp_safe_redirect( add_query_arg( 'htech_notice', '1', wp_get_referer() ?: admin_url( 'admin.php?page=htech-ai-leads' ) ) );
		exit;
	}

	/**
	 * Download the knowledge base as JSON.
	 *
	 * @return void
	 */
	public function export_knowledge() {
		$this->verify_action( 'htech_ai_kb_export' );

		$json = htech_ai()->knowledge->export_json();

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=htech-ai-knowledge-' . gmdate( 'Y-m-d' ) . '.json' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw JSON download.
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Small view helpers used by the templates
	 * ------------------------------------------------------------------ */

	/**
	 * Open the dashboard shell (sidebar + topbar).
	 *
	 * @param string $active   Active menu slug.
	 * @param string $title    Page title.
	 * @param string $subtitle Subtitle.
	 * @param string $actions  Pre-escaped action HTML.
	 * @return void
	 */
	public static function shell_start( $active, $title, $subtitle = '', $actions = '' ) {
		$menu = array(
			'htech-ai-assistant'     => array( __( 'Overview', 'htech-ai-assistant' ), 'dashicons-chart-area' ),
			'htech-ai-settings'      => array( __( 'Chatbot Settings', 'htech-ai-assistant' ), 'dashicons-admin-generic' ),
			'htech-ai-knowledge'     => array( __( 'Knowledge Base', 'htech-ai-assistant' ), 'dashicons-book-alt' ),
			'htech-ai-documents'     => array( __( 'Documents', 'htech-ai-assistant' ), 'dashicons-media-document' ),
			'htech-ai-appearance'    => array( __( 'Appearance', 'htech-ai-assistant' ), 'dashicons-art' ),
			'htech-ai-conversations' => array( __( 'Conversations', 'htech-ai-assistant' ), 'dashicons-format-chat' ),
			'htech-ai-leads'         => array( __( 'Leads', 'htech-ai-assistant' ), 'dashicons-email-alt' ),
			'htech-ai-installation'  => array( __( 'Installation', 'htech-ai-assistant' ), 'dashicons-share' ),
			'htech-ai-general'       => array( __( 'General Settings', 'htech-ai-assistant' ), 'dashicons-admin-tools' ),
		);

		$widget_on = HTECH_AI_Settings::is_enabled( 'widget_enabled' );
		?>
		<div class="htech-shell">
			<aside class="htech-sidebar">
				<div class="htech-sidebar__brand">
					<div class="htech-sidebar__mark">HC</div>
					<div class="htech-sidebar__title">
						<?php esc_html_e( 'Free Website Chatbot AI by HTECH', 'htech-ai-assistant' ); ?>
						<span><?php esc_html_e( 'Self-hosted chatbot', 'htech-ai-assistant' ); ?></span>
					</div>
				</div>

				<nav class="htech-nav" aria-label="<?php esc_attr_e( 'Plugin sections', 'htech-ai-assistant' ); ?>">
					<?php foreach ( $menu as $slug => $item ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="<?php echo $slug === $active ? 'is-active' : ''; ?>">
							<span class="dashicons <?php echo esc_attr( $item[1] ); ?>"></span>
							<?php echo esc_html( $item[0] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="htech-sidebar__foot">
					<strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong><br />
					<?php
					printf(
						/* translators: %s: plugin version */
						esc_html__( 'Version %s · independent install', 'htech-ai-assistant' ),
						esc_html( HTECH_AI_VERSION )
					);
					?>
				</div>
			</aside>

			<main class="htech-main">
				<header class="htech-topbar">
					<div>
						<h1>
							<?php echo esc_html( $title ); ?>
							<span class="htech-pill <?php echo $widget_on ? 'htech-pill--on' : 'htech-pill--off'; ?>">
								<span class="htech-dot"></span>
								<?php echo $widget_on ? esc_html__( 'Widget live', 'htech-ai-assistant' ) : esc_html__( 'Widget disabled', 'htech-ai-assistant' ); ?>
							</span>
						</h1>
						<?php if ( $subtitle ) : ?>
							<p><?php echo esc_html( $subtitle ); ?></p>
						<?php endif; ?>
					</div>
					<div class="htech-topbar__actions">
						<?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* by callers. ?>
					</div>
				</header>
		<?php
	}

	/**
	 * Close the dashboard shell.
	 *
	 * @return void
	 */
	public static function shell_end() {
		echo '</main></div>';
	}

	/**
	 * Render a settings text field.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 * @param string $help  Help text.
	 * @param array  $args  type, placeholder, class, rows.
	 * @return void
	 */
	public static function field( $key, $label, $help = '', array $args = array() ) {
		$type        = $args['type'] ?? 'text';
		$value       = HTECH_AI_Settings::get( $key, '' );
		$name        = HTECH_AI_Settings::OPTION . '[' . $key . ']';
		$placeholder = $args['placeholder'] ?? '';
		$class       = $args['class'] ?? '';
		$id          = 'htech-field-' . $key;

		echo '<div class="htech-ai-field ' . esc_attr( $class ) . '">';
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';

		if ( 'textarea' === $type ) {
			printf(
				'<textarea id="%1$s" name="%2$s" rows="%3$d" placeholder="%4$s" class="large-text">%5$s</textarea>',
				esc_attr( $id ),
				esc_attr( $name ),
				(int) ( $args['rows'] ?? 5 ),
				esc_attr( $placeholder ),
				esc_textarea( (string) $value )
			);
		} elseif ( 'select' === $type ) {
			printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
			foreach ( (array) ( $args['options'] ?? array() ) as $option_value => $option_label ) {
				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $option_value ),
					selected( (string) $value, (string) $option_value, false ),
					esc_html( $option_label )
				);
			}
			echo '</select>';
		} elseif ( 'number' === $type ) {
			printf(
				'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="%6$s" class="small-text" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) $value ),
				esc_attr( (string) ( $args['min'] ?? 0 ) ),
				esc_attr( (string) ( $args['max'] ?? 9999 ) ),
				esc_attr( (string) ( $args['step'] ?? 1 ) )
			);
		} elseif ( 'color' === $type ) {
			/*
			 * A native colour swatch plus a hex text field. The swatch always
			 * works (no jQuery/iris dependency), and the hex field lets the
			 * value be pasted from a brand guide. The hidden input is what gets
			 * submitted; the two visible controls are kept in sync by admin.js.
			 */
			printf(
				'<span class="htech-color-field" data-default="%5$s">'
					. '<input type="color" class="htech-color-field__swatch" value="%3$s" aria-label="%6$s" />'
					. '<input type="text" class="htech-color-field__hex regular-text" id="%1$s" value="%3$s" '
					. 'maxlength="7" spellcheck="false" pattern="#[0-9A-Fa-f]{6}" aria-label="%7$s" />'
					. '<input type="hidden" name="%2$s" value="%3$s" />'
					. '</span>',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value ),
				esc_attr( (string) $value ),
				esc_attr( $args['default'] ?? '#000000' ),
				esc_attr__( 'Choose colour', 'htech-ai-assistant' ),
				esc_attr__( 'Colour hex value', 'htech-ai-assistant' )
			);
		} elseif ( 'checkbox' === $type ) {
			printf(
				'<input type="hidden" name="%2$s" value="0" /><label class="htech-ai-toggle"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s /><span></span></label>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( (bool) $value, true, false )
			);
		} else {
			printf(
				'<input type="%4$s" id="%1$s" name="%2$s" value="%3$s" placeholder="%5$s" class="regular-text" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) $value ),
				esc_attr( $type ),
				esc_attr( $placeholder )
			);
		}

		if ( $help ) {
			echo '<p class="htech-ai-help">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Standard hidden fields for a settings form.
	 *
	 * @param string $group Settings group.
	 * @return void
	 */
	public static function settings_form_open( $group = 'htech_ai_settings' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '" class="htech-ai-form">';
		settings_fields( $group );
	}

	/**
	 * Submit button.
	 *
	 * @param string $label Label.
	 * @return void
	 */
	public static function settings_form_close( $label = '' ) {
		$label = $label ? $label : __( 'Save changes', 'htech-ai-assistant' );
		submit_button( $label, 'primary htech-ai-save', 'submit', false );
		echo '</form>';
	}
}
