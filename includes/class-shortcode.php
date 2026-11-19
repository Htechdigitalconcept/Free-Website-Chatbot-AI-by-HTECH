<?php
/**
 * Front-end asset loading and the [htech_ai_assistant] shortcode.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Shortcode
 */
class HTECH_AI_Shortcode {

	/**
	 * Whether the script handles were already enqueued this request.
	 *
	 * @var bool
	 */
	private static $enqueued = false;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_shortcode( 'htech_ai_assistant', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 20 );
		add_action( 'wp_footer', array( $this, 'render_mount_points' ), 99 );
	}

	/**
	 * Should the floating widget load on this page?
	 *
	 * @return bool
	 */
	public function should_load_widget() {
		if ( ! HTECH_AI_Settings::is_enabled( 'widget_enabled' ) ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() ) {
			return false;
		}

		$mode = (string) HTECH_AI_Settings::get( 'display_pages', 'all' );
		if ( 'all' === $mode ) {
			$load = true;
		} else {
			$ids = array_filter( array_map( 'absint', explode( ',', (string) HTECH_AI_Settings::get( 'display_ids', '' ) ) ) );
			$on  = is_singular() && in_array( (int) get_queried_object_id(), $ids, true );
			$load = ( 'include' === $mode ) ? $on : ! $on;
		}

		/**
		 * Filter whether the widget renders on the current request.
		 *
		 * @param bool $load Whether to load.
		 */
		return (bool) apply_filters( 'htech_ai_should_load_widget', $load );
	}

	/**
	 * Enqueue widget assets when needed. The AI library and document parsers
	 * are never enqueued here: the widget loads them on demand.
	 *
	 * @return void
	 */
	public function maybe_enqueue() {
		if ( ! $this->should_load_widget() ) {
			return;
		}

		$this->enqueue_widget( 'floating' );
	}

	/**
	 * Print the two host elements the widget mounts into.
	 *
	 * The widget itself is created in the Shadow DOM of these nodes, so page CSS
	 * cannot affect it and its CSS cannot leak into the page. Printed in the
	 * footer so it never blocks rendering or document parsing.
	 *
	 * @return void
	 */
	public function render_mount_points() {
		static $printed = false;

		if ( $printed || ! $this->should_load_widget() ) {
			return;
		}

		$printed   = true;
		$position  = HTECH_AI_Settings::get( 'widget_position', 'bottom-right' );

		printf(
			'<div id="htech-ai-panel" data-position="%s" aria-live="polite"></div>' . "\n" .
			'<div id="htech-ai-launcher" data-position="%s"></div>' . "\n",
			esc_attr( $position ),
			esc_attr( $position )
		);
	}

	/**
	 * Enqueue the widget bundle.
	 *
	 * @param string $mode floating|inline.
	 * @return void
	 */
	public function enqueue_widget( $mode = 'floating' ) {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;

		wp_enqueue_style(
			'htech-ai-widget',
			HTECH_AI_URL . 'public/chatbot-widget.css',
			array(),
			HTECH_AI_VERSION
		);

		wp_enqueue_script(
			'htech-ai-widget',
			HTECH_AI_URL . 'public/chatbot-widget.js',
			array(),
			HTECH_AI_VERSION,
			true
		);

		/**
		 * Font hosting.
		 *
		 * Google Fonts is free but is an external request. Hosts that need
		 * full privacy can filter this to false and enqueue a locally hosted
		 * font; the widget falls back to the system font stack regardless.
		 *
		 * @param bool $use_google_fonts Whether to request the Google Fonts stylesheet.
		 */
		$use_remote_fonts = (bool) apply_filters( 'htech_ai_use_google_fonts', true );

		$font = (string) HTECH_AI_Settings::get( 'font_family', 'Inter' );
		if ( $use_remote_fonts && $font && 'system' !== $font ) {
			wp_enqueue_style(
				'htech-ai-font',
				add_query_arg(
					array(
						'family'  => str_replace( ' ', '+', $font ) . ':wght@400;500;600;700',
						'display' => 'swap',
					),
					'https://fonts.googleapis.com/css2'
				),
				array(),
				null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			);
		}

		// Inline config: avoids a blocking round-trip before the widget paints.
		$config = HTECH_AI_Settings::public_config();
		$config['mode']        = $mode;
		$config['launcherMount'] = 'htech-ai-launcher';
		$config['panelMount']    = 'htech-ai-panel';

		wp_add_inline_script(
			'htech-ai-widget',
			'window.HTECH_AI_CONFIG = ' . wp_json_encode( $config ) . ';',
			'before'
		);

		wp_localize_script( 'htech-ai-widget', 'HTECH_AI_BOOT', array( 'config' => $config ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * Usage: [htech_ai_assistant] or [htech_ai_assistant mode="inline" height="600"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'mode'   => 'floating',
				'height' => '',
				'width'  => '',
				'title'  => '',
			),
			$atts,
			'htech_ai_assistant'
		);

		// Inline embeds work even when the floating widget is disabled.
		$mode = 'inline' === $atts['mode'] ? 'inline' : 'floating';

		if ( 'floating' === $mode && ! HTECH_AI_Settings::is_enabled( 'widget_enabled' ) ) {
			if ( current_user_can( HTECH_AI_Security::capability() ) ) {
				return '<p class="htech-ai-admin-note"><em>' . esc_html__( 'Free Website Chatbot AI by HTECH: the widget is currently disabled in Chatbot Settings.', 'htech-ai-assistant' ) . '</em></p>';
			}
			return '';
		}

		$this->enqueue_widget( $mode );

		$style = '';
		if ( $atts['height'] ) {
			$style .= 'height:' . absint( $atts['height'] ) . 'px;';
		}
		if ( $atts['width'] ) {
			$style .= 'width:' . absint( $atts['width'] ) . 'px;max-width:100%;';
		}

		ob_start();
		?>
		<div class="htech-ai-shortcode-wrap htech-ai-shortcode-<?php echo esc_attr( $mode ); ?>">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="htech-ai-shortcode-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>
			<div
				id="htech-ai-panel"
				class="htech-ai-embed-mount"
				data-htech-mode="<?php echo esc_attr( $mode ); ?>"
				data-position="<?php echo esc_attr( (string) HTECH_AI_Settings::get( 'widget_position', 'bottom-right' ) ); ?>"
				<?php echo $style ? 'style="' . esc_attr( $style ) . '"' : ''; ?>
			></div>
			<?php if ( 'floating' === $mode ) : ?>
				<div id="htech-ai-launcher" class="htech-ai-embed-launcher" data-htech-mode="floating"></div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
