<?php
/**
 * Einstellungen für Installation und Deinstallation.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

if ( ! class_exists( 'AFSpaces\\Interface\\InstallationSettingsPage' ) ) {

	/**
	 * Bietet die bewusst sichtbare Opt-in-Entscheidung für vollständiges Cleanup.
	 */
	final class InstallationSettingsPage {

		public const OPTION = 'afspaces_cleanup_on_uninstall';

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
				__( 'AFSpaces Installation', 'afspaces' ),
				__( 'AFSpaces Installation', 'afspaces' ),
				'manage_options',
				'afspaces-installation',
				array( $this, 'render_page' )
			);
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
		}

		/**
		 * @param mixed $value Rohwert.
		 * @return bool
		 */
		public function sanitize_option( $value ): bool {
			return ! empty( $value );
		}

		/**
		 * @return void
		 */
		public function render_page(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'AFSpaces Installation', 'afspaces' ); ?></h1>
				<form action="options.php" method="post">
					<?php settings_fields( 'afspaces_installation_group' ); ?>
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
			</div>
			<?php
		}
	}
}
