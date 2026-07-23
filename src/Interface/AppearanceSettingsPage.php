<?php
/**
 * Admin-Seite fuer AFSpaces Look-and-Feel-Einstellungen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

if ( ! class_exists( 'AFSpaces\\Interface\\AppearanceSettingsPage' ) ) {

	/**
	 * Bietet ein einfaches Theme-Tuning fuer AFSpaces im WordPress-Backend.
	 */
	class AppearanceSettingsPage {

		private const OPTION_KEY = 'afspaces_appearance_options';
		private const PRESET_ASGAROS = 'asgaros';
		private const PRESET_NEUTRAL = 'neutral';
		private const PRESET_CONTRAST = 'contrast';

		private static bool $inline_style_added = false;

		/**
		 * Registriert Admin-Hooks.
		 *
		 * @return void
		 */
		public function init(): void {
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}

		/**
		 * @return void
		 */
		public function register_menu(): void {
			add_options_page(
				__( 'AFSpaces Look & Feel', 'afspaces' ),
				__( 'AFSpaces Look & Feel', 'afspaces' ),
				'manage_options',
				'afspaces-look-and-feel',
				array( $this, 'render_page' )
			);
		}

		/**
		 * @return void
		 */
		public function register_settings(): void {
			register_setting(
				'afspaces_appearance_group',
				self::OPTION_KEY,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( $this, 'sanitize_options' ),
					'default'           => self::defaults(),
				)
			);
		}

		/**
		 * @return array<string,mixed>
		 */
		public static function defaults(): array {
			return array(
				'base_font_family'       => 'Quicksand, sans-serif',
				'heading_font_family'    => 'Quicksand, sans-serif',
				'base_font_size'         => 20,
				'heading_color'          => '#f6a81d',
				'text_color'             => '#444444',
				'link_color'             => '#2d5d7f',
				'breadcrumb_text_color'  => '#888888',
				'wrapper_background'     => '#fafbfc',
				'wrapper_border_color'   => '#e1e8ed',
				'wrapper_border_radius'  => 30,
				'nav_background'         => '#2d5d7f',
				'nav_text_color'         => '#ffffff',
				'nav_active_background'  => '#ffffff',
				'nav_active_text_color'  => '#1d2f43',
				'pager_background'       => '#f2f2f2',
				'pager_text_color'       => '#888888',
				'button_primary_bg'      => '#2d5d7f',
				'button_secondary_bg'    => '#7f98ac',
				'button_text_color'      => '#ffffff',
			);
		}

		/**
		 * @return array<string,array<string,mixed>>
		 */
		public static function presets(): array {
			return array(
				self::PRESET_ASGAROS  => self::defaults(),
				self::PRESET_NEUTRAL  => array(
					'base_font_family'       => 'Segoe UI, Arial, sans-serif',
					'heading_font_family'    => 'Segoe UI, Arial, sans-serif',
					'base_font_size'         => 18,
					'heading_color'          => '#1f3f5b',
					'text_color'             => '#2d3742',
					'link_color'             => '#2d5d7f',
					'breadcrumb_text_color'  => '#687482',
					'wrapper_background'     => '#ffffff',
					'wrapper_border_color'   => '#d9e0e6',
					'wrapper_border_radius'  => 18,
					'nav_background'         => '#345d79',
					'nav_text_color'         => '#ffffff',
					'nav_active_background'  => '#eef3f7',
					'nav_active_text_color'  => '#203448',
					'pager_background'       => '#f5f7f9',
					'pager_text_color'       => '#52606d',
					'button_primary_bg'      => '#2f74ae',
					'button_secondary_bg'    => '#6d7f90',
					'button_text_color'      => '#ffffff',
				),
				self::PRESET_CONTRAST => array(
					'base_font_family'       => 'Arial, sans-serif',
					'heading_font_family'    => 'Arial, sans-serif',
					'base_font_size'         => 20,
					'heading_color'          => '#c66d00',
					'text_color'             => '#1a1a1a',
					'link_color'             => '#003d73',
					'breadcrumb_text_color'  => '#444444',
					'wrapper_background'     => '#ffffff',
					'wrapper_border_color'   => '#b7c2cb',
					'wrapper_border_radius'  => 12,
					'nav_background'         => '#184a6b',
					'nav_text_color'         => '#ffffff',
					'nav_active_background'  => '#ffffff',
					'nav_active_text_color'  => '#111111',
					'pager_background'       => '#ffffff',
					'pager_text_color'       => '#1a1a1a',
					'button_primary_bg'      => '#005b99',
					'button_secondary_bg'    => '#4f5f6f',
					'button_text_color'      => '#ffffff',
				),
			);
		}

		/**
		 * @return array<string,mixed>
		 */
		public static function get_settings(): array {
			$stored = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}

			return array_merge( self::defaults(), $stored );
		}

		/**
		 * Fuegt das aktuell konfigurierte Inline-CSS einmalig hinzu.
		 *
		 * @return void
		 */
		public static function enqueue_inline_style(): void {
			if ( self::$inline_style_added ) {
				return;
			}

			$css = self::build_inline_css();
			if ( '' !== $css ) {
				wp_add_inline_style( 'afspaces-frontend', $css );
			}

			self::$inline_style_added = true;
		}

		/**
		 * @return string
		 */
		public static function build_inline_css(): string {
			$s = self::get_settings();

			$font_base   = (string) $s['base_font_family'];
			$font_heading = (string) $s['heading_font_family'];
			$font_size   = (int) $s['base_font_size'];
			$radius      = (int) $s['wrapper_border_radius'];

			return sprintf(
				'#af-wrapper.afspaces-wrapper { font-family: %1$s; font-size: %2$dpx; color: %3$s; background: %4$s; border-color: %5$s !important; border-radius: %6$dpx; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-dashboard h2, #af-wrapper.afspaces-wrapper .afspaces-members h2, #af-wrapper.afspaces-wrapper .afspaces-invitations h2, #af-wrapper.afspaces-wrapper .afspaces-join-requests h2, #af-wrapper.afspaces-wrapper .afspaces-my-invitations h2, #af-wrapper.afspaces-wrapper .afspaces-space-context-title { color: %7$s; font-family: %8$s; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-breadcrumb, #af-wrapper.afspaces-wrapper .afspaces-breadcrumb a { color: %9$s; }'
				. '#af-wrapper.afspaces-wrapper #forum-header.afspaces-forum-header, #af-wrapper.afspaces-wrapper .afspaces-space-nav { background: %10$s; border-color: %10$s; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-hub-tab { color: %11$s; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-hub-tab.is-active { background: %12$s; color: %13$s; border-bottom-color: %12$s; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-pagination a { background: %14$s; color: %15$s; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-pagination a[aria-current="page"] { background: %10$s; color: %11$s; border-color: %10$s; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-button { background: %16$s !important; border-color: %16$s !important; color: %18$s !important; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-button-secondary { background: %17$s !important; border-color: %17$s !important; color: %18$s !important; }'
				. '#af-wrapper.afspaces-wrapper a { color: %19$s; }',
				$font_base,
				$font_size,
				(string) $s['text_color'],
				(string) $s['wrapper_background'],
				(string) $s['wrapper_border_color'],
				$radius,
				(string) $s['heading_color'],
				$font_heading,
				(string) $s['breadcrumb_text_color'],
				(string) $s['nav_background'],
				(string) $s['nav_text_color'],
				(string) $s['nav_active_background'],
				(string) $s['nav_active_text_color'],
				(string) $s['pager_background'],
				(string) $s['pager_text_color'],
				(string) $s['button_primary_bg'],
				(string) $s['button_secondary_bg'],
				(string) $s['button_text_color'],
				(string) $s['link_color']
			);
		}

		/**
		 * @param mixed $input
		 * @return array<string,mixed>
		 */
		public function sanitize_options( $input ): array {
			$input = is_array( $input ) ? $input : array();

			if ( isset( $_POST['afspaces_reset_defaults'] ) ) {
				return self::defaults();
			}

			if ( isset( $_POST['afspaces_apply_preset'] ) ) {
				$preset_key = isset( $_POST['afspaces_preset_key'] ) ? sanitize_key( (string) wp_unslash( $_POST['afspaces_preset_key'] ) ) : '';
				$presets = self::presets();
				if ( isset( $presets[ $preset_key ] ) ) {
					return $presets[ $preset_key ];
				}
			}

			$out   = self::defaults();

			$out['base_font_family']      = $this->sanitize_font_stack( $input['base_font_family'] ?? $out['base_font_family'] );
			$out['heading_font_family']   = $this->sanitize_font_stack( $input['heading_font_family'] ?? $out['heading_font_family'] );
			$out['base_font_size']        = max( 12, min( 22, (int) ( $input['base_font_size'] ?? $out['base_font_size'] ) ) );
			$out['wrapper_border_radius'] = max( 0, min( 40, (int) ( $input['wrapper_border_radius'] ?? $out['wrapper_border_radius'] ) ) );

			$color_keys = array(
				'heading_color',
				'text_color',
				'link_color',
				'breadcrumb_text_color',
				'wrapper_background',
				'wrapper_border_color',
				'nav_background',
				'nav_text_color',
				'nav_active_background',
				'nav_active_text_color',
				'pager_background',
				'pager_text_color',
				'button_primary_bg',
				'button_secondary_bg',
				'button_text_color',
			);

			foreach ( $color_keys as $key ) {
				$raw = isset( $input[ $key ] ) ? (string) $input[ $key ] : (string) $out[ $key ];
				$san = sanitize_hex_color( $raw );
				$out[ $key ] = $san ?: (string) self::defaults()[ $key ];
			}

			return $out;
		}

		/**
		 * @param mixed $value
		 * @return string
		 */
		private function sanitize_font_stack( $value ): string {
			$clean = sanitize_text_field( (string) $value );
			$clean = trim( preg_replace( '/[^A-Za-z0-9,\-\s\"\"]/u', '', $clean ) ?? '' );

			if ( '' === $clean ) {
				return 'inherit';
			}

			return $clean;
		}

		/**
		 * @return void
		 */
		public function render_page(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$opts = self::get_settings();
			$presets = self::presets();
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'AFSpaces Look & Feel', 'afspaces' ); ?></h1>
				<p><?php echo esc_html__( 'Hier kannst du Farben, Schrift und Grundlayout der AFSpaces-Oberfläche an das Asgaros-Design anpassen.', 'afspaces' ); ?></p>
				<form method="post" action="options.php">
					<?php settings_fields( 'afspaces_appearance_group' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="afspaces_preset_key"><?php echo esc_html__( 'Preset', 'afspaces' ); ?></label></th>
							<td>
								<select id="afspaces_preset_key" name="afspaces_preset_key">
									<option value="<?php echo esc_attr( self::PRESET_ASGAROS ); ?>"><?php echo esc_html__( 'Asgaros-Nah', 'afspaces' ); ?></option>
									<option value="<?php echo esc_attr( self::PRESET_NEUTRAL ); ?>"><?php echo esc_html__( 'Neutral', 'afspaces' ); ?></option>
									<option value="<?php echo esc_attr( self::PRESET_CONTRAST ); ?>"><?php echo esc_html__( 'Kontrastreich', 'afspaces' ); ?></option>
								</select>
								<button type="submit" name="afspaces_apply_preset" class="button button-secondary" value="1"><?php echo esc_html__( 'Preset laden', 'afspaces' ); ?></button>
								<button type="submit" name="afspaces_reset_defaults" class="button" value="1"><?php echo esc_html__( 'Auf Standard zurücksetzen', 'afspaces' ); ?></button>
								<p class="description"><?php echo esc_html__( 'Ein Preset überschreibt die aktuellen Werte. Zurücksetzen lädt die AFSpaces-Standardwerte neu.', 'afspaces' ); ?></p>
							</td>
						</tr>
					</table>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="afspaces_base_font_family"><?php echo esc_html__( 'Grundschrift', 'afspaces' ); ?></label></th>
							<td><input type="text" class="regular-text" id="afspaces_base_font_family" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[base_font_family]" value="<?php echo esc_attr( (string) $opts['base_font_family'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_heading_font_family"><?php echo esc_html__( 'Ueberschriften-Schrift', 'afspaces' ); ?></label></th>
							<td><input type="text" class="regular-text" id="afspaces_heading_font_family" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[heading_font_family]" value="<?php echo esc_attr( (string) $opts['heading_font_family'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_base_font_size"><?php echo esc_html__( 'Grundschriftgroesse (px)', 'afspaces' ); ?></label></th>
							<td><input type="number" min="12" max="22" id="afspaces_base_font_size" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[base_font_size]" value="<?php echo esc_attr( (string) $opts['base_font_size'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_heading_color"><?php echo esc_html__( 'Ueberschriftenfarbe', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_heading_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[heading_color]" value="<?php echo esc_attr( (string) $opts['heading_color'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_text_color"><?php echo esc_html__( 'Textfarbe', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_text_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[text_color]" value="<?php echo esc_attr( (string) $opts['text_color'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_link_color"><?php echo esc_html__( 'Linkfarbe', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_link_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[link_color]" value="<?php echo esc_attr( (string) $opts['link_color'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_breadcrumb_text_color"><?php echo esc_html__( 'Brotkruemel-Farbe', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_breadcrumb_text_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[breadcrumb_text_color]" value="<?php echo esc_attr( (string) $opts['breadcrumb_text_color'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_wrapper_background"><?php echo esc_html__( 'Panel-Hintergrund', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_wrapper_background" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[wrapper_background]" value="<?php echo esc_attr( (string) $opts['wrapper_background'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_wrapper_border_color"><?php echo esc_html__( 'Panel-Randfarbe', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_wrapper_border_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[wrapper_border_color]" value="<?php echo esc_attr( (string) $opts['wrapper_border_color'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_wrapper_border_radius"><?php echo esc_html__( 'Panel-Rundung (px)', 'afspaces' ); ?></label></th>
							<td><input type="number" min="0" max="40" id="afspaces_wrapper_border_radius" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[wrapper_border_radius]" value="<?php echo esc_attr( (string) $opts['wrapper_border_radius'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_nav_background"><?php echo esc_html__( 'Top-Navigation Hintergrund', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_nav_background" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[nav_background]" value="<?php echo esc_attr( (string) $opts['nav_background'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_nav_text_color"><?php echo esc_html__( 'Top-Navigation Text', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_nav_text_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[nav_text_color]" value="<?php echo esc_attr( (string) $opts['nav_text_color'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_nav_active_background"><?php echo esc_html__( 'Aktiver Tab Hintergrund', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_nav_active_background" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[nav_active_background]" value="<?php echo esc_attr( (string) $opts['nav_active_background'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_nav_active_text_color"><?php echo esc_html__( 'Aktiver Tab Text', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_nav_active_text_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[nav_active_text_color]" value="<?php echo esc_attr( (string) $opts['nav_active_text_color'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_pager_background"><?php echo esc_html__( 'Pager Hintergrund', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_pager_background" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pager_background]" value="<?php echo esc_attr( (string) $opts['pager_background'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_pager_text_color"><?php echo esc_html__( 'Pager Text', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_pager_text_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pager_text_color]" value="<?php echo esc_attr( (string) $opts['pager_text_color'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_button_primary_bg"><?php echo esc_html__( 'Primär-Button Hintergrund', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_button_primary_bg" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[button_primary_bg]" value="<?php echo esc_attr( (string) $opts['button_primary_bg'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_button_secondary_bg"><?php echo esc_html__( 'Sekundär-Button Hintergrund', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_button_secondary_bg" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[button_secondary_bg]" value="<?php echo esc_attr( (string) $opts['button_secondary_bg'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces_button_text_color"><?php echo esc_html__( 'Button-Textfarbe', 'afspaces' ); ?></label></th>
							<td><input type="color" id="afspaces_button_text_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[button_text_color]" value="<?php echo esc_attr( (string) $opts['button_text_color'] ); ?>" /></td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}
	}
}
