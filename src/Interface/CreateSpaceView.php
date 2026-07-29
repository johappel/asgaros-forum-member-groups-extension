<?php
/**
 * Frontend-Ansicht für die Selbstgründung eines Raums (MVP 4).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Application\SpaceCreationService;
use AFSpaces\Core\SpaceCreationSettings;

if ( ! class_exists( 'AFSpaces\\Interface\\CreateSpaceView' ) ) {

	/**
	 * Rendert den zugänglichen Raumassistenten.
	 *
	 * Der Assistent funktioniert vollständig als serverseitiges Ein-Seiten-Formular.
	 * JavaScript verbessert die Bedienung optional zu einem mehrstufigen Ablauf,
	 * ist aber für keine Kernfunktion erforderlich.
	 */
	class CreateSpaceView {

		private SpaceCreationService $creation;

		public function __construct( SpaceCreationService $creation ) {
			$this->creation = $creation;
		}

		/**
		 * Rendert die Ansicht.
		 *
		 * @return string
		 */
		public function render(): string {
			$actor = get_current_user_id();
			if ( 0 === $actor ) {
				return $this->notice( __( 'Bitte melde dich an, um eine Arbeitsgruppe zu gründen.', 'afspaces' ) );
			}

			if ( ! $this->creation->can_user_create( $actor ) ) {
				return $this->notice( __( 'Die Gründung von Arbeitsgruppen ist für dich derzeit nicht verfügbar.', 'afspaces' ) );
			}

			$settings     = $this->creation->get_settings();
			$visibilities = $settings->allowed_visibilities;
			$old          = $this->old_input();

			ob_start();
			?>
			<section class="afspaces-create-wizard" aria-labelledby="afspaces-create-heading">
				<h2 id="afspaces-create-heading"><?php echo esc_html__( 'Neue Arbeitsgruppe gründen', 'afspaces' ); ?></h2>
				<?php echo $this->render_message(); ?>

				<p class="afspaces-create-intro">
					<?php echo esc_html__( 'Als Gründerin oder Gründer wirst du automatisch verantwortlich für diese Arbeitsgruppe. Du verwaltest Mitglieder, Einladungen und Beitrittsanfragen.', 'afspaces' ); ?>
				</p>

				<?php if ( $settings->require_approval && ! user_can( $actor, \AFSpaces\Core\Capabilities::MANAGE_ALL_SPACES ) ) : ?>
					<p class="afspaces-notice" role="note">
						<?php echo esc_html__( 'Neue Arbeitsgruppen müssen vor der Veröffentlichung von einer Administratorin oder einem Administrator freigegeben werden.', 'afspaces' ); ?>
					</p>
				<?php endif; ?>

				<form method="post" class="afspaces-create-form afspaces-form-grid" data-afspaces-wizard>
					<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
					<input type="hidden" name="afspaces_action" value="create_space" />

					<div class="afspaces-form-error-summary" role="alert" tabindex="-1" hidden data-afspaces-error-summary>
						<h3><?php echo esc_html__( 'Bitte korrigiere folgende Angaben:', 'afspaces' ); ?></h3>
						<ul></ul>
					</div>

					<fieldset class="afspaces-wizard-step" data-afspaces-step="1">
						<legend><?php echo esc_html__( 'Schritt 1: Name und Beschreibung', 'afspaces' ); ?></legend>

						<p>
							<label for="afspaces-space-name"><?php echo esc_html__( 'Name der Arbeitsgruppe', 'afspaces' ); ?> <span aria-hidden="true">*</span></label>
							<input
								type="text"
								id="afspaces-space-name"
								name="name"
								value="<?php echo esc_attr( $old['name'] ); ?>"
								required
								minlength="<?php echo esc_attr( (string) $settings->name_min_length ); ?>"
								maxlength="<?php echo esc_attr( (string) $settings->name_max_length ); ?>"
								aria-describedby="afspaces-space-name-hint"
							/>
							<span id="afspaces-space-name-hint" class="description">
								<?php echo esc_html( sprintf( __( 'Zwischen %1$d und %2$d Zeichen.', 'afspaces' ), $settings->name_min_length, $settings->name_max_length ) ); ?>
							</span>
						</p>

						<p>
							<label for="afspaces-space-description"><?php echo esc_html__( 'Beschreibung', 'afspaces' ); ?></label>
							<textarea
								id="afspaces-space-description"
								name="description"
								rows="5"
								<?php echo $settings->description_max_length > 0 ? 'maxlength="' . esc_attr( (string) $settings->description_max_length ) . '"' : ''; ?>
							><?php echo esc_textarea( $old['description'] ); ?></textarea>
						</p>
					</fieldset>

					<fieldset class="afspaces-wizard-step" data-afspaces-step="2">
						<legend><?php echo esc_html__( 'Schritt 2: Sichtbarkeit', 'afspaces' ); ?></legend>
						<?php foreach ( $visibilities as $index => $visibility ) : ?>
							<p class="afspaces-radio-option">
								<label>
									<input
										type="radio"
										name="visibility"
										value="<?php echo esc_attr( $visibility ); ?>"
										<?php checked( $old['visibility'] ? $old['visibility'] === $visibility : 0 === $index ); ?>
									/>
									<span><?php echo esc_html( self::visibility_label( $visibility ) ); ?></span>
								</label>
								<span class="description"><?php echo esc_html( self::visibility_description( $visibility ) ); ?></span>
							</p>
						<?php endforeach; ?>
					</fieldset>

					<fieldset class="afspaces-wizard-step" data-afspaces-step="3">
						<legend><?php echo esc_html__( 'Schritt 3: Zusammenfassung und Bestätigung', 'afspaces' ); ?></legend>
						<p><?php echo esc_html__( 'Mit dem Gründen wird ein neuer, zugriffsbeschränkter Forenbereich erstellt. Du kannst später Mitglieder einladen und die Einstellungen anpassen.', 'afspaces' ); ?></p>
						<p class="afspaces-checkbox">
							<label>
								<input type="checkbox" name="accept_responsibility" value="1" required <?php checked( ! empty( $old['accept_responsibility'] ) ); ?> />
								<span><?php echo esc_html__( 'Ich verstehe, dass ich für diese Arbeitsgruppe verantwortlich bin.', 'afspaces' ); ?></span>
							</label>
						</p>
					</fieldset>

					<p class="afspaces-form-actions">
						<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Arbeitsgruppe gründen', 'afspaces' ); ?></button>
					</p>
				</form>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Beschriftung eines Sichtbarkeitsmodus.
		 *
		 * @param string $visibility Modus.
		 * @return string
		 */
		public static function visibility_label( string $visibility ): string {
			switch ( $visibility ) {
				case SpaceCreationSettings::VISIBILITY_PUBLIC:
					return __( 'Öffentlich', 'afspaces' );
				case SpaceCreationSettings::VISIBILITY_PROTECTED:
					return __( 'Geschützt (alle angemeldeten Personen)', 'afspaces' );
				case SpaceCreationSettings::VISIBILITY_PRIVATE:
				default:
					return __( 'Privat (nur Mitglieder)', 'afspaces' );
			}
		}

		/**
		 * Erläuterung eines Sichtbarkeitsmodus.
		 *
		 * @param string $visibility Modus.
		 * @return string
		 */
		public static function visibility_description( string $visibility ): string {
			switch ( $visibility ) {
				case SpaceCreationSettings::VISIBILITY_PUBLIC:
					return __( 'Für alle Besucherinnen und Besucher sichtbar.', 'afspaces' );
				case SpaceCreationSettings::VISIBILITY_PROTECTED:
					return __( 'Für alle angemeldeten Personen lesbar.', 'afspaces' );
				case SpaceCreationSettings::VISIBILITY_PRIVATE:
				default:
					return __( 'Nur eingeladene Mitglieder haben Zugriff.', 'afspaces' );
			}
		}

		/**
		 * Liest zwischengespeicherte Formulareingaben nach einem Fehler.
		 *
		 * @return array<string,mixed>
		 */
		private function old_input(): array {
			$defaults = array(
				'name'                  => '',
				'description'           => '',
				'visibility'            => '',
				'accept_responsibility' => false,
			);

			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}

			if ( empty( $_SESSION['afspaces_create_input'] ) || ! is_array( $_SESSION['afspaces_create_input'] ) ) {
				return $defaults;
			}

			$stored = $_SESSION['afspaces_create_input'];
			unset( $_SESSION['afspaces_create_input'] );

			return array(
				'name'                  => isset( $stored['name'] ) ? (string) $stored['name'] : '',
				'description'           => isset( $stored['description'] ) ? (string) $stored['description'] : '',
				'visibility'            => isset( $stored['visibility'] ) ? (string) $stored['visibility'] : '',
				'accept_responsibility' => ! empty( $stored['accept_responsibility'] ),
			);
		}

		/**
		 * @param string $text Text.
		 * @return string
		 */
		private function notice( string $text ): string {
			return sprintf( '<p class="afspaces-notice" role="status">%s</p>', esc_html( $text ) );
		}

		/**
		 * Rendert eine gespeicherte Statusmeldung (Post/Redirect/Get).
		 *
		 * @return string
		 */
		private function render_message(): string {
			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}

			if ( empty( $_SESSION['afspaces_message'] ) ) {
				return '';
			}

			$msg = $_SESSION['afspaces_message'];
			unset( $_SESSION['afspaces_message'] );

			$role = ( 'error' === $msg['type'] ) ? 'alert' : 'status';
			return sprintf(
				'<div class="afspaces-message afspaces-message-%1$s" role="%2$s" aria-live="polite">%3$s</div>',
				esc_attr( $msg['type'] ),
				esc_attr( $role ),
				esc_html( $msg['message'] )
			);
		}
	}
}
