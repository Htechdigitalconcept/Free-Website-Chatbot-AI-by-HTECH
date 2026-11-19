<?php
/**
 * Overview screen.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

$stats    = HTECH_AI_Database::get_stats();
$coverage = htech_ai()->knowledge->coverage();
$docs     = htech_ai()->documents->get_documents( array( 'per_page' => 5 ) );
$settings = HTECH_AI_Settings::all();
$welcome  = isset( $_GET['welcome'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$checks = array(
	array(
		'label'   => __( 'Widget enabled', 'htech-ai-assistant' ),
		'ok'      => HTECH_AI_Settings::is_enabled( 'widget_enabled' ),
		'hint'    => __( 'The floating chat button is visible on your site.', 'htech-ai-assistant' ),
		'fix'     => admin_url( 'admin.php?page=htech-ai-settings' ),
		'fixText' => __( 'Open settings', 'htech-ai-assistant' ),
	),
	array(
		'label'   => __( 'Business information added', 'htech-ai-assistant' ),
		'ok'      => $coverage['words'] > 30 || $coverage['faqs'] > 0,
		'hint'    => __( 'At least a company overview, services and contact details.', 'htech-ai-assistant' ),
		'fix'     => admin_url( 'admin.php?page=htech-ai-knowledge' ),
		'fixText' => __( 'Add knowledge', 'htech-ai-assistant' ),
	),
	array(
		'label'   => __( 'Searchable content indexed', 'htech-ai-assistant' ),
		'ok'      => $coverage['chunks'] > 0 || $stats['chunks_total'] > 0,
		'hint'    => __( 'The retrieval engine needs indexed chunks to answer questions.', 'htech-ai-assistant' ),
		'fix'     => admin_url( 'admin.php?page=htech-ai-knowledge&tab=diagnostics' ),
		'fixText' => __( 'Re-index now', 'htech-ai-assistant' ),
	),
	array(
		'label'   => __( 'Branding customised', 'htech-ai-assistant' ),
		'ok'      => ! empty( $settings['logo_id'] ) || '#0A1F44' !== $settings['color_header_bg'],
		'hint'    => __( 'Upload your logo and set your brand colours.', 'htech-ai-assistant' ),
		'fix'     => admin_url( 'admin.php?page=htech-ai-appearance' ),
		'fixText' => __( 'Appearance', 'htech-ai-assistant' ),
	),
	array(
		'label'   => __( 'Conversation storage choice reviewed', 'htech-ai-assistant' ),
		'ok'      => true,
		'hint'    => HTECH_AI_Settings::is_enabled( 'store_conversations' )
			? sprintf( /* translators: %d: days */ __( 'Storing transcripts for %d days.', 'htech-ai-assistant' ), (int) $settings['retention_days'] )
			: __( 'Transcripts are not stored.', 'htech-ai-assistant' ),
		'fix'     => admin_url( 'admin.php?page=htech-ai-general' ),
		'fixText' => __( 'Privacy', 'htech-ai-assistant' ),
	),
);

$outstanding = array_filter( $checks, static function ( $check ) {
	return ! $check['ok'];
} );

$max_daily = 1;
foreach ( $stats['daily'] as $day ) {
	$max_daily = max( $max_daily, (int) $day['total'] );
}

HTECH_AI_Admin::shell_start(
	'htech-ai-assistant',
	__( 'Overview', 'htech-ai-assistant' ),
	__( 'Everything is stored on this website. No external AI service, no subscription, no central server.', 'htech-ai-assistant' ),
	'<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=htech-ai-settings' ) ) . '">' . esc_html__( 'Configure chatbot', 'htech-ai-assistant' ) . '</a>'
);
?>

<?php if ( $welcome ) : ?>
	<div class="htech-alert htech-alert--success">
		<span class="dashicons dashicons-superhero"></span>
		<div>
			<strong><?php esc_html_e( 'HTECH AI Assistant is installed on this site.', 'htech-ai-assistant' ); ?></strong>
			<p><?php esc_html_e( 'Your widget is already working in knowledge-base mode. Add business information so it can answer accurately, then customise the branding.', 'htech-ai-assistant' ); ?></p>
		</div>
	</div>
<?php endif; ?>

