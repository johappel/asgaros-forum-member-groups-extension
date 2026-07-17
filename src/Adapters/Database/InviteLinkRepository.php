<?php
/**
 * Datenbank-Repository für widerrufbare Invite-Links.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Database;

use AFSpaces\Domain\InviteLink;

if ( ! class_exists( 'AFSpaces\\Adapters\\Database\\InviteLinkRepository' ) ) {

	/**
	 * Verwaltet Invite-Links und deren Nutzungszähler.
	 */
	class InviteLinkRepository {

		/**
		 * @var \wpdb
		 */
		private $db;

		private string $table;

		public function __construct() {
			global $wpdb;
			$this->db    = $wpdb;
			$this->table = $wpdb->prefix . 'afspaces_invite_links';
		}

		/**
		 * @return void
		 */
		public function install(): void {
			$charset = $this->db->get_charset_collate();
			$sql = "CREATE TABLE {$this->table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				space_id int unsigned NOT NULL,
				creator_user_id bigint(20) unsigned NOT NULL,
				token_hash char(64) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				approval_mode varchar(30) NOT NULL DEFAULT 'auto_join',
				max_uses int unsigned NOT NULL DEFAULT 1,
				use_count int unsigned NOT NULL DEFAULT 0,
				allow_registration tinyint(1) NOT NULL DEFAULT 0,
				expires_at datetime NOT NULL,
				revoked_at datetime NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				UNIQUE KEY token_hash (token_hash),
				KEY space_id (space_id),
				KEY status (status),
				KEY expires_at (expires_at)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		/**
		 * @return InviteLink
		 */
		public function create(
			int $space_id,
			int $creator_user_id,
			string $token_hash,
			string $approval_mode,
			int $max_uses,
			bool $allow_registration,
			string $expires_at
		): InviteLink {
			$now = current_time( 'mysql' );
			$this->db->insert(
				$this->table,
				array(
					'space_id'           => $space_id,
					'creator_user_id'    => $creator_user_id,
					'token_hash'         => $token_hash,
					'status'             => InviteLink::STATUS_ACTIVE,
					'approval_mode'      => $approval_mode,
					'max_uses'           => $max_uses,
					'allow_registration' => $allow_registration ? 1 : 0,
					'expires_at'         => $expires_at,
					'created_at'         => $now,
					'updated_at'         => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
			);

			return $this->get_by_id( (int) $this->db->insert_id );
		}

		/**
		 * @param int $link_id ID.
		 * @return InviteLink|null
		 */
		public function get_by_id( int $link_id ): ?InviteLink {
			$row = $this->db->get_row(
				$this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d;", $link_id ),
				ARRAY_A
			);

			return $row ? new InviteLink( $row ) : null;
		}

		/**
		 * @param string $token_hash Hash.
		 * @return InviteLink|null
		 */
		public function get_by_token_hash( string $token_hash ): ?InviteLink {
			$row = $this->db->get_row(
				$this->db->prepare( "SELECT * FROM {$this->table} WHERE token_hash = %s LIMIT 1;", $token_hash ),
				ARRAY_A
			);

			return $row ? new InviteLink( $row ) : null;
		}

		/**
		 * @param int $space_id Space-ID.
		 * @return InviteLink[]
		 */
		public function list_for_space( int $space_id ): array {
			$rows = $this->db->get_results(
				$this->db->prepare( "SELECT * FROM {$this->table} WHERE space_id = %d ORDER BY created_at DESC;", $space_id ),
				ARRAY_A
			);

			if ( ! $rows ) {
				return array();
			}

			return array_map(
				static fn( array $row ): InviteLink => new InviteLink( $row ),
				(array) $rows
			);
		}

		/**
		 * @param InviteLink $link Modell.
		 * @return void
		 */
		public function save( InviteLink $link ): void {
			$this->db->update(
				$this->table,
				array(
					'status'             => $link->status,
					'approval_mode'      => $link->approval_mode,
					'max_uses'           => $link->max_uses,
					'use_count'          => $link->use_count,
					'allow_registration' => $link->allow_registration ? 1 : 0,
					'expires_at'         => $link->expires_at,
					'revoked_at'         => '' !== $link->revoked_at ? $link->revoked_at : null,
					'updated_at'         => '' !== $link->updated_at ? $link->updated_at : current_time( 'mysql' ),
				),
				array( 'id' => $link->id ),
				array( '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		/**
		 * Erhöht den Nutzungszähler atomar, sofern der Link noch verwendbar ist.
		 *
		 * @param int $link_id Link-ID.
		 * @return bool
		 */
		public function claim_use( int $link_id ): bool {
			$now = current_time( 'mysql' );
			$result = $this->db->query(
				$this->db->prepare(
					"UPDATE {$this->table}
					SET use_count = use_count + 1, updated_at = %s
					WHERE id = %d
						AND status = %s
						AND expires_at >= %s
						AND ( max_uses = 0 OR use_count < max_uses );",
					$now,
					$link_id,
					InviteLink::STATUS_ACTIVE,
					$now
				)
			);

			return 1 === (int) $result;
		}
	}
}