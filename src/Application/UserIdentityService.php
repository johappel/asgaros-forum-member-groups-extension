<?php
/**
 * Zentrale, erweiterbare Auflösung sichtbarer WordPress-Benutzeridentitäten.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

if ( ! class_exists( 'AFSpaces\\Application\\UserIdentityService' ) ) {

	/**
	 * Trennt die technische WordPress-User-ID von Name, Avatar und Suche.
	 */
	class UserIdentityService {

		/**
		 * @param int $user_id WordPress-Benutzer-ID.
		 * @return array{user_id:int,display_name:string,user_login:string,avatar_url:string}|null
		 */
		public function get_identity( int $user_id ): ?array {
			$user = $this->get_user( $user_id );
			if ( ! $user ) {
				return null;
			}

			return array(
				'user_id'      => (int) $user->ID,
				'display_name' => $this->display_name_for_user( $user ),
				'user_login'   => (string) ( $user->user_login ?? '' ),
				'avatar_url'   => $this->avatar_url_for_user( (int) $user->ID, 40 ),
			);
		}

		/**
		 * @param int $user_id WordPress-Benutzer-ID.
		 * @return string
		 */
		public function get_display_name( int $user_id ): string {
			$user = $this->get_user( $user_id );
			return $user ? $this->display_name_for_user( $user ) : '';
		}

		/**
		 * Prüft die technische Existenz eines WordPress-Benutzers, ohne eine
		 * sichtbare Identität oder einen Avatar aufzulösen.
		 *
		 * @param int $user_id WordPress-Benutzer-ID.
		 * @return bool
		 */
		public function user_exists( int $user_id ): bool {
			return false !== $this->get_user( $user_id );
		}

		/**
		 * @param int $user_id WordPress-Benutzer-ID.
		 * @param int $size Gewünschte Bildgröße.
		 * @return string
		 */
		public function get_avatar_url( int $user_id, int $size = 40 ): string {
			return $this->avatar_url_for_user( $user_id, $size );
		}

		/**
		 * Erzeugt sicheres Avatar-Markup aus der gefilterten URL.
		 *
		 * @param int                  $user_id WordPress-Benutzer-ID.
		 * @param int                  $size    Bildgröße.
		 * @param array<string,mixed>  $args    Zusätzliche Darstellungsoptionen.
		 * @return string
		 */
		public function get_avatar_html( int $user_id, int $size = 40, array $args = array() ): string {
			$url = $this->get_avatar_url( $user_id, $size );
			if ( '' === $url ) {
				return '';
			}

			$name       = $this->get_display_name( $user_id );
			$alt        = isset( $args['alt'] ) ? (string) $args['alt'] : $name;
			$class      = isset( $args['class'] ) ? (string) $args['class'] : '';
			$class_attr = trim( 'avatar ' . $class );
			$size       = max( 1, $size );

			return sprintf(
				'<img src="%1$s" alt="%2$s" width="%3$d" height="%3$d" class="%4$s" />',
				$this->escape_url( $url ),
				$this->escape_attribute( $alt ),
				$size,
				$this->escape_attribute( $class_attr )
			);
		}

		/**
		 * Sucht Benutzer über die WordPress-Suche und einen optionalen externen
		 * Provider. Der Filter arbeitet ausschließlich mit User-IDs.
		 *
		 * Filter: `afspaces_user_search_results`
		 * Shape: `array{user_ids:int[],total:int}`.
		 *
		 * @param string $search Suchbegriff.
		 * @param int    $page Seite.
		 * @param int    $per_page Ergebnisse pro Seite.
		 * @return array{members:array<int,array{user_id:int,display_name:string,user_login:string}>,total:int,page:int,per_page:int}
		 */
		public function search_users( string $search, int $page = 1, int $per_page = 20 ): array {
			$page     = max( 1, $page );
			$per_page = max( 1, min( 100, $per_page ) );
			$term     = sanitize_text_field( $search );
			$candidate_limit = min( 1000, max( $per_page, $page * $per_page ) );
			$base     = $this->search_wordpress_user_ids( $term, $candidate_limit );
			$result   = $base;

			if ( function_exists( 'apply_filters' ) ) {
				$filtered = apply_filters( 'afspaces_user_search_results', $base, $term, $page, $per_page, $candidate_limit );
				if ( is_array( $filtered ) ) {
					$result = $filtered;
				}
			}

			$user_ids = $this->normalize_user_ids( $result['user_ids'] ?? array() );
			$members  = array();
			$offset = ( $page - 1 ) * $per_page;
			foreach ( array_slice( $user_ids, $offset, $per_page ) as $user_id ) {
				$identity = $this->get_identity( $user_id );
				if ( $identity ) {
					$members[] = array(
						'user_id'      => $identity['user_id'],
						'display_name' => $identity['display_name'],
						'user_login'   => $identity['user_login'],
					);
				}
			}

			return array(
				'members'  => $members,
				'total'    => max( 0, (int) ( $result['total'] ?? count( $user_ids ) ) ),
				'page'     => $page,
				'per_page' => $per_page,
			);
		}

		/**
		 * @param string $term Suchbegriff.
		 * @param int    $limit Kandidatenlimit.
		 * @return array{user_ids:int[],total:int}
		 */
		private function search_wordpress_user_ids( string $term, int $limit ): array {
			if ( ! class_exists( '\\WP_User_Query' ) ) {
				return array( 'user_ids' => array(), 'total' => 0 );
			}

			$query = new \WP_User_Query(
				array(
					'search'         => '*' . $term . '*',
					'search_columns' => array( 'display_name', 'user_login', 'user_nicename' ),
					'number'         => $limit,
					'paged'          => 1,
					'fields'         => array( 'ID' ),
				)
			);

			$ids = array();
			foreach ( (array) $query->get_results() as $user ) {
				$ids[] = (int) ( is_object( $user ) ? ( $user->ID ?? 0 ) : $user );
			}

			return array(
				'user_ids' => $this->normalize_user_ids( $ids ),
				'total'    => (int) $query->get_total(),
			);
		}

		/**
		 * @param int $user_id WordPress-Benutzer-ID.
		 * @return object|false
		 */
		private function get_user( int $user_id ) {
			if ( $user_id < 1 || ! function_exists( 'get_userdata' ) ) {
				return false;
			}

			$user = get_userdata( $user_id );
			return is_object( $user ) && ! empty( $user->ID ) ? $user : false;
		}

		/**
		 * @param object $user WordPress-Benutzerobjekt.
		 * @return string
		 */
		private function display_name_for_user( object $user ): string {
			$name = (string) ( $user->display_name ?? '' );
			if ( function_exists( 'apply_filters' ) ) {
				$name = (string) apply_filters( 'asgarosforum_filter_username', $name, $user );
				$name = (string) apply_filters( 'afspaces_user_display_name', $name, (int) $user->ID, $user );
			}

			return trim( wp_strip_all_tags( $name ) );
		}

		/**
		 * @param int $user_id WordPress-Benutzer-ID.
		 * @param int $size    Bildgröße.
		 * @return string
		 */
		private function avatar_url_for_user( int $user_id, int $size ): string {
			if ( $user_id < 1 || ! function_exists( 'get_avatar_url' ) ) {
				return '';
			}

			$url = (string) get_avatar_url( $user_id, array( 'size' => max( 1, $size ) ) );
			if ( function_exists( 'apply_filters' ) ) {
				$url = (string) apply_filters( 'afspaces_user_avatar_url', $url, $user_id, max( 1, $size ) );
			}

			return function_exists( 'esc_url_raw' ) ? (string) esc_url_raw( $url ) : $url;
		}

		/**
		 * @param mixed[] $ids Benutzer-IDs.
		 * @return int[]
		 */
		private function normalize_user_ids( array $ids ): array {
			$out = array();
			foreach ( $ids as $id ) {
				$id = (int) $id;
				if ( $id > 0 ) {
					$out[ $id ] = $id;
				}
			}

			return array_values( $out );
		}

		private function escape_url( string $url ): string {
			return function_exists( 'esc_url' ) ? (string) esc_url( $url ) : htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
		}

		private function escape_attribute( string $value ): string {
			return function_exists( 'esc_attr' ) ? (string) esc_attr( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
		}
	}
}
