<?php
/**
 * Leads screen.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable

$table  = HTECH_AI_Database::leads_table();
$where  = array( '1=1' );
$params = array();

if ( $search ) {
	$like     = '%' . $wpdb->esc_like( $search ) . '%';
	$where[]  = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR description LIKE %s)';
	$params   = array_merge( $params, array( $like, $like, $like, $like ) );
}
if ( $status ) {
	$where[]  = 'status = %s';
	$params[] = $status;
}

$where_sql = implode( ' AND ', $where );
$per_page  = 25;
$offset    = ( $paged - 1 ) * $per_page;

$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

$rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		array_merge( $params, array( $per_page, $offset ) )
	),
	ARRAY_A
);

$new_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'new' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

HTECH_AI_Admin::shell_start(
	'htech-ai-leads',
	__( 'Leads', 'htech-ai-assistant' ),
	__( 'Enquiries visitors chose to submit through the chatbot. Nothing here is collected without consent.', 'htech-ai-assistant' ),
	'<a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=htech_ai_export_leads' ), 'htech_ai_export_leads' ) ) . '">' . esc_html__( 'Export CSV', 'htech-ai-assistant' ) . '</a>'
);
?>

<?php if ( ! HTECH_AI_Settings::is_enabled( 'lead_enabled' ) ) : ?>
	<div class="htech-alert htech-alert--info">
		<span class="dashicons dashicons-info"></span>
		<div>
			<strong><?php esc_html_e( 'Lead collection is currently disabled.', 'htech-ai-assistant' ); ?></strong>
			<p><?php esc_html_e( 'Existing leads remain visible. Enable lead capture in Chatbot Settings → Lead capture to start collecting enquiries again.', 'htech-ai-assistant' ); ?></p>
			<a class="button htech-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=htech-ai-settings&tab=leads' ) ); ?>"><?php esc_html_e( 'Open lead settings', 'htech-ai-assistant' ); ?></a>
		</div>
	</div>
<?php endif; ?>

<div class="htech-grid htech-grid--stats" style="margin-bottom:18px">
	<div class="htech-stat">
		<p class="htech-stat__label"><?php esc_html_e( 'All leads', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__value"><?php echo esc_html( number_format_i18n( $total ) ); ?></p>
	</div>
	<div class="htech-stat">
		<p class="htech-stat__label"><?php esc_html_e( 'New / uncontacted', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__value"><?php echo esc_html( number_format_i18n( $new_count ) ); ?></p>
	</div>
	<div class="htech-stat">
		<p class="htech-stat__label"><?php esc_html_e( 'Retention', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__value" style="font-size:20px"><?php echo esc_html( (int) HTECH_AI_Settings::get( 'lead_retention_days', 365 ) ); ?> <?php esc_html_e( 'days', 'htech-ai-assistant' ); ?></p>
		<p class="htech-stat__meta"><?php esc_html_e( 'Deleted automatically after this period', 'htech-ai-assistant' ); ?></p>
	</div>
</div>

<div class="htech-card">
	<form method="get" class="htech-search-form">
		<input type="hidden" name="page" value="htech-ai-leads" />
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, phone…', 'htech-ai-assistant' ); ?>" />
		<select name="status">
			<option value=""><?php esc_html_e( 'All statuses', 'htech-ai-assistant' ); ?></option>
			<option value="new" <?php selected( $status, 'new' ); ?>><?php esc_html_e( 'New', 'htech-ai-assistant' ); ?></option>
			<option value="contacted" <?php selected( $status, 'contacted' ); ?>><?php esc_html_e( 'Contacted', 'htech-ai-assistant' ); ?></option>
			<option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Closed', 'htech-ai-assistant' ); ?></option>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'htech-ai-assistant' ); ?></button>
	</form>

	<?php if ( empty( $rows ) ) : ?>
		<div class="htech-empty">
			<span class="dashicons dashicons-email-alt"></span>
			<strong><?php esc_html_e( 'No leads yet', 'htech-ai-assistant' ); ?></strong>
			<?php esc_html_e( 'When a visitor shares their details in the chat window, the enquiry appears here.', 'htech-ai-assistant' ); ?>
		</div>
	<?php else : ?>
		<table class="htech-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Received', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Contact', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Interest', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Message', 'htech-ai-assistant' ); ?></th>
					<th><?php esc_html_e( 'Status', 'htech-ai-assistant' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $lead ) : ?>
					<tr>
						<td class="htech-muted">
							<?php echo esc_html( mysql2date( 'j M Y', $lead['created_at'] ) ); ?><br>
							<?php echo esc_html( mysql2date( 'H:i', $lead['created_at'] ) ); ?>
						</td>
						<td>
							<strong><?php echo esc_html( $lead['name'] ); ?></strong><br>
							<?php if ( $lead['email'] ) : ?>
								<a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>"><?php echo esc_html( $lead['email'] ); ?></a><br>
							<?php endif; ?>
							<?php if ( $lead['phone'] ) : ?>
								<span class="htech-mono"><?php echo esc_html( $lead['phone'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $lead['service'] ? $lead['service'] : '—' ); ?></td>
						<td style="max-width:340px">
							<span class="htech-muted"><?php echo esc_html( wp_trim_words( (string) $lead['description'], 22 ) ); ?></span>
							<?php if ( $lead['conversation_id'] ) : ?>
								<br><a href="<?php echo esc_url( admin_url( 'admin.php?page=htech-ai-conversations&conversation=' . (int) $lead['conversation_id'] ) ); ?>"><?php esc_html_e( 'View conversation', 'htech-ai-assistant' ); ?></a>
							<?php endif; ?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'htech_ai_lead_status' ); ?>
								<input type="hidden" name="action" value="htech_ai_lead_status" />
								<input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead['id'] ); ?>" />
								<select name="status" onchange="this.form.submit()">
									<option value="new" <?php selected( $lead['status'], 'new' ); ?>><?php esc_html_e( 'New', 'htech-ai-assistant' ); ?></option>
									<option value="contacted" <?php selected( $lead['status'], 'contacted' ); ?>><?php esc_html_e( 'Contacted', 'htech-ai-assistant' ); ?></option>
									<option value="closed" <?php selected( $lead['status'], 'closed' ); ?>><?php esc_html_e( 'Closed', 'htech-ai-assistant' ); ?></option>
								</select>
							</form>
						</td>
						<td>
							<div class="htech-table__actions">
								<button type="button" class="button htech-btn--sm htech-btn--danger" data-tool="purge-lead" data-delete-lead="<?php echo esc_attr( $lead['id'] ); ?>">
									<?php esc_html_e( 'Delete', 'htech-ai-assistant' ); ?>
								</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total > $per_page ) : ?>
			<div class="htech-pagination">
				<?php
				$base = admin_url( 'admin.php?page=htech-ai-leads' );
				if ( $search ) { $base = add_query_arg( 's', $search, $base ); }
				if ( $status ) { $base = add_query_arg( 'status', $status, $base ); }
				$pages = (int) ceil( $total / $per_page );
				?>
				<?php for ( $page = 1; $page <= $pages; $page++ ) : ?>
					<a class="button htech-btn--sm <?php echo $page === $paged ? 'button-primary' : 'htech-btn--ghost'; ?>"
						href="<?php echo esc_url( add_query_arg( 'paged', $page, $base ) ); ?>"><?php echo esc_html( $page ); ?></a>
				<?php endfor; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<script>
/* Lead deletion uses the same REST helper pattern as documents. */
document.querySelectorAll('[data-delete-lead]').forEach(function (button) {
	button.addEventListener('click', function () {
		if (!window.confirm('Delete this lead permanently?')) { return; }

		fetch(window.HTECH_AI_ADMIN.restUrl + '/admin/leads/delete', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.HTECH_AI_ADMIN.nonce },
			credentials: 'same-origin',
			body: JSON.stringify({ id: parseInt(button.getAttribute('data-delete-lead'), 10) })
		}).then(function (response) { return response.json(); }).then(function () {
			var row = button.closest('tr');
			if (row) { row.remove(); }
		});
	});
});
</script>

<?php HTECH_AI_Admin::shell_end(); ?>
