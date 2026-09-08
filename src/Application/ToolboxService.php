<?php
/**
 * Anwendungsdienst für den Werkzeugkasten (Toolbox) einer Arbeitsgruppe.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceLinkRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\SpaceLink;
use AFSpaces\Domain\SpacePolicy;

if ( ! class_exists( 'AFSpaces\\Application\\ToolboxService' ) ) {

	/**
	 * Kapselt Lese- und Verwaltungslogik für Toolbox-Links.
	 *
	 * Die Rechteprüfung nutzt ausschließlich die bestehende
	 * {@see SpacePolicy}, es wird keine eigene Rollenlogik eingeführt.
	 */
	class ToolboxService {

		private const MAX_TITLE_LENGTH       = 200;
		private const MAX_DESCRIPTION_LENGTH = 500;

		private SpaceRepository $spaces;
		private SpaceLinkRepository $links;
		private SpacePolicy $policy;
		private AuditRepository $audit;

		/**
		 * Konstruktor.
		 */
		public function __construct( SpaceRepository $spaces, SpaceLinkRepository $links, SpacePolicy $policy, AuditRepository $audit ) {
			$this->spaces = $spaces;
			$this->links  = $links;
			$this->policy = $policy;
			$this->audit  = $audit;
		}

		/**
		 * Ermittelt den Space anhand einer Forum-ID (Primär- oder Zusatzforum).
		 *
		 * @param int $forum_id Asgaros-Forum-ID.
		 * @return \AFSpaces\Domain\Space|null
		 */
		public function resolve_space_by_forum( int $forum_id ) {
			if ( $forum_id < 1 ) {
				return null;
			}

			$space = $this->spaces->get_space_by_forum( $forum_id );
			if ( ! $space || 'active' !== $space->status ) {
				return null;
			}

			return $space;
		}

		/**
		 * Gibt die Links einer Arbeitsgruppe in Anzeigereihenfolge zurück.
		 *
		 * @param int $space_id Space-ID.
		 * @return SpaceLink[]
		 */
		public function list_links( int $space_id ): array {
			return $this->links->list_for_space( $space_id );
		}

		/**
		 * Legt einen neuen Link an.
		 *
		 * @param int                 $space_id Space-ID.
		 * @param int                 $actor    Handelnde Benutzer-ID.
		 * @param array<string,mixed> $input    Roh-Eingaben.
		 * @return int Neue Link-ID.
		 *
		 * @throws DomainException Bei fehlender Berechtigung oder ungültiger Eingabe.
		 */
		public function add_link( int $space_id, int $actor, array $input ): int {
			$this->require_manage( $space_id, $actor );
			$data = $this->validate_input( $input );

			$link = new SpaceLink(
				array(
					'space_id'     => $space_id,
					'title'        => $data['title'],
					'url'          => $data['url'],
					'description'  => $data['description'],
					'icon'         => $data['icon'],
					'open_new_tab' => $data['open_new_tab'],
					'sort_order'   => $this->links->max_sort_order( $space_id ) + 1,
				)
			);

			$link_id = $this->links->create( $link );
			$this->audit->log( $space_id, $actor, 0, 'toolbox_link_created', 'toolbox_link' );

			return $link_id;
		}

		/**
		 * Aktualisiert einen bestehenden Link.
		 *
		 * @param int                 $space_id Space-ID.
		 * @param int                 $actor    Handelnde Benutzer-ID.
		 * @param int                 $link_id  Link-ID.
		 * @param array<string,mixed> $input    Roh-Eingaben.
		 * @return void
		 *
		 * @throws DomainException Bei fehlender Berechtigung oder ungültiger Eingabe.
		 */
		public function update_link( int $space_id, int $actor, int $link_id, array $input ): void {
			$this->require_manage( $space_id, $actor );
			$existing = $this->require_link_in_space( $space_id, $link_id );
			$data     = $this->validate_input( $input );

			$link = new SpaceLink(
				array(
					'id'           => $existing->id,
					'space_id'     => $space_id,
					'title'        => $data['title'],
					'url'          => $data['url'],
					'description'  => $data['description'],
					'icon'         => $data['icon'],
					'open_new_tab' => $data['open_new_tab'],
					'sort_order'   => $existing->sort_order,
				)
			);

			$this->links->update( $link );
			$this->audit->log( $space_id, $actor, 0, 'toolbox_link_updated', 'toolbox_link' );
		}

		/**
		 * Löscht einen Link.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $actor    Handelnde Benutzer-ID.
		 * @param int $link_id  Link-ID.
		 * @return void
		 *
		 * @throws DomainException Bei fehlender Berechtigung.
		 */
		public function delete_link( int $space_id, int $actor, int $link_id ): void {
			$this->require_manage( $space_id, $actor );
			$this->require_link_in_space( $space_id, $link_id );

			$this->links->delete( $link_id );
			$this->audit->log( $space_id, $actor, 0, 'toolbox_link_deleted', 'toolbox_link' );
		}

		/**
		 * Setzt die Reihenfolge der Links neu.
		 *
		 * @param int   $space_id    Space-ID.
		 * @param int   $actor       Handelnde Benutzer-ID.
		 * @param int[] $ordered_ids Link-IDs in gewünschter Reihenfolge.
		 * @return void
		 *
		 * @throws DomainException Bei fehlender Berechtigung.
		 */
		public function reorder_links( int $space_id, int $actor, array $ordered_ids ): void {
			$this->require_manage( $space_id, $actor );

			$valid_ids = array();
			foreach ( $this->links->list_for_space( $space_id ) as $link ) {
				$valid_ids[ $link->id ] = true;
			}

			$position = 1;
			foreach ( $ordered_ids as $raw_id ) {
				$link_id = (int) $raw_id;
				if ( $link_id < 1 || ! isset( $valid_ids[ $link_id ] ) ) {
					continue;
				}
				$this->links->set_sort_order( $link_id, $position );
				$position++;
			}

			$this->audit->log( $space_id, $actor, 0, 'toolbox_links_reordered', 'toolbox_link' );
		}

		/**
		 * Verschiebt einen Link um eine Position nach oben oder unten.
		 *
		 * @param int    $space_id  Space-ID.
		 * @param int    $actor     Handelnde Benutzer-ID.
		 * @param int    $link_id   Link-ID.
		 * @param string $direction 'up' oder 'down'.
		 * @return void
		 *
		 * @throws DomainException Bei fehlender Berechtigung.
		 */
		public function move_link( int $space_id, int $actor, int $link_id, string $direction ): void {
			$this->require_manage( $space_id, $actor );

			$links = $this->links->list_for_space( $space_id );
			$order = array();
			foreach ( $links as $link ) {
				$order[] = $link->id;
			}

			$index = array_search( $link_id, $order, true );
			if ( false === $index ) {
				return;
			}

			$swap_with = 'up' === $direction ? $index - 1 : $index + 1;
			if ( $swap_with < 0 || $swap_with >= count( $order ) ) {
				return;
			}

			$tmp                 = $order[ $index ];
			$order[ $index ]     = $order[ $swap_with ];
			$order[ $swap_with ] = $tmp;

			$this->reorder_links( $space_id, $actor, $order );
		}

		/**
		 * Stellt sicher, dass der Akteur die Arbeitsgruppe verwalten darf.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $actor    Benutzer-ID.
		 * @return void
		 *
		 * @throws DomainException
		 */
		private function require_manage( int $space_id, int $actor ): void {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Arbeitsgruppe nicht gefunden.', 'afspaces' ) );
			}

			if ( $actor < 1 || ! $this->policy->can_manage( $space_id, $actor ) ) {
				throw new DomainException( __( 'Du darfst den Werkzeugkasten dieser Arbeitsgruppe nicht verwalten.', 'afspaces' ) );
			}
		}

		/**
		 * Prüft, dass ein Link zur angegebenen Arbeitsgruppe gehört.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $link_id  Link-ID.
		 * @return SpaceLink
		 *
		 * @throws DomainException
		 */
		private function require_link_in_space( int $space_id, int $link_id ): SpaceLink {
			$link = $this->links->get_link( $link_id );
			if ( ! $link || $link->space_id !== $space_id ) {
				throw new DomainException( __( 'Der Link gehört nicht zu dieser Arbeitsgruppe.', 'afspaces' ) );
			}

			return $link;
		}

		/**
		 * Validiert und normalisiert die Link-Eingaben.
		 *
		 * @param array<string,mixed> $input Roh-Eingaben.
		 * @return array{title:string,url:string,description:string,icon:string,open_new_tab:bool}
		 *
		 * @throws DomainException Bei ungültiger Eingabe.
		 */
		private function validate_input( array $input ): array {
			$title = SpaceLink::sanitize_title( (string) ( $input['title'] ?? '' ) );
			if ( '' === $title ) {
				throw new DomainException( __( 'Bitte gib einen Titel für den Link an.', 'afspaces' ) );
			}
			if ( mb_strlen( $title ) > self::MAX_TITLE_LENGTH ) {
				$title = mb_substr( $title, 0, self::MAX_TITLE_LENGTH );
			}

			$url = SpaceLink::sanitize_url( (string) ( $input['url'] ?? '' ) );
			if ( '' === $url ) {
				throw new DomainException( __( 'Bitte gib eine gültige Webadresse (http oder https) an.', 'afspaces' ) );
			}

			$description = trim( (string) ( $input['description'] ?? '' ) );
			if ( function_exists( 'sanitize_textarea_field' ) ) {
				$description = (string) sanitize_textarea_field( $description );
			}
			if ( mb_strlen( $description ) > self::MAX_DESCRIPTION_LENGTH ) {
				$description = mb_substr( $description, 0, self::MAX_DESCRIPTION_LENGTH );
			}

			return array(
				'title'        => $title,
				'url'          => $url,
				'description'  => $description,
				'icon'         => SpaceLink::sanitize_icon( (string) ( $input['icon'] ?? '' ) ),
				'open_new_tab' => ! empty( $input['open_new_tab'] ),
			);
		}
	}
}
