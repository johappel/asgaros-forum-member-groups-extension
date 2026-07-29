<?php
/**
 * Admin-Seite für die globalen Raumgründungs-Richtlinien (MVP 4, M4.1).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Core\SpaceCreationSettings;

if ( ! class_exists( 'AFSpaces\\Interface\\SpaceCreationSettingsPage' ) ) {

	/**
	 * Ermöglicht Administratoren die zentrale Steuerung der Selbstgründung.
	 */
	class SpaceCreationSettingsPage {

		private const GROUP = 'afspaces_creation_group';

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
				__( 'AFSpaces Raumgründung', 'afspaces' ),
				__( 'AFSpaces Raumgründung', 'afspaces' ),
				'manage_options',
				'afspaces-creation',
				array( $this, 'render_page' )
			);
		}

		/**
		 * @return void
		 */
		public function register_settings(): void {
			register_setting(
				self::GROUP,
				SpaceCreationSettings::OPTION,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( $this, 'sanitize' ),
					'default'           => SpaceCreationSettings::defaults(),
				)
			);
		}

		/**
		 * Bereinigt und validiert die eingereichten Optionen.
		 *
		 * @param mixed $input Rohdaten aus dem Formular.
		 * @return array<string,mixed>
		 */
		public function sanitize( $input ): array {
			$input = is_array( $input ) ? $input : array();

			$visibilities = array();
			if ( isset( $input['allowed_visibilities'] ) && is_array( $input['allowed_visibilities'] ) ) {
				foreach ( $input['allowed_visibilities'] as $visibility ) {
					$visibility = sanitize_key( (string) $visibility );
					if ( in_array( $visibility, SpaceCreationSettings::all_visibilities(), true ) ) {
						$visibilities[] = $visibility;
					}
				}
			}
			if ( empty( $visibilities ) ) {
				$visibilities = array( SpaceCreationSettings::VISIBILITY_PRIVATE );
			}

			$regular_visibilities = array();
			if ( isset( $input['regular_visibilities'] ) && is_array( $input['regular_visibilities'] ) ) {
				foreach ( $input['regular_visibilities'] as $visibility ) {
					$visibility = sanitize_key( (string) $visibility );
					if ( in_array( $visibility, SpaceCreationSettings::all_visibilities(), true ) ) {
						$regular_visibilities[] = $visibility;
					}
				}
			}

			$roles = array();
			if ( isset( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ) {
				foreach ( $input['allowed_roles'] as $role ) {
					$role = sanitize_key( (string) $role );
					if ( '' !== $role ) {
						$roles[] = $role;
					}
				}
			}

			$settings = new SpaceCreationSettings(
				array(
					'enabled'                => ! empty( $input['enabled'] ),
					'allowed_roles'          => $roles,
					'max_spaces_per_user'    => isset( $input['max_spaces_per_user'] ) ? (int) $input['max_spaces_per_user'] : 3,
					'allowed_visibilities'   => $visibilities,
					'regular_visibilities'   => $regular_visibilities,
					'require_approval'       => ! empty( $input['require_approval'] ),
					'name_min_length'        => isset( $input['name_min_length'] ) ? (int) $input['name_min_length'] : 3,
					'name_max_length'        => isset( $input['name_max_length'] ) ? (int) $input['name_max_length'] : 60,
					'description_max_length' => isset( $input['description_max_length'] ) ? (int) $input['description_max_length'] : 2000,
					'reserved_names'         => isset( $input['reserved_names'] ) ? (string) $input['reserved_names'] : '',
					'rate_limit_seconds'     => isset( $input['rate_limit_seconds'] ) ? (int) $input['rate_limit_seconds'] : 300,
					'default_icon'           => isset( $input['default_icon'] ) ? sanitize_text_field( (string) $input['default_icon'] ) : 'users',
				)
			);

			return $settings->to_array();
		}

		/**
		 * Rendert die Einstellungsseite.
		 *
		 * @return void
		 */
		public function render_page(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$settings = SpaceCreationSettings::load();
			$roles    = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();
			if ( empty( $roles ) && function_exists( 'wp_roles' ) ) {
				$roles = wp_roles()->roles;
			}
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'AFSpaces Raumgründung', 'afspaces' ); ?></h1>
				<p><?php echo esc_html__( 'Hier legst du fest, wer eigene Arbeitsgruppen gründen darf und innerhalb welcher Grenzen.', 'afspaces' ); ?></p>
				<form method="post" action="options.php">
					<?php settings_fields( self::GROUP ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Funktion aktivieren', 'afspaces' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( SpaceCreationSettings::OPTION ); ?>[enabled]" value="1" <?php checked( $settings->enabled ); ?> />
									<?php echo esc_html__( 'Selbstgründung von Arbeitsgruppen erlauben', 'afspaces' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Erlaubte Rollen', 'afspaces' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php echo esc_html__( 'Erlaubte Rollen', 'afspaces' ); ?></legend>
									<?php foreach ( $roles as $slug => $role ) : ?>
										<label style="display:block;">
											<input type="checkbox" name="<?php echo esc_attr( SpaceCreationSettings::OPTION ); ?>[allowed_roles][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $settings->allowed_roles, true ) ); ?> />
											<?php echo esc_html( translate_user_role( (string) ( $role['name'] ?? $slug ) ) ); ?>
										</label>
									<?php endforeach; ?>
									<p class="description"><?php echo esc_html__( 'Ohne Auswahl dürfen alle angemeldeten Personen gründen (sobald die Funktion aktiviert ist). Mit Auswahl dürfen nur die gewählten Rollen gründen.', 'afspaces' ); ?></p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces-max-spaces"><?php echo esc_html__( 'Maximale Räume pro Person', 'afspaces' ); ?></label></th>
							<td>
								<input type="number" min="0" id="afspaces-max-spaces" name="<?php echo esc_attr( SpaceCreationSettings::OPTION ); ?>[max_spaces_per_user]" value="<?php echo esc_attr( (string) $settings->max_spaces_per_user ); ?>" />
								<p class="description"><?php echo esc_html__( '0 bedeutet unbegrenzt.', 'afspaces' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Erlaubte Sichtbarkeiten', 'afspaces' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php echo esc_html__( 'Erlaubte Sichtbarkeiten', 'afspaces' ); ?></legend>
									<?php foreach ( SpaceCreationSettings::all_visibilities() as $visibility ) : ?>
										<label style="display:block;">
											<input type="checkbox" name="<?php echo esc_attr( SpaceCreationSettings::OPTION ); ?>[allowed_visibilities][]" value="<?php echo esc_attr( $visibility ); ?>" <?php checked( in_array( $visibility, $settings->allowed_visibilities, true ) ); ?> />
											<?php echo esc_html( CreateSpaceView::visibility_label( $visibility ) ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Sichtbarkeiten für normale Nutzer', 'afspaces' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php echo esc_html__( 'Sichtbarkeiten für normale Nutzer', 'afspaces' ); ?></legend>
									<?php foreach ( SpaceCreationSettings::all_visibilities() as $visibility ) : ?>
										<label style="display:block;">
											<input type="checkbox" name="<?php echo esc_attr( SpaceCreationSettings::OPTION ); ?>[regular_visibilities][]" value="<?php echo esc_attr( $visibility ); ?>" <?php checked( in_array( $visibility, $settings->regular_visibilities, true ) ); ?> />
											<?php echo esc_html( CreateSpaceView::visibility_label( $visibility ) ); ?>
										</label>
									<?php endforeach; ?>
									<p class="description"><?php echo esc_html__( 'Diese Auswahl gilt für Nutzer ohne Moderations-/Administratorrechte. So kannst du z. B. festlegen, dass normale Nutzer nur private Arbeitsgruppen gründen dürfen. Moderatoren und Administratoren dürfen alle oben erlaubten Sichtbarkeiten wählen.', 'afspaces' ); ?></p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Freigabepflicht', 'afspaces' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( SpaceCreationSettings::OPTION ); ?>[require_approval]" value="1" <?php checked( $settings->require_approval ); ?> />
									<?php echo esc_html__( 'Neue Arbeitsgruppen müssen vor der Veröffentlichung freigegeben werden', 'afspaces' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Namensgrenzen', 'afspaces' ); ?></th>
							<td>
								<label><?php echo esc_html__( 'Minimum', 'afspaces' ); ?>
									<input type="number" min="1" name="<?php echo esc_attr( SpaceCreationSettings::OPTION ); ?>[name_min_length]" value="<?php echo esc_attr( (string) $settings->name_min_length ); ?>" />
								</label>
								<label><?php echo esc_html__( 'Maximum', 'afspaces' ); ?>
									<input type="number" min="1" name="<?php echo esc_attr( SpaceCreationSettings::OPTION ); ?>[name_max_length]" value="<?php echo esc_attr( (string) $settings->name_max_length ); ?>" />
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces-desc-max"><?php echo esc_html__( 'Maximale Beschreibungslänge', 'afspaces' ); ?></label></th>
							<td>
								<input type="number" min="0" id="afspaces-desc-max" name="<?php echo esc_attr( SpaceCreationSettings::OPTION ); ?>[description_max_length]" value="<?php echo esc_attr( (string) $settings->description_max_length ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces-rate-limit"><?php echo esc_html__( 'Wartezeit zwischen Gründungen (Sekunden)', 'afspaces' ); ?></label></th>
							<td>
								<input type="number" min="0" id="afspaces-rate-limit" name="<?php echo esc_attr( SpaceCreationSettings::OPTION ); ?>[rate_limit_seconds]" value="<?php echo esc_attr( (string) $settings->rate_limit_seconds ); ?>" />
								<p class="description"><?php echo esc_html__( '0 deaktiviert die Drosselung.', 'afspaces' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces-reserved"><?php echo esc_html__( 'Reservierte Namen', 'afspaces' ); ?></label></th>
							<td>
								<textarea id="afspaces-reserved" name="<?php echo esc_attr( SpaceCreationSettings::OPTION ); ?>[reserved_names]" rows="4" cols="50"><?php echo esc_textarea( implode( "\n", $settings->reserved_names ) ); ?></textarea>
								<p class="description"><?php echo esc_html__( 'Ein Name pro Zeile. Diese Namen können nicht für Arbeitsgruppen verwendet werden.', 'afspaces' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}
	}
}
