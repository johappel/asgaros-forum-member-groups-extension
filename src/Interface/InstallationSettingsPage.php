<?php
/**
 * Einstellungen für Installation und Deinstallation.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Core\ForumManagementSettings;

if ( ! class_exists( 'AFSpaces\\Interface\\InstallationSettingsPage' ) ) {

	/**
	 * Bietet die bewusst sichtbare Opt-in-Entscheidung für vollständiges Cleanup.
	 */
	final class InstallationSettingsPage {

		public const OPTION = 'afspaces_cleanup_on_uninstall';

		public const FORUM_CREATION_OPTION = ForumManagementSettings::OPTION;

		/**
		 * Registriert Admin-Hooks.
		 *
		 * @return void
		 */
		public function init(): void {
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}

		/**
		 * @return void
		 */
		public function register_settings(): void {
			register_setting(
				'afspaces_installation_group',
				self::OPTION,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => array( $this, 'sanitize_option' ),
					'default'           => false,
				)
			);
			register_setting(
				'afspaces_installation_group',
				self::FORUM_CREATION_OPTION,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => array( $this, 'sanitize_option' ),
					'default'           => ForumManagementSettings::DEFAULT,
				)
			);
		}

		/**
		 * @param mixed $value Rohwert.
		 * @return bool
		 */
		public function sanitize_option( $value ): bool {
			return in_array( $value, array( true, 1, '1', 'true', 'on', 'yes' ), true );
		}

		/**
		 * @return void
		 */
		public function render_page( bool $embedded = false ): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			?>
			<?php if ( ! $embedded ) : ?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'Arbeitsgruppen-Extras', 'afspaces' ); ?></h1>
			<?php endif; ?>
				<form action="options.php" method="post">
					<?php settings_fields( 'afspaces_installation_group' ); ?>
					<h2><?php echo esc_html__( 'Forumverwaltung', 'afspaces' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Zusätzliche Foren', 'afspaces' ); ?></th>
							<td>
								<label for="afspaces-group-managers-can-create-forums">
									<input type="hidden" name="<?php echo esc_attr( self::FORUM_CREATION_OPTION ); ?>" value="0" />
									<input type="checkbox" id="afspaces-group-managers-can-create-forums" name="<?php echo esc_attr( self::FORUM_CREATION_OPTION ); ?>" value="1" <?php checked( ForumManagementSettings::group_managers_can_create_forums() ); ?> />
									<?php echo esc_html__( 'Arbeitsgruppenverantwortliche dürfen neue Foren ihrer Arbeitsgruppe anlegen', 'afspaces' ); ?>
								</label>
								<p class="description"><?php echo esc_html__( 'Erlaubt Verantwortlichen, innerhalb ihrer eigenen Arbeitsgruppe zusätzliche Foren anzulegen. Dadurch erhalten sie keine globalen Asgaros-Administrations- oder Moderationsrechte.', 'afspaces' ); ?></p>
							</td>
						</tr>
					</table>

					<h2><?php echo esc_html__( 'Deinstallation', 'afspaces' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Daten bei Deinstallation', 'afspaces' ); ?></th>
							<td>
								<label for="afspaces-cleanup-on-uninstall">
									<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>" value="0" />
									<input type="checkbox" id="afspaces-cleanup-on-uninstall" name="<?php echo esc_attr( self::OPTION ); ?>" value="1" <?php checked( (bool) get_option( self::OPTION, false ) ); ?> />
									<?php echo esc_html__( 'AFSpaces-Daten beim Löschen des Plugins vollständig entfernen', 'afspaces' ); ?>
								</label>
								<p class="description"><?php echo esc_html__( 'Standardmäßig bleiben AFSpaces-Tabellen, Optionen und die verwaltete Hub-Seite erhalten. Asgaros-Daten werden auch bei vollständigem Cleanup nie gelöscht.', 'afspaces' ); ?></p>
							</td>
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
