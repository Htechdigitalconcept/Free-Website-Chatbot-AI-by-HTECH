<?php
/**
 * Appearance & Branding screen.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

$s = HTECH_AI_Settings::all();

$logo_url = '';
if ( ! empty( $s['logo_id'] ) ) {
	$src = wp_get_attachment_image_src( (int) $s['logo_id'], 'medium' );
	if ( $src ) {
		$logo_url = $src[0];
	}
}

$fonts = array(
	'Inter'     => 'Inter',
	'Roboto'    => 'Roboto',
	'Poppins'   => 'Poppins',
	'Open Sans' => 'Open Sans',
	'Lato'      => 'Lato',
	'Nunito'    => 'Nunito',
	'system'    => __( 'System font (no external request)', 'htech-ai-assistant' ),
);

$initials = strtoupper( mb_substr( $s['business_name'] ? $s['business_name'] : $s['chatbot_name'], 0, 2 ) );

HTECH_AI_Admin::shell_start(
	'htech-ai-appearance',
	__( 'Appearance & Branding', 'htech-ai-assistant' ),
	__( 'Your branding completely replaces the defaults. HTECH branding is never shown unless you enable attribution.', 'htech-ai-assistant' ),
	'<a class="button htech-btn--ghost" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View site', 'htech-ai-assistant' ) . '</a>'
);
?>

<?php HTECH_AI_Admin::settings_form_open(); ?>

<div class="htech-grid" style="grid-template-columns:minmax(0,1fr) minmax(320px,420px);align-items:start">
	<div>
		<!-- ============================ LOGO ============================ -->
		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-format-image"></span> <?php esc_html_e( 'Logo', 'htech-ai-assistant' ); ?></h2>
					<p><?php esc_html_e( 'PNG, JPG or SVG from the media library. A square mark works best — it is shown in the header and launcher.', 'htech-ai-assistant' ); ?></p>
				</div>
			</div>

			<div class="htech-flex" style="gap:18px">
				<div id="htech-logo-preview" style="width:72px;height:72px;border-radius:14px;background:#f1f5f9;display:grid;place-items:center;overflow:hidden;border:1px solid var(--ht-line)">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" style="width:<?php echo esc_attr( (int) $s['logo_size'] ); ?>px;height:<?php echo esc_attr( (int) $s['logo_size'] ); ?>px;object-fit:cover;border-radius:10px" alt="" />
					<?php else : ?>
						<span class="htech-muted"><?php esc_html_e( 'No logo', 'htech-ai-assistant' ); ?></span>
					<?php endif; ?>
				</div>

				<div style="flex:1">
					<input type="hidden" id="htech-field-logo_id" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[logo_id]" value="<?php echo esc_attr( (int) $s['logo_id'] ); ?>" />
					<div class="htech-flex">
						<button type="button" class="button button-primary" id="htech-logo-select"><?php esc_html_e( 'Choose logo', 'htech-ai-assistant' ); ?></button>
						<button type="button" class="button htech-btn--danger" id="htech-logo-remove" <?php echo $logo_url ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove', 'htech-ai-assistant' ); ?></button>
					</div>
					<p class="htech-ai-help"><?php esc_html_e( 'SVG files uploaded through the media library are only allowed for administrators, which keeps SVG XSS out of reach of lower roles.', 'htech-ai-assistant' ); ?></p>
				</div>
			</div>

			<div style="margin-top:14px">
				<?php
				HTECH_AI_Admin::field(
					'logo_size',
					__( 'Logo display size (px)', 'htech-ai-assistant' ),
					__( 'Header logo size. 28–40 px looks right in most designs.', 'htech-ai-assistant' ),
					array( 'type' => 'number', 'min' => 16, 'max' => 96 )
				);
				?>
			</div>
		</div>

		<!-- =========================== COLOURS =========================== -->
		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Brand colours', 'htech-ai-assistant' ); ?></h2>
					<p><?php esc_html_e( 'Hex values. The preview updates as you type.', 'htech-ai-assistant' ); ?></p>
				</div>
			</div>

			<div class="htech-colors">
				<?php
				$colors = array(
					'color_primary'     => array( __( 'Primary', 'htech-ai-assistant' ), '#0A1F44' ),
					'color_secondary'   => array( __( 'Secondary / accent', 'htech-ai-assistant' ), '#00B4D8' ),
					'color_header_bg'   => array( __( 'Header background', 'htech-ai-assistant' ), '#0A1F44' ),
					'color_chat_bg'     => array( __( 'Chat window background', 'htech-ai-assistant' ), '#F4F7FB' ),
					'color_user_bubble' => array( __( 'User message', 'htech-ai-assistant' ), '#00B4D8' ),
					'color_bot_bubble'  => array( __( 'Assistant message', 'htech-ai-assistant' ), '#FFFFFF' ),
					'color_text'        => array( __( 'Text', 'htech-ai-assistant' ), '#0F172A' ),
					'color_button'      => array( __( 'Send button', 'htech-ai-assistant' ), '#00B4D8' ),
					'color_border'      => array( __( 'Borders', 'htech-ai-assistant' ), '#E2E8F0' ),
				);

				foreach ( $colors as $key => $config ) {
					HTECH_AI_Admin::field(
						$key,
						$config[0],
						'',
						array( 'type' => 'color', 'default' => $config[1] )
					);
				}
				?>
			</div>
		</div>

		<!-- ========================= TYPOGRAPHY ========================= -->
		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-editor-textcolor"></span> <?php esc_html_e( 'Typography', 'htech-ai-assistant' ); ?></h2>
					<p><?php esc_html_e( 'Fonts load from Google Fonts (free). Choose "System font" for a zero-external-request setup.', 'htech-ai-assistant' ); ?></p>
				</div>
			</div>

			<div class="htech-field-row">
				<?php
				HTECH_AI_Admin::field( 'font_family', __( 'Font family', 'htech-ai-assistant' ), '', array( 'type' => 'select', 'options' => $fonts ) );
				HTECH_AI_Admin::field( 'font_size', __( 'Base font size (px)', 'htech-ai-assistant' ), '', array( 'type' => 'number', 'min' => 11, 'max' => 20 ) );
				?>
			</div>

			<div class="htech-toggle-row">
				<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[dark_mode]" value="0" />
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Dark chat window', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'A dark variant of the widget, independent of the visitor\'s theme.', 'htech-ai-assistant' ); ?></span>
				</div>
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-dark_mode" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[dark_mode]" value="1" <?php checked( (bool) $s['dark_mode'], true ); ?> />
					<span></span>
				</label>
			</div>
		</div>

		<!-- ======================== WIDGET LAYOUT ======================= -->
		<div class="htech-card">
			<div class="htech-card__head">
				<div>
					<h2><span class="dashicons dashicons-layout"></span> <?php esc_html_e( 'Widget layout', 'htech-ai-assistant' ); ?></h2>
				</div>
			</div>

			<div class="htech-field-row">
				<?php
				HTECH_AI_Admin::field(
					'widget_position',
					__( 'Screen position', 'htech-ai-assistant' ),
					'',
					array(
						'type'    => 'select',
						'options' => array(
							'bottom-right' => __( 'Bottom right', 'htech-ai-assistant' ),
							'bottom-left'  => __( 'Bottom left', 'htech-ai-assistant' ),
						),
					)
				);
				HTECH_AI_Admin::field(
					'header_style',
					__( 'Header style', 'htech-ai-assistant' ),
					'',
					array(
						'type'    => 'select',
						'options' => array(
							'gradient' => __( 'Gradient (premium)', 'htech-ai-assistant' ),
							'solid'    => __( 'Solid colour', 'htech-ai-assistant' ),
							'minimal'  => __( 'Minimal (light header)', 'htech-ai-assistant' ),
						),
					)
				);
				HTECH_AI_Admin::field(
					'launcher_icon',
					__( 'Launcher icon', 'htech-ai-assistant' ),
					'',
					array(
						'type'    => 'select',
						'options' => array(
							'spark' => __( 'Spark (AI)', 'htech-ai-assistant' ),
							'chat'  => __( 'Speech bubble', 'htech-ai-assistant' ),
							'help'  => __( 'Question mark', 'htech-ai-assistant' ),
							'logo'  => __( 'Business logo', 'htech-ai-assistant' ),
						),
					)
				);
				HTECH_AI_Admin::field( 'widget_width', __( 'Width (px)', 'htech-ai-assistant' ), '', array( 'type' => 'number', 'min' => 280, 'max' => 560 ) );
				HTECH_AI_Admin::field( 'widget_height', __( 'Height (px)', 'htech-ai-assistant' ), '', array( 'type' => 'number', 'min' => 380, 'max' => 860 ) );
				HTECH_AI_Admin::field( 'border_radius', __( 'Border radius (px)', 'htech-ai-assistant' ), '', array( 'type' => 'number', 'min' => 0, 'max' => 40 ) );
				?>
			</div>

			<div class="htech-toggle-row">
				<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[show_launcher_label]" value="0" />
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Launcher label', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Show a small pill of text next to the floating button.', 'htech-ai-assistant' ); ?></span>
				</div>
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-show_launcher_label" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[show_launcher_label]" value="1" <?php checked( (bool) $s['show_launcher_label'], true ); ?> />
					<span></span>
				</label>
			</div>

			<?php
			HTECH_AI_Admin::field( 'launcher_label', __( 'Launcher label text', 'htech-ai-assistant' ), '' );
			HTECH_AI_Admin::field( 'chatbot_name', __( 'Display name in header', 'htech-ai-assistant' ), __( 'Kept in sync with Chatbot Settings.', 'htech-ai-assistant' ) );
			HTECH_AI_Admin::field( 'welcome_message', __( 'Welcome message', 'htech-ai-assistant' ), '', array( 'type' => 'textarea', 'rows' => 2 ) );
			HTECH_AI_Admin::field(
				'suggested_questions',
				__( 'Suggested questions', 'htech-ai-assistant' ),
				__( 'One per line — shown as chips.', 'htech-ai-assistant' ),
				array( 'type' => 'textarea', 'rows' => 4 )
			);
			?>

			<div class="htech-toggle-row">
				<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[online_status]" value="0" />
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Online indicator', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Green dot in the header and launcher.', 'htech-ai-assistant' ); ?></span>
				</div>
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-online_status" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[online_status]" value="1" <?php checked( (bool) $s['online_status'], true ); ?> />
					<span></span>
				</label>
			</div>

			<div class="htech-toggle-row">
				<input type="hidden" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[attribution]" value="0" />
				<div class="htech-toggle-row__text">
					<strong><?php esc_html_e( 'Show "Powered by HTECH" attribution', 'htech-ai-assistant' ); ?></strong>
					<span><?php esc_html_e( 'Off by default. Enable it only if you want the credit line visible to visitors.', 'htech-ai-assistant' ); ?></span>
				</div>
				<label class="htech-ai-toggle">
					<input type="checkbox" id="htech-field-attribution" name="<?php echo esc_attr( HTECH_AI_Settings::OPTION ); ?>[attribution]" value="1" <?php checked( (bool) $s['attribution'], true ); ?> />
					<span></span>
				</label>
			</div>
		</div>
	</div>

	<!-- ============================ PREVIEW ============================ -->
	<aside style="position:sticky;top:42px">
		<div class="htech-card" style="padding:16px">
			<div class="htech-card__head" style="margin-bottom:12px">
				<div>
					<h2><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Live preview', 'htech-ai-assistant' ); ?></h2>
					<p style="margin-top:3px"><?php esc_html_e( 'Reflects unsaved changes.', 'htech-ai-assistant' ); ?></p>
				</div>
			</div>

			<div class="htech-flex" style="margin-bottom:12px">
				<button type="button" class="button button-primary htech-btn--sm" data-preview-device="desktop"><?php esc_html_e( 'Desktop', 'htech-ai-assistant' ); ?></button>
				<button type="button" class="button htech-btn--sm htech-btn--ghost" data-preview-device="mobile"><?php esc_html_e( 'Mobile', 'htech-ai-assistant' ); ?></button>
				<div class="htech-spacer"></div>
				<label class="htech-ai-toggle" title="<?php esc_attr_e( 'Toggle dark preview', 'htech-ai-assistant' ); ?>">
					<input type="checkbox" id="htech-field-dark_mode_preview" <?php checked( (bool) $s['dark_mode'], true ); ?> />
					<span></span>
				</label>
			</div>

			<div class="htech-preview__stage" id="htech-preview-stage" data-device="desktop">
				<div id="htech-preview-frame">
					<div class="htech-preview__window" id="htech-preview-root">
						<div class="htech-preview__head" id="htech-preview-head">
							<div class="htech-preview__logo">
								<?php if ( $logo_url ) : ?>
									<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
								<?php else : ?>
									<?php echo esc_html( $initials ); ?>
								<?php endif; ?>
							</div>
							<div>
								<div class="htech-preview__name"><?php echo esc_html( $s['chatbot_name'] ); ?></div>
								<div class="htech-preview__status">
									<span style="width:5px;height:5px;border-radius:50%;background:#4ADE80;display:inline-block"></span>
									<?php echo esc_html( $s['online_text'] ); ?>
								</div>
							</div>
						</div>

						<div class="htech-preview__body">
							<div class="htech-preview__bubble htech-preview__bubble--bot" id="htech-preview-welcome"><?php echo esc_html( $s['welcome_message'] ); ?></div>
							<div class="htech-preview__bubble htech-preview__bubble--user"><?php esc_html_e( 'What do you charge for a website?', 'htech-ai-assistant' ); ?></div>
							<div class="htech-preview__bubble htech-preview__bubble--bot"><?php esc_html_e( 'Our Starter package is ₦150,000 and includes design, build and launch support.', 'htech-ai-assistant' ); ?></div>
						</div>

						<div class="htech-preview__chips">
							<?php
							$preview_questions = array_slice( array_filter( array_map( 'trim', explode( "\n", (string) $s['suggested_questions'] ) ) ), 0, 3 );
							foreach ( $preview_questions as $question ) :
								?>
								<span class="htech-preview__chip"><?php echo esc_html( $question ); ?></span>
							<?php endforeach; ?>
						</div>

						<div class="htech-preview__input">
							<div><?php esc_html_e( 'Type your message…', 'htech-ai-assistant' ); ?></div>
							<div class="htech-preview__send">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div style="margin-top:14px;display:flex;justify-content:flex-end">
				<div class="htech-preview__launcher">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/></svg>
				</div>
			</div>
		</div>

		<div class="htech-alert htech-alert--info">
			<span class="dashicons dashicons-info"></span>
			<div><?php esc_html_e( 'The widget renders inside a Shadow DOM, so your theme\'s CSS cannot break it and the widget cannot restyle your site.', 'htech-ai-assistant' ); ?></div>
		</div>

		<div class="htech-card" style="display:flex;flex-direction:column;gap:10px">
			<?php submit_button( __( 'Save appearance', 'htech-ai-assistant' ), 'primary htech-ai-save', 'submit', false ); ?>
			<p class="htech-ai-help" style="margin:0"><?php esc_html_e( 'Saved for this installation only. Re-open your site (not a cached page) to see the change.', 'htech-ai-assistant' ); ?></p>
		</div>
	</aside>
</div>

<?php HTECH_AI_Admin::settings_form_close( __( 'Save appearance', 'htech-ai-assistant' ) ); ?>

<?php HTECH_AI_Admin::shell_end(); ?>
