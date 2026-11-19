<?php
/**
 * General Settings: privacy, retention, uploads, tools, data deletion.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

$s = HTECH_AI_Settings::all();

$uploads = HTECH_AI_Document_Manager::uploads_dir();
$uploads_ok = ! is_wp_error( $uploads );

HTECH_AI_Admin::shell_start(
	'htech-ai-general',
	__( 'General Settings', 'htech-ai-assistant' ),
	__( 'Privacy, retention, upload limits and maintenance tools.', 'htech-ai-assistant' )
);
?>

<div class="htech-tabs" data-htech-tabs>
	<button type="button" class="is-active" data-tab="privacy"><?php esc_html_e( 'Privacy & retention', 'htech-ai-assistant' ); ?></button>
	<button type="button" data-tab="uploads"><?php esc_html_e( 'Uploads', 'htech-ai-assistant' ); ?></button>
	<button type="button" data-tab="tools"><?php esc_html_e( 'Maintenance', 'htech-ai-assistant' ); ?></button>
	<button type="button" data-tab="data"><?php esc_html_e( 'Delete data', 'htech-ai-assistant' ); ?></button>
	<button type="button" data-tab="system"><?php esc_html_e( 'System status', 'htech-ai-assistant' ); ?></button>
</div>

<?php HTECH_AI_Admin::settings_form_open(); ?>

<!-- ============================= PRIVACY ============================== -->
<div class="htech-tab-panel" data-tab-panel="privacy">
	<div class="htech-grid htech-grid--2">
		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-privacy"></span> <?php esc_html_e( 'Conversation storage', 'htech-ai-assistant' ); ?></h2>
					<p><?php esc_html_e( 'Storing transcripts lets you review what visitors ask and improve the knowledge base. Not storing them is the most privacy-preserving option.', 'htech-ai-assistant' ); ?></p>
				</div>
			</div>

			<div class="htech-toggle-row">
				<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[store_conversations]" value="0" />
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Store conversations', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Messages and timestamps are saved in your own database. IP addresses are hashed, never stored raw.', 'htech-ai-assistant' ); ?></span>
				</div>
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-store_conversations" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[store_conversations]" value="1" <?php checked( (bool) $s['store_conversations'], true ); ?> />
					<span></span>
				</label>
			</div>

			<?php
			HTECH_AI_Admin::field(
				'retention_days',
				__( 'Delete conversations after (days)', 'htech-ai-assistant' ),
				__( 'A daily WP-Cron job removes anything older. Set 0 to keep indefinitely — not recommended. 30–90 days is typical.', 'htech-ai-assistant' ),
				array( 'type' => 'number', 'min' => 0, 'max' => 3650 )
			);
			HTECH_AI_Admin::field(
				'greeting_name',
				__( 'Ask visitors for their name', 'htech-ai-assistant' ),
				__( 'Optional. Off by default to minimise data collection.', 'htech-ai-assistant' ),
				array( 'type' => 'checkbox' )
			);
			?>
		</div>

		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'What is collected', 'htech-ai-assistant' ); ?></h2>
				</div>
			</div>

			<dl class="htech-kv">
				<dt><?php esc_html_e( 'Visitor messages', 'htech-ai-assistant' ); ?></dt>
				<dd><?php esc_html_e( 'Only when conversation storage is on.', 'htech-ai-assistant' ); ?></dd>

				<dt><?php esc_html_e( 'IP address', 'htech-ai-assistant' ); ?></dt>
				<dd><?php esc_html_e( 'Never stored. A salted hash is kept for rate limiting and duplicate-lead detection.', 'htech-ai-assistant' ); ?></dd>

				<dt><?php esc_html_e( 'Browser / user agent', 'htech-ai-assistant' ); ?></dt>
				<dd><?php esc_html_e( 'Shortened to 255 characters and stored with the conversation for support purposes.', 'htech-ai-assistant' ); ?></dd>

				<dt><?php esc_html_e( 'Page URL', 'htech-ai-assistant' ); ?></dt>
				<dd><?php esc_html_e( 'Which page the chat was opened on.', 'htech-ai-assistant' ); ?></dd>

				<dt><?php esc_html_e( 'Sent to third parties', 'htech-ai-assistant' ); ?></dt>
				<dd><strong><?php esc_html_e( 'Nothing. No analytics, no AI provider, no external API.', 'htech-ai-assistant' ); ?></strong></dd>

				<dt><?php esc_html_e( 'Browser storage', 'htech-ai-assistant' ); ?></dt>
				<dd><?php esc_html_e( 'The transcript is kept in sessionStorage so a page reload does not lose the chat. It is discarded when the tab closes.', 'htech-ai-assistant' ); ?></dd>
			</dl>

			<div class="htech-alert htech-alert--warn" style="margin-top:14px">
				<span class="dashicons dashicons-warning"></span>
				<div><?php esc_html_e( 'If you enable on-device AI, the model runs locally but the assembled prompt (including the visitor\'s question and the retrieved business text) exists in the visitor\'s browser memory. It is not transmitted to any AI service.', 'htech-ai-assistant' ); ?></div>
			</div>
		</div>
	</div>
</div>

<!-- ============================= UPLOADS ============================== -->
<div class="htech-tab-panel" data-tab-panel="uploads" hidden>
	<div class="htech-grid htech-grid--2">
		<div class="htech-card">
			<div class="htech-card__head">
				<h2><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Upload restrictions', 'htech-ai-assistant' ); ?></h2>
			</div>

			<?php
			HTECH_AI_Admin::field(
				'max_file_size_mb',
				__( 'Maximum file size (MB)', 'htech-ai-assistant' ),
				sprintf(
					/* translators: %s: server limit */
					__( 'Your server currently allows %s per upload (upload_max_filesize / post_max_size). The smaller of the two values wins.', 'htech-ai-assistant' ),
					size_format( wp_max_upload_size() )
				),
				array( 'type' => 'number', 'min' => 1, 'max' => 64 )
			);
			HTECH_AI_Admin::field(
				'max_files_per_upload',
				__( 'Maximum files per batch', 'htech-ai-assistant' ),
				__( 'Each file is uploaded and indexed separately in the background of your browser tab.', 'htech-ai-assistant' ),
				array( 'type' => 'number', 'min' => 1, 'max' => 20 )
			);
			?>

			<div class="htech-ai-field">
				<label><?php esc_html_e( 'Allowed file types', 'htech-ai-assistant' ); ?></label>
				<?php
				$enabled = array_filter( explode( ',', (string) $s['allowed_extensions'] ) );
				$all     = array( 'pdf' => 'PDF', 'docx' => 'DOCX', 'txt' => 'TXT', 'csv' => 'CSV', 'md' => 'Markdown', 'json' => 'JSON' );
				?>
				<?php foreach ( $all as $key => $label ) : ?>
					<label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:5px">
						<input type="checkbox" value="<?php echo esc_attr( $key ); ?>" data-ext <?php checked( in_array( $key, $enabled, true ), true ); ?> />
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<input type="hidden" id="htech-allowed-extensions" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[allowed_extensions]" value="<?php echo esc_attr( $s['allowed_extensions'] ); ?>" />
				<p class="htech-ai-help"><?php esc_html_e( 'Turning a type off also affects existing documents of that type: they stay in the library but are no longer offered for upload.', 'htech-ai-assistant' ); ?></p>
			</div>

			<div class="htech-toggle-row">
				<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[index_uploads]" value="0" />
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Index media library uploads', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'When on, eligible files uploaded through the normal WordPress media library are also indexed. Off by default to keep the two libraries separate.', 'htech-ai-assistant' ); ?></span>
				</div>
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-index_uploads" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[index_uploads]" value="1" <?php checked( (bool) $s['index_uploads'], true ); ?> />
					<span></span>
				</label>
			</div>
		</div>

		<div class="htech-card">
			<div class="htech-card__head">
				<h2><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Storage security', 'htech-ai-assistant' ); ?></h2>
			</div>

			<dl class="htech-kv">
				<dt><?php esc_html_e( 'Storage folder', 'htech-ai-assistant' ); ?></dt>
				<dd class="htech-mono"><?php echo esc_html( $uploads_ok ? $uploads . '/' : '—' ); ?></dd>

				<dt><?php esc_html_e( 'Writable', 'htech-ai-assistant' ); ?></dt>
				<dd>
					<?php if ( $uploads_ok ) : ?>
						<span class="htech-pill htech-pill--on"><span class="htech-dot"></span><?php esc_html_e( 'Yes', 'htech-ai-assistant' ); ?></span>
					<?php else : ?>
						<span class="htech-pill htech-pill--off"><span class="htech-dot"></span><?php echo esc_html( $uploads->get_error_message() ); ?></span>
					<?php endif; ?>
				</dd>

				<dt><?php esc_html_e( 'Direct URL access', 'htech-ai-assistant' ); ?></dt>
				<dd><?php esc_html_e( 'Denied via .htaccess, web.config and index.php guards.', 'htech-ai-assistant' ); ?></dd>

				<dt><?php esc_html_e( 'Filenames', 'htech-ai-assistant' ); ?></dt>
				<dd><?php esc_html_e( 'Randomised with a content hash and timestamp — not guessable.', 'htech-ai-assistant' ); ?></dd>

				<dt><?php esc_html_e( 'File signature checks', 'htech-ai-assistant' ); ?></dt>
				<dd><?php esc_html_e( 'PDF magic bytes, DOCX zip structure and PHP-tag scanning on text uploads.', 'htech-ai-assistant' ); ?></dd>
			</dl>

			<?php if ( ! $uploads_ok ) : ?>
				<div class="htech-alert htech-alert--error" style="margin-top:14px">
					<span class="dashicons dashicons-warning"></span>
					<div><?php esc_html_e( 'Document uploads will fail until the uploads folder is writable. Ask your host to fix permissions on wp-content/uploads (usually 755 for folders).', 'htech-ai-assistant' ); ?></div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- ============================== TOOLS =============================== -->
