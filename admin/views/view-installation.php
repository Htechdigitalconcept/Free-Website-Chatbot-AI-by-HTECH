<?php
/**
 * Installation & embedding screen.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

$s          = HTECH_AI_Settings::all();
$shortcode  = '[htech_ai_assistant]';
$embed      = '<script src="' . esc_url( rest_url( 'htech-ai/v1/embed.js' ) ) . '" async></script>';
$rest_root  = rest_url( 'htech-ai/v1' );
$widget_on  = HTECH_AI_Settings::is_enabled( 'widget_enabled' );

$standalone_url = add_query_arg(
	array(
		'site'  => rawurlencode( home_url() ),
		'token' => wp_create_nonce( 'htech_ai_standalone' ),
	),
	rest_url( 'htech-ai/v1/standalone-config' )
);

HTECH_AI_Admin::shell_start(
	'htech-ai-installation',
	__( 'Installation', 'htech-ai-assistant' ),
	__( 'The WordPress widget needs no code. The shortcode and JavaScript snippet are optional extras.', 'htech-ai-assistant' )
);
?>

<div class="htech-grid htech-grid--2">
	<div class="htech-card">
		<div class="htech-card__head">
			<div>
				<h2><span class="dashicons dashicons-wordpress"></span> <?php esc_html_e( 'WordPress installation (primary)', 'htech-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Already done — you are reading this inside the plugin. Nothing else is required.', 'htech-ai-assistant' ); ?></p>
			</div>
			<span class="htech-pill <?php echo $widget_on ? 'htech-pill--on' : 'htech-pill--off'; ?>">
				<span class="htech-dot"></span>
				<?php echo $widget_on ? esc_html__( 'Widget live', 'htech-ai-assistant' ) : esc_html__( 'Widget off', 'htech-ai-assistant' ); ?>
			</span>
		</div>

		<ol class="htech-steps">
			<li>
				<strong><?php esc_html_e( 'Plugin installed and activated', 'htech-ai-assistant' ); ?></strong>
				<?php esc_html_e( 'Database tables and default settings were created on activation.', 'htech-ai-assistant' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Configure the business', 'htech-ai-assistant' ); ?></strong>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=htech-ai-settings' ) ); ?>"><?php esc_html_e( 'Chatbot Settings', 'htech-ai-assistant' ); ?></a> ·
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=htech-ai-knowledge' ) ); ?>"><?php esc_html_e( 'Knowledge Base', 'htech-ai-assistant' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Match the branding', 'htech-ai-assistant' ); ?></strong>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=htech-ai-appearance' ) ); ?>"><?php esc_html_e( 'Appearance & Branding', 'htech-ai-assistant' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Turn the widget on', 'htech-ai-assistant' ); ?></strong>
				<?php esc_html_e( 'Enable "Floating widget" in Chatbot Settings → Behaviour. The button then appears on every page.', 'htech-ai-assistant' ); ?>
			</li>
		</ol>
	</div>

	<div class="htech-card">
		<div class="htech-card__head">
			<div>
				<h2><span class="dashicons dashicons-shortcode"></span> <?php esc_html_e( 'Shortcode (optional)', 'htech-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Place the chat window inline in a page, post or page builder shortcode block.', 'htech-ai-assistant' ); ?></p>
			</div>
		</div>

		<div class="htech-inline-code">
			<button type="button" class="htech-copy-btn" data-copy-target="htech-code-shortcode"><?php esc_html_e( 'Copy', 'htech-ai-assistant' ); ?></button>
			<span id="htech-code-shortcode"><?php echo esc_html( $shortcode ); ?></span>
		</div>

		<p class="htech-ai-help" style="margin-top:12px"><?php esc_html_e( 'Optional attributes:', 'htech-ai-assistant' ); ?></p>
		<div class="htech-inline-code">
			<button type="button" class="htech-copy-btn" data-copy-target="htech-code-shortcode-2"><?php esc_html_e( 'Copy', 'htech-ai-assistant' ); ?></button>
			<span id="htech-code-shortcode-2">[htech_ai_assistant mode="inline" height="600" title="Ask us anything"]</span>
		</div>

		<ul class="htech-ai-help" style="margin-top:10px;padding-left:18px">
			<li><code>mode</code> — <code>floating</code> (default) or <code>inline</code>.</li>
			<li><code>height</code> / <code>width</code> — pixel dimensions for inline mode.</li>
			<li><code>title</code> — optional heading above the chat window.</li>
		</ul>

		<div class="htech-alert htech-alert--info" style="margin-top:14px">
			<span class="dashicons dashicons-info"></span>
			<div><?php esc_html_e( 'The shortcode works even when the floating widget is disabled — useful for a dedicated "Support" page.', 'htech-ai-assistant' ); ?></div>
		</div>
	</div>
</div>

<div class="htech-card">
	<div class="htech-card__head">
		<div>
			<h2><span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'JavaScript embed (optional)', 'htech-ai-assistant' ); ?></h2>
			<p><?php esc_html_e( 'Loads this installation\'s widget on a page that WordPress does not control — for example a static landing page on the same domain. It calls this site\'s REST API; there is no central HTECH server involved.', 'htech-ai-assistant' ); ?></p>
		</div>
	</div>

	<div class="htech-inline-code">
		<button type="button" class="htech-copy-btn" data-copy-target="htech-code-embed"><?php esc_html_e( 'Copy', 'htech-ai-assistant' ); ?></button>
		<span id="htech-code-embed"><?php echo esc_html( $embed ); ?></span>
	</div>

	<p class="htech-ai-help">
		<?php esc_html_e( 'Paste before </body>. Because the widget calls this site\'s API, the page must be able to load scripts from this domain. If the page is on a different domain, use the standalone package below instead — cross-origin nonces must not be shared.', 'htech-ai-assistant' ); ?>
	</p>

	<hr class="htech-divider" />

	<dl class="htech-kv">
		<dt><?php esc_html_e( 'REST namespace', 'htech-ai-assistant' ); ?></dt>
		<dd class="htech-mono"><?php echo esc_html( $rest_root ); ?></dd>

		<dt><?php esc_html_e( 'Public endpoints', 'htech-ai-assistant' ); ?></dt>
		<dd class="htech-mono">/bootstrap · /chat/message · /chat/complete · /chat/reset · /lead · /health</dd>

		<dt><?php esc_html_e( 'Health check', 'htech-ai-assistant' ); ?></dt>
		<dd><a href="<?php echo esc_url( rest_url( 'htech-ai/v1/health' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open /health', 'htech-ai-assistant' ); ?></a></dd>
	</dl>
</div>

<div class="htech-card">
	<div class="htech-card__head">
		<div>
			<h2><span class="dashicons dashicons-media-code"></span> <?php esc_html_e( 'Standalone JavaScript package (non-WordPress sites)', 'htech-ai-assistant' ); ?></h2>
			<p><?php esc_html_e( 'A self-contained copy of the widget for plain HTML sites, static hosts or another CMS. It runs entirely on the client site and only uses the knowledge file you generate here.', 'htech-ai-assistant' ); ?></p>
		</div>
	</div>

	<ol class="htech-steps">
		<li>
			<strong><?php esc_html_e( 'Copy the package folder', 'htech-ai-assistant' ); ?></strong>
			<?php esc_html_e( 'In the delivered ZIP there is a folder named "standalone/". Upload it to your site, e.g. https://example.com/htech-ai/.', 'htech-ai-assistant' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Generate the knowledge file', 'htech-ai-assistant' ); ?></strong>
			<?php esc_html_e( 'Export your knowledge base as JSON (Knowledge Base → Export JSON), then copy the exported chunks into the package with the included script, or use the prebuilt knowledge.json placeholder and replace its contents.', 'htech-ai-assistant' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Edit config.js', 'htech-ai-assistant' ); ?></strong>
			<?php esc_html_e( 'Set the business name, colours, welcome message, suggested questions and whether AI is enabled.', 'htech-ai-assistant' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Add two tags to the page', 'htech-ai-assistant' ); ?></strong>
			<?php esc_html_e( 'A stylesheet link and the widget script. Full instructions are in standalone/README.md.', 'htech-ai-assistant' ); ?>
		</li>
	</ol>

	<div class="htech-alert htech-alert--warn">
		<span class="dashicons dashicons-warning"></span>
		<div>
			<strong><?php esc_html_e( 'Standalone limitations — stated honestly.', 'htech-ai-assistant' ); ?></strong>
			<p><?php esc_html_e( 'Without WordPress there is no server-side database, so the standalone package does the same BM25 retrieval in the browser over a static knowledge file. That means: (1) the whole knowledge file is downloadable by anyone who can read the page — do not include confidential documents; (2) conversations and leads cannot be stored on a server; (3) updating knowledge means regenerating the file. If you need stored transcripts or lead capture, use the WordPress plugin.', 'htech-ai-assistant' ); ?></p>
		</div>
	</div>

	<div class="htech-inline-code">
		<button type="button" class="htech-copy-btn" data-copy-target="htech-code-standalone"><?php esc_html_e( 'Copy', 'htech-ai-assistant' ); ?></button>
		<span id="htech-code-standalone">&lt;link rel="stylesheet" href="/htech-ai/chatbot-widget.css"&gt;
&lt;script src="/htech-ai/utils/chunker.js"&gt;&lt;/script&gt;
&lt;script src="/htech-ai/standalone.js"&gt;&lt;/script&gt;</span>
	</div>
</div>

<div class="htech-card">
	<div class="htech-card__head">
		<div>
			<h2><span class="dashicons dashicons-admin-site-alt"></span> <?php esc_html_e( 'Delivery checklist for HTECH', 'htech-ai-assistant' ); ?></h2>
			<p><?php esc_html_e( 'Repeat these steps on every client site — each installation is fully independent.', 'htech-ai-assistant' ); ?></p>
		</div>
	</div>

	<ol class="htech-steps">
		<li><strong><?php esc_html_e( 'Upload the ZIP', 'htech-ai-assistant' ); ?></strong><?php esc_html_e( 'Plugins → Add New → Upload Plugin → Activate.', 'htech-ai-assistant' ); ?></li>
		<li><strong><?php esc_html_e( 'Enter the business details', 'htech-ai-assistant' ); ?></strong><?php esc_html_e( 'Name, category, description, purpose, personality, language.', 'htech-ai-assistant' ); ?></li>
		<li><strong><?php esc_html_e( 'Fill the knowledge base', 'htech-ai-assistant' ); ?></strong><?php esc_html_e( 'Overview, services, pricing, hours, contact, policies and 5–10 FAQs.', 'htech-ai-assistant' ); ?></li>
		<li><strong><?php esc_html_e( 'Upload documents', 'htech-ai-assistant' ); ?></strong><?php esc_html_e( 'Any PDF/DOCX catalogue or policy. Watch the chunk count in the Documents screen.', 'htech-ai-assistant' ); ?></li>
		<li><strong><?php esc_html_e( 'Apply the client\'s branding', 'htech-ai-assistant' ); ?></strong><?php esc_html_e( 'Logo, colours, font — and leave HTECH attribution off.', 'htech-ai-assistant' ); ?></li>
		<li><strong><?php esc_html_e( 'Test as a visitor', 'htech-ai-assistant' ); ?></strong><?php esc_html_e( 'Ask 10 real customer questions in a private window, including one you know the answer to and one you do not.', 'htech-ai-assistant' ); ?></li>
		<li><strong><?php esc_html_e( 'Hand over', 'htech-ai-assistant' ); ?></strong><?php esc_html_e( 'Give the client the installation, configuration and troubleshooting guides from the delivery package.', 'htech-ai-assistant' ); ?></li>
	</ol>
</div>

<?php HTECH_AI_Admin::shell_end(); ?>
