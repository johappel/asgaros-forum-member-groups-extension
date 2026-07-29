<?php
/**
 * Reine Richtlinien-Policy für die Selbstgründung von Räumen (MVP 4).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Domain;

use AFSpaces\Core\DomainException;
use AFSpaces\Core\SpaceCreationSettings;

if ( ! class_exists( 'AFSpaces\\Domain\\SpaceCreationPolicy' ) ) {

	/**
	 * Entscheidet ohne WordPress-Abhängigkeiten, ob eine Raumgründung zulässig ist.
	 *
	 * Alle Prüfungen sind absichtlich frei von globalem Zustand, damit sie
	 * deterministisch unit-getestet werden können. Die konkreten Werte
	 * (Capability, Rollen, Zähler, Zeitabstand) werden vom aufrufenden Service
	 * aus WordPress ermittelt und hier nur validiert.
	 */
	final class SpaceCreationPolicy {

		/**
		 * Prüft die grundsätzliche Berechtigung zur Gründung.
		 *
		 * @param SpaceCreationSettings $settings          Richtlinien.
		 * @param bool                  $can_manage_all    Akteur besitzt globale Verwaltung.
		 * @param bool                  $has_create_cap    Akteur besitzt Gründungs-Capability.
		 * @param string[]              $actor_roles       Rollen-Slugs des Akteurs.
		 * @return void
		 * @throws DomainException Wenn die Gründung nicht erlaubt ist.
		 */
		public function assert_can_create(
			SpaceCreationSettings $settings,
			bool $can_manage_all,
			bool $has_create_cap,
			array $actor_roles
		): void {
			if ( $can_manage_all ) {
				return;
			}

			if ( ! $settings->enabled ) {
				throw new DomainException( __( 'Die Selbstgründung von Arbeitsgruppen ist derzeit deaktiviert.', 'afspaces' ) );
			}

			// Ist die Funktion aktiv und wurde KEINE Rolle eingeschränkt, dürfen
			// alle angemeldeten Personen gründen. Sind Rollen konfiguriert, ist
			// berechtigt, wer eine dieser Rollen ODER die Gründungs-Capability hat.
			if ( empty( $settings->allowed_roles ) ) {
				return;
			}

			$by_role = ! empty( array_intersect( $settings->allowed_roles, $actor_roles ) );
			if ( ! $by_role && ! $has_create_cap ) {
				throw new DomainException( __( 'Deine Benutzerrolle ist für die Gründung von Arbeitsgruppen nicht freigeschaltet.', 'afspaces' ) );
			}
		}

		/**
		 * Prüft das Raumlimit pro Benutzer.
		 *
		 * @param SpaceCreationSettings $settings           Richtlinien.
		 * @param bool                  $can_manage_all     Akteur besitzt globale Verwaltung.
		 * @param int                   $active_space_count Aktuelle Anzahl aktiver/anhängiger Räume des Akteurs.
		 * @return void
		 * @throws DomainException Wenn das Limit erreicht ist.
		 */
		public function assert_within_quota(
			SpaceCreationSettings $settings,
			bool $can_manage_all,
			int $active_space_count
		): void {
			if ( $can_manage_all ) {
				return;
			}

			if ( $settings->max_spaces_per_user > 0 && $active_space_count >= $settings->max_spaces_per_user ) {
				throw new DomainException(
					sprintf(
						/* translators: %d: maximale Anzahl */
						__( 'Du hast die maximale Anzahl von %d Arbeitsgruppen erreicht.', 'afspaces' ),
						$settings->max_spaces_per_user
					)
				);
			}
		}

		/**
		 * Prüft die Erstellungsfrequenz (Missbrauchsschutz).
		 *
		 * @param SpaceCreationSettings $settings          Richtlinien.
		 * @param bool                  $can_manage_all    Akteur besitzt globale Verwaltung.
		 * @param int|null              $seconds_since_last Sekunden seit der letzten Gründung (null = keine).
		 * @return void
		 * @throws DomainException Wenn zu schnell hintereinander gegründet wird.
		 */
		public function assert_rate_limit(
			SpaceCreationSettings $settings,
			bool $can_manage_all,
			?int $seconds_since_last
		): void {
			if ( $can_manage_all || $settings->rate_limit_seconds <= 0 || null === $seconds_since_last ) {
				return;
			}

			if ( $seconds_since_last < $settings->rate_limit_seconds ) {
				throw new DomainException( __( 'Bitte warte einen Moment, bevor du die nächste Arbeitsgruppe gründest.', 'afspaces' ) );
			}
		}

		/**
		 * Validiert und normalisiert den Raumnamen.
		 *
		 * @param SpaceCreationSettings $settings Richtlinien.
		 * @param string                $name     Rohname.
		 * @return string Bereinigter Name.
		 * @throws DomainException Bei ungültigem oder reserviertem Namen.
		 */
		public function validate_name( SpaceCreationSettings $settings, string $name ): string {
			$name = trim( preg_replace( '/\s+/u', ' ', $name ) ?? '' );
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $name, 'UTF-8' ) : strlen( $name );

			if ( $length < $settings->name_min_length ) {
				throw new DomainException(
					sprintf(
						/* translators: %d: Mindestlänge */
						__( 'Der Name muss mindestens %d Zeichen lang sein.', 'afspaces' ),
						$settings->name_min_length
					)
				);
			}

			if ( $length > $settings->name_max_length ) {
				throw new DomainException(
					sprintf(
						/* translators: %d: Maximallänge */
						__( 'Der Name darf höchstens %d Zeichen lang sein.', 'afspaces' ),
						$settings->name_max_length
					)
				);
			}

			if ( $settings->is_reserved_name( $name ) ) {
				throw new DomainException( __( 'Dieser Name ist reserviert und kann nicht verwendet werden.', 'afspaces' ) );
			}

			return $name;
		}

		/**
		 * Validiert und begrenzt die Beschreibung.
		 *
		 * @param SpaceCreationSettings $settings    Richtlinien.
		 * @param string                $description Rohbeschreibung.
		 * @return string Bereinigte Beschreibung.
		 * @throws DomainException Bei Überlänge.
		 */
		public function validate_description( SpaceCreationSettings $settings, string $description ): string {
			$description = trim( $description );
			if ( 0 === $settings->description_max_length ) {
				return $description;
			}

			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $description, 'UTF-8' ) : strlen( $description );
			if ( $length > $settings->description_max_length ) {
				throw new DomainException(
					sprintf(
						/* translators: %d: Maximallänge */
						__( 'Die Beschreibung darf höchstens %d Zeichen lang sein.', 'afspaces' ),
						$settings->description_max_length
					)
				);
			}

			return $description;
		}

		/**
		 * Validiert die gewünschte Sichtbarkeit.
		 *
		 * @param SpaceCreationSettings $settings         Richtlinien.
		 * @param string                $visibility       Sichtbarkeitswert.
		 * @param string[]|null         $allowed_override Optionale, nutzerspezifische Positivliste.
		 * @return string Erlaubte Sichtbarkeit.
		 * @throws DomainException Wenn die Sichtbarkeit nicht erlaubt ist.
		 */
		public function validate_visibility( SpaceCreationSettings $settings, string $visibility, ?array $allowed_override = null ): string {
			$visibility = trim( $visibility );
			$allowed    = null !== $allowed_override ? $allowed_override : $settings->allowed_visibilities;
			if ( ! in_array( $visibility, $allowed, true ) ) {
				throw new DomainException( __( 'Die gewählte Sichtbarkeit ist nicht erlaubt.', 'afspaces' ) );
			}

			return $visibility;
		}
	}
}