<div class="htech-tab-panel" data-tab-panel="tools" hidden>
	<div class="htech-grid htech-grid--2">
		<div class="htech-card">
			<div class="htech-card__head">
				<h2><span class="dashicons dashicons-performance"></span> <?php esc_html_e( 'Maintenance', 'htech-ai-assistant' ); ?></h2>
			</div>

			<div class="htech-toggle-row">
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Clear rate limits', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Useful after testing when you have hit the message limit yourself.', 'htech-ai-assistant' ); ?></span>
				</div>
				<button type="button" class="button htech-btn--ghost htech-btn--sm" data-tool="flush-limits"><?php esc_html_e( 'Clear', 'htech-ai-assistant' ); ?></button>
			</div>

			<div class="htech-toggle-row">
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Restore default settings', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Resets chatbot, appearance and AI settings. Knowledge base, documents and conversations are untouched.', 'htech-ai-assistant' ); ?></span>
				</div>
				<button type="button" class="button htech-btn--danger htech-btn--sm" data-tool="reset-settings" data-confirm="<?php esc_attr_e( 'Reset all settings to defaults? Your knowledge base and documents are kept.', 'htech-ai-assistant' ); ?>"><?php esc_html_e( 'Reset', 'htech-ai-assistant' ); ?></button>
			</div>

			<div class="htech-toggle-row">
				<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[delete_on_uninstall]" value="0" />
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Delete all data when the plugin is uninstalled', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Off by default — deactivating or updating never deletes business data. When on, uninstalling drops the tables, options, documents and leads.', 'htech-ai-assistant' ); ?></span>
				</div>
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-delete_on_uninstall" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[delete_on_uninstall]" value="1" <?php checked( (bool) $s['delete_on_uninstall'], true ); ?> />
					<span></span>
				</label>
			</div>
		</div>

		<div class="htech-card">
			<div class="htech-card__head">
				<h2><span class="dashicons dashicons-admin-customizer"></span> <?php esc_html_e( 'Backup & migration', 'htech-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Export the knowledge base to move it between installations or keep an offline copy.', 'htech-ai-assistant' ); ?></p>
			</div>

			<div class="htech-flex" style="flex-direction:column;align-items:flex-start;gap:10px">
				<a class="button htech-btn--ghost" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=htech_ai_kb_export' ), 'htech_ai_kb_export' ) ); ?>">
					<?php esc_html_e( 'Export knowledge base (JSON)', 'htech-ai-assistant' ); ?>
				</a>
				<a class="button htech-btn--ghost" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=htech_ai_export_leads' ), 'htech_ai_export_leads' ) ); ?>">
					<?php esc_html_e( 'Export leads (CSV)', 'htech-ai-assistant' ); ?>
				</a>
			</div>

			<p class="htech-ai-help" style="margin-top:12px"><?php esc_html_e( 'Documents live in wp-content/uploads/htech-ai-assistant — include that folder in your regular site backups.', 'htech-ai-assistant' ); ?></p>
		</div>
	</div>
