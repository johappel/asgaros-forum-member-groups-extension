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
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		/**
		 * Lädt die progressive Hex-Farbbedienung nur auf der AFSpaces-Settingsseite.
		 *
		 * @return void
		 */
		public function enqueue_admin_assets(): void {
			$page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
			if ( 'afspaces-settings' !== $page ) {
				return;
			}

			wp_enqueue_script(
				'afspaces-admin',
				AFSPACES_URL . 'assets/afspaces-admin.js',
				array(),
				AFSPACES_VERSION,
				true
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
				'heading_color'          => '#2d5d7f',
				'purple_color'           => '#561188',
				'text_color'             => '#3a4f66',
				'link_color'             => '#2d5d7f',
				'breadcrumb_text_color'  => '#3a4f66',
				'wrapper_background'     => '#d9d9d9',
				'wrapper_border_color'   => '#d9d9d9',
				'wrapper_border_radius'  => 30,
				'nav_background'         => '#2d5d7f',
				'nav_text_color'         => '#ffffff',
				'nav_active_background'  => '#2d5d7f',
				'nav_active_text_color'  => '#ffffff',
				'pager_background'       => '#d9d9d9',
				'pager_text_color'       => '#3a4f66',
				'button_primary_bg'      => '#2d5d7f',
				'button_secondary_bg'    => '#364149',
				'button_text_color'      => '#ffffff',
				'button_secondary_text_color' => '#ffffff',
				'button_hover_bg'        => '#f5ae35',
				'button_hover_text_color' => '#3a4f66',
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
					'purple_color'           => '#561188',
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
					'button_secondary_text_color' => '#ffffff',
					'button_hover_bg'        => '#f8b521',
					'button_hover_text_color' => '#000000',
				),
				self::PRESET_CONTRAST => array(
					'base_font_family'       => 'Arial, sans-serif',
					'heading_font_family'    => 'Arial, sans-serif',
					'base_font_size'         => 20,
					'heading_color'          => '#c66d00',
					'purple_color'           => '#561188',
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
					'button_secondary_bg'    => '#50575e',
					'button_text_color'      => '#ffffff',
					'button_secondary_text_color' => '#ffffff',
					'button_hover_bg'        => '#ffb900',
					'button_hover_text_color' => '#111111',
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

			$settings = array_merge( self::defaults(), $stored );
			$legacy_values = array(
				'button_primary_bg'   => array( '#f5ae35' ),
				'button_secondary_bg' => array( '#5c677c' ),
				'button_text_color'   => array( '#3a4f66' ),
			);
			foreach ( $legacy_values as $key => $values ) {
				if ( isset( $stored[ $key ] ) && in_array( strtolower( trim( (string) $stored[ $key ] ) ), $values, true ) ) {
					$settings[ $key ] = self::defaults()[ $key ];
				}
			}
			foreach ( array_keys( self::color_fields() ) as $key ) {
				$normalized = self::normalize_hex_color( $settings[ $key ] ?? '' );
				$settings[ $key ] = $normalized ?: (string) self::defaults()[ $key ];
			}

			return $settings;
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
				'#af-wrapper.afspaces-wrapper { --afspaces-color-blue: %7$s; --afspaces-color-yellow: %21$s; --afspaces-color-purple: %23$s; --afspaces-color-text: %3$s; --afspaces-color-secondary-background: %17$s; --afspaces-color-light-background: %4$s; font-family: %1$s; font-size: %2$dpx; color: %3$s; background: %4$s; border-color: %5$s !important; border-radius: %6$dpx; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-dashboard h2, #af-wrapper.afspaces-wrapper .afspaces-members h2, #af-wrapper.afspaces-wrapper .afspaces-invitations h2, #af-wrapper.afspaces-wrapper .afspaces-join-requests h2, #af-wrapper.afspaces-wrapper .afspaces-my-invitations h2, #af-wrapper.afspaces-wrapper .afspaces-space-context-title { color: %7$s; font-family: %8$s; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-breadcrumb, #af-wrapper.afspaces-wrapper .afspaces-breadcrumb a { color: %9$s; }'
				. '#af-wrapper.afspaces-wrapper #forum-header.afspaces-forum-header { background: %10$s; border-color: %10$s; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-hub-tab { color: %11$s; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-hub-tab.is-active { background: %12$s; color: %13$s; border-bottom-color: %12$s; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-pagination a { background: %14$s; color: %15$s; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-pagination a[aria-current="page"] { background: %10$s; color: %11$s; border-color: %10$s; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-button { background: %16$s !important; border-color: %16$s !important; color: %18$s !important; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-button:hover, #af-wrapper.afspaces-wrapper .afspaces-button:focus { background: %21$s !important; border-color: %21$s !important; color: %22$s !important; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-button-secondary { background: %17$s !important; border-color: %17$s !important; color: %20$s !important; }'
				. '#af-wrapper.afspaces-wrapper .afspaces-button-secondary:hover, #af-wrapper.afspaces-wrapper .afspaces-button-secondary:focus { background: %21$s !important; border-color: %21$s !important; color: %22$s !important; }'
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
				(string) $s['link_color'],
				(string) $s['button_secondary_text_color'],
				(string) $s['button_hover_bg'],
				(string) $s['button_hover_text_color'],
				(string) $s['purple_color']
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
				'purple_color',
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
				'button_secondary_text_color',
				'button_hover_bg',
				'button_hover_text_color',
			);

			foreach ( $color_keys as $key ) {
				$raw = isset( $input[ $key ] ) ? (string) $input[ $key ] : (string) $out[ $key ];
				$san = self::normalize_hex_color( $raw );
				$out[ $key ] = $san ?: (string) self::defaults()[ $key ];
			}

			return $out;
		}

		/**
		 * Normalisiert drei- und sechsstellige Hexwerte auf #RRGGBB.
		 *
		 * @param mixed $value Farbwert.
		 * @return string
		 */
		private static function normalize_hex_color( $value ): string {
			$raw = trim( (string) $value );
			if ( 1 !== preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $raw, $matches ) ) {
				return '';
			}

			$hex = strtoupper( $matches[1] );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}

			return '#' . $hex;
		}

		/**
		 * @return array<string,string>
		 */
		private static function color_fields(): array {
			return array(
				'heading_color'              => __( 'Überschriftenfarbe', 'afspaces' ),
				'purple_color'               => __( 'Lila Akzentfarbe', 'afspaces' ),
				'text_color'                 => __( 'Textfarbe', 'afspaces' ),
				'link_color'                 => __( 'Linkfarbe', 'afspaces' ),
				'breadcrumb_text_color'      => __( 'Breadcrumb-Farbe', 'afspaces' ),
				'wrapper_background'          => __( 'Panel-Hintergrund', 'afspaces' ),
				'wrapper_border_color'        => __( 'Panel-Randfarbe', 'afspaces' ),
				'nav_background'              => __( 'Top-Navigation Hintergrund', 'afspaces' ),
				'nav_text_color'              => __( 'Top-Navigation Text', 'afspaces' ),
				'nav_active_background'       => __( 'Aktiver Tab Hintergrund', 'afspaces' ),
				'nav_active_text_color'       => __( 'Aktiver Tab Text', 'afspaces' ),
				'pager_background'            => __( 'Pager Hintergrund', 'afspaces' ),
				'pager_text_color'            => __( 'Pager Text', 'afspaces' ),
				'button_primary_bg'           => __( 'Primär-Button Hintergrund', 'afspaces' ),
				'button_secondary_bg'         => __( 'Sekundär-Button Hintergrund', 'afspaces' ),
				'button_text_color'           => __( 'Button-Textfarbe', 'afspaces' ),
				'button_secondary_text_color' => __( 'Sekundär-Button Textfarbe', 'afspaces' ),
				'button_hover_bg'             => __( 'Button-Hover Hintergrund', 'afspaces' ),
				'button_hover_text_color'     => __( 'Button-Hover Textfarbe', 'afspaces' ),
			);
		}

		/**
		 * @param string               $key Optionenschlüssel.
		 * @param string               $label Sichtbarer Feldname.
		 * @param array<string,mixed>  $opts Aktuelle Optionen.
		 * @return void
		 */
		private static function render_color_field( string $key, string $label, array $opts ): void {
			$field_id = 'afspaces_' . $key;
			$value = self::normalize_hex_color( $opts[ $key ] ?? '' );
			if ( '' === $value ) {
				$value = '#000000';
			}
			?>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $field_id . '_hex' ); ?>"><?php echo esc_html( $label ); ?></label></th>
				<td>
					<div class="afspaces-admin-color-control" data-afspaces-color-control>
						<input type="color" id="<?php echo esc_attr( $field_id . '_picker' ); ?>" value="<?php echo esc_attr( strtolower( $value ) ); ?>" data-afspaces-color-picker aria-label="<?php echo esc_attr( $label . ' Farbwähler' ); ?>" />
						<input type="text" id="<?php echo esc_attr( $field_id . '_hex' ); ?>" name="<?php echo esc_attr( self::OPTION_KEY . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text afspaces-hex-color" data-afspaces-hex-input inputmode="text" maxlength="7" pattern="#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?" autocomplete="off" spellcheck="false" aria-describedby="<?php echo esc_attr( $field_id . '_description' ); ?>" />
						<span id="<?php echo esc_attr( $field_id . '_description' ); ?>" class="description">#RRGGBB</span>
					</div>
				</td>
			</tr>
			<?php
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
		public function render_page( bool $embedded = false ): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$opts = self::get_settings();
			$presets = self::presets();
			?>
			<?php if ( ! $embedded ) : ?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'Arbeitsgruppen-Darstellung', 'afspaces' ); ?></h1>
			<?php endif; ?>
				<p><?php echo esc_html__( 'Hier kannst du Farben, Schrift und Grundlayout der AFSpaces-Oberfläche an das Asgaros-Design anpassen.', 'afspaces' ); ?></p>
				<p class="description"><?php echo esc_html__( 'Farben können per Farbwähler oder direkt als Hexwert eingegeben und per Copy-and-paste übernommen werden. Erlaubt sind zum Beispiel #2D5D7F und #ABC.', 'afspaces' ); ?></p>
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
						<?php foreach ( self::color_fields() as $key => $label ) : ?>
							<?php self::render_color_field( $key, $label, $opts ); ?>
						<?php endforeach; ?>
						<tr>
							<th scope="row"><label for="afspaces_wrapper_border_radius"><?php echo esc_html__( 'Panel-Rundung (px)', 'afspaces' ); ?></label></th>
							<td><input type="number" min="0" max="40" id="afspaces_wrapper_border_radius" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[wrapper_border_radius]" value="<?php echo esc_attr( (string) $opts['wrapper_border_radius'] ); ?>" /></td>
						</tr>

					</table>
					<?php submit_button(); ?>
				</form>
			<?php if ( ! $embedded ) : ?>
			</div>
			<?php endif; ?>
			<?php
		}
	}
}
