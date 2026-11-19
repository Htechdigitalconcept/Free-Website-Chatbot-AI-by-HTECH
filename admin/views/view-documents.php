<?php
/**
 * Documents screen.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable

$list  = htech_ai()->documents->get_documents(
	array(
		'search'   => $search,
		'status'   => $status,
		'paged'    => $paged,
		'per_page' => 20,
	)
);
$total_size = htech_ai()->documents->total_size();
$max_bytes  = HTECH_AI_Document_Manager::max_upload_bytes();
$php_limit  = wp_max_upload_size();
$extensions = HTECH_AI_Document_Manager::enabled_extensions();

HTECH_AI_Admin::shell_start(
	'htech-ai-documents',
	__( 'Documents', 'htech-ai-assistant' ),
	__( 'Upload price lists, catalogues and policies. Files are stored privately and never exposed by URL.', 'htech-ai-assistant' )
);
?>

<div class="htech-grid htech-grid--3" style="margin-bottom:18px">
	<div class="htech-card">
		<h2 style="margin:0 0 8px;font-size:13px;color:var(--ht-navy)">
			<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Upload limit', 'htech-ai-assistant' ); ?>
		</h2>
		<p style="margin:0;font-size:22px;font-weight:700;color:var(--ht-navy)"><?php echo esc_html( size_format( $max_bytes ) ); ?></p>
		<p class="htech-ai-help">
			<?php
			if ( $php_limit < HTECH_AI_Settings::get( 'max_file_size_mb', 8 ) * MB_IN_BYTES ) {
				printf(
					/* translators: %s: php limit */
					esc_html__( 'Capped by your server\'s upload_max_filesize (%s). Raise it in php.ini to allow larger files.', 'htech-ai-assistant' ),
					esc_html( size_format( $php_limit ) )
				);
			} else {
				esc_html_e( 'Configured in General Settings.', 'htech-ai-assistant' );
			}
			?>
		</p>
	</div>

	<div class="htech-card">
		<h2 style="margin:0 0 8px;font-size:13px;color:var(--ht-navy)">
			<span class="dashicons dashicons-media-document"></span> <?php esc_html_e( 'Storage used', 'htech-ai-assistant' ); ?>
		</h2>
		<p style="margin:0;font-size:22px;font-weight:700;color:var(--ht-navy)"><?php echo esc_html( size_format( $total_size ) ); ?></p>
		<p class="htech-ai-help"><?php esc_html_e( 'Stored in a protected folder inside wp-content/uploads.', 'htech-ai-assistant' ); ?></p>
	</div>

	<div class="htech-card">
		<h2 style="margin:0 0 8px;font-size:13px;color:var(--ht-navy)">
			<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Accepted types', 'htech-ai-assistant' ); ?>
		</h2>
		<p style="margin:0;font-size:14px;font-weight:600;color:var(--ht-navy)"><?php echo esc_html( strtoupper( implode( ', ', $extensions ) ) ); ?></p>
		<p class="htech-ai-help"><?php esc_html_e( 'PDF and DOCX are parsed in your browser with PDF.js and Mammoth.js — nothing is sent to an external service.', 'htech-ai-assistant' ); ?></p>
	</div>
</div>

<div class="htech-card">
	<div class="htech-card__head">
		<div>
			<h2><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Upload documents', 'htech-ai-assistant' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: max files, 2: extensions */
					esc_html__( 'Drop up to %1$s files at once (%2$s). Text is extracted and indexed automatically.', 'htech-ai-assistant' ),
					esc_html( HTECH_AI_Settings::get( 'max_files_per_upload', 5 ) ),
					esc_html( strtoupper( implode( ', ', $extensions ) ) )
				);
				?>
			</p>
		</div>
	</div>

	<div class="htech-dropzone" id="htech-dropzone" role="button" tabindex="0">
		<span class="dashicons dashicons-cloud-upload"></span>
		<strong><?php esc_html_e( 'Click to choose files, or drag them here', 'htech-ai-assistant' ); ?></strong>
		<p><?php esc_html_e( 'Scanned PDFs contain no text layer and cannot be indexed — run OCR first.', 'htech-ai-assistant' ); ?></p>
		<input type="file" id="htech-file-input" multiple accept=".pdf,.docx,.txt,.csv,.md,.json" style="display:none" />
	</div>

	<div class="htech-upload-list" id="htech-upload-list"></div>
</div>

