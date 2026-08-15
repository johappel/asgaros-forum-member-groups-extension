<?php
/**
 * Schreibschutz für geschützte Arbeitsgruppen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\SpaceCreationSettings;

if ( ! class_exists( 'AFSpaces\\Application\\ForumContentWritePolicy' ) ) {

	/** Verhindert Schreibzugriff durch reine Leseberechtigung. */
	final class ForumContentWritePolicy {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;

		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros ) {
			$this->spaces  = $spaces;
			$this->asgaros = $asgaros;
		}

		/** Registriert die serverseitigen Asgaros-Prüfungen. */
		public function init(): void {
			add_filter( 'asgarosforum_filter_insert_custom_validation', array( $this, 'validate_submission' ) );
			add_filter( 'asgarosforum_filter_check_access', array( $this, 'validate_editor_access' ), 10, 2 );
		}

		/**
		 * Reine Fachprüfung, getrennt von Asgaros-Requestdetails.
		 *
		 * @param string $visibility Sichtbarkeitswert.
		 * @param bool   $is_member Mitglied der Arbeitsgruppe.
		 * @param bool   $is_moderator Globale Asgaros-Moderation.
		 * @return bool
		 */
		public static function can_write( string $visibility, bool $is_member, bool $is_moderator ): bool {
			if ( in_array( $visibility, array( SpaceCreationSettings::VISIBILITY_PRIVATE, SpaceCreationSettings::VISIBILITY_PROTECTED ), true ) ) {
				return $is_member || $is_moderator;
			}

			return true;
		}

		/**
		 * Blockiert das Einfügen von Themen und Beiträgen für Nichtmitglieder.
		 *
		 * @param mixed $allowed Bisherige Asgaros-Entscheidung.
		 * @return bool
		 */
		public function validate_submission( $allowed ): bool {
			if ( ! $allowed ) {
				return false;
			}

			$action = isset( $_POST['submit_action'] ) && is_scalar( $_POST['submit_action'] )
				? sanitize_key( (string) wp_unslash( $_POST['submit_action'] ) )
				: '';
			if ( ! in_array( $action, array( 'add_topic', 'add_post' ), true ) ) {
				return true;
			}

			return $this->can_current_user_write();
		}

		/**
		 * Verhindert auch den direkten Aufruf des Asgaros-Editors.
		 * Lesen der Themenansicht bleibt davon unberührt.
		 *
		 * @param mixed $allowed Bisherige Asgaros-Entscheidung.
		 * @param int   $category_id Kategorie-ID.
		 * @return bool
		 */
		public function validate_editor_access( $allowed, int $category_id ): bool {
			$view = $this->current_view();
			if ( ! in_array( $view, array( 'addtopic', 'addpost' ), true ) ) {
				return (bool) $allowed;
			}

			return (bool) $allowed && $this->can_current_user_write();
		}

		private function can_current_user_write(): bool {
			$user_id = get_current_user_id();
			$forum_id = $this->asgaros->get_current_forum_id();
			if ( $forum_id < 1 ) {
				// Schreibanfragen ohne auflösbaren Forumkontext werden sicher
				// abgewiesen; normale, nicht von AFSpaces verwaltete Foren mit
				// gültiger Forum-ID bleiben von der Policy unberührt.
				return false;
			}

			$space = $this->spaces->get_space_by_forum( $forum_id );
			if ( ! $space ) {
				return true;
			}

			return self::can_write(
				$space->visibility,
				$this->asgaros->is_user_in_group( $user_id, $space->primary_group_id ),
				$this->asgaros->is_forum_moderator( $user_id )
			);
		}

		private function current_view(): string {
			return $this->asgaros->get_current_view();
		}
	}
}
