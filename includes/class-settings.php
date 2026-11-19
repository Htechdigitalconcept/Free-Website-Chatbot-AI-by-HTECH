<?php
/**
 * Plugin settings: schema, defaults, sanitisation and accessors.
 *
 * Every installation stores its own settings in its own wp_options row, so
 * no two client sites can ever share configuration.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Settings
 */
class HTECH_AI_Settings {

	const OPTION = 'htech_ai_settings';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Register the option with the Settings API.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Register setting so WordPress handles nonce + option storage rules.
	 *
	 * @return void
	 */
	public function register() {
		register_setting(
			'htech_ai_settings',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Default values for a brand new installation.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			/* --- identity / purpose ---------------------------------------- */
			'chatbot_name'        => 'HTECH Assistant',
			'business_name'       => get_bloginfo( 'name' ),
			'business_description' => '',
			'business_category'   => 'Digital agency',
			'website_url'         => home_url(),
			'welcome_message'     => 'Hi! I am here to help you with anything about our business. What would you like to know?',
			'primary_language'    => 'en',
			'personality'         => 'friendly',
			'purpose'             => 'general',
			'purpose_custom'      => '',
			'attribution'         => 0,

			/* --- widget behaviour ------------------------------------------ */
			'widget_enabled'      => 1,
			'display_pages'       => 'all',
			'display_ids'         => '',
			'widget_position'     => 'bottom-right',
			'launcher_icon'       => 'spark',
			'launcher_label'      => 'Chat with us',
			'show_launcher_label' => 0,
			'widget_width'        => 380,
			'widget_height'       => 560,
			'border_radius'       => 16,
			'header_style'        => 'gradient',
			'online_status'       => 1,
			'online_text'         => 'Online — ask me anything',
			'offline_text'        => 'Leave a message',
			'clear_on_close'      => 0,

			/* --- appearance ------------------------------------------------ */
			'logo_id'             => 0,
			'logo_size'           => 34,
			'color_primary'       => '#0A1F44',
			'color_secondary'     => '#00B4D8',
			'color_header_bg'     => '#0A1F44',
			'color_chat_bg'       => '#F4F7FB',
			'color_user_bubble'   => '#00B4D8',
			'color_bot_bubble'    => '#FFFFFF',
			'color_text'          => '#0F172A',
			'color_button'        => '#00B4D8',
			'color_border'        => '#E2E8F0',
			'font_family'         => 'Inter',
			'font_size'           => 14,
			'dark_mode'           => 0,

			/* --- conversation ---------------------------------------------- */
			'conversation_context'    => 1,
			'context_messages'        => 8,
			'store_conversations'     => 1,
			'retention_days'          => 90,
			'max_message_length'      => 1000,
			'rate_limit_per_minute'   => 20,
			'greeting_name'           => 0,

			/* --- knowledge ------------------------------------------------- */
			'retrieval_top_k'         => 4,
			'retrieval_min_score'     => 0.18,
			'chunk_size'              => 900,
			'chunk_overlap'           => 120,
			'index_uploads'           => 0,
			'allowed_extensions'      => 'pdf,docx,txt,csv,md,json',
			'max_file_size_mb'        => 8,
			'max_files_per_upload'    => 5,
			'importer_max_pages'      => 12,
			'importer_store_html'     => 0,

			/* --- AI engine ------------------------------------------------- */
			'ai_enabled'              => 1,
			'ai_provider'             => 'webllm',
			'ai_model'                => 'Qwen2.5-1.5B-Instruct-q4f16_1-MLC',
			'ai_model_custom'         => '',
			'ai_auto_load'            => 'manual',
			'ai_temperature'          => 0.6,
			'ai_max_tokens'           => 512,
			'ai_context_chars'        => 6000,
			'ai_lib_url'              => 'https://cdn.jsdelivr.net/npm/@mlc-ai/web-llm@0.2.89/+esm',
			'ai_fallback_mode'        => 'fallback',
			'ai_allow_general'        => 1,
			'ai_strict_knowledge'     => 1,
			'ai_gateway_url'          => '',

			/* --- leads ----------------------------------------------------- */
			'lead_enabled'            => 0,
			'lead_trigger'            => 'manual',
			'lead_trigger_after'      => 4,
			'lead_fields'             => 'name,email,phone,service,description',
			'lead_required'           => 'name,email',
			'lead_notify_email'       => get_option( 'admin_email' ),
			'lead_notify_admin'       => 1,
			'lead_retention_days'     => 365,
			'lead_success_message'    => 'Thank you! Our team will be in touch shortly.',

			/* --- suggested prompts ----------------------------------------- */
			'suggested_questions'     => "What services do you offer?\nHow much do your services cost?\nWhere are you located?\nHow can I contact you?",

			/* --- appearance / system --------------------------------------- */
			'license'                 => 'private',
			'delete_on_uninstall'     => 0,
			'onboarding_dismissed'    => 0,
		);
	}

