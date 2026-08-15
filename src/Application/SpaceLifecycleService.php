<?php
/**
 * Verwaltung und Lebenszyklus selbstgegründeter Räume (MVP 4).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceMetaRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\DomainException;
use AFSpaces\Core\SpaceCreationSettings;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceCreationPolicy;
use AFSpaces\Domain\SpaceLifecycle;
use AFSpaces\Domain\SpaceManager;

if ( ! class_exists( 'AFSpaces\\Application\\SpaceLifecycleService' ) ) {

	/**
	 * Kapselt Umbenennung, Sichtbarkeit, Owner-Übertragung, Archivierung,
	 * Löschung sowie den Freigabeprozess von Räumen.
	 */
	class SpaceLifecycleService {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private SpaceMetaRepository $meta;
		private AuditRepository $audit;
		private SpaceCreationPolicy $policy;

		public function __construct(
			SpaceRepository $spaces,
			AsgarosAdapterInterface $asgaros,
			SpaceMetaRepository $meta,
			AuditRepository $audit,
			?SpaceCreationPolicy $policy = null
		) {
			$this->spaces  = $spaces;
			$this->asgaros = $asgaros;
			$this->meta    = $meta;
			$this->audit   = $audit;
			$this->policy  = $policy ?? new SpaceCreationPolicy();
		}

		/**
		 * Benennt einen Raum um (Forumsname innerhalb der Richtlinien).
		 *
		 * @param int    $space_id      Space-ID.
		 * @param int    $actor_user_id Akteur.
		 * @param string $name          Neuer Name.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder ungültigem Namen.
		 */
		public function rename( int $space_id, int $actor_user_id, string $name ): void {
			$space = $this->require_managed_space( $space_id, $actor_user_id );
			$name  = $this->validate_name( $space_id, $actor_user_id, $name );

			$this->asgaros->update_forum( $space->forum_id, array( 'name' => $name ) );
			$this->audit->log( $space_id, $actor_user_id, $actor_user_id, 'space_renamed', 'space' );
		}

		/**
		 * Ändert die Sichtbarkeit innerhalb der erlaubten Modi.
		 *
		 * @param int    $space_id      Space-ID.
		 * @param int    $actor_user_id Akteur.
		 * @param string $visibility    Neue Sichtbarkeit.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder unerlaubter Sichtbarkeit.
		 */
		public function change_visibility( int $space_id, int $actor_user_id, string $visibility ): void {
			$space      = $this->require_managed_space( $space_id, $actor_user_id );
			$visibility = $this->validate_visibility( $space_id, $actor_user_id, $visibility );

			$this->apply_visibility( $space, $visibility );
			$this->spaces->update_visibility( $space_id, $visibility );
			$this->audit->log( $space_id, $actor_user_id, $actor_user_id, 'space_visibility_changed', 'space' );
		}

		/**
		 * Validiert einen Namen ohne einen Schreibzugriff.
		 *
		 * @param int    $space_id      Space-ID.
		 * @param int    $actor_user_id Akteur.
		 * @param string $name          Neuer Name.
		 * @return string Normalisierter Name.
		 */
		public function validate_name( int $space_id, int $actor_user_id, string $name ): string {
			$this->require_managed_space( $space_id, $actor_user_id );
			return $this->policy->validate_name( SpaceCreationSettings::load(), $name );
		}

		/**
		 * Validiert die Sichtbarkeit ohne einen Schreibzugriff.
		 *
		 * @param int    $space_id      Space-ID.
		 * @param int    $actor_user_id Akteur.
		 * @param string $visibility    Sichtbarkeitswert.
		 * @return string Validierter Sichtbarkeitswert.
		 */
		public function validate_visibility( int $space_id, int $actor_user_id, string $visibility ): string {
			$space      = $this->require_managed_space( $space_id, $actor_user_id );
			$settings   = SpaceCreationSettings::load();
			$privileged = user_can( $actor_user_id, Capabilities::MANAGE_ALL_SPACES )
				|| user_can( $actor_user_id, Capabilities::MODERATE_SPACE );
			$allowed    = $settings->visibilities_for( $privileged );

			// Bereits bestehende Werte bleiben für ein unverändertes Speichern
			// zulässig, auch wenn die globale Auswahl inzwischen enger ist.
			if ( $visibility === $space->visibility && ! in_array( $visibility, $allowed, true ) ) {
				$allowed[] = $visibility;
			}

			return $this->policy->validate_visibility( $settings, $visibility, $allowed );
		}

		/**
		 * Überträgt die Eigentümerschaft auf eine andere Person.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur (muss Owner oder globaler Verwalter sein).
		 * @param int $new_owner_id  Neue Eigentümer-Benutzer-ID.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder ungültigem Ziel.
		 */
		public function transfer_owner( int $space_id, int $actor_user_id, int $new_owner_id ): void {
			$space = $this->require_space( $space_id );

			$is_admin = user_can( $actor_user_id, Capabilities::MANAGE_ALL_SPACES );
			if ( ! $is_admin && $space->owner_user_id !== $actor_user_id ) {
				throw new DomainException( __( 'Nur die aktuelle Eigentümerin oder ein Administrator darf die Verantwortung übertragen.', 'afspaces' ) );
			}

			if ( $new_owner_id < 1 ) {
				throw new DomainException( __( 'Bitte wähle eine gültige Person für die Übertragung.', 'afspaces' ) );
			}

			if ( $new_owner_id === $space->owner_user_id ) {
				throw new DomainException( __( 'Diese Person ist bereits Eigentümerin.', 'afspaces' ) );
			}

			if ( ! get_userdata( $new_owner_id ) ) {
				throw new DomainException( __( 'Die gewählte Person existiert nicht.', 'afspaces' ) );
			}

			// Sicherstellen, dass die neue Eigentümerin Mitglied und Verantwortliche ist.
			$this->asgaros->add_user_to_group( $new_owner_id, $space->primary_group_id );
			$this->spaces->add_manager(
				new SpaceManager(
					array(
						'space_id' => $space_id,
						'user_id'  => $new_owner_id,
						'role'     => SpaceManager::ROLE_OWNER,
					)
				)
			);

			// Bisherige Eigentümerin als Verantwortliche (Manager) behalten.
			$this->spaces->add_manager(
				new SpaceManager(
					array(
						'space_id' => $space_id,
						'user_id'  => $space->owner_user_id,
						'role'     => SpaceManager::ROLE_MANAGER,
					)
				)
			);

			$this->spaces->set_owner_user( $space_id, $new_owner_id );
			$this->audit->log( $space_id, $actor_user_id, $new_owner_id, 'space_owner_transferred', 'space' );
		}

		/**
		 * Archiviert einen Raum (kein neuer Beitrag, aus Übersichten ausgeblendet).
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder unzulässigem Übergang.
		 */
		public function archive( int $space_id, int $actor_user_id ): void {
			$space = $this->require_managed_space( $space_id, $actor_user_id );
			$this->assert_transition( $space->status, SpaceLifecycle::STATUS_ARCHIVED );

			$this->asgaros->update_forum( $space->forum_id, array( 'forum_status' => 'closed' ) );
			$this->spaces->update_status( $space_id, SpaceLifecycle::STATUS_ARCHIVED );
			$this->audit->log( $space_id, $actor_user_id, $actor_user_id, 'space_archived', 'space' );
		}

		/**
		 * Reaktiviert einen archivierten Raum.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder unzulässigem Übergang.
		 */
		public function reactivate( int $space_id, int $actor_user_id ): void {
			$space = $this->require_managed_space( $space_id, $actor_user_id );
			$this->assert_transition( $space->status, SpaceLifecycle::STATUS_ACTIVE );

			$this->asgaros->update_forum( $space->forum_id, array( 'forum_status' => 'normal' ) );
			$this->spaces->update_status( $space_id, SpaceLifecycle::STATUS_ACTIVE );
			$this->audit->log( $space_id, $actor_user_id, $actor_user_id, 'space_reactivated', 'space' );
		}

		/**
		 * Löscht einen Raum inklusive der Asgaros-Struktur (mit Aufbewahrung des Datensatzes).
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur (Owner oder globaler Verwalter).
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder unzulässigem Übergang.
		 */
		public function delete( int $space_id, int $actor_user_id ): void {
			$space    = $this->require_space( $space_id );
			$is_admin = user_can( $actor_user_id, Capabilities::MANAGE_ALL_SPACES );
			if ( ! $is_admin && $space->owner_user_id !== $actor_user_id ) {
				throw new DomainException( __( 'Nur die Eigentümerin oder ein Administrator darf diesen Raum löschen.', 'afspaces' ) );
			}

			$this->assert_transition( $space->status, SpaceLifecycle::STATUS_DELETED );

			$this->remove_asgaros_structure( $space );
			$this->spaces->update_status( $space_id, SpaceLifecycle::STATUS_DELETED );
			$this->audit->log( $space_id, $actor_user_id, $actor_user_id, 'space_deleted', 'space' );
		}

		/**
		 * Gibt einen anhängigen Raum frei.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Freigebende Person (Admin/Moderator).
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder unzulässigem Übergang.
		 */
		public function approve( int $space_id, int $actor_user_id ): void {
			$this->assert_can_moderate( $actor_user_id );
			$space = $this->require_space( $space_id );
			$this->assert_transition( $space->status, SpaceLifecycle::STATUS_ACTIVE );

			// Bei Freigabe die tatsächliche Sichtbarkeit anwenden (Sperre ggf. lösen).
			$this->apply_visibility( $space, $space->visibility );
			// Forum wieder öffnen (bei der Gründung war es bis zur Freigabe geschlossen).
			$this->asgaros->update_forum( $space->forum_id, array( 'forum_status' => 'normal' ) );
			$this->spaces->update_status( $space_id, SpaceLifecycle::STATUS_ACTIVE );
			$this->audit->log( $space_id, $actor_user_id, $space->owner_user_id, 'space_approved', 'space' );
		}

		/**
		 * Lehnt einen anhängigen Raum mit Begründung ab und entfernt die Struktur.
		 *
		 * @param int    $space_id      Space-ID.
		 * @param int    $actor_user_id Ablehnende Person (Admin/Moderator).
		 * @param string $reason        Begründung.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder unzulässigem Übergang.
		 */
		public function reject( int $space_id, int $actor_user_id, string $reason ): void {
			$this->assert_can_moderate( $actor_user_id );
			$space = $this->require_space( $space_id );
			$this->assert_transition( $space->status, SpaceLifecycle::STATUS_REJECTED );

			$this->remove_asgaros_structure( $space );
			$this->spaces->set_rejection_reason( $space_id, trim( $reason ) );
			$this->spaces->update_status( $space_id, SpaceLifecycle::STATUS_REJECTED );
			$this->audit->log( $space_id, $actor_user_id, $space->owner_user_id, 'space_rejected', 'space' );
		}

		/**
		 * Listet anhängige Räume für die Freigabe.
		 *
		 * @param int $actor_user_id Akteur.
		 * @return Space[]
		 */
		public function list_pending( int $actor_user_id ): array {
			$this->assert_can_moderate( $actor_user_id );
			return $this->spaces->list_spaces_by_status( SpaceLifecycle::STATUS_PENDING );
		}

		/**
		 * Gibt die Zahl der für den Akteur sichtbaren offenen Freigaben zurück.
		 *
		 * Die Berechtigungsprüfung ist dieselbe wie bei `list_pending()`. Für
		 * Navigations-Badges wird bei fehlender Berechtigung bewusst nur 0
		 * zurückgegeben und kein Status nach außen verraten.
		 *
		 * @param int $actor_user_id Akteur.
		 * @return int
		 */
		public function count_pending_for_actor( int $actor_user_id ): int {
			if ( ! $this->can_moderate( $actor_user_id ) ) {
				return 0;
			}

			return $this->spaces->count_spaces_by_status( SpaceLifecycle::STATUS_PENDING );
		}

		/**
		 * Wendet eine Sichtbarkeit auf die Asgaros-Struktur eines Raums an.
		 *
		 * @param Space  $space      Space.
		 * @param string $visibility Sichtbarkeit.
		 * @return void
		 */
		private function apply_visibility( Space $space, string $visibility ): void {
			$restrict = ( SpaceCreationSettings::VISIBILITY_PRIVATE === $visibility );
			$access   = ( SpaceCreationSettings::VISIBILITY_PUBLIC === $visibility ) ? 'everyone' : 'loggedin';

			$this->asgaros->set_forum_visibility(
				$space->forum_id,
				array(
					'access'   => $access,
					'restrict' => $restrict,
					'group_id' => $space->primary_group_id,
				)
			);
		}

		/**
		 * Entfernt Forum, Kategorie und Gruppe eines Raums.
		 *
		 * @param Space $space Space.
		 * @return void
		 */
		private function remove_asgaros_structure( Space $space ): void {
			$forum       = $this->asgaros->get_forum( $space->forum_id );
			$category_id = (int) ( $forum['category_id'] ?? 0 );

			$this->asgaros->delete_forum( $space->forum_id );
			if ( $space->primary_group_id > 0 ) {
				$this->asgaros->delete_group( $space->primary_group_id );
			}
			if ( $category_id > 0 ) {
				$this->asgaros->delete_forum_category( $category_id );
			}
		}

		/**
		 * Lädt einen Space oder wirft eine Ausnahme.
		 *
		 * @param int $space_id Space-ID.
		 * @return Space
		 * @throws DomainException Wenn der Space nicht existiert.
		 */
		private function require_space( int $space_id ): Space {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Diese Arbeitsgruppe existiert nicht.', 'afspaces' ) );
			}
			return $space;
		}

		/**
		 * Lädt einen Space und stellt sicher, dass der Akteur ihn verwalten darf.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @return Space
		 * @throws DomainException Bei fehlender Berechtigung.
		 */
		private function require_managed_space( int $space_id, int $actor_user_id ): Space {
			$space = $this->require_space( $space_id );
			if ( ! user_can( $actor_user_id, Capabilities::MANAGE_ALL_SPACES )
				&& ! $this->spaces->is_manager( $space_id, $actor_user_id ) ) {
				throw new DomainException( __( 'Du darfst diese Arbeitsgruppe nicht verwalten.', 'afspaces' ) );
			}
			return $space;
		}

		/**
		 * Stellt sicher, dass der Akteur Freigaben erteilen darf.
		 *
		 * @param int $actor_user_id Akteur.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung.
		 */
		private function assert_can_moderate( int $actor_user_id ): void {
			if ( ! $this->can_moderate( $actor_user_id ) ) {
				throw new DomainException( __( 'Dir fehlt die Berechtigung, Arbeitsgruppen freizugeben.', 'afspaces' ) );
			}
		}

		/**
		 * Prüft die globale Freigabeberechtigung.
		 *
		 * @param int $actor_user_id Akteur.
		 * @return bool
		 */
		public function can_moderate( int $actor_user_id ): bool {
			return $actor_user_id > 0
				&& ( user_can( $actor_user_id, Capabilities::MANAGE_ALL_SPACES )
					|| user_can( $actor_user_id, Capabilities::MODERATE_SPACE ) );
		}

		/**
		 * Validiert einen Statusübergang.
		 *
		 * @param string $from Ausgangsstatus.
		 * @param string $to   Zielstatus.
		 * @return void
		 * @throws DomainException Wenn der Übergang unzulässig ist.
		 */
		private function assert_transition( string $from, string $to ): void {
			if ( ! SpaceLifecycle::can_transition( $from, $to ) ) {
				throw new DomainException( __( 'Dieser Statuswechsel ist nicht zulässig.', 'afspaces' ) );
			}
		}
	}
}