<div class="htech-card">
	<div class="htech-card__head">
		<div>
			<h2><span class="dashicons dashicons-library"></span> <?php esc_html_e( 'Document library', 'htech-ai-assistant' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %d: count */
					esc_html( _n( '%d document', '%d documents', $list['total'], 'htech-ai-assistant' ) ),
					(int) $list['total']
				);
				?>
			</p>
		</div>
	</div>

	<form method="get" class="htech-search-form">
		<input type="hidden" name="page" value="htech-ai-documents" />
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search documents…', 'htech-ai-assistant' ); ?>" />
		<select name="status">
			<option value=""><?php esc_html_e( 'All statuses', 'htech-ai-assistant' ); ?></option>
			<option value="ready" <?php selected( $status, 'ready' ); ?>><?php esc_html_e( 'Ready', 'htech-ai-assistant' ); ?></option>
			<option value="processing" <?php selected( $status, 'processing' ); ?>><?php esc_html_e( 'Processing', 'htech-ai-assistant' ); ?></option>
			<option value="needs_browser" <?php selected( $status, 'needs_browser' ); ?>><?php esc_html_e( 'Needs browser parsing', 'htech-ai-assistant' ); ?></option>
			<option value="failed" <?php selected( $status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'htech-ai-assistant' ); ?></option>
			<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active only', 'htech-ai-assistant' ); ?></option>
			<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive only', 'htech-ai-assistant' ); ?></option>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'htech-ai-assistant' ); ?></button>
		<?php if ( $search || $status ) : ?>
			<a class="button htech-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=htech-ai-documents' ) ); ?>"><?php esc_html_e( 'Reset', 'htech-ai-assistant' ); ?></a>
		<?php endif; ?>
	</form>

	<?php if ( empty( $list['items'] ) ) : ?>
		<div class="htech-empty">
			<span class="dashicons dashicons-media-default"></span>
			<strong><?php esc_html_e( 'No documents yet', 'htech-ai-assistant' ); ?></strong>
			<?php esc_html_e( 'Upload a PDF price list or a DOCX service catalogue to get started.', 'htech-ai-assistant' ); ?>
		</div>
	<?php else : ?>
		<table class="htech-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Document', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Type', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Size', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Uploaded', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Status', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Chunks', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Active', 'htech-ai-assistant' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $list['items'] as $document ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $document['title'] ); ?></strong><br>
							<span class="htech-mono"><?php echo esc_html( $document['file_name'] ); ?></span>
							<?php if ( ! empty( $document['status_message'] ) ) : ?>
								<br><span class="htech-muted" style="color:#B45309"><?php echo esc_html( mb_substr( $document['status_message'], 0, 140 ) ); ?></span>
							<?php endif; ?>
						</td>
						<td><span class="htech-badge"><?php echo esc_html( strtoupper( $document['file_type'] ) ); ?></span></td>
						<td class="htech-muted"><?php echo esc_html( size_format( (int) $document['file_size'] ) ); ?></td>
						<td class="htech-muted">
							<?php echo esc_html( mysql2date( get_option( 'date_format' ), $document['created_at'] ) ); ?><br>
							<?php echo esc_html( mysql2date( 'H:i', $document['created_at'] ) ); ?>
						</td>
						<td>
							<span class="htech-badge htech-badge--<?php echo esc_attr( $document['status'] ); ?>">
								<?php
								$labels = array(
									'ready'          => __( 'Ready', 'htech-ai-assistant' ),
									'processing'     => __( 'Processing', 'htech-ai-assistant' ),
									'pending'        => __( 'Pending', 'htech-ai-assistant' ),
									'needs_browser'  => __( 'Needs browser parse', 'htech-ai-assistant' ),
									'failed'         => __( 'Failed', 'htech-ai-assistant' ),
								);
								echo esc_html( $labels[ $document['status'] ] ?? $document['status'] );
								?>
							</span>
							<?php if ( $document['extractor'] ) : ?>
								<br><span class="htech-mono"><?php echo esc_html( $document['extractor'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( (int) $document['chunk_count'] ) ); ?></td>
						<td>
							<span class="htech-badge htech-badge--<?php echo ! empty( $document['is_active'] ) ? 'active' : 'inactive'; ?>">
								<?php echo ! empty( $document['is_active'] ) ? esc_html__( 'Active', 'htech-ai-assistant' ) : esc_html__( 'Off', 'htech-ai-assistant' ); ?>
							</span>
						</td>
						<td>
							<div class="htech-table__actions">
								<button type="button" class="button htech-btn--sm htech-btn--ghost" data-doc-toggle="<?php echo esc_attr( $document['id'] ); ?>">
									<?php echo ! empty( $document['is_active'] ) ? esc_html__( 'Disable', 'htech-ai-assistant' ) : esc_html__( 'Enable', 'htech-ai-assistant' ); ?>
								</button>
								<button type="button" class="button htech-btn--sm htech-btn--ghost" data-doc-reprocess="<?php echo esc_attr( $document['id'] ); ?>">
									<?php esc_html_e( 'Reprocess', 'htech-ai-assistant' ); ?>
								</button>
								<button type="button" class="button htech-btn--sm htech-btn--danger" data-doc-delete="<?php echo esc_attr( $document['id'] ); ?>">
									<?php esc_html_e( 'Delete', 'htech-ai-assistant' ); ?>
								</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $list['pages'] > 1 ) : ?>
			<div class="htech-pagination">
				<?php
				$base = admin_url( 'admin.php?page=htech-ai-documents' );
				if ( $search ) {
					$base = add_query_arg( 's', $search, $base );
				}
				if ( $status ) {
					$base = add_query_arg( 'status', $status, $base );
				}
				?>
				<?php for ( $page = 1; $page <= $list['pages']; $page++ ) : ?>
					<a class="button htech-btn--sm <?php echo $page === $paged ? 'button-primary' : 'htech-btn--ghost'; ?>"
						href="<?php echo esc_url( add_query_arg( 'paged', $page, $base ) ); ?>"><?php echo esc_html( $page ); ?></a>
				<?php endfor; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<div class="htech-alert htech-alert--info">
	<span class="dashicons dashicons-lock"></span>
	<div>
		<p><strong><?php esc_html_e( 'How your documents are protected.', 'htech-ai-assistant' ); ?></strong></p>
		<p><?php esc_html_e( 'Files are stored outside the media library in wp-content/uploads/htech-ai-assistant/ with directory-level deny rules, an index.php guard and random hashed filenames. There is no public URL for them. Only administrators can delete or replace them; visitors only ever receive short extracted snippets in answers.', 'htech-ai-assistant' ); ?></p>
	</div>
</div>

<?php HTECH_AI_Admin::shell_end(); ?>