	/**
	 * Create the option row on activation without clobbering existing values.
	 *
	 * @return void
	 */
	public static function install_defaults() {
		$existing = get_option( self::OPTION );
		if ( ! is_array( $existing ) ) {
			add_option( self::OPTION, self::defaults(), '', 'no' );
			return;
		}
		update_option( self::OPTION, array_merge( self::defaults(), $existing ), 'no' );
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			$stored      = is_array( $stored ) ? $stored : array();
			self::$cache = array_merge( self::defaults(), $stored );
		}
		return self::$cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Get a boolean setting.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function is_enabled( $key ) {
		return (bool) self::get( $key, 0 );
	}

	/**
	 * Persist a partial update (used by onboarding, importer, tools).
	 *
	 * @param array $patch Key/value pairs.
	 * @return void
	 */
	public static function update( array $patch ) {
		$clean = self::sanitize( array_merge( self::all(), $patch ) );
		update_option( self::OPTION, $clean, 'no' );
		self::$cache = $clean;
	}

	/**
	 * Reset to factory defaults.
	 *
	 * @return void
	 */
	public static function reset() {
		update_option( self::OPTION, self::defaults(), 'no' );
		self::$cache = null;
	}

	/**
	 * Sanitise the whole settings array.
	 *
	 * Unknown keys are dropped, so the option can never be used to smuggle
	 * executable content into the plugin.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();

		$text_fields = array(
			'chatbot_name', 'business_name', 'business_category', 'primary_language',
			'personality', 'purpose', 'purpose_custom', 'widget_position', 'launcher_icon',
			'launcher_label', 'online_text', 'offline_text', 'ai_provider', 'ai_model',
			'ai_model_custom', 'ai_auto_load', 'ai_fallback_mode', 'lead_trigger',
			'lead_fields', 'lead_required', 'font_family', 'header_style', 'display_pages',
			'allowed_extensions',
		);

		$textarea_fields = array(
			'business_description', 'welcome_message', 'suggested_questions', 'lead_success_message',
		);

		$int_ranges = array(
			'logo_id'               => array( 0, PHP_INT_MAX ),
			'logo_size'             => array( 16, 96 ),
			'widget_width'          => array( 280, 560 ),
			'widget_height'         => array( 380, 860 ),
			'border_radius'         => array( 0, 40 ),
			'font_size'             => array( 11, 20 ),
			'context_messages'      => array( 0, 30 ),
			'retention_days'        => array( 0, 3650 ),
			'max_message_length'    => array( 100, 8000 ),
			'rate_limit_per_minute' => array( 1, 300 ),
			'retrieval_top_k'       => array( 1, 12 ),
			'chunk_size'            => array( 300, 3000 ),
			'chunk_overlap'         => array( 0, 600 ),
			'max_file_size_mb'      => array( 1, 64 ),
			'max_files_per_upload'  => array( 1, 20 ),
			'importer_max_pages'    => array( 1, 50 ),
			'ai_max_tokens'         => array( 64, 2048 ),
			'ai_context_chars'      => array( 1000, 20000 ),
			'lead_trigger_after'    => array( 1, 30 ),
			'lead_retention_days'   => array( 1, 3650 ),
			'display_ids'           => null, // handled separately (string CSV)
		);

		$bool_fields = array(
			'widget_enabled', 'show_launcher_label', 'online_status', 'dark_mode',
			'conversation_context', 'store_conversations', 'index_uploads',
			'importer_store_html', 'ai_enabled', 'ai_allow_general', 'ai_strict_knowledge',
			'lead_enabled', 'lead_notify_admin', 'attribution', 'delete_on_uninstall',
			'onboarding_dismissed', 'clear_on_close', 'greeting_name',
		);

		$color_fields = array(
			'color_primary', 'color_secondary', 'color_header_bg', 'color_chat_bg',
			'color_user_bubble', 'color_bot_bubble', 'color_text', 'color_button', 'color_border',
		);

		foreach ( $text_fields as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$out[ $key ] = sanitize_text_field( (string) $input[ $key ] );
		}

		foreach ( $textarea_fields as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$out[ $key ] = sanitize_textarea_field( (string) $input[ $key ] );
		}

		foreach ( $int_ranges as $key => $range ) {
			if ( null === $range || ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$value         = (int) $input[ $key ];
			$out[ $key ]   = max( $range[0], min( $range[1], $value ) );
		}

		foreach ( $bool_fields as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		foreach ( $color_fields as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$out[ $key ] = self::sanitize_hex( $input[ $key ], $defaults[ $key ] );
		}

		/* URLs */
		foreach ( array( 'website_url', 'ai_lib_url', 'ai_gateway_url' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = esc_url_raw( (string) $input[ $key ] );
			}
		}