<?php if ( $outstanding ) : ?>
	<div class="htech-alert htech-alert--warn">
		<span class="dashicons dashicons-flag"></span>
		<div>
			<strong><?php esc_html_e( 'Setup checklist', 'htech-ai-assistant' ); ?></strong>
			<p><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d item still needs attention.', '%d items still need attention.', count( $outstanding ), 'htech-ai-assistant' ), count( $outstanding ) ) ); ?></p>
		</div>
	</div>
<?php endif; ?>

<div class="htech-grid htech-grid--stats" style="margin-bottom:18px">
	<div class="htech-stat">
		<p class="htech-stat__label"><?php esc_html_e( 'Conversations', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__value"><?php echo esc_html( number_format_i18n( $stats['conversations_total'] ) ); ?></p>
		<p class="htech-stat__meta">
			<?php
			printf(
				/* translators: 1: today, 2: this week */
				esc_html__( '%1$s today · %2$s in 7 days', 'htech-ai-assistant' ),
				esc_html( number_format_i18n( $stats['conversations_today'] ) ),
				esc_html( number_format_i18n( $stats['conversations_week'] ) )
			);
			?>
		</p>
	</div>

	<div class="htech-stat">
		<p class="htech-stat__label"><?php esc_html_e( 'Messages', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__value"><?php echo esc_html( number_format_i18n( $stats['messages_total'] ) ); ?></p>
		<p class="htech-stat__meta"><?php esc_html_e( 'Visitor + assistant turns stored', 'htech-ai-assistant' ); ?></p>
	</div>

	<div class="htech-stat">
		<p class="htech-stat__label"><?php esc_html_e( 'Knowledge chunks', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__value"><?php echo esc_html( number_format_i18n( $stats['chunks_total'] ) ); ?></p>
		<p class="htech-stat__meta">
			<?php
			printf(
				/* translators: 1: manual, 2: documents */
				esc_html__( '%1$s manual · %2$s from documents', 'htech-ai-assistant' ),
				esc_html( number_format_i18n( $stats['kb_manual_chunks'] ) ),
				esc_html( number_format_i18n( max( 0, $stats['chunks_total'] - $stats['kb_manual_chunks'] ) ) )
			);
			?>
		</p>
	</div>

	<div class="htech-stat">
		<p class="htech-stat__label"><?php esc_html_e( 'Documents', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__value"><?php echo esc_html( number_format_i18n( $stats['documents_total'] ) ); ?></p>
		<p class="htech-stat__meta">
			<?php
			printf(
				/* translators: %s: count */
				esc_html__( '%s processed and searchable', 'htech-ai-assistant' ),
				esc_html( number_format_i18n( $stats['documents_ready'] ) )
			);
			?>
		</p>
	</div>

	<div class="htech-stat">
		<p class="htech-stat__label"><?php esc_html_e( 'Leads', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__value"><?php echo esc_html( number_format_i18n( $stats['leads_total'] ) ); ?></p>
		<p class="htech-stat__meta">
			<?php
			if ( HTECH_AI_Settings::is_enabled( 'lead_enabled' ) ) {
				printf(
					/* translators: %s: count */
					esc_html__( '%s in the last 7 days', 'htech-ai-assistant' ),
					esc_html( number_format_i18n( $stats['leads_week'] ) )
				);
			} else {
				esc_html_e( 'Lead capture is off', 'htech-ai-assistant' );
			}
			?>
		</p>
	</div>
</div>

<div class="htech-grid htech-grid--2">
	<div class="htech-card">
		<div class="htech-card__head">
			<div>
				<h2><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Conversations, last 14 days', 'htech-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Counted from the conversations table in your own database.', 'htech-ai-assistant' ); ?></p>
			</div>
		</div>

		<?php if ( empty( $stats['daily'] ) || 0 === array_sum( wp_list_pluck( $stats['daily'], 'total' ) ) ) : ?>
			<div class="htech-empty">
				<span class="dashicons dashicons-chart-area"></span>
				<strong><?php esc_html_e( 'No conversations yet', 'htech-ai-assistant' ); ?></strong>
				<?php esc_html_e( 'Open your website and send the widget a test message — it will appear here immediately.', 'htech-ai-assistant' ); ?>
			</div>
		<?php else : ?>
			<div class="htech-chart-wrap">
				<div class="htech-chart">
					<?php
					$day_count = count( $stats['daily'] );
					$position  = 0;

					foreach ( $stats['daily'] as $day ) :
						// Label every third day plus the final day, keeping at least
						// two quiet slots before it so labels never overlap.
						$quiet = ( 0 !== $position % 3 ) && ( $position < $day_count - 2 );
						++$position;
						?>
						<div class="htech-chart__bar<?php echo $quiet ? ' htech-chart__bar--quiet' : ''; ?>"
							style="height:<?php echo esc_attr( max( 4, round( ( (int) $day['total'] / $max_daily ) * 100 ) ) ); ?>%"
							title="<?php echo esc_attr( $day['day'] . ': ' . $day['total'] ); ?>">
							<span><?php echo esc_html( gmdate( 'j M', strtotime( $day['day'] ) ) ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<div class="htech-card">
		<div class="htech-card__head">
			<div>
				<h2><span class="dashicons dashicons-shield-alt"></span> <?php esc_html_e( 'Answer quality', 'htech-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'How answers were produced. "No verified answer" means the bot correctly refused to guess.', 'htech-ai-assistant' ); ?></p>
			</div>
		</div>

		<dl class="htech-kv">
			<dt><?php esc_html_e( 'From business knowledge', 'htech-ai-assistant' ); ?></dt>
			<dd><strong><?php echo esc_html( number_format_i18n( $stats['fallback_answers'] ) ); ?></strong></dd>

			<dt><?php esc_html_e( 'Answered with on-device AI', 'htech-ai-assistant' ); ?></dt>
			<dd><strong><?php echo esc_html( number_format_i18n( $stats['ai_answers'] ) ); ?></strong></dd>

			<dt><?php esc_html_e( 'Refused (no verified info)', 'htech-ai-assistant' ); ?></dt>
			<dd><strong><?php echo esc_html( number_format_i18n( $stats['unanswered'] ) ); ?></strong></dd>

			<dt><?php esc_html_e( 'Manual knowledge words', 'htech-ai-assistant' ); ?></dt>
			<dd><?php echo esc_html( number_format_i18n( $coverage['words'] ) ); ?></dd>

			<dt><?php esc_html_e( 'Active FAQs', 'htech-ai-assistant' ); ?></dt>
			<dd><?php echo esc_html( number_format_i18n( $coverage['faqs'] ) ); ?></dd>
		</dl>

		<?php if ( $stats['unanswered'] > 0 ) : ?>
			<div class="htech-alert htech-alert--info" style="margin-top:16px">
				<span class="dashicons dashicons-lightbulb"></span>
				<div>
					<?php esc_html_e( 'Some questions had no verified answer. Review the conversations to see what visitors asked, then add that information to the knowledge base.', 'htech-ai-assistant' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=htech-ai-conversations' ) ); ?>"><?php esc_html_e( 'Review conversations', 'htech-ai-assistant' ); ?></a>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="htech-grid htech-grid--2">
	<div class="htech-card">
		<div class="htech-card__head">
			<div>
				<h2><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Setup checklist', 'htech-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Live status based on this installation.', 'htech-ai-assistant' ); ?></p>
			</div>
		</div>

		<?php foreach ( $checks as $check ) : ?>
			<div class="htech-toggle-row">
				<div class="htech-toggle-row__text">
					<strong>
						<span class="dashicons <?php echo $check['ok'] ? 'dashicons-yes' : 'dashicons-marker'; ?>" style="color:<?php echo $check['ok'] ? '#10B981' : '#F59E0B'; ?>;font-size:15px;width:15px;height:15px"></span>
						<?php echo esc_html( $check['label'] ); ?>
					</strong>
					<span><?php echo esc_html( $check['hint'] ); ?></span>
				</div>
				<?php if ( ! $check['ok'] ) : ?>
					<a class="button htech-btn--sm" href="<?php echo esc_url( $check['fix'] ); ?>"><?php echo esc_html( $check['fixText'] ); ?></a>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="htech-card">
		<div class="htech-card__head">
			<div>
				<h2><span class="dashicons dashicons-cpu"></span> <?php esc_html_e( 'AI engine status', 'htech-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Inference runs in the visitor\'s browser. Nothing is sent to a third party.', 'htech-ai-assistant' ); ?></p>
			</div>
		</div>

		<dl class="htech-kv">
			<dt><?php esc_html_e( 'Generative AI', 'htech-ai-assistant' ); ?></dt>
			<dd>
				<?php if ( HTECH_AI_Settings::is_enabled( 'ai_enabled' ) ) : ?>
					<span class="htech-pill htech-pill--on"><span class="htech-dot"></span><?php esc_html_e( 'Enabled', 'htech-ai-assistant' ); ?></span>
				<?php else : ?>
					<span class="htech-pill htech-pill--off"><span class="htech-dot"></span><?php esc_html_e( 'Off — knowledge-base mode', 'htech-ai-assistant' ); ?></span>
				<?php endif; ?>
			</dd>

			<dt><?php esc_html_e( 'Model', 'htech-ai-assistant' ); ?></dt>
			<dd class="htech-mono"><?php echo esc_html( $settings['ai_model'] ); ?></dd>

			<dt><?php esc_html_e( 'Load strategy', 'htech-ai-assistant' ); ?></dt>
			<dd>
				<?php
				$strategies = array(
					'manual' => __( 'On request — visitor clicks the AI badge (recommended)', 'htech-ai-assistant' ),
					'open'   => __( 'When the chat window opens', 'htech-ai-assistant' ),
					'eager'  => __( 'Immediately on page load (not recommended on mobile)', 'htech-ai-assistant' ),
				);
				echo esc_html( $strategies[ $settings['ai_auto_load'] ] ?? $settings['ai_auto_load'] );
				?>
			</dd>

			<dt><?php esc_html_e( 'WebGPU detected here', 'htech-ai-assistant' ); ?></dt>
			<dd><?php esc_html_e( 'Detected in the visitor\'s browser at runtime, not on the server.', 'htech-ai-assistant' ); ?></dd>

			<dt><?php esc_html_e( 'Fallback', 'htech-ai-assistant' ); ?></dt>
			<dd><?php esc_html_e( 'Always available: server-side keyword retrieval with extractive answers.', 'htech-ai-assistant' ); ?></dd>
		</dl>

		<div class="htech-alert htech-alert--info" style="margin-top:14px">
			<span class="dashicons dashicons-info"></span>
			<div><?php esc_html_e( 'WebGPU works in Chrome/Edge 113+, Opera and most recent Android Chrome. Safari and Firefox support is partial, so those visitors automatically get knowledge-base answers.', 'htech-ai-assistant' ); ?></div>
		</div>
	</div>
</div>

<div class="htech-card">
	<div class="htech-card__head">
		<div>
			<h2><span class="dashicons dashicons-media-document"></span> <?php esc_html_e( 'Recent documents', 'htech-ai-assistant' ); ?></h2>
		</div>
		<a class="button htech-btn--ghost htech-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=htech-ai-documents' ) ); ?>"><?php esc_html_e( 'Manage documents', 'htech-ai-assistant' ); ?></a>
	</div>

	<?php if ( empty( $docs['items'] ) ) : ?>
		<div class="htech-empty">
			<span class="dashicons dashicons-media-default"></span>
			<strong><?php esc_html_e( 'No documents uploaded yet', 'htech-ai-assistant' ); ?></strong>
			<?php esc_html_e( 'Upload price lists, service catalogues or policy PDFs to make them answerable.', 'htech-ai-assistant' ); ?>
		</div>
	<?php else : ?>
		<table class="htech-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Document', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Type', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Chunks', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Status', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Uploaded', 'htech-ai-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $docs['items'] as $document ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $document['title'] ); ?></strong><br><span class="htech-mono"><?php echo esc_html( $document['file_name'] ); ?></span></td>
						<td><span class="htech-badge"><?php echo esc_html( strtoupper( $document['file_type'] ) ); ?></span></td>
						<td><?php echo esc_html( number_format_i18n( $document['chunk_count'] ) ); ?></td>
						<td><span class="htech-badge htech-badge--<?php echo esc_attr( $document['status'] ); ?>"><?php echo esc_html( $document['status'] ); ?></span></td>
						<td class="htech-muted"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $document['created_at'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<?php HTECH_AI_Admin::shell_end(); ?>
