<?php
/**
 * Conversations screen.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$from   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
$to     = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$view   = isset( $_GET['conversation'] ) ? absint( $_GET['conversation'] ) : 0;
// phpcs:enable

$list = HTECH_AI_Database::get_conversations(
	array(
		'search'   => $search,
		'from'     => $from,
		'to'       => $to,
		'paged'    => $paged,
		'per_page' => 20,
	)
);

HTECH_AI_Admin::shell_start(
	'htech-ai-conversations',
	__( 'Conversations', 'htech-ai-assistant' ),
	HTECH_AI_Settings::is_enabled( 'store_conversations' )
		? sprintf( /* translators: %d: days */ __( 'Transcripts are stored on this server for %d days, then deleted automatically.', 'htech-ai-assistant' ), (int) HTECH_AI_Settings::get( 'retention_days', 90 ) )
		: __( 'Conversation storage is currently disabled, so no transcripts are recorded.', 'htech-ai-assistant' )
);
?>

<?php if ( ! HTECH_AI_Settings::is_enabled( 'store_conversations' ) ) : ?>
	<div class="htech-alert htech-alert--warn">
		<span class="dashicons dashicons-hidden"></span>
		<div>
			<strong><?php esc_html_e( 'Conversation storage is off.', 'htech-ai-assistant' ); ?></strong>
			<p><?php esc_html_e( 'The chatbot works normally but nothing is recorded. You can enable storage in General Settings → Privacy if you want transcripts and unanswered-question insights.', 'htech-ai-assistant' ); ?></p>
			<a class="button htech-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=htech-ai-general' ) ); ?>"><?php esc_html_e( 'Open privacy settings', 'htech-ai-assistant' ); ?></a>
		</div>
	</div>
<?php endif; ?>

