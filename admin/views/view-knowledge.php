<?php
/**
 * Knowledge Base screen: manual sections, FAQs, custom entries, website
 * importer and diagnostics.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

$kb       = htech_ai()->knowledge->all();
$sections = HTECH_AI_Knowledge_Base::sections();
$coverage = htech_ai()->knowledge->coverage();

HTECH_AI_Admin::shell_start(
	'htech-ai-knowledge',
	__( 'Knowledge Base', 'htech-ai-assistant' ),
	__( 'Everything the chatbot is allowed to state as fact about your business.', 'htech-ai-assistant' ),
	'<a class="button htech-btn--ghost" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=htech_ai_kb_export' ), 'htech_ai_kb_export' ) ) . '">' . esc_html__( 'Export JSON', 'htech-ai-assistant' ) . '</a>'
);
?>

<div class="htech-grid htech-grid--stats" style="margin-bottom:18px">
	<div class="htech-stat">
		<p class="htech-stat__label"><?php esc_html_e( 'Sections filled', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__value" id="htech-coverage-sections"><?php echo esc_html( $coverage['sections'] ); ?></p>
		<p class="htech-stat__meta"><?php echo esc_html( sprintf( /* translators: %d: total */ __( 'of %d available', 'htech-ai-assistant' ), count( $sections ) ) ); ?></p>
	</div>
	<div class="htech-stat">
		<p class="htech-stat__label"><?php esc_html_e( 'Active FAQs', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__value" id="htech-coverage-faqs"><?php echo esc_html( $coverage['faqs'] ); ?></p>
		<p class="htech-stat__meta"><?php esc_html_e( 'Curated question/answer pairs', 'htech-ai-assistant' ); ?></p>
	</div>
	<div class="htech-stat">
		<p class="htech-stat__label"><?php esc_html_e( 'Custom entries', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__value" id="htech-coverage-custom"><?php echo esc_html( $coverage['custom'] ); ?></p>
		<p class="htech-stat__meta"><?php esc_html_e( 'Free-form knowledge blocks', 'htech-ai-assistant' ); ?></p>
	</div>
	<div class="htech-stat">
		<p class="htech-stat__label"><?php esc_html_e( 'Manual chunks', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__value" id="htech-coverage-chunks"><?php echo esc_html( $coverage['chunks'] ); ?></p>
		<p class="htech-stat__meta"><span id="htech-coverage-words"><?php echo esc_html( $coverage['words'] ); ?></span> <?php esc_html_e( 'words indexed', 'htech-ai-assistant' ); ?></p>
	</div>
</div>

<?php if ( $coverage['words'] < 40 && ! $coverage['faqs'] ) : ?>
	<div class="htech-alert htech-alert--warn">
		<span class="dashicons dashicons-warning"></span>
		<div>
			<strong><?php esc_html_e( 'The knowledge base is almost empty.', 'htech-ai-assistant' ); ?></strong>
			<p><?php esc_html_e( 'Until you add business information, the chatbot will answer almost every business question with "I don\'t have enough verified information…". Start with Company overview, Services and Contact details — those three cover most real questions.', 'htech-ai-assistant' ); ?></p>
		</div>
	</div>
<?php endif; ?>

<div class="htech-tabs" data-htech-tabs>
	<button type="button" class="is-active" data-tab="sections"><?php esc_html_e( 'Business information', 'htech-ai-assistant' ); ?></button>
	<button type="button" data-tab="faqs"><?php esc_html_e( 'FAQs', 'htech-ai-assistant' ); ?></button>
	<button type="button" data-tab="custom"><?php esc_html_e( 'Custom entries', 'htech-ai-assistant' ); ?></button>
	<button type="button" data-tab="importer"><?php esc_html_e( 'Import from website', 'htech-ai-assistant' ); ?></button>
	<button type="button" data-tab="diagnostics"><?php esc_html_e( 'Index & diagnostics', 'htech-ai-assistant' ); ?></button>
</div>

<!-- ============================ SECTIONS ============================== -->
<div class="htech-tab-panel" data-tab-panel="sections">
	<div class="htech-alert htech-alert--info">
		<span class="dashicons dashicons-lightbulb"></span>
		<div>
			<strong><?php esc_html_e( 'Write like a customer is reading.', 'htech-ai-assistant' ); ?></strong>
			<p><?php esc_html_e( 'Use the exact words your customers use, and state prices and policies literally. The chatbot can only quote what you write here — it will never invent a price or promise.', 'htech-ai-assistant' ); ?></p>
		</div>
	</div>

	<div class="htech-grid htech-grid--2">
		<?php foreach ( $sections as $key => $section ) : ?>
			<?php
			$value    = $kb['sections'][ $key ]['content'] ?? '';
			$is_active = ! empty( $kb['sections'][ $key ]['active'] );
			$updated  = (int) ( $kb['sections'][ $key ]['updated'] ?? 0 );
			?>
			<div class="htech-card">
				<div class="htech-card__head">
					<div>
						<h2><?php echo esc_html( $section['title'] ); ?></h2>
						<p>
							<?php if ( $updated ) : ?>
								<?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Last updated %s', 'htech-ai-assistant' ), date_i18n( get_option( 'date_format' ) . ' H:i', $updated ) ) ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Not filled in yet', 'htech-ai-assistant' ); ?>
							<?php endif; ?>
						</p>
					</div>
					<span class="htech-badge htech-badge--<?php echo $value ? 'ready' : 'inactive'; ?>">
						<?php echo $value ? esc_html__( 'Has content', 'htech-ai-assistant' ) : esc_html__( 'Empty', 'htech-ai-assistant' ); ?>
					</span>
				</div>

				<form data-kb-section-form data-kb-section="<?php echo esc_attr( $key ); ?>">
					<div class="htech-ai-field">
						<textarea name="content" rows="6" class="large-text" placeholder="<?php echo esc_attr( $section['placeholder'] ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
					</div>
					<div class="htech-flex htech-flex--between">
						<label class="htech-ai-toggle" style="font-weight:500;font-size:12.5px">
							<input type="checkbox" <?php checked( $is_active, true ); ?> />
							<span></span>
							<span style="position:static;width:auto;height:auto;background:none;box-shadow:none;font-weight:500;margin-left:9px"><?php esc_html_e( 'Use this section', 'htech-ai-assistant' ); ?></span>
						</label>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save section', 'htech-ai-assistant' ); ?></button>
					</div>
				</form>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<!-- ============================== FAQS ================================ -->
<div class="htech-tab-panel" data-tab-panel="faqs" hidden>
	<div class="htech-grid htech-grid--2">
		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e( 'Add a question and answer', 'htech-ai-assistant' ); ?></h2>
					<p><?php esc_html_e( 'FAQs are the strongest signal in the knowledge base: an exact question match is prioritised over document text.', 'htech-ai-assistant' ); ?></p>
				</div>
			</div>

			<form id="htech-faq-form">
				<input type="hidden" name="faq_id" value="0" />
				<div class="htech-ai-field">
					<label for="faq_question"><?php esc_html_e( 'Question', 'htech-ai-assistant' ); ?></label>
					<input type="text" id="faq_question" name="faq_question" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Do you deliver outside Lagos?', 'htech-ai-assistant' ); ?>" required />
					<p class="htech-ai-help"><?php esc_html_e( 'Phrase it the way a visitor would type it.', 'htech-ai-assistant' ); ?></p>
				</div>
				<div class="htech-ai-field">
					<label for="faq_answer"><?php esc_html_e( 'Answer', 'htech-ai-assistant' ); ?></label>
					<textarea id="faq_answer" name="faq_answer" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Answer exactly as the business would. Include figures if they are published.', 'htech-ai-assistant' ); ?>" required></textarea>
				</div>
				<div class="htech-flex htech-flex--between">
					<label class="htech-ai-toggle" style="font-weight:500;font-size:12.5px">
						<input type="checkbox" name="faq_active" checked />
						<span></span>
						<span style="position:static;width:auto;height:auto;background:none;box-shadow:none;font-weight:500;margin-left:9px"><?php esc_html_e( 'Active', 'htech-ai-assistant' ); ?></span>
					</label>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save FAQ', 'htech-ai-assistant' ); ?></button>
				</div>
			</form>
		</div>

		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Your FAQs', 'htech-ai-assistant' ); ?></h2>
					<p><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d entry', '%d entries', count( $kb['faqs'] ), 'htech-ai-assistant' ), count( $kb['faqs'] ) ) ); ?></p>
				</div>
			</div>

			<?php if ( empty( $kb['faqs'] ) ) : ?>
				<div class="htech-empty">
					<span class="dashicons dashicons-editor-help"></span>
					<strong><?php esc_html_e( 'No FAQs yet', 'htech-ai-assistant' ); ?></strong>
					<?php esc_html_e( 'Add the questions your team answers most often on the phone.', 'htech-ai-assistant' ); ?>
				</div>
			<?php else : ?>
				<table class="htech-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Question', 'htech-ai-assistant' ); ?></th>
							<th><?php esc_html_e( 'Status', 'htech-ai-assistant' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $kb['faqs'] as $faq ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $faq['question'] ); ?></strong><br>
									<span class="htech-muted"><?php echo esc_html( mb_substr( $faq['answer'], 0, 110 ) ); ?><?php echo mb_strlen( $faq['answer'] ) > 110 ? '…' : ''; ?></span>
								</td>
								<td><span class="htech-badge htech-badge--<?php echo ! empty( $faq['active'] ) ? 'active' : 'inactive'; ?>"><?php echo ! empty( $faq['active'] ) ? esc_html__( 'Active', 'htech-ai-assistant' ) : esc_html__( 'Hidden', 'htech-ai-assistant' ); ?></span></td>
								<td>
									<div class="htech-table__actions">
										<button type="button" class="button htech-btn--sm htech-btn--ghost" data-kb-edit-faq
											data-payload="<?php echo esc_attr( wp_json_encode( array( 'id' => (int) $faq['id'], 'question' => $faq['question'], 'answer' => $faq['answer'], 'active' => (int) $faq['active'] ) ) ); ?>">
											<?php esc_html_e( 'Edit', 'htech-ai-assistant' ); ?>
										</button>
										<button type="button" class="button htech-btn--sm htech-btn--danger" data-kb-delete-faq="<?php echo esc_attr( $faq['id'] ); ?>"><?php esc_html_e( 'Delete', 'htech-ai-assistant' ); ?></button>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- ============================= CUSTOM ================================ -->
<div class="htech-tab-panel" data-tab-panel="custom" hidden>
	<div class="htech-grid htech-grid--2">
		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e( 'Add a custom knowledge entry', 'htech-ai-assistant' ); ?></h2>
					<p><?php esc_html_e( 'Use this for anything that does not fit the standard sections — service areas, booking rules, warranties, team bios.', 'htech-ai-assistant' ); ?></p>
				</div>
			</div>

			<form id="htech-custom-form">
				<input type="hidden" name="custom_id" value="0" />
				<div class="htech-ai-field">
					<label for="custom_title"><?php esc_html_e( 'Title', 'htech-ai-assistant' ); ?></label>
					<input type="text" id="custom_title" name="custom_title" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Service areas', 'htech-ai-assistant' ); ?>" required />
				</div>
				<div class="htech-ai-field">
					<label for="custom_content"><?php esc_html_e( 'Content', 'htech-ai-assistant' ); ?></label>
					<textarea id="custom_content" name="custom_content" rows="8" class="large-text" placeholder="<?php esc_attr_e( 'Write the facts in plain language. This text becomes searchable and is quoted verbatim when relevant.', 'htech-ai-assistant' ); ?>" required></textarea>
				</div>
				<div class="htech-flex htech-flex--between">
					<label class="htech-ai-toggle" style="font-weight:500;font-size:12.5px">
						<input type="checkbox" name="custom_active" checked />
						<span></span>
						<span style="position:static;width:auto;height:auto;background:none;box-shadow:none;font-weight:500;margin-left:9px"><?php esc_html_e( 'Active', 'htech-ai-assistant' ); ?></span>
					</label>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save entry', 'htech-ai-assistant' ); ?></button>
				</div>
			</form>
		</div>

		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Custom entries', 'htech-ai-assistant' ); ?></h2>
					<p><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d entry', '%d entries', count( $kb['custom'] ), 'htech-ai-assistant' ), count( $kb['custom'] ) ) ); ?></p>
				</div>
			</div>

			<?php if ( empty( $kb['custom'] ) ) : ?>
				<div class="htech-empty">
					<span class="dashicons dashicons-list-view"></span>
					<strong><?php esc_html_e( 'No custom entries yet', 'htech-ai-assistant' ); ?></strong>
					<?php esc_html_e( 'Most businesses can start with the standard sections alone.', 'htech-ai-assistant' ); ?>
				</div>
			<?php else : ?>
				<table class="htech-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'htech-ai-assistant' ); ?></th>
							<th><?php esc_html_e( 'Status', 'htech-ai-assistant' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $kb['custom'] as $entry ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $entry['title'] ); ?></strong><br>
									<span class="htech-muted"><?php echo esc_html( mb_substr( wp_strip_all_tags( $entry['content'] ), 0, 110 ) ); ?>…</span>
								</td>
								<td><span class="htech-badge htech-badge--<?php echo ! empty( $entry['active'] ) ? 'active' : 'inactive'; ?>"><?php echo ! empty( $entry['active'] ) ? esc_html__( 'Active', 'htech-ai-assistant' ) : esc_html__( 'Hidden', 'htech-ai-assistant' ); ?></span></td>
								<td>
									<div class="htech-table__actions">
										<button type="button" class="button htech-btn--sm htech-btn--ghost" data-kb-edit-custom
											data-payload="<?php echo esc_attr( wp_json_encode( array( 'id' => (int) $entry['id'], 'title' => $entry['title'], 'content' => $entry['content'], 'active' => (int) $entry['active'] ) ) ); ?>">
											<?php esc_html_e( 'Edit', 'htech-ai-assistant' ); ?>
										</button>
										<button type="button" class="button htech-btn--sm htech-btn--danger" data-kb-delete-custom="<?php echo esc_attr( $entry['id'] ); ?>"><?php esc_html_e( 'Delete', 'htech-ai-assistant' ); ?></button>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- ============================ IMPORTER =============================== -->
<div class="htech-tab-panel" data-tab-panel="importer" hidden>
	<div class="htech-card">
		<div class="htech-card__head">
			<div>
				<h2><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Import content from a public page', 'htech-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Fetches one public page and extracts its readable text for you to review. Nothing is saved until you approve it.', 'htech-ai-assistant' ); ?></p>
			</div>
		</div>

		<div class="htech-alert htech-alert--warn">
			<span class="dashicons dashicons-lock"></span>
			<div>
				<p><strong><?php esc_html_e( 'What this can and cannot do.', 'htech-ai-assistant' ); ?></strong></p>
				<p><?php esc_html_e( 'Only publicly accessible HTML pages can be imported. It cannot log in, solve CAPTCHAs, bypass paywalls or render JavaScript-only sites, and it never touches private areas. Imported text is untrusted: review and trim it before saving, because it becomes something the chatbot may quote to visitors.', 'htech-ai-assistant' ); ?></p>
			</div>
		</div>

		<form id="htech-importer-form" class="htech-search-form">
			<input type="url" name="import_url" class="regular-text" placeholder="https://example.com/about" required />
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Fetch page', 'htech-ai-assistant' ); ?></button>
		</form>
		<p class="htech-ai-help"><?php esc_html_e( 'Tip: import your About, Services and FAQ pages — one import each — then delete the navigation and footer text you do not want the chatbot to repeat.', 'htech-ai-assistant' ); ?></p>

		<div id="htech-importer-review" hidden style="margin-top:20px">
			<div class="htech-card__head">
				<div>
					<h2><?php esc_html_e( 'Review before saving', 'htech-ai-assistant' ); ?></h2>
					<p id="htech-importer-meta"></p>
				</div>
			</div>

			<div class="htech-ai-field">
				<label for="import_title"><?php esc_html_e( 'Entry title', 'htech-ai-assistant' ); ?></label>
				<input type="text" id="import_title" name="import_title" class="regular-text" />
			</div>
			<div class="htech-ai-field">
				<label for="import_text"><?php esc_html_e( 'Extracted text (editable)', 'htech-ai-assistant' ); ?></label>
				<textarea id="import_text" name="import_text" rows="14" class="large-text"></textarea>
			</div>
			<button type="button" class="button button-primary" id="htech-importer-save"><?php esc_html_e( 'Save as custom knowledge entry', 'htech-ai-assistant' ); ?></button>
		</div>
	</div>
</div>

<!-- =========================== DIAGNOSTICS ============================= -->
<div class="htech-tab-panel" data-tab-panel="diagnostics" hidden>
	<div class="htech-grid htech-grid--2">
		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Re-index knowledge', 'htech-ai-assistant' ); ?></h2>
					<p><?php esc_html_e( 'Rebuilds the searchable chunks from all manual sections, FAQs and custom entries. Run this after importing a knowledge file or changing chunk settings.', 'htech-ai-assistant' ); ?></p>
				</div>
			</div>

			<button type="button" class="button button-primary" id="htech-reindex"><?php esc_html_e( 'Re-index now', 'htech-ai-assistant' ); ?></button>

			<hr class="htech-divider" />

			<h3 style="font-size:13px;margin:0 0 8px"><?php esc_html_e( 'Import a knowledge file', 'htech-ai-assistant' ); ?></h3>
			<p class="htech-ai-help"><?php esc_html_e( 'Upload a JSON file created with "Export JSON" on this or another installation. This replaces the manual knowledge base — export first if you need a backup.', 'htech-ai-assistant' ); ?></p>

			<label class="button htech-btn--ghost" style="cursor:pointer">
				<?php esc_html_e( 'Choose JSON file', 'htech-ai-assistant' ); ?>
				<input type="file" id="htech-kb-import" accept="application/json,.json" style="display:none" />
			</label>
		</div>

		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-database"></span> <?php esc_html_e( 'Index health', 'htech-ai-assistant' ); ?></h2>
				</div>
			</div>

			<?php
			global $wpdb;
			$chunk_table    = HTECH_AI_Database::chunks_table();
			$doc_table      = HTECH_AI_Database::documents_table();
			$total_chunks   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$chunk_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			$manual_chunks  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$chunk_table} WHERE source_type = %s", 'manual' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$doc_chunks     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$chunk_table} WHERE source_type = %s", 'document' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$active_docs    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$doc_table} WHERE is_active = 1 AND status = 'ready'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			$index_version  = HTECH_AI_Retrieval::version();
			$corpus         = HTECH_AI_Retrieval::corpus_stats();
			?>

			<dl class="htech-kv">
				<dt><?php esc_html_e( 'Total chunks', 'htech-ai-assistant' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $total_chunks ) ); ?></dd>

				<dt><?php esc_html_e( 'From manual knowledge', 'htech-ai-assistant' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $manual_chunks ) ); ?></dd>

				<dt><?php esc_html_e( 'From documents', 'htech-ai-assistant' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $doc_chunks ) ); ?></dd>

				<dt><?php esc_html_e( 'Active documents', 'htech-ai-assistant' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $active_docs ) ); ?></dd>

				<dt><?php esc_html_e( 'Average chunk length', 'htech-ai-assistant' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( round( $corpus['avgdl'] ) ) ); ?> <?php esc_html_e( 'tokens', 'htech-ai-assistant' ); ?></dd>

				<dt><?php esc_html_e( 'Index version', 'htech-ai-assistant' ); ?></dt>
				<dd class="htech-mono"><?php echo esc_html( substr( $index_version, 0, 20 ) ); ?></dd>
			</dl>

			<?php if ( $total_chunks === 0 ) : ?>
				<div class="htech-alert htech-alert--error" style="margin-top:14px">
					<span class="dashicons dashicons-warning"></span>
					<div><?php esc_html_e( 'Nothing is indexed yet, so the chatbot cannot answer any business question. Add information and press "Re-index now".', 'htech-ai-assistant' ); ?></div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
document.getElementById('htech-kb-import')?.addEventListener('change', function (event) {
	var file = event.target.files[0];
	if (!file) { return; }
	if (!window.confirm('Importing replaces the current manual knowledge base. Continue?')) { event.target.value = ''; return; }

	var reader = new FileReader();
	reader.onload = function () {
		fetch((window.HTECH_AI_ADMIN.restUrl) + '/admin/kb/import', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.HTECH_AI_ADMIN.nonce },
			credentials: 'same-origin',
			body: JSON.stringify({ json: String(reader.result) })
		}).then(function (r) { return r.json(); }).then(function (data) {
			if (data && data.ok) {
				window.location.reload();
			} else {
				window.alert(data.message || 'Import failed.');
			}
		});
	};
	reader.readAsText(file);
});
</script>

<?php HTECH_AI_Admin::shell_end(); ?>