</div>

<?php HTECH_AI_Admin::settings_form_close( __( 'Save general settings', 'htech-ai-assistant' ) ); ?>

<!-- ============================== DATA ================================ -->
<div class="htech-tab-panel" data-tab-panel="data" hidden>
	<div class="htech-card">
		<div class="htech-card__head">
			<div>
				<h2><span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete data', 'htech-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'These actions are immediate and cannot be undone. Export first if you need a copy.', 'htech-ai-assistant' ); ?></p>
			</div>
		</div>

		<div class="htech-toggle-row">
			<div class="htech-toggle-row__text">
				<strong><?php esc_html_e( 'Delete all conversations', 'htech-ai-assistant' ); ?></strong>
				<span><?php esc_html_e( 'Removes every transcript and message. Leads already captured are kept.', 'htech-ai-assistant' ); ?></span>
			</div>
			<button type="button" class="button htech-btn--danger htech-btn--sm" data-tool="purge" data-scope="conversations" data-confirm="<?php esc_attr_e( 'Delete ALL conversations permanently?', 'htech-ai-assistant' ); ?>"><?php esc_html_e( 'Delete', 'htech-ai-assistant' ); ?></button>
		</div>

		<div class="htech-toggle-row">
			<div class="htech-toggle-row__text">
				<strong><?php esc_html_e( 'Delete all documents', 'htech-ai-assistant' ); ?></strong>
				<span><?php esc_html_e( 'Removes the files from disk and all indexed chunks.', 'htech-ai-assistant' ); ?></span>
			</div>
			<button type="button" class="button htech-btn--danger htech-btn--sm" data-tool="purge" data-scope="documents" data-confirm="<?php esc_attr_e( 'Delete ALL documents and their files permanently?', 'htech-ai-assistant' ); ?>"><?php esc_html_e( 'Delete', 'htech-ai-assistant' ); ?></button>
		</div>

		<div class="htech-toggle-row">
			<div class="htech-toggle-row__text">
				<strong><?php esc_html_e( 'Delete manual knowledge base', 'htech-ai-assistant' ); ?></strong>
				<span><?php esc_html_e( 'Clears all manual sections, FAQs and custom entries.', 'htech-ai-assistant' ); ?></span>
			</div>
			<button type="button" class="button htech-btn--danger htech-btn--sm" data-tool="purge" data-scope="knowledge" data-confirm="<?php esc_attr_e( 'Delete the entire manual knowledge base permanently?', 'htech-ai-assistant' ); ?>"><?php esc_html_e( 'Delete', 'htech-ai-assistant' ); ?></button>
		</div>

		<div class="htech-toggle-row">
			<div class="htech-toggle-row__text">
				<strong><?php esc_html_e( 'Delete all leads', 'htech-ai-assistant' ); ?></strong>
				<span><?php esc_html_e( 'Removes every stored enquiry, including contact details.', 'htech-ai-assistant' ); ?></span>
			</div>
			<button type="button" class="button htech-btn--danger htech-btn--sm" data-tool="purge" data-scope="leads" data-confirm="<?php esc_attr_e( 'Delete ALL leads permanently?', 'htech-ai-assistant' ); ?>"><?php esc_html_e( 'Delete', 'htech-ai-assistant' ); ?></button>
		</div>
	</div>
