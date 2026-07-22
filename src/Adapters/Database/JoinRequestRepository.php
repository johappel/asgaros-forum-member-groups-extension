<?php
/**
 * Datenbank-Repository fuer Beitrittsanfragen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Database;

use AFSpaces\Domain\JoinRequest;

if ( ! class_exists( 'AFSpaces\\Adapters\\Database\\JoinRequestRepository' ) ) {

	/**
	 * Verwaltet Beitrittsanfragen inklusive Statuswechseln.
	 */
	class JoinRequestRepository {

		/**
		 * @var \wpdb
		 */
		private $db;

		private string $table;

		public function __construct() {
			global $wpdb;
			$this->db    = $wpdb;
			$this->table = $wpdb->prefix . 'afspaces_join_requests';
		}

		/**
		 * @return void
		 */
		public function install(): void {
			$charset = $this->db->get_charset_collate();
			$sql = "CREATE TABLE {$this->table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				space_id int unsigned NOT NULL,
				requester_user_id bigint(20) unsigned NOT NULL,
				request_message text NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				decider_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				decision_message text NOT NULL,
				approved_at datetime NULL,
				rejected_at datetime NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				KEY space_id (space_id),
				KEY requester_user_id (requester_user_id),
				KEY status (status)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		/**
		 * @param int $space_id Space-ID.
		 * @param int $requester_user_id Anfragender.
		 * @param string $message Nachricht.
		 * @return JoinRequest
		 */
		public function create( int $space_id, int $requester_user_id, string $message ): JoinRequest {
			$now = current_time( 'mysql' );
			$this->db->insert(
				$this->table,
				array(
					'space_id'           => $space_id,
					'requester_user_id'  => $requester_user_id,
					'request_message'    => $message,
					'status'             => JoinRequest::STATUS_PENDING,
					'decider_user_id'    => 0,
					'decision_message'   => '',
					'created_at'         => $now,
					'updated_at'         => $now,
				),
				array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
			);

			return $this->get_by_id( (int) $this->db->insert_id );
		}

		/**
		 * @param int $request_id Anfrage-ID.
		 * @return JoinRequest|null
		 */
		public function get_by_id( int $request_id ): ?JoinRequest {
			$row = $this->db->get_row(
				$this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d;", $request_id ),
				ARRAY_A
			);

			return $row ? new JoinRequest( $row ) : null;
		}

		/**
		 * @param int $space_id Space-ID.
		 * @param int $requester_user_id Benutzer-ID.
		 * @return JoinRequest|null
		 */
		public function find_pending_for_user( int $space_id, int $requester_user_id ): ?JoinRequest {
			$row = $this->db->get_row(
				$this->db->prepare(
					"SELECT * FROM {$this->table} WHERE space_id = %d AND requester_user_id = %d AND status = %s ORDER BY id DESC LIMIT 1;",
					$space_id,
					$requester_user_id,
					JoinRequest::STATUS_PENDING
				),
				ARRAY_A
			);

			return $row ? new JoinRequest( $row ) : null;
		}

		/**
		 * @param JoinRequest $request Modell.
		 * @return void
		 */
		public function save( JoinRequest $request ): void {
			$this->db->update(
				$this->table,
				array(
					'status'            => $request->status,
					'decider_user_id'   => $request->decider_user_id,
					'decision_message'  => $request->decision_message,
					'approved_at'       => '' !== $request->approved_at ? $request->approved_at : null,
					'rejected_at'       => '' !== $request->rejected_at ? $request->rejected_at : null,
					'updated_at'        => '' !== $request->updated_at ? $request->updated_at : current_time( 'mysql' ),
				),
				array( 'id' => $request->id ),
				array( '%s', '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		/**
		 * @param int $space_id Space-ID.
		 * @param string|null $status Optionaler Status.
		 * @return JoinRequest[]
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

			return array_map( static fn( $row ) => new JoinRequest( $row ), $rows );
		}

		/**
		 * @param int $requester_user_id Benutzer-ID.
		 * @param string|null $status Optionaler Status.
		 * @return JoinRequest[]
		 */
		public function list_for_requester( int $requester_user_id, ?string $status = null ): array {
			if ( null !== $status && '' !== $status ) {
				$rows = $this->db->get_results(
					$this->db->prepare(
						"SELECT * FROM {$this->table} WHERE requester_user_id = %d AND status = %s ORDER BY id DESC;",
						$requester_user_id,
						$status
					),
					ARRAY_A
				);
			} else {
				$rows = $this->db->get_results(
					$this->db->prepare( "SELECT * FROM {$this->table} WHERE requester_user_id = %d ORDER BY id DESC;", $requester_user_id ),
					ARRAY_A
				);
			}

			if ( empty( $rows ) ) {
				return array();
			}

			return array_map( static fn( $row ) => new JoinRequest( $row ), $rows );
		}
	}
}
