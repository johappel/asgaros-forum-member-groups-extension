<?php
/**
 * Selbstgründung privater Räume (MVP 4) mit transaktionsähnlichem Rollback.
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
use AFSpaces\Domain\WorkingGroupMeta;

if ( ! class_exists( 'AFSpaces\\Application\\SpaceCreationService' ) ) {

	/**
	 * Orchestriert die Gründung eines neuen Raums über den Asgaros-Adapter.
	 */
	class SpaceCreationService {

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
		 * Gibt die aktuellen Gründungsrichtlinien zurück.
		 *
		 * @return SpaceCreationSettings
		 */
		public function get_settings(): SpaceCreationSettings {
			return SpaceCreationSettings::load();
		}

		/**
		 * Prüft ohne Ausnahme, ob der Benutzer grundsätzlich gründen darf.
		 *
		 * @param int $actor_user_id Benutzer-ID.
		 * @return bool
		 */
		public function can_user_create( int $actor_user_id ): bool {
			if ( $actor_user_id < 1 ) {
				return false;
			}

			try {
				$this->policy->assert_can_create(
					$this->get_settings(),
					user_can( $actor_user_id, Capabilities::MANAGE_ALL_SPACES ),
					user_can( $actor_user_id, Capabilities::CREATE_SPACE ),
					$this->actor_roles( $actor_user_id )
				);
				return true;
			} catch ( DomainException $e ) {
				return false;
			}
		}

		/**
		 * Gründet einen neuen Raum.
		 *
		 * Alle Asgaros-Artefakte (Kategorie, Gruppe, Forum) werden bei einem
		 * Teilfehler in umgekehrter Reihenfolge wieder entfernt (Rollback).
		 *
		 * @param int                 $actor_user_id Benutzer-ID des Gründers.
		 * @param array<string,mixed> $input         Eingaben: name, description, visibility.
		 * @return Space
		 * @throws DomainException Bei fehlender Berechtigung, Limitverletzung oder Fehler.
		 */
		public function create( int $actor_user_id, array $input ): Space {
			if ( $actor_user_id < 1 ) {
				throw new DomainException( __( 'Bitte melde dich an, um eine Arbeitsgruppe zu gründen.', 'afspaces' ) );
			}

			$settings       = $this->get_settings();
			$can_manage_all = user_can( $actor_user_id, Capabilities::MANAGE_ALL_SPACES );

			// 1. Richtlinien- und Limitprüfungen (zuerst, ohne Seiteneffekte).
			$this->policy->assert_can_create(
				$settings,
				$can_manage_all,
				user_can( $actor_user_id, Capabilities::CREATE_SPACE ),
				$this->actor_roles( $actor_user_id )
			);

			$this->policy->assert_within_quota(
				$settings,
				$can_manage_all,
				$this->spaces->count_owner_live_spaces( $actor_user_id )
			);

			$this->policy->assert_rate_limit(
				$settings,
				$can_manage_all,
				$this->seconds_since_last_creation( $actor_user_id )
			);

			$name        = $this->policy->validate_name( $settings, (string) ( $input['name'] ?? '' ) );
			$description = $this->policy->validate_description( $settings, (string) ( $input['description'] ?? '' ) );
			$visibility  = $this->policy->validate_visibility( $settings, (string) ( $input['visibility'] ?? SpaceCreationSettings::VISIBILITY_PRIVATE ) );

			// 2. Asgaros-Struktur transaktionsähnlich anlegen.
			$category_id = 0;
			$group_id    = 0;
			$forum_id    = 0;
			$space_id    = 0;

			// Status vorab bestimmen: bei Freigabepflicht bleibt der Raum "pending".
			$status = ( $settings->require_approval && ! $can_manage_all )
				? SpaceLifecycle::STATUS_PENDING
				: SpaceLifecycle::STATUS_ACTIVE;

			// Zugriff beschränken, solange der Raum privat ODER noch nicht freigegeben ist.
			// So entsteht vor der Freigabe kein ungewollter öffentlicher Zugriff.
			$restrict_access = ( SpaceLifecycle::STATUS_PENDING === $status )
				|| ( SpaceCreationSettings::VISIBILITY_PRIVATE === $visibility );

			try {
				$access      = self::visibility_to_access( $visibility );
				$category_id = $this->asgaros->create_forum_category(
					array(
						'name'   => $name,
						'access' => $restrict_access ? 'loggedin' : $access,
					)
				);

				$group_id = $this->asgaros->create_group(
					array(
						'name'  => $this->group_name( $name, $category_id ),
						'color' => '#2d5d7f',
					)
				);

				$forum_id = $this->asgaros->create_forum(
					array(
						'category_id' => $category_id,
						'name'        => $name,
						'description' => $description,
						'icon'        => 'fas fa-comments',
					)
				);

				// Solange der Raum beschränkt ist, wirkt die Gruppe als Zugriffssperre;
				// bei Freigabe eines öffentlichen/geschützten Raums wird sie später gelöst.
				if ( $restrict_access ) {
					$this->asgaros->assign_group_to_forum( $forum_id, $group_id );
				}

				// Gründer als Gruppenmitglied eintragen.
				$this->asgaros->add_user_to_group( $actor_user_id, $group_id );

				// 3. Space-Datensatz erstellen.
				$space_id = $this->spaces->create_space(
					new Space(
						array(
							'forum_id'         => $forum_id,
							'primary_group_id' => $group_id,
							'owner_user_id'    => $actor_user_id,
							'visibility'       => $visibility,
							'status'           => $status,
						)
					)
				);

				if ( $space_id < 1 ) {
					throw new DomainException( __( 'Der Raum konnte nicht gespeichert werden.', 'afspaces' ) );
				}

				$this->spaces->add_manager(
					new SpaceManager(
						array(
							'space_id' => $space_id,
							'user_id'  => $actor_user_id,
							'role'     => SpaceManager::ROLE_OWNER,
						)
					)
				);

				// 4. Sichtbare Metadaten (Beschreibung/Verzeichnissichtbarkeit) speichern.
				$this->meta->save(
					new WorkingGroupMeta(
						array(
							'space_id'             => $space_id,
							'description'          => $description,
							'directory_visibility' => ( SpaceCreationSettings::VISIBILITY_PRIVATE === $visibility )
								? WorkingGroupMeta::DIRECTORY_MEMBERS
								: WorkingGroupMeta::DIRECTORY_LISTED,
							'join_policy'          => WorkingGroupMeta::JOIN_POLICY_REQUEST,
							'join_requests_enabled' => 1,
						)
					)
				);

				$this->audit->log( $space_id, $actor_user_id, $actor_user_id, 'space_created', 'space' );
			} catch ( \Throwable $e ) {
				$this->rollback( $space_id, $forum_id, $group_id, $category_id );

				if ( $e instanceof DomainException ) {
					throw $e;
				}

				throw new DomainException(
					sprintf(
						/* translators: %s: Fehlermeldung */
						__( 'Die Arbeitsgruppe konnte nicht erstellt werden: %s', 'afspaces' ),
						$e->getMessage()
					)
				);
			}

			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Der Raum konnte nicht geladen werden.', 'afspaces' ) );
			}

			return $space;
		}

		/**
		 * Entfernt teilweise erstellte Artefakte in umgekehrter Reihenfolge.
		 *
		 * @param int $space_id    Space-ID (0 = keiner).
		 * @param int $forum_id    Forum-ID (0 = keiner).
		 * @param int $group_id    Gruppen-ID (0 = keiner).
		 * @param int $category_id Kategorie-ID (0 = keine).
		 * @return void
		 */
		private function rollback( int $space_id, int $forum_id, int $group_id, int $category_id ): void {
			if ( $space_id > 0 ) {
				try {
					$this->spaces->delete_space( $space_id );
				} catch ( \Throwable $ignored ) {
					// Bewusst still: Rollback darf keine neue Ausnahme werfen.
					unset( $ignored );
				}
			}

			foreach ( array(
				array( 'delete_forum', $forum_id ),
				array( 'delete_group', $group_id ),
				array( 'delete_forum_category', $category_id ),
			) as $step ) {
				list( $method, $id ) = $step;
				if ( $id > 0 ) {
					try {
						$this->asgaros->{$method}( $id );
					} catch ( \Throwable $ignored ) {
						unset( $ignored );
					}
				}
			}
		}

		/**
		 * Ermittelt die Rollen-Slugs eines Benutzers.
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return string[]
		 */
		private function actor_roles( int $user_id ): array {
			$user = get_userdata( $user_id );
			if ( ! $user || ! isset( $user->roles ) || ! is_array( $user->roles ) ) {
				return array();
			}
			return array_values( array_map( 'strval', $user->roles ) );
		}

		/**
		 * Sekunden seit der letzten Gründung dieses Benutzers (null = keine).
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return int|null
		 */
		private function seconds_since_last_creation( int $user_id ): ?int {
			$last = $this->spaces->latest_created_at_for_owner( $user_id );
			if ( null === $last ) {
				return null;
			}

			$timestamp = strtotime( $last );
			if ( false === $timestamp ) {
				return null;
			}

			$now = function_exists( 'current_time' ) ? (int) current_time( 'timestamp' ) : time();
			return max( 0, $now - $timestamp );
		}

		/**
		 * Baut einen eindeutigen internen Gruppennamen.
		 *
		 * @param string $name        Raumname.
		 * @param int    $category_id Kategorie-ID (für Eindeutigkeit).
		 * @return string
		 */
		private function group_name( string $name, int $category_id ): string {
			return sprintf(
				/* translators: 1: Raumname, 2: Kategorie-ID */
				__( '%1$s (Raum #%2$d)', 'afspaces' ),
				$name,
				$category_id
			);
		}

		/**
		 * Bildet die Sichtbarkeit auf ein Asgaros-Kategorie-Zugriffslevel ab.
		 *
		 * @param string $visibility Sichtbarkeit.
		 * @return string
		 */
		private static function visibility_to_access( string $visibility ): string {
			return ( SpaceCreationSettings::VISIBILITY_PUBLIC === $visibility ) ? 'everyone' : 'loggedin';
		}
	}
}