</div>

<!-- ============================= SYSTEM =============================== -->
<div class="htech-tab-panel" data-tab-panel="system" hidden>
	<div class="htech-grid htech-grid--2">
		<div class="htech-card">
			<div class="htech-card__head">
				<h2><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Environment', 'htech-ai-assistant' ); ?></h2>
			</div>

			<?php
			global $wpdb;
			$tables = array(
				'conversations' => HTECH_AI_Database::conversations_table(),
				'messages'      => HTECH_AI_Database::messages_table(),
				'documents'     => HTECH_AI_Database::documents_table(),
				'chunks'        => HTECH_AI_Database::chunks_table(),
				'leads'         => HTECH_AI_Database::leads_table(),
				'rate_limits'   => $wpdb->prefix . 'htech_rate_limits',
			);
			?>

			<dl class="htech-kv">
				<dt><?php esc_html_e( 'Plugin version', 'htech-ai-assistant' ); ?></dt>
				<dd class="htech-mono"><?php echo esc_html( HTECH_AI_VERSION ); ?></dd>

				<dt><?php esc_html_e( 'Schema version', 'htech-ai-assistant' ); ?></dt>
				<dd class="htech-mono"><?php echo esc_html( get_option( 'htech_ai_schema_version', '—' ) ); ?></dd>

				<dt><?php esc_html_e( 'WordPress / PHP', 'htech-ai-assistant' ); ?></dt>
				<dd><?php echo esc_html( get_bloginfo( 'version' ) . ' / ' . PHP_VERSION ); ?></dd>

				<dt><?php esc_html_e( 'Upload limit (server)', 'htech-ai-assistant' ); ?></dt>
				<dd><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></dd>

				<dt><?php esc_html_e( 'Zip extension', 'htech-ai-assistant' ); ?></dt>
				<dd><?php echo class_exists( 'ZipArchive' ) ? esc_html__( 'Available (DOCX parsing on server)', 'htech-ai-assistant' ) : esc_html__( 'Missing — browser parsing only', 'htech-ai-assistant' ); ?></dd>

				<dt><?php esc_html_e( 'mbstring', 'htech-ai-assistant' ); ?></dt>
				<dd><?php echo function_exists( 'mb_substr' ) ? esc_html__( 'Available', 'htech-ai-assistant' ) : esc_html__( 'Missing — multi-byte handling may be limited', 'htech-ai-assistant' ); ?></dd>

				<dt><?php esc_html_e( 'Object cache', 'htech-ai-assistant' ); ?></dt>
				<dd><?php echo wp_using_ext_object_cache() ? esc_html__( 'External cache detected', 'htech-ai-assistant' ) : esc_html__( 'Database transients', 'htech-ai-assistant' ); ?></dd>

				<dt><?php esc_html_e( 'Next retention cleanup', 'htech-ai-assistant' ); ?></dt>
				<dd><?php echo esc_html( wp_next_scheduled( 'htech_ai_retention_cleanup' ) ? human_time_diff( wp_next_scheduled( 'htech_ai_retention_cleanup' ) ) : __( 'Not scheduled', 'htech-ai-assistant' ) ); ?></dd>
			</dl>
		</div>

		<div class="htech-card">
			<div class="htech-card__head">
				<h2><span class="dashicons dashicons-database"></span> <?php esc_html_e( 'Database tables', 'htech-ai-assistant' ); ?></h2>
			</div>

			<table class="htech-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Table', 'htech-ai-assistant' ); ?></th>
						<th><?php esc_html_e( 'Rows', 'htech-ai-assistant' ); ?></th>
						<th><?php esc_html_e( 'Status', 'htech-ai-assistant' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $tables as $label => $table ) : ?>
						<?php
						$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
						$rows   = $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
						?>
						<tr>
							<td class="htech-mono"><?php echo esc_html( $table ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $rows ) ); ?></td>
							<td>
								<span class="htech-badge htech-badge--<?php echo $exists ? 'ready' : 'failed'; ?>">
									<?php echo $exists ? esc_html__( 'Present', 'htech-ai-assistant' ) : esc_html__( 'Missing', 'htech-ai-assistant' ); ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', HTECH_AI_Database::chunks_table() ) ) ) : // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery ?>
				<div class="htech-alert htech-alert--error" style="margin-top:12px">
					<span class="dashicons dashicons-warning"></span>
					<div><?php esc_html_e( 'Tables are missing. Deactivate and reactivate the plugin to recreate them.', 'htech-ai-assistant' ); ?></div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
/* Sync the extension checkboxes with the hidden CSV field. */
(function () {
	var boxes = document.querySelectorAll('[data-ext]');
	var target = document.getElementById('htech-allowed-extensions');
	if (!target) { return; }

	var update = function () {
		var values = [];
		boxes.forEach(function (box) { if (box.checked) { values.push(box.value); } });
		target.value = values.join(',');
	};

	boxes.forEach(function (box) { box.addEventListener('change', update); });
	update();
})();
</script>

<?php HTECH_AI_Admin::shell_end(); ?>