<?php if ( $view ) : ?>
	<?php
	$conversation = HTECH_AI_Database::get_conversation( $view );

	if ( ! $conversation ) {
		echo '<div class="htech-alert htech-alert--error">' . esc_html__( 'That conversation no longer exists.', 'htech-ai-assistant' ) . '</div>';
		HTECH_AI_Admin::shell_end();
		return;
	}

	$messages = HTECH_AI_Database::get_messages( $view );
	$lead     = null;
	global $wpdb;
	$lead_table = HTECH_AI_Database::leads_table();
	$lead       = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$lead_table} WHERE conversation_id = %d", $view ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);
	?>

	<div class="htech-card">
		<div class="htech-card__head">
			<div>
				<h2>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=htech-ai-conversations' ) ); ?>">← <?php esc_html_e( 'All conversations', 'htech-ai-assistant' ); ?></a>
				</h2>
				<p>
					<?php
					printf(
						/* translators: 1: id, 2: start, 3: message count */
						esc_html__( 'Conversation #%1$s · started %2$s · %3$s messages', 'htech-ai-assistant' ),
						esc_html( (string) $conversation['id'] ),
						esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $conversation['created_at'] ) ),
						esc_html( (string) count( $messages ) )
					);
					?>
				</p>
			</div>
			<div class="htech-flex">
				<a class="button htech-btn--ghost htech-btn--sm" target="_blank" rel="noopener"
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=htech_ai_export_conversation&id=' . (int) $conversation['id'] ), 'htech_ai_export_conversation' ) ); ?>">
					<?php esc_html_e( 'Export .txt', 'htech-ai-assistant' ); ?>
				</a>
				<button type="button" class="button htech-btn--danger htech-btn--sm" data-delete-conversation="<?php echo esc_attr( $conversation['id'] ); ?>">
					<?php esc_html_e( 'Delete', 'htech-ai-assistant' ); ?>
				</button>
			</div>
		</div>

		<dl class="htech-kv" style="margin-bottom:18px">
			<dt><?php esc_html_e( 'Visitor', 'htech-ai-assistant' ); ?></dt>
			<dd><?php echo esc_html( $conversation['visitor_label'] ? $conversation['visitor_label'] : __( 'Anonymous', 'htech-ai-assistant' ) ); ?></dd>

			<dt><?php esc_html_e( 'Page', 'htech-ai-assistant' ); ?></dt>
			<dd class="htech-mono"><?php echo esc_html( $conversation['page_url'] ? $conversation['page_url'] : '—' ); ?></dd>

			<dt><?php esc_html_e( 'Lead captured', 'htech-ai-assistant' ); ?></dt>
			<dd><?php echo $lead ? esc_html( '#' . $lead['id'] . ' — ' . $lead['name'] . ' (' . $lead['email'] . ')' ) : esc_html__( 'No', 'htech-ai-assistant' ); ?></dd>
		</dl>

		<div class="htech-transcript">
			<?php foreach ( $messages as $message ) : ?>
				<div class="htech-transcript__row htech-transcript__row--<?php echo esc_attr( $message['role'] ); ?>">
					<?php echo nl2br( esc_html( $message['content'] ) ); ?>
					<div class="htech-transcript__meta">
						<span><?php echo esc_html( mysql2date( 'j M H:i', $message['created_at'] ) ); ?></span>
						<?php if ( $message['mode'] ) : ?>
							<span class="htech-badge"><?php echo esc_html( $message['mode'] ); ?></span>
						<?php endif; ?>
						<?php
						$sources = $message['sources'] ? json_decode( $message['sources'], true ) : array();
						if ( is_array( $sources ) && $sources ) :
							?>
							<span class="htech-badge htech-badge--needs_browser"><?php echo esc_html( $sources[0]['label'] ?? '' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

<?php else : ?>

	<div class="htech-card">
		<form method="get" class="htech-search-form">
			<input type="hidden" name="page" value="htech-ai-conversations" />
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search messages and visitors…', 'htech-ai-assistant' ); ?>" />
			<label class="htech-muted"><?php esc_html_e( 'From', 'htech-ai-assistant' ); ?></label>
			<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" />
			<label class="htech-muted"><?php esc_html_e( 'To', 'htech-ai-assistant' ); ?></label>
			<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" />
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'htech-ai-assistant' ); ?></button>
			<?php if ( $search || $from || $to ) : ?>
				<a class="button htech-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=htech-ai-conversations' ) ); ?>"><?php esc_html_e( 'Reset', 'htech-ai-assistant' ); ?></a>
			<?php endif; ?>
		</form>

		<?php if ( empty( $list['items'] ) ) : ?>
			<div class="htech-empty">
				<span class="dashicons dashicons-format-chat"></span>
				<strong><?php esc_html_e( 'No conversations found', 'htech-ai-assistant' ); ?></strong>
				<?php echo ( $search || $from || $to )
					? esc_html__( 'Nothing matches those filters. Try a broader search.', 'htech-ai-assistant' )
					: esc_html__( 'Conversations appear here as soon as visitors start chatting.', 'htech-ai-assistant' ); ?>
			</div>
		<?php else : ?>
			<table class="htech-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Conversation', 'htech-ai-assistant' ); ?></th>
						<th><?php esc_html_e( 'Visitor', 'htech-ai-assistant' ); ?></th>
						<th><?php esc_html_e( 'Messages', 'htech-ai-assistant' ); ?></th>
						<th><?php esc_html_e( 'Lead', 'htech-ai-assistant' ); ?></th>
						<th><?php esc_html_e( 'Last activity', 'htech-ai-assistant' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $list['items'] as $conversation ) : ?>
						<tr>
							<td><strong>#<?php echo esc_html( $conversation['id'] ); ?></strong></td>
							<td>
								<?php echo esc_html( $conversation['visitor_label'] ? $conversation['visitor_label'] : __( 'Anonymous', 'htech-ai-assistant' ) ); ?>
								<?php if ( $conversation['page_url'] ) : ?>
									<br><span class="htech-mono"><?php echo esc_html( wp_parse_url( $conversation['page_url'], PHP_URL_PATH ) ?: '/' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( number_format_i18n( (int) $conversation['message_count'] ) ); ?></td>
							<td>
								<?php if ( ! empty( $conversation['lead_captured'] ) ) : ?>
									<span class="htech-badge htech-badge--ready"><?php esc_html_e( 'Captured', 'htech-ai-assistant' ); ?></span>
								<?php else : ?>
									<span class="htech-muted">—</span>
								<?php endif; ?>
							</td>
							<td class="htech-muted"><?php echo esc_html( mysql2date( 'j M Y H:i', $conversation['updated_at'] ) ); ?></td>
							<td>
								<div class="htech-table__actions">
									<a class="button htech-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=htech-ai-conversations&conversation=' . (int) $conversation['id'] ) ); ?>"><?php esc_html_e( 'Open', 'htech-ai-assistant' ); ?></a>
									<button type="button" class="button htech-btn--sm htech-btn--danger" data-delete-conversation="<?php echo esc_attr( $conversation['id'] ); ?>"><?php esc_html_e( 'Delete', 'htech-ai-assistant' ); ?></button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $list['pages'] > 1 ) : ?>
				<div class="htech-pagination">
					<?php
					$base = admin_url( 'admin.php?page=htech-ai-conversations' );
					if ( $search ) { $base = add_query_arg( 's', $search, $base ); }
					if ( $from ) { $base = add_query_arg( 'from', $from, $base ); }
					if ( $to ) { $base = add_query_arg( 'to', $to, $base ); }
					?>
					<?php for ( $page = 1; $page <= $list['pages']; $page++ ) : ?>
						<a class="button htech-btn--sm <?php echo $page === $paged ? 'button-primary' : 'htech-btn--ghost'; ?>"
							href="<?php echo esc_url( add_query_arg( 'paged', $page, $base ) ); ?>"><?php echo esc_html( $page ); ?></a>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php HTECH_AI_Admin::shell_end(); ?>