		if ( array_key_exists( 'lead_notify_email', $input ) ) {
			$email                     = sanitize_email( (string) $input['lead_notify_email'] );
			$out['lead_notify_email'] = is_email( $email ) ? $email : $defaults['lead_notify_email'];
		}

		if ( array_key_exists( 'display_ids', $input ) ) {
			$ids               = array_filter( array_map( 'absint', explode( ',', (string) $input['display_ids'] ) ) );
			$out['display_ids'] = implode( ',', array_unique( $ids ) );
		}

		/* Floating point */
		if ( array_key_exists( 'ai_temperature', $input ) ) {
			$out['ai_temperature'] = max( 0.0, min( 1.5, (float) $input['ai_temperature'] ) );
		}
		if ( array_key_exists( 'retrieval_min_score', $input ) ) {
			$out['retrieval_min_score'] = max( 0.0, min( 1.0, (float) $input['retrieval_min_score'] ) );
		}

		/* Enum guards */
		$enums = array(
			'purpose'         => array( 'general', 'support', 'sales', 'business-info', 'lead-gen', 'custom' ),
			'personality'     => array( 'friendly', 'professional', 'concise', 'enthusiastic', 'formal', 'witty' ),
			'widget_position' => array( 'bottom-right', 'bottom-left' ),
			'header_style'    => array( 'gradient', 'solid', 'minimal' ),
			'launcher_icon'   => array( 'spark', 'chat', 'help', 'logo' ),
			'ai_provider'     => array( 'webllm', 'gateway', 'off' ),
			'ai_auto_load'    => array( 'manual', 'open', 'eager' ),
			'ai_fallback_mode' => array( 'fallback', 'off' ),
			'lead_trigger'    => array( 'manual', 'auto', 'both' ),
			'display_pages'   => array( 'all', 'include', 'exclude' ),
			'font_family'     => array( 'Inter', 'Roboto', 'Poppins', 'Open Sans', 'Lato', 'Nunito', 'system' ),
			'primary_language' => array( 'en', 'fr', 'es', 'pt', 'ar', 'de', 'it', 'nl', 'sw', 'yo', 'ha', 'ig', 'zh', 'hi' ),
		);
		foreach ( $enums as $key => $allowed ) {
			if ( isset( $out[ $key ] ) && ! in_array( $out[ $key ], $allowed, true ) ) {
				$out[ $key ] = $defaults[ $key ];
			}
		}

		/* Model ids are free strings but must look like a model id. */
		if ( isset( $out['ai_model'] ) && ! preg_match( '/^[A-Za-z0-9._\-]{2,80}$/', $out['ai_model'] ) ) {
			$out['ai_model'] = $defaults['ai_model'];
		}

		/* Language / extension lists */
		if ( isset( $out['allowed_extensions'] ) ) {
			$ext = array_filter( array_map( 'sanitize_key', explode( ',', strtolower( $out['allowed_extensions'] ) ) ) );
			$ext = array_values( array_intersect( $ext, array( 'pdf', 'docx', 'txt', 'csv', 'md', 'json' ) ) );
			$out['allowed_extensions'] = $ext ? implode( ',', array_unique( $ext ) ) : 'pdf,docx,txt,csv';
		}

		if ( isset( $out['lead_fields'] ) ) {
			$fields = array_filter( array_map( 'sanitize_key', explode( ',', $out['lead_fields'] ) ) );
			$fields = array_values( array_intersect( $fields, array( 'name', 'email', 'phone', 'service', 'description' ) ) );
			$out['lead_fields'] = $fields ? implode( ',', array_unique( $fields ) ) : 'name,email';
		}
		if ( isset( $out['lead_required'] ) ) {
			$req                = array_filter( array_map( 'sanitize_key', explode( ',', $out['lead_required'] ) ) );
			$out['lead_required'] = implode( ',', array_unique( $req ) );
		}

		/* Chunk overlap must stay below chunk size. */
		if ( isset( $out['chunk_size'], $out['chunk_overlap'] ) && $out['chunk_overlap'] >= $out['chunk_size'] ) {
			$out['chunk_overlap'] = (int) floor( $out['chunk_size'] / 4 );
		}

