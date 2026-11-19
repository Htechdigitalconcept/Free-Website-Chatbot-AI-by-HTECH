<?php
/**
 * Chatbot Settings screen.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

$s = HTECH_AI_Settings::all();

$purposes = array(
	'general'       => __( 'General AI assistant — answer anything about the business, plus everyday questions.', 'htech-ai-assistant' ),
	'support'       => __( 'Customer support — resolve issues, explain policies, escalate to a human.', 'htech-ai-assistant' ),
	'sales'         => __( 'Sales assistant — explain offerings, qualify interest, invite a quote request.', 'htech-ai-assistant' ),
	'business-info' => __( 'Business information assistant — hours, location, contact details, services.', 'htech-ai-assistant' ),
	'lead-gen'      => __( 'Lead generation — understand the need and collect contact details.', 'htech-ai-assistant' ),
	'custom'        => __( 'Custom assistant — describe the role yourself below.', 'htech-ai-assistant' ),
);

$personalities = array(
	'friendly'     => __( 'Friendly and warm', 'htech-ai-assistant' ),
	'professional' => __( 'Professional and precise', 'htech-ai-assistant' ),
	'concise'      => __( 'Short and concise', 'htech-ai-assistant' ),
	'enthusiastic' => __( 'Upbeat and enthusiastic', 'htech-ai-assistant' ),
	'formal'       => __( 'Formal and polite', 'htech-ai-assistant' ),
	'witty'        => __( 'Lightly witty', 'htech-ai-assistant' ),
);

$languages = array(
	'en' => 'English',
	'fr' => 'Français',
	'es' => 'Español',
	'pt' => 'Português',
	'ar' => 'العربية',
	'de' => 'Deutsch',
	'it' => 'Italiano',
	'nl' => 'Nederlands',
	'sw' => 'Kiswahili',
	'yo' => 'Yorùbá',
	'ha' => 'Hausa',
	'ig' => 'Igbo',
	'zh' => '中文',
	'hi' => 'हिन्दी',
);

HTECH_AI_Admin::shell_start(
	'htech-ai-settings',
	__( 'Chatbot Settings', 'htech-ai-assistant' ),
	__( 'Identity, purpose, behaviour and AI engine. Saved per installation.', 'htech-ai-assistant' )
);
?>

<div class="htech-tabs" data-htech-tabs>
	<button type="button" class="is-active" data-tab="identity"><?php esc_html_e( 'Identity & purpose', 'htech-ai-assistant' ); ?></button>
	<button type="button" data-tab="behaviour"><?php esc_html_e( 'Behaviour', 'htech-ai-assistant' ); ?></button>
	<button type="button" data-tab="ai"><?php esc_html_e( 'AI engine', 'htech-ai-assistant' ); ?></button>
	<button type="button" data-tab="knowledge"><?php esc_html_e( 'Retrieval', 'htech-ai-assistant' ); ?></button>
	<button type="button" data-tab="leads"><?php esc_html_e( 'Lead capture', 'htech-ai-assistant' ); ?></button>
</div>

<?php HTECH_AI_Admin::settings_form_open(); ?>

<!-- ============================== IDENTITY ============================== -->
<div class="htech-tab-panel" data-tab-panel="identity">
	<div class="htech-grid htech-grid--2">
		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'Business identity', 'htech-ai-assistant' ); ?></h2>
					<p><?php esc_html_e( 'Used to ground every answer. The more accurate this is, the better the answers.', 'htech-ai-assistant' ); ?></p>
				</div>
			</div>

			<?php
			HTECH_AI_Admin::field( 'business_name', __( 'Business name', 'htech-ai-assistant' ), __( 'The exact name visitors know you by.', 'htech-ai-assistant' ) );
			HTECH_AI_Admin::field( 'business_category', __( 'Business category', 'htech-ai-assistant' ), __( 'e.g. Digital agency, Restaurant, Law firm, Online store.', 'htech-ai-assistant' ) );
			HTECH_AI_Admin::field(
				'business_description',
				__( 'Business description', 'htech-ai-assistant' ),
				__( 'One or two sentences describing what you do and who you help. This is sent to the AI model as context.', 'htech-ai-assistant' ),
				array( 'type' => 'textarea', 'rows' => 4 )
			);
			HTECH_AI_Admin::field( 'website_url', __( 'Website URL', 'htech-ai-assistant' ), __( 'Shown to visitors when you have no other contact information.', 'htech-ai-assistant' ), array( 'type' => 'url' ) );
			?>
		</div>

		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( 'Assistant identity', 'htech-ai-assistant' ); ?></h2>
				</div>
			</div>

			<?php
			HTECH_AI_Admin::field( 'chatbot_name', __( 'Chatbot name', 'htech-ai-assistant' ), __( 'Shown in the widget header, e.g. "HTECH Assistant" or "Ada from Support".', 'htech-ai-assistant' ) );
			HTECH_AI_Admin::field(
				'welcome_message',
				__( 'Welcome message', 'htech-ai-assistant' ),
				__( 'The first message a visitor sees. Keep it short and inviting.', 'htech-ai-assistant' ),
				array( 'type' => 'textarea', 'rows' => 3 )
			);
			HTECH_AI_Admin::field(
				'primary_language',
				__( 'Primary language', 'htech-ai-assistant' ),
				__( 'The AI is instructed to reply in this language. Knowledge-base answers use the language your content is written in.', 'htech-ai-assistant' ),
				array( 'type' => 'select', 'options' => $languages )
			);
			HTECH_AI_Admin::field(
				'suggested_questions',
				__( 'Suggested questions', 'htech-ai-assistant' ),
				__( 'One per line. Shown as tappable chips under the welcome message. Active FAQ questions are added automatically.', 'htech-ai-assistant' ),
				array( 'type' => 'textarea', 'rows' => 5 )
			);
			?>
		</div>
	</div>

	<div class="htech-card">
		<div class="htech-card__head">
			<div>
				<h2><span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e( 'Purpose & personality', 'htech-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Purpose controls the assistant\'s objective. Personality controls tone. Both are enforced in the system prompt.', 'htech-ai-assistant' ); ?></p>
			</div>
		</div>

		<div class="htech-field-row">
			<?php
			HTECH_AI_Admin::field( 'purpose', __( 'Primary purpose', 'htech-ai-assistant' ), '', array( 'type' => 'select', 'options' => $purposes ) );
			HTECH_AI_Admin::field( 'personality', __( 'Personality', 'htech-ai-assistant' ), '', array( 'type' => 'select', 'options' => $personalities ) );
			?>
		</div>

		<?php
		HTECH_AI_Admin::field(
			'purpose_custom',
			__( 'Custom purpose description', 'htech-ai-assistant' ),
			__( 'Only used when purpose is set to "Custom assistant". Describe the job in one sentence.', 'htech-ai-assistant' ),
			array( 'type' => 'textarea', 'rows' => 2 )
		);
		?>
	</div>
</div>

<!-- ============================== BEHAVIOUR ============================= -->
<div class="htech-tab-panel" data-tab-panel="behaviour" hidden>
	<div class="htech-grid htech-grid--2">
		<div class="htech-card">
			<div class="htech-card__head">
				<h2><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Where the widget appears', 'htech-ai-assistant' ); ?></h2>
			</div>

			<div class="htech-toggle-row">
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Floating widget', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Show the chat button on your site. The shortcode works independently of this switch.', 'htech-ai-assistant' ); ?></span>
				</div>
<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[widget_enabled]" value="0" />
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-widget_enabled" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[widget_enabled]" value="1" <?php checked( (bool) $s['widget_enabled'], true ); ?> />
					<span></span>
				</label>
			</div>

			<?php
			HTECH_AI_Admin::field(
				'display_pages',
				__( 'Show on', 'htech-ai-assistant' ),
				__( 'Restrict the floating widget to specific pages or post IDs.', 'htech-ai-assistant' ),
				array(
					'type'    => 'select',
					'options' => array(
						'all'     => __( 'Every page', 'htech-ai-assistant' ),
						'include' => __( 'Only the IDs listed below', 'htech-ai-assistant' ),
						'exclude' => __( 'Every page except the IDs listed below', 'htech-ai-assistant' ),
					),
				)
			);
			HTECH_AI_Admin::field( 'display_ids', __( 'Page / post IDs', 'htech-ai-assistant' ), __( 'Comma-separated IDs, e.g. 12,48,102. Find the ID in the page editor URL.', 'htech-ai-assistant' ) );
			?>
		</div>

		<div class="htech-card">
			<div class="htech-card__head">
				<h2><span class="dashicons dashicons-admin-comments"></span> <?php esc_html_e( 'Status & conversation', 'htech-ai-assistant' ); ?></h2>
			</div>

			<div class="htech-toggle-row">
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Show "online" indicator', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'A green dot and status line in the widget header. It is a display setting, not live presence detection.', 'htech-ai-assistant' ); ?></span>
				</div>
<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[online_status]" value="0" />
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-online_status" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[online_status]" value="1" <?php checked( (bool) $s['online_status'], true ); ?> />
					<span></span>
				</label>
			</div>

			<?php
			HTECH_AI_Admin::field( 'online_text', __( 'Online text', 'htech-ai-assistant' ) );
			HTECH_AI_Admin::field( 'offline_text', __( 'Offline text', 'htech-ai-assistant' ), __( 'Used when the online indicator is disabled.', 'htech-ai-assistant' ) );
			HTECH_AI_Admin::field(
				'max_message_length',
				__( 'Maximum message length', 'htech-ai-assistant' ),
				__( 'Characters a visitor can type per message. 1000 is a sensible default.', 'htech-ai-assistant' ),
				array( 'type' => 'number', 'min' => 100, 'max' => 8000 )
			);
			HTECH_AI_Admin::field(
				'rate_limit_per_minute',
				__( 'Messages per minute (per visitor)', 'htech-ai-assistant' ),
				__( 'Protects your server from abuse. Applies per IP address.', 'htech-ai-assistant' ),
				array( 'type' => 'number', 'min' => 1, 'max' => 300 )
			);
			?>

			<div class="htech-toggle-row">
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Clear transcript when closed', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Start a fresh conversation each time the window is closed.', 'htech-ai-assistant' ); ?></span>
				</div>
<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[clear_on_close]" value="0" />
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-clear_on_close" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[clear_on_close]" value="1" <?php checked( (bool) $s['clear_on_close'], true ); ?> />
					<span></span>
				</label>
			</div>
		</div>
	</div>

	<div class="htech-card">
		<div class="htech-card__head">
			<h2><span class="dashicons dashicons-controls-repeat"></span> <?php esc_html_e( 'Conversation context', 'htech-ai-assistant' ); ?></h2>
			<p><?php esc_html_e( 'When enabled, recent turns are sent with each question so follow-ups like "how much does it cost?" are understood.', 'htech-ai-assistant' ); ?></p>
		</div>

		<div class="htech-toggle-row">
			<div class="htech-toggle-row__text">
				<strong><?php esc_html_e( 'Maintain conversation context', 'htech-ai-assistant' ); ?></strong>
				<span><?php esc_html_e( 'Recommended. Also makes the retrieval engine widen vague follow-up questions.', 'htech-ai-assistant' ); ?></span>
			</div>
<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[conversation_context]" value="0" />
			<label class="htech-ai-toggle">
				<input type="checkbox" id="htech-field-conversation_context" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[conversation_context]" value="1" <?php checked( (bool) $s['conversation_context'], true ); ?> />
				<span></span>
			</label>
		</div>

		<?php
		HTECH_AI_Admin::field(
			'context_messages',
			__( 'Previous messages sent to the model', 'htech-ai-assistant' ),
			__( 'Each extra message costs GPU time on the visitor\'s device. 6–10 is a good balance.', 'htech-ai-assistant' ),
			array( 'type' => 'number', 'min' => 0, 'max' => 30 )
		);
		?>
	</div>
</div>

<!-- ================================= AI ================================ -->
<div class="htech-tab-panel" data-tab-panel="ai" hidden>
	<div class="htech-card">
		<div class="htech-card__head">
			<div>
				<h2><span class="dashicons dashicons-cpu"></span> <?php esc_html_e( 'On-device AI (WebLLM + WebGPU)', 'htech-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'The model runs inside the visitor\'s browser using their GPU. No API key, no per-message cost, and questions are never sent to an AI provider.', 'htech-ai-assistant' ); ?></p>
			</div>
			<span class="htech-pill htech-pill--info"><?php esc_html_e( '$0 running cost', 'htech-ai-assistant' ); ?></span>
		</div>

		<div class="htech-alert htech-alert--warn">
			<span class="dashicons dashicons-warning"></span>
			<div>
				<p><strong><?php esc_html_e( 'Be realistic about browser AI.', 'htech-ai-assistant' ); ?></strong></p>
				<p><?php esc_html_e( 'The first visitor to use AI waits for a one-off model download (250 MB – 1.1 GB depending on the model) which is cached by the browser afterwards. It requires WebGPU, a reasonably modern device and a fast connection. Visitors without WebGPU automatically get knowledge-base answers instead — the widget stays useful.', 'htech-ai-assistant' ); ?></p>
			</div>
		</div>

		<div class="htech-toggle-row">
			<div class="htech-toggle-row__text">
				<strong><?php esc_html_e( 'Enable generative AI', 'htech-ai-assistant' ); ?></strong>
				<span><?php esc_html_e( 'When off, the chatbot answers purely from your knowledge base. This is faster, works everywhere, and is the recommended setting for most businesses.', 'htech-ai-assistant' ); ?></span>
			</div>
<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[ai_enabled]" value="0" />
			<label class="htech-ai-toggle">
				<input type="checkbox" id="htech-field-ai_enabled" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[ai_enabled]" value="1" <?php checked( (bool) $s['ai_enabled'], true ); ?> />
				<span></span>
			</label>
		</div>

		<div class="htech-field-row">
			<?php
			HTECH_AI_Admin::field(
				'ai_provider',
				__( 'Provider', 'htech-ai-assistant' ),
				__( '"Browser (WebLLM)" is the only genuinely $0 option. "Own gateway" is for administrators who already run their own server and is optional.', 'htech-ai-assistant' ),
				array(
					'type'    => 'select',
					'options' => array(
						'webllm'  => __( 'Browser — WebLLM / WebGPU (free)', 'htech-ai-assistant' ),
						'gateway' => __( 'Own inference gateway (advanced, self-hosted)', 'htech-ai-assistant' ),
						'off'     => __( 'Disable generative AI', 'htech-ai-assistant' ),
					),
				)
			);
			HTECH_AI_Admin::field(
				'ai_model',
				__( 'Model', 'htech-ai-assistant' ),
				__( 'Smaller models download faster and run on weaker devices. Qwen2.5 1.5B is the best all-round choice.', 'htech-ai-assistant' ),
				array(
					'type'    => 'select',
					'options' => array(
						'SmolLM2-360M-Instruct-q4f16_1-MLC'   => 'SmolLM2 360M — ~376 MB VRAM, very fast, basic quality',
						'Qwen2.5-0.5B-Instruct-q4f16_1-MLC'  => 'Qwen2.5 0.5B — ~945 MB, fast, good for FAQ-style chat',
						'Qwen2.5-1.5B-Instruct-q4f16_1-MLC'  => 'Qwen2.5 1.5B — ~1.6 GB, recommended balance',
						'Llama-3.2-1B-Instruct-q4f16_1-MLC'  => 'Llama 3.2 1B — ~879 MB, strong instruction following',
						'Llama-3.2-3B-Instruct-q4f16_1-MLC'  => 'Llama 3.2 3B — ~2.3 GB, best quality, needs a decent GPU',
						'Phi-3.5-mini-instruct-q4f16_1-MLC'  => 'Phi-3.5 mini — ~2.5 GB, excellent reasoning',
						'gemma3-1b-it-q4f16_1-MLC'           => 'Gemma 3 1B — ~711 MB, compact and capable',
						'custom'                              => __( 'Custom model id (advanced)', 'htech-ai-assistant' ),
					),
				)
			);
			?>
		</div>

		<?php
		HTECH_AI_Admin::field(
			'ai_model_custom',
			__( 'Custom model ID', 'htech-ai-assistant' ),
			__( 'Any model id available in the WebLLM prebuilt config, e.g. Qwen3-1.7B-q4f16_1-MLC. Leave empty unless you know the exact id.', 'htech-ai-assistant' ),
			array( 'placeholder' => 'Qwen3-1.7B-q4f16_1-MLC' )
		);

		HTECH_AI_Admin::field(
			'ai_auto_load',
			__( 'When to download the model', 'htech-ai-assistant' ),
			__( 'Never download a large model automatically on page load — it wastes visitors\' data. "On request" shows an "Enable AI answers" button in the widget.', 'htech-ai-assistant' ),
			array(
				'type'    => 'select',
				'options' => array(
					'manual' => __( 'On request — visitor clicks the AI badge (recommended)', 'htech-ai-assistant' ),
					'open'   => __( 'When the chat window is opened', 'htech-ai-assistant' ),
					'eager'  => __( 'Immediately after page load (not recommended)', 'htech-ai-assistant' ),
				),
			)
		);

		HTECH_AI_Admin::field(
			'ai_lib_url',
			__( 'WebLLM library URL', 'htech-ai-assistant' ),
			__( 'Loaded only when AI is actually used. For strict privacy, download the package and host it locally, then point this field at your own copy (e.g. <code>https://yoursite.com/wp-content/uploads/htech-ai-lib/index.js</code>).', 'htech-ai-assistant' ),
			array( 'type' => 'url' )
		);

		HTECH_AI_Admin::field(
			'ai_gateway_url',
			__( 'Self-hosted gateway URL (optional)', 'htech-ai-assistant' ),
			__( 'Only for administrators running their own Ollama/llama.cpp-compatible endpoint. Leave empty. HTECH does not provide such a service.', 'htech-ai-assistant' ),
			array( 'type' => 'url' )
		);
		?>
	</div>

	<div class="htech-grid htech-grid--2">
		<div class="htech-card">
			<div class="htech-card__head">
				<h2><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Generation settings', 'htech-ai-assistant' ); ?></h2>
			</div>

			<?php
			HTECH_AI_Admin::field(
				'ai_temperature',
				__( 'Temperature', 'htech-ai-assistant' ),
				__( '0 = very consistent, 1 = creative. Keep it low (0.3–0.7) for business answers.', 'htech-ai-assistant' ),
				array( 'type' => 'number', 'min' => 0, 'max' => 1.5, 'step' => 0.1 )
			);
			HTECH_AI_Admin::field(
				'ai_max_tokens',
				__( 'Maximum answer length (tokens)', 'htech-ai-assistant' ),
				__( 'Higher values take longer on small devices. 256–512 is plenty for chat.', 'htech-ai-assistant' ),
				array( 'type' => 'number', 'min' => 64, 'max' => 2048 )
			);
			?>
		</div>

		<div class="htech-card">
			<div class="htech-card__head">
				<h2><span class="dashicons dashicons-shield-alt"></span> <?php esc_html_e( 'Answer rules', 'htech-ai-assistant' ); ?></h2>
			</div>

			<div class="htech-toggle-row">
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Strict business knowledge', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Blocks answers containing refund/warranty/discount claims when no business knowledge matched, and rejects invented prices server-side.', 'htech-ai-assistant' ); ?></span>
				</div>
<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[ai_strict_knowledge]" value="0" />
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-ai_strict_knowledge" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[ai_strict_knowledge]" value="1" <?php checked( (bool) $s['ai_strict_knowledge'], true ); ?> />
					<span></span>
				</label>
			</div>

			<div class="htech-toggle-row">
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Allow general questions', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Let the model answer unrelated general questions too. Turn off to keep the bot strictly on-topic.', 'htech-ai-assistant' ); ?></span>
				</div>
<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[ai_allow_general]" value="0" />
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-ai_allow_general" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[ai_allow_general]" value="1" <?php checked( (bool) $s['ai_allow_general'], true ); ?> />
					<span></span>
				</label>
			</div>
		</div>
	</div>
</div>

<!-- ============================== RETRIEVAL ============================= -->
<div class="htech-tab-panel" data-tab-panel="knowledge" hidden>
	<div class="htech-card">
		<div class="htech-card__head">
			<div>
				<h2><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Local retrieval engine', 'htech-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'BM25 keyword scoring over your own database. No vector database, no embeddings API, no licence fees.', 'htech-ai-assistant' ); ?></p>
			</div>
		</div>

		<div class="htech-field-row">
			<?php
			HTECH_AI_Admin::field(
				'retrieval_top_k',
				__( 'Chunks retrieved per question', 'htech-ai-assistant' ),
				__( 'More chunks = more context but slower and noisier. 3–5 works well.', 'htech-ai-assistant' ),
				array( 'type' => 'number', 'min' => 1, 'max' => 12 )
			);
			HTECH_AI_Admin::field(
				'retrieval_min_score',
				__( 'Minimum relevance score', 'htech-ai-assistant' ),
				__( 'Below this score the bot refuses to answer instead of guessing. Raise it (0.25–0.35) for stricter answers, lower it if too many valid questions are refused.', 'htech-ai-assistant' ),
				array( 'type' => 'number', 'min' => 0, 'max' => 1, 'step' => 0.01 )
			);
			HTECH_AI_Admin::field(
				'chunk_size',
				__( 'Chunk size (characters)', 'htech-ai-assistant' ),
				__( 'How documents are split before indexing. 900 is a good default.', 'htech-ai-assistant' ),
				array( 'type' => 'number', 'min' => 300, 'max' => 3000, 'step' => 50 )
			);
			HTECH_AI_Admin::field(
				'chunk_overlap',
				__( 'Chunk overlap (characters)', 'htech-ai-assistant' ),
				__( 'Repeated text between neighbouring chunks so answers are not cut in half.', 'htech-ai-assistant' ),
				array( 'type' => 'number', 'min' => 0, 'max' => 600, 'step' => 20 )
			);
			?>
		</div>

		<div class="htech-alert htech-alert--info">
			<span class="dashicons dashicons-update"></span>
			<div><?php esc_html_e( 'Changing chunk size or overlap only affects documents you re-process. Use "Reprocess" on each document afterwards (or re-upload).', 'htech-ai-assistant' ); ?></div>
		</div>

		<div class="htech-card__head" style="margin-top:20px">
			<h2><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Test the retrieval engine', 'htech-ai-assistant' ); ?></h2>
		</div>

		<form id="htech-retrieval-form" class="htech-search-form">
			<input type="search" name="query" class="regular-text" placeholder="<?php esc_attr_e( 'Type a question a visitor might ask…', 'htech-ai-assistant' ); ?>" required />
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Test', 'htech-ai-assistant' ); ?></button>
		</form>

		<div id="htech-retrieval-output"></div>
		<p class="htech-ai-help"><?php esc_html_e( 'This runs the exact same retrieval and answer composition the chatbot uses in knowledge-base mode — no model involved.', 'htech-ai-assistant' ); ?></p>
	</div>
</div>

<!-- =============================== LEADS ================================ -->
<div class="htech-tab-panel" data-tab-panel="leads" hidden>
	<div class="htech-grid htech-grid--2">
		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Lead capture', 'htech-ai-assistant' ); ?></h2>
					<p><?php esc_html_e( 'Optional. Visitors are never forced to share personal details to chat.', 'htech-ai-assistant' ); ?></p>
				</div>
			</div>

			<div class="htech-toggle-row">
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Enable lead collection', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Adds a "Share my details" option inside the chat window.', 'htech-ai-assistant' ); ?></span>
				</div>
<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[lead_enabled]" value="0" />
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-lead_enabled" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[lead_enabled]" value="1" <?php checked( (bool) $s['lead_enabled'], true ); ?> />
					<span></span>
				</label>
			</div>

			<?php
			HTECH_AI_Admin::field(
				'lead_trigger',
				__( 'When to offer the form', 'htech-ai-assistant' ),
				__( '"Visitor asks to be contacted" covers quote requests and callbacks. "After N messages" is a soft prompt.', 'htech-ai-assistant' ),
				array(
					'type'    => 'select',
					'options' => array(
						'manual' => __( 'Only when the visitor asks to be contacted', 'htech-ai-assistant' ),
						'auto'   => __( 'After a set number of messages', 'htech-ai-assistant' ),
						'both'   => __( 'Either of the above (recommended)', 'htech-ai-assistant' ),
					),
				)
			);
			HTECH_AI_Admin::field( 'lead_trigger_after', __( 'Offer after this many messages', 'htech-ai-assistant' ), '', array( 'type' => 'number', 'min' => 1, 'max' => 30 ) );
			?>

			<div class="htech-field-row">
				<?php
				$field_options = array(
					'name'        => __( 'Name', 'htech-ai-assistant' ),
					'email'       => __( 'Email', 'htech-ai-assistant' ),
					'phone'       => __( 'Phone', 'htech-ai-assistant' ),
					'service'     => __( 'Service of interest', 'htech-ai-assistant' ),
					'description' => __( 'Project description', 'htech-ai-assistant' ),
				);
				$active_fields = array_filter( explode( ',', (string) $s['lead_fields'] ) );
				?>
				<div class="htech-ai-field">
					<label><?php esc_html_e( 'Fields to collect', 'htech-ai-assistant' ); ?></label>
					<?php foreach ( $field_options as $key => $label ) : ?>
						<label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:5px">
							<input type="checkbox" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[lead_fields_temp][]" value="<?php echo esc_attr( $key ); ?>" data-lead-field <?php checked( in_array( $key, $active_fields, true ), true ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
					<input type="hidden" id="htech-lead-fields" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[lead_fields]" value="<?php echo esc_attr( $s['lead_fields'] ); ?>" />
					<p class="htech-ai-help"><?php esc_html_e( 'Keep it short — every extra field reduces completion. Name + email is usually enough.', 'htech-ai-assistant' ); ?></p>
				</div>

				<div class="htech-ai-field">
					<label><?php esc_html_e( 'Required fields', 'htech-ai-assistant' ); ?></label>
					<?php $required = array_filter( explode( ',', (string) $s['lead_required'] ) ); ?>
					<?php foreach ( $field_options as $key => $label ) : ?>
						<label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:5px">
							<input type="checkbox" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[lead_required_temp][]" value="<?php echo esc_attr( $key ); ?>" data-lead-required <?php checked( in_array( $key, $required, true ), true ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
					<input type="hidden" id="htech-lead-required" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[lead_required]" value="<?php echo esc_attr( $s['lead_required'] ); ?>" />
				</div>
			</div>
		</div>

		<div class="htech-card">
			<div class="htech-card__head">
				<h2><span class="dashicons dashicons-email"></span> <?php esc_html_e( 'Notifications', 'htech-ai-assistant' ); ?></h2>
			</div>

			<div class="htech-toggle-row">
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Email new leads', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Uses wp_mail() — no external service. Delivery depends on your host\'s mail configuration.', 'htech-ai-assistant' ); ?></span>
				</div>
<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[lead_notify_admin]" value="0" />
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-lead_notify_admin" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[lead_notify_admin]" value="1" <?php checked( (bool) $s['lead_notify_admin'], true ); ?> />
					<span></span>
				</label>
			</div>

			<?php
			HTECH_AI_Admin::field( 'lead_notify_email', __( 'Send notifications to', 'htech-ai-assistant' ), '', array( 'type' => 'email' ) );
			HTECH_AI_Admin::field(
				'lead_success_message',
				__( 'Thank-you message', 'htech-ai-assistant' ),
				__( 'Shown after a successful submission.', 'htech-ai-assistant' ),
				array( 'type' => 'textarea', 'rows' => 2 )
			);
			HTECH_AI_Admin::field(
				'lead_retention_days',
				__( 'Delete leads after (days)', 'htech-ai-assistant' ),
				__( 'Automatic cleanup runs daily. Set 0 to never auto-delete (not recommended under GDPR/NDPA).', 'htech-ai-assistant' ),
				array( 'type' => 'number', 'min' => 1, 'max' => 3650 )
			);
			?>
		</div>
	</div>
</div>

<div class="htech-card" style="display:flex;align-items:center;justify-content:space-between;gap:14px">
	<div class="htech-muted"><?php esc_html_e( 'Changes take effect immediately for new page loads.', 'htech-ai-assistant' ); ?></div>
	<?php submit_button( __( 'Save chatbot settings', 'htech-ai-assistant' ), 'primary htech-ai-save', 'submit', false ); ?>
</div>

<?php HTECH_AI_Admin::settings_form_close( __( 'Save chatbot settings', 'htech-ai-assistant' ) ); ?>

<script>
/* Keep the lead checkbox groups in sync with the hidden CSV inputs. */
(function () {
	function sync(selector, targetId) {
		var boxes = document.querySelectorAll(selector);
		var target = document.getElementById(targetId);
		if (!target) { return; }

		var update = function () {
			var values = [];
			boxes.forEach(function (box) { if (box.checked) { values.push(box.value); } });
			target.value = values.join(',');
		};

		boxes.forEach(function (box) { box.addEventListener('change', update); });
		update();
	}

	sync('[data-lead-field]', 'htech-lead-fields');
	sync('[data-lead-required]', 'htech-lead-required');
})();
</script>

<?php HTECH_AI_Admin::shell_end(); ?>
