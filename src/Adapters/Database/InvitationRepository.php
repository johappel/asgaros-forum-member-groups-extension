<?php
/**
 * Datenbank-Repository für persönliche Einladungen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Database;

use AFSpaces\Domain\Invitation;

if ( ! class_exists( 'AFSpaces\\Adapters\\Database\\InvitationRepository' ) ) {

	/**
	 * Verwaltet Einladungen inklusive Statuswechseln.
	 */
	class InvitationRepository {

		/**
		 * @var \wpdb
		 */
		private $db;

		/**
		 * @var string
		 */
		private string $table;

		/**
		 * Konstruktor.
		 */
		public function __construct() {
			global $wpdb;
			$this->db    = $wpdb;
			$this->table = $wpdb->prefix . 'afspaces_invitations';
		}

		/**
		 * Erstellt die Tabelle.
		 *
		 * @return void
		 */
		public function install(): void {
			$charset = $this->db->get_charset_collate();
			$sql = "CREATE TABLE {$this->table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				space_id int unsigned NOT NULL,
				inviter_user_id bigint(20) unsigned NOT NULL,
				invitee_user_id bigint(20) unsigned NOT NULL,
				message text NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				expires_at datetime NOT NULL,
				accepted_at datetime NULL,
				declined_at datetime NULL,
				revoked_at datetime NULL,
				last_sent_at datetime NULL,
				send_count int unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				KEY space_id (space_id),
				KEY invitee_user_id (invitee_user_id),
				KEY status (status),
				KEY expires_at (expires_at)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		/**
		 * Legt eine neue Einladung an.
		 *
		 * @param int    $space_id Space-ID.
		 * @param int    $inviter_user_id Einladender.
		 * @param int    $invitee_user_id Eingeladener.
		 * @param string $message Nachricht.
		 * @param string $expires_at Ablaufzeitpunkt.
		 * @return Invitation
		 */
		public function create(
			int $space_id,
			int $inviter_user_id,
			int $invitee_user_id,
			string $message,
			string $expires_at
		): Invitation {
			$now = current_time( 'mysql' );
			$this->db->insert(
				$this->table,
				array(
					'space_id'        => $space_id,
					'inviter_user_id' => $inviter_user_id,
					'invitee_user_id' => $invitee_user_id,
					'message'         => $message,
					'status'          => Invitation::STATUS_PENDING,
					'expires_at'      => $expires_at,
					'created_at'      => $now,
					'updated_at'      => $now,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);

			return $this->get_by_id( (int) $this->db->insert_id );
		}

		/**
		 * Gibt eine Einladung per ID zurück.
		 *
		 * @param int $invitation_id ID.
		 * @return Invitation|null
		 */
		public function get_by_id( int $invitation_id ): ?Invitation {
			$row = $this->db->get_row(
				$this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d;", $invitation_id ),
				ARRAY_A
			);
			return $row ? new Invitation( $row ) : null;
		}

		/**
		 * Gibt eine offene Einladung für Space/Zielbenutzer zurück.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $invitee_user_id Zielbenutzer.
		 * @return Invitation|null
		 */
		public function find_pending_for_user( int $space_id, int $invitee_user_id ): ?Invitation {
			$row = $this->db->get_row(
				$this->db->prepare(
					"SELECT * FROM {$this->table} WHERE space_id = %d AND invitee_user_id = %d AND status = %s ORDER BY id DESC LIMIT 1;",
					$space_id,
					$invitee_user_id,
					Invitation::STATUS_PENDING
				),
				ARRAY_A
			);
			return $row ? new Invitation( $row ) : null;
		}

		/**
		 * Aktualisiert Nachricht und Ablauf einer Einladung.
		 *
		 * @param int    $invitation_id Einladung.
		 * @param string $message Nachricht.
		 * @param string $expires_at Ablauf.
		 * @return Invitation|null
		 */
		public function update_pending_details( int $invitation_id, string $message, string $expires_at ): ?Invitation {
			$this->db->update(
				$this->table,
				array(
					'message'    => $message,
					'expires_at' => $expires_at,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $invitation_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			return $this->get_by_id( $invitation_id );
		}

		/**
		 * Persistiert den kompletten Statuszustand.
		 *
		 * @param Invitation $invitation Modell.
		 * @return void
		 */
		public function save( Invitation $invitation ): void {
			$this->db->update(
				$this->table,
				array(
					'status'      => $invitation->status,
					'expires_at'  => $invitation->expires_at,
					'accepted_at' => '' !== $invitation->accepted_at ? $invitation->accepted_at : null,
					'declined_at' => '' !== $invitation->declined_at ? $invitation->declined_at : null,
					'revoked_at'  => '' !== $invitation->revoked_at ? $invitation->revoked_at : null,
					'updated_at'  => '' !== $invitation->updated_at ? $invitation->updated_at : current_time( 'mysql' ),
				),
				array( 'id' => $invitation->id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		/**
		 * Erhöht den Versandzähler.
		 *
		 * @param int $invitation_id Einladung.
		 * @return void
		 */
		public function mark_sent( int $invitation_id ): void {
			$now = current_time( 'mysql' );
			$this->db->query(
				$this->db->prepare(
					"UPDATE {$this->table} SET send_count = send_count + 1, last_sent_at = %s, updated_at = %s WHERE id = %d;",
					$now,
					$now,
					$invitation_id
				)
			);
		}

		/**
		 * Markiert abgelaufene Einladungen explizit als expired.
		 *
		 * @return int Anzahl aktualisierter Datensätze.
		 */
		public function expire_pending(): int {
			$now = current_time( 'mysql' );
			$result = $this->db->query(
				$this->db->prepare(
					"UPDATE {$this->table} SET status = %s, updated_at = %s WHERE status = %s AND expires_at < %s;",
					Invitation::STATUS_EXPIRED,
					$now,
					Invitation::STATUS_PENDING,
					$now
				)
			);
			return is_int( $result ) ? $result : 0;
		}

		/**
		 * Listet Einladungen eines Spaces.
		 *
		 * @param int         $space_id Space-ID.
		 * @param string|null $status Optionaler Statusfilter.
		 * @return Invitation[]
		 */
		public function list_for_space( int $space_id, ?string $status = null ): array {
			if ( null !== $status && '' !== $status ) {
				$rows = $this->db->get_results(
					$this->db->prepare(
						"SELECT * FROM {$this->table} WHERE space_id = %d AND status = %s ORDER BY id DESC;",
						$space_id,
						$status
					),
					ARRAY_A
				);
			} else {
				$rows = $this->db->get_results(
					$this->db->prepare( "SELECT * FROM {$this->table} WHERE space_id = %d ORDER BY id DESC;", $space_id ),
					ARRAY_A
				);
			}

			if ( empty( $rows ) ) {
				return array();
			}

			return array_map( static fn( $row ) => new Invitation( $row ), $rows );
		}

		/**
		 * Listet Einladungen eines Benutzers.
		 *
		 * @param int         $invitee_user_id Benutzer-ID.
		 * @param string|null $status Optionaler Status.
		 * @return Invitation[]
		 */
		public function list_for_invitee( int $invitee_user_id, ?string $status = null ): array {
			if ( null !== $status && '' !== $status ) {
				$rows = $this->db->get_results(
					$this->db->prepare(
						"SELECT * FROM {$this->table} WHERE invitee_user_id = %d AND status = %s ORDER BY id DESC;",
						$invitee_user_id,
						$status
					),
					ARRAY_A
				);
			} else {
				$rows = $this->db->get_results(
					$this->db->prepare( "SELECT * FROM {$this->table} WHERE invitee_user_id = %d ORDER BY id DESC;", $invitee_user_id ),
					ARRAY_A
				);
			}

			if ( empty( $rows ) ) {
				return array();
			}

			return array_map( static fn( $row ) => new Invitation( $row ), $rows );
		}

		/**
		 * Löscht personenbezogene Nachrichtendaten eines Benutzers.
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return int Anzahl geänderter Datensätze.
		 */
		public function erase_personal_messages_for_user( int $user_id ): int {
			$result = $this->db->query(
				$this->db->prepare(
					"UPDATE {$this->table} SET message = '' WHERE inviter_user_id = %d OR invitee_user_id = %d;",
					$user_id,
					$user_id
				)
			);
			return is_int( $result ) ? $result : 0;
		}
	}
}