		return array_merge( $defaults, $out );
	}

	/**
	 * Accept #rgb / #rrggbb and normalise to #rrggbb.
	 *
	 * @param string $value    Raw colour.
	 * @param string $fallback Fallback colour.
	 * @return string
	 */
	public static function sanitize_hex( $value, $fallback = '#000000' ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#?([A-Fa-f0-9]{3})$/', $value, $m ) ) {
			$value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}
		return preg_match( '/^#[A-Fa-f0-9]{6}$/', $value ) ? strtoupper( $value ) : $fallback;
	}

	/**
	 * Visitor-safe configuration for the widget.
	 *
	 * Anything that could leak administration data (gateway URLs with keys,
	 * retention settings, notification addresses, model library overrides used
	 * server-side) is deliberately excluded.
	 *
	 * @return array
	 */
	public static function public_config() {
		$all = self::all();

		$logo_url = '';
		if ( ! empty( $all['logo_id'] ) ) {
			$src = wp_get_attachment_image_src( (int) $all['logo_id'], 'medium' );
			if ( $src ) {
				$logo_url = $src[0];
			}
		}

		$contact = '';
		$data    = htech_ai()->knowledge->all();
		if ( ! empty( $data['sections']['contact']['content'] ) ) {
			$contact = mb_substr( trim( preg_replace( '/\s+/', ' ', $data['sections']['contact']['content'] ) ), 0, 400 );
		}

		$config = array(
			'version'      => HTECH_AI_VERSION,
			'restUrl'      => esc_url_raw( rest_url( 'htech-ai/v1' ) ),
			'nonce'        => HTECH_AI_Security::public_nonce(),
			'ajaxUrl'      => esc_url_raw( admin_url( 'admin-ajax.php' ) ),

			'enabled'      => (bool) $all['widget_enabled'],
			'name'         => (string) $all['chatbot_name'],
			'business'     => (string) $all['business_name'],
			'welcome'      => (string) $all['welcome_message'],
			'language'     => (string) $all['primary_language'],
			'purpose'      => (string) $all['purpose'],
			'position'     => (string) $all['widget_position'],
			'width'        => (int) $all['widget_width'],
			'height'       => (int) $all['widget_height'],
			'radius'       => (int) $all['border_radius'],
			'headerStyle'  => (string) $all['header_style'],
			'launcher'     => (string) $all['launcher_icon'],
			'launcherLabel' => (string) $all['launcher_label'],
			'showLauncherLabel' => (bool) $all['show_launcher_label'],
			'online'       => (bool) $all['online_status'],
			'onlineText'   => (string) $all['online_text'],
			'offlineText'  => (string) $all['offline_text'],
			'clearOnClose' => (bool) $all['clear_on_close'],
			'logo'         => esc_url_raw( $logo_url ),
			'logoSize'     => (int) $all['logo_size'],
			'font'         => (string) $all['font_family'],
			'fontSize'     => (int) $all['font_size'],
			'darkMode'     => (bool) $all['dark_mode'],
			'attribution'  => (bool) $all['attribution'],
			'contact'      => $contact,
			'website'      => esc_url_raw( (string) $all['website_url'] ),

			'colors'       => array(
				'primary'   => (string) $all['color_primary'],
				'secondary' => (string) $all['color_secondary'],
				'header'    => (string) $all['color_header_bg'],
				'chat'      => (string) $all['color_chat_bg'],
				'user'      => (string) $all['color_user_bubble'],
				'bot'       => (string) $all['color_bot_bubble'],
				'text'      => (string) $all['color_text'],
				'button'    => (string) $all['color_button'],
				'border'    => (string) $all['color_border'],
			),

			'suggestions'  => htech_ai()->knowledge->suggestions( 4 ),
			'maxLength'    => (int) $all['max_message_length'],
			'storeChats'   => (bool) $all['store_conversations'],

			'ai'           => array(
				'enabled'     => (bool) $all['ai_enabled'] && 'off' !== $all['ai_provider'],
				'mode'        => (string) $all['ai_provider'],
				'model'       => (string) ( 'custom' === $all['ai_model'] ? $all['ai_model_custom'] : $all['ai_model'] ),
				'autoLoad'    => (string) $all['ai_auto_load'],
				'libUrl'      => esc_url_raw( (string) $all['ai_lib_url'] ),
				'allowGeneral' => (bool) $all['ai_allow_general'],
				'temperature' => (float) $all['ai_temperature'],
				'maxTokens'   => (int) $all['ai_max_tokens'],
			),

			'lead'         => array(
				'enabled'  => (bool) $all['lead_enabled'],
				'trigger'  => (string) $all['lead_trigger'],
				'after'    => (int) $all['lead_trigger_after'],
				'fields'   => array_values( array_filter( explode( ',', (string) $all['lead_fields'] ) ) ),
				'required' => array_values( array_filter( explode( ',', (string) $all['lead_required'] ) ) ),
				'success'  => (string) $all['lead_success_message'],
			),

			'i18n'         => array(
				'placeholder'   => __( 'Type your message…', 'htech-ai-assistant' ),
				'send'          => __( 'Send', 'htech-ai-assistant' ),
				'close'         => __( 'Close', 'htech-ai-assistant' ),
				'clear'         => __( 'New chat', 'htech-ai-assistant' ),
				'retry'         => __( 'Retry', 'htech-ai-assistant' ),
				'thinking'      => __( 'Thinking…', 'htech-ai-assistant' ),
				'loadingModel'  => __( 'Loading AI model…', 'htech-ai-assistant' ),
				'fallbackBadge' => __( 'Knowledge base mode', 'htech-ai-assistant' ),
				'aiBadge'       => __( 'AI answer', 'htech-ai-assistant' ),
				'askAi'         => __( 'Enable AI answers (downloads a model)', 'htech-ai-assistant' ),
				'noWebGpu'      => __( 'This browser or device cannot run the on-device AI model, so answers come from the business knowledge base.', 'htech-ai-assistant' ),
				'errorGeneric'  => __( 'Something went wrong. Please try again.', 'htech-ai-assistant' ),
				'errorNetwork'  => __( 'Network error — check your connection and retry.', 'htech-ai-assistant' ),
				'leadCta'       => __( 'Share my details', 'htech-ai-assistant' ),
				'leadTitle'     => __( 'Let us contact you', 'htech-ai-assistant' ),
				'leadName'      => __( 'Your name', 'htech-ai-assistant' ),
				'leadEmail'     => __( 'Email address', 'htech-ai-assistant' ),
				'leadPhone'     => __( 'Phone number', 'htech-ai-assistant' ),
				'leadService'   => __( 'Service of interest', 'htech-ai-assistant' ),
				'leadMessage'   => __( 'How can we help?', 'htech-ai-assistant' ),
				'leadSubmit'    => __( 'Send details', 'htech-ai-assistant' ),
				'leadCancel'    => __( 'Cancel', 'htech-ai-assistant' ),
				'sources'       => __( 'Source', 'htech-ai-assistant' ),
				'poweredBy'     => __( 'Powered by Free Website Chatbot AI by HTECH', 'htech-ai-assistant' ),
				'modelReady'    => __( 'AI ready', 'htech-ai-assistant' ),
			),
		);

		/**
		 * Filter the public widget configuration.
		 *
		 * @param array $config Public config.
		 */
		return apply_filters( 'htech_ai_public_config', $config );
	}

	/**
	 * Suggested starter questions per purpose (used by the setup wizard).
	 *
	 * @param string $purpose Purpose key.
	 * @return string[]
	 */
	public static function purpose_prompts( $purpose ) {
		$map = array(
			'general'       => array( 'What does this business do?', 'What services are available?', 'How can I get in touch?' ),
			'support'       => array( 'How do I track my order?', 'What is your returns policy?', 'I need help with an issue' ),
			'sales'         => array( 'What do your packages include?', 'How much does it cost?', 'Can I get a quote?' ),
			'business-info' => array( 'What are your opening hours?', 'Where are you located?', 'What services do you offer?' ),
			'lead-gen'      => array( 'I would like a quote', 'Can someone call me back?', 'What is your process?' ),
			'custom'        => array( 'How can you help me?', 'Tell me more', 'Who should I contact?' ),
		);
		return isset( $map[ $purpose ] ) ? $map[ $purpose ] : $map['general'];
	}

	/**
	 * Personality description injected into AI system prompts.
	 *
	 * @param string $personality Personality key.
	 * @return string
	 */
	public static function personality_prompt( $personality ) {
		$map = array(
			'friendly'      => 'Be warm, friendly and approachable. Use short, natural sentences.',
			'professional'  => 'Be professional, precise and courteous. Keep answers businesslike.',
			'concise'       => 'Be extremely concise. Prefer 1-3 sentences unless detail is requested.',
			'enthusiastic'  => 'Be upbeat and enthusiastic while staying accurate and helpful.',
			'formal'        => 'Be formal and polite, using complete sentences and no slang.',
			'witty'         => 'Be lightly witty and human, but never at the expense of accuracy.',
		);
		return isset( $map[ $personality ] ) ? $map[ $personality ] : $map['friendly'];
	}
}
