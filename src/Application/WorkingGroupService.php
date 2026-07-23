<?php
/**
 * Geschaeftslogik fuer sichtbare Arbeitsgruppen-Metadaten.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceMetaRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\SpacePolicy;
use AFSpaces\Domain\WorkingGroupMeta;

if ( ! class_exists( 'AFSpaces\\Application\\WorkingGroupService' ) ) {

	/**
	 * Validiert und speichert Arbeitsgruppen-Metadaten.
	 */
	class WorkingGroupService {

		private const DEFAULT_ICON = 'users';

		private SpaceRepository $spaces;
		private SpaceMetaRepository $meta;
		private AsgarosAdapterInterface $asgaros;
		private SpacePolicy $policy;
		private AuditRepository $audit;

		public function __construct(
			SpaceRepository $spaces,
			SpaceMetaRepository $meta,
			AsgarosAdapterInterface $asgaros,
			SpacePolicy $policy,
			AuditRepository $audit
		) {
			$this->spaces = $spaces;
			$this->meta = $meta;
			$this->asgaros = $asgaros;
			$this->policy = $policy;
			$this->audit = $audit;
		}

		/**
		 * @return array<string,string>
		 */
		public static function icon_options(): array {
			return array(
				'users'      => __( 'Menschen', 'afspaces' ),
				'comments'   => __( 'Gespräch', 'afspaces' ),
				'book'       => __( 'Wissen', 'afspaces' ),
				'briefcase'  => __( 'Organisation', 'afspaces' ),
				'lightbulb'  => __( 'Ideen', 'afspaces' ),
			);
		}

		/**
		 * @param string $icon Icon-Schluessel.
		 * @return string
		 */
		public static function icon_class( string $icon ): string {
			$map = array(
				'users'     => 'fas fa-users',
				'comments'  => 'fas fa-comments',
				'book'      => 'fas fa-book',
				'briefcase' => 'fas fa-briefcase',
				'lightbulb' => 'fas fa-lightbulb',
			);

			return $map[ $icon ] ?? $map[ self::DEFAULT_ICON ];
		}

		/**
		 * @param int $space_id Space-ID.
		 * @return WorkingGroupMeta
		 */
		public function get_metadata( int $space_id ): WorkingGroupMeta {
			return $this->meta->get_for_space( $space_id );
		}

		/**
		 * @param int $space_id Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param array<string,mixed> $input Formulardaten.
		 * @return WorkingGroupMeta
		 */
		public function save_metadata( int $space_id, int $actor_user_id, array $input ): WorkingGroupMeta {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Die Arbeitsgruppe existiert nicht.', 'afspaces' ) );
			}

			if ( ! $this->policy->can_manage( $space_id, $actor_user_id ) ) {
				throw new DomainException( __( 'Du darfst diese Arbeitsgruppe nicht bearbeiten.', 'afspaces' ) );
			}

			$meta = new WorkingGroupMeta(
				array(
					'space_id'              => $space_id,
					'description'           => $this->sanitize_textarea( $input['description'] ?? '' ),
					'accent_color'          => $this->sanitize_hex_color( (string) ( $input['accent_color'] ?? '' ) ),
					'icon'                  => $this->sanitize_icon( (string) ( $input['icon'] ?? '' ) ),
					'contact_text'          => $this->sanitize_textarea( $input['contact_text'] ?? '' ),
					'directory_visibility'  => $this->sanitize_directory_visibility( (string) ( $input['directory_visibility'] ?? '' ) ),
					'join_policy'           => $this->sanitize_join_policy( (string) ( $input['join_policy'] ?? '' ) ),
					'join_requests_enabled' => ! empty( $input['join_requests_enabled'] ),
					'topic_ids'             => $this->sanitize_topic_ids( $input['topic_ids'] ?? array() ),
				)
			);

			$this->meta->save( $meta );
			$this->audit->log( $space_id, $actor_user_id, $actor_user_id, 'working_group_meta_updated', 'working_group' );

			return $meta;
		}

		/**
		 * @return array<int,array<string,mixed>>
		 */
		public function list_topics(): array {
			$taxonomy = $this->topics_taxonomy();
			if ( ! function_exists( '\taxonomy_exists' ) || ! taxonomy_exists( $taxonomy ) ) {
				return array();
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			if ( ! is_array( $terms ) ) {
				return array();
			}

			return array_map(
				static fn( $term ): array => array(
					'id'   => (int) $term->term_id,
					'name' => (string) $term->name,
				),
				$terms
			);
		}

		/**
		 * @param WorkingGroupMeta $meta Metadaten.
		 * @return string[]
		 */
		public function topic_names( WorkingGroupMeta $meta ): array {
			if ( empty( $meta->topic_ids ) ) {
				return array();
			}

			$topic_map = array();
			foreach ( $this->list_topics() as $topic ) {
				$topic_map[ (int) $topic['id'] ] = (string) $topic['name'];
			}

			$names = array();
			foreach ( $meta->topic_ids as $topic_id ) {
				if ( isset( $topic_map[ $topic_id ] ) ) {
					$names[] = $topic_map[ $topic_id ];
				}
			}

			return $names;
		}

		/**
		 * @param WorkingGroupMeta $meta Metadaten.
		 * @param bool $viewer_is_member Mitglied?
		 * @param bool $viewer_is_manager Verantwortlich?
		 * @param bool $viewer_is_subject Eigenes Profil?
		 * @return bool
		 */
		public function can_view_group( WorkingGroupMeta $meta, bool $viewer_is_member, bool $viewer_is_manager, bool $viewer_is_subject = false ): bool {
			return $this->policy->can_view_working_group( $meta, $viewer_is_member, $viewer_is_manager, $viewer_is_subject );
		}

		/**
		 * @param WorkingGroupMeta $meta Metadaten.
		 * @param bool $actor_is_member Mitglied?
		 * @param bool $actor_is_manager Verantwortlich?
		 * @param bool $has_pending_request Offene Anfrage?
		 * @param bool $has_open_invitation Offene Einladung?
		 * @return bool
		 */
		public function can_request_join( WorkingGroupMeta $meta, bool $actor_is_member, bool $actor_is_manager, bool $has_pending_request = false, bool $has_open_invitation = false ): bool {
			return $this->policy->can_request_to_join( $meta, $actor_is_member, $actor_is_manager, $has_pending_request, $has_open_invitation );
		}

		/**
		 * @param int $space_id Space-ID.
		 * @return array<int,array<string,mixed>>
		 */
		public function list_responsibles( int $space_id ): array {
			$responsibles = array();
			foreach ( $this->spaces->get_managers( $space_id ) as $manager ) {
				$user = function_exists( '\get_userdata' ) ? get_userdata( $manager->user_id ) : null;
				if ( ! $user ) {
					continue;
				}

				$responsibles[] = array(
					'user_id'      => (int) $user->ID,
					'display_name' => (string) $user->display_name,
					'role'         => (string) $manager->role,
					'role_label'   => 'owner' === $manager->role
						? __( 'Owner', 'afspaces' )
						: __( 'Arbeitsgruppenverantwortlich', 'afspaces' ),
				);
			}

			return $responsibles;
		}

		/**
		 * @return string
		 */
		public function topics_taxonomy(): string {
			$taxonomy = 'themen';
			if ( function_exists( '\apply_filters' ) ) {
				$taxonomy = (string) apply_filters( 'afspaces_working_group_topics_taxonomy', $taxonomy );
			}

			return $taxonomy;
		}

		/**
		 * @param mixed $value Rohwert.
		 * @return string
		 */
		private function sanitize_textarea( $value ): string {
			$value = is_scalar( $value ) ? (string) $value : '';
			if ( function_exists( '\sanitize_textarea_field' ) ) {
				return sanitize_textarea_field( $value );
			}

			return trim( strip_tags( $value ) );
		}

		/**
		 * @param string $value Farbe.
		 * @return string
		 */
		private function sanitize_hex_color( string $value ): string {
			$value = trim( $value );
			if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) {
				return strtolower( $value );
			}

			return (string) WorkingGroupMeta::defaults()['accent_color'];
		}

		/**
		 * @param string $value Icon.
		 * @return string
		 */
		private function sanitize_icon( string $value ): string {
			$value = function_exists( '\sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-z0-9_-]/', '', $value ) );
			return array_key_exists( $value, self::icon_options() ) ? $value : self::DEFAULT_ICON;
		}

		/**
		 * @param string $value Sichtbarkeit.
		 * @return string
		 */
		private function sanitize_directory_visibility( string $value ): string {
			$allowed = array(
				WorkingGroupMeta::DIRECTORY_LISTED,
				WorkingGroupMeta::DIRECTORY_MEMBERS,
				WorkingGroupMeta::DIRECTORY_HIDDEN,
			);

			return in_array( $value, $allowed, true ) ? $value : WorkingGroupMeta::DIRECTORY_LISTED;
		}

		/**
		 * @param string $value Beitritt.
		 * @return string
		 */
		private function sanitize_join_policy( string $value ): string {
			$allowed = array(
				WorkingGroupMeta::JOIN_POLICY_REQUEST,
				WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY,
				WorkingGroupMeta::JOIN_POLICY_CLOSED,
			);

			return in_array( $value, $allowed, true ) ? $value : WorkingGroupMeta::JOIN_POLICY_REQUEST;
		}

		/**
		 * @param mixed $value Themen.
		 * @return int[]
		 */
		private function sanitize_topic_ids( $value ): array {
			$topic_ids = is_array( $value ) ? array_values( array_map( 'intval', $value ) ) : array();
			$topic_ids = array_values( array_unique( array_filter( $topic_ids, static fn( int $id ): bool => $id > 0 ) ) );

			$taxonomy = $this->topics_taxonomy();
			if ( empty( $topic_ids ) || ! function_exists( '\taxonomy_exists' ) || ! taxonomy_exists( $taxonomy ) ) {
				return array();
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'include'    => $topic_ids,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);

			$valid_ids = is_array( $terms ) ? array_map( 'intval', $terms ) : array();
			sort( $valid_ids );
			$check_ids = $topic_ids;
			sort( $check_ids );

			if ( $valid_ids !== $check_ids ) {
				throw new DomainException( __( 'Mindestens ein ausgewaehltes Thema ist ungueltig.', 'afspaces' ) );
			}

			return $topic_ids;
		}
	}
}