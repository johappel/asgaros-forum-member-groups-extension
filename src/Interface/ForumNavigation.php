<?php
/**
 * Integration in die Asgaros-Forum-Navigation.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Database\InvitationRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\Capabilities;

if ( ! class_exists( 'AFSpaces\\Interface\\ForumNavigation' ) ) {

	/**
	 * Hängt einen Menüpunkt in die Forum-Navigation ein und rendert ein
	 * kompaktes Einstiegs-Panel auf der Forum-Übersicht.
	 *
	 * Verwendete, dokumentierte Asgaros-Hooks:
	 * - Filter `asgarosforum_filter_header_menu`
	 * - Action `asgarosforum_overview_custom_content_top`
	 */
	class ForumNavigation {

		private SpaceRepository $spaces;
		private InvitationRepository $invitations;

		/**
		 * Konstruktor.
		 */
		public function __construct( SpaceRepository $spaces, InvitationRepository $invitations ) {
			$this->spaces      = $spaces;
			$this->invitations = $invitations;
		}

		/**
		 * Registriert die Asgaros-Hooks.
		 *
		 * @return void
		 */
		public function init(): void {
			add_filter( 'asgarosforum_filter_header_menu', array( $this, 'add_menu_entry' ) );
			// Rendert innerhalb von #af-wrapper direkt unterhalb der Forum-Navigation.
			add_action( 'asgarosforum_content_header', array( $this, 'render_overview_panel' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		/**
		 * Fügt den Menüpunkt „Räume" hinzu, wenn der Benutzer berechtigt ist.
		 *
		 * @param array<string,mixed> $menu_entries Bestehende Menüeinträge.
		 * @return array<string,mixed>
		 */
		public function add_menu_entry( $menu_entries ) {
			if ( ! is_array( $menu_entries ) ) {
				return $menu_entries;
			}

			$user_id = get_current_user_id();
			if ( ! $this->is_eligible( $user_id ) ) {
				return $menu_entries;
			}

			$menu_entries['afspaces'] = array(
				'menu_class'        => 'afspaces-link',
				'menu_link_text'    => esc_html__( 'Räume', 'afspaces' ),
			'menu_url'          => '#',
			'menu_login_status' => 1,
			'menu_new_tab'      => false,
		);

			return $menu_entries;
		}

		/**
		 * Rendert das Einstiegs-Panel auf der Forum-Übersicht.
		 *
		 * @return void
		 */
		public function render_overview_panel(): void {
			// Nur auf der Forum-Übersicht anzeigen, nicht in Themen/Beiträgen.
			if ( ! $this->is_forum_overview() ) {
				return;
			}

			$user_id = get_current_user_id();
			if ( 0 === $user_id ) {
				return;
			}

			$managed_count = $this->managed_space_count( $user_id );
			$pending_count = $this->pending_count( $user_id );
			$can_create    = $this->can_create_spaces( $user_id );
			$can_discover  = is_user_logged_in();

			// Panel nur anzeigen, wenn es für den Benutzer relevant ist.
			if ( 0 === $managed_count && 0 === $pending_count && ! $can_create && ! $can_discover ) {
				return;
			}

			echo '<section class="afspaces-forum-panel" id="afspaces-forum-panel" style="display: none;" aria-labelledby="afspaces-forum-panel-heading">';
			printf(
				'<h2 id="afspaces-forum-panel-heading" class="afspaces-forum-panel-heading">%s</h2>',
				esc_html__( 'Deine Räume', 'afspaces' )
			);
			echo '<ul class="afspaces-forum-panel-links">';

			if ( $pending_count > 0 ) {
				printf(
					'<li><a class="afspaces-button" href="%1$s">%2$s</a></li>',
					esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_MY_INVITATIONS ) ),
					esc_html(
						sprintf(
							/* translators: %d: Anzahl offener Einladungen */
							_n( 'Meine Einladungen (%d offen)', 'Meine Einladungen (%d offen)', $pending_count, 'afspaces' ),
							$pending_count
						)
					)
				);
			} else {
				printf(
					'<li><a class="afspaces-button afspaces-button-secondary" href="%1$s">%2$s</a></li>',
					esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_MY_INVITATIONS ) ),
					esc_html__( 'Meine Einladungen', 'afspaces' )
				);
			}

			if ( $managed_count > 0 ) {
				printf(
					'<li><a class="afspaces-button" href="%1$s">%2$s</a></li>',
					esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_DASHBOARD ) ),
					esc_html(
						sprintf(
							/* translators: %d: Anzahl verwalteter Räume */
							_n( 'Verwaltete Räume (%d)', 'Verwaltete Räume (%d)', $managed_count, 'afspaces' ),
							$managed_count
						)
					)
				);
			}

			if ( $can_create ) {
				printf(
					'<li><a class="afspaces-button afspaces-button-secondary" href="%1$s">%2$s</a></li>',
					esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_CREATE ) ),
					esc_html__( 'Raum gründen', 'afspaces' )
				);
			}

			if ( $can_discover ) {
				printf(
					'<li><a class="afspaces-button afspaces-button-secondary" href="%1$s">%2$s</a></li>',
					esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_DISCOVER ) ),
					esc_html__( 'Raeume entdecken', 'afspaces' )
				);
			}

			echo '</ul>';
			echo '</section>';

		// Toggle-JavaScript: Zeigt/verbirgt das Panel auf Klick des Menüpunkts.
		?>
		<script type="module">
			document.addEventListener( 'DOMContentLoaded', function() {
				const menuLink = document.querySelector( 'a.afspaces-link[href="#"]' );
				const panel = document.getElementById( 'afspaces-forum-panel' );
				if ( ! menuLink || ! panel ) return;

				menuLink.addEventListener( 'click', function( e ) {
					e.preventDefault();
					const isVisible = panel.style.display !== 'none';
					panel.style.display = isVisible ? 'none' : 'block';
					menuLink.classList.toggle( 'is-active', ! isVisible );
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Bindet die Frontend-Stile auf Forumseiten ein.
		 *
		 * @return void
		 */
		public function enqueue_assets(): void {
			if ( ! function_exists( 'has_shortcode' ) ) {
				return;
			}

			$content = (string) get_post_field( 'post_content', get_the_ID() );
			if ( ! has_shortcode( $content, 'forum' ) ) {
				return;
			}

			wp_enqueue_style(
				'afspaces-frontend',
				AFSPACES_URL . 'assets/afspaces.css',
				array(),
				AFSPACES_VERSION
			);
		}

		/**
		 * Prüft, ob der Menüpunkt für den Benutzer sichtbar sein soll.
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return bool
		 */
		private function is_eligible( int $user_id ): bool {
			if ( 0 === $user_id ) {
				return false;
			}

			if ( $this->managed_space_count( $user_id ) > 0 ) {
				return true;
			}

			if ( $this->pending_count( $user_id ) > 0 ) {
				return true;
			}

			if ( is_user_logged_in() ) {
				return true;
			}

			return $this->can_create_spaces( $user_id );
		}

		/**
		 * Prüft, ob gerade die Forum-Übersicht (nicht ein Thema/Beitrag) angezeigt wird.
		 *
		 * @return bool
		 */
		private function is_forum_overview(): bool {
			global $asgarosforum;
			if ( ! is_object( $asgarosforum ) || ! isset( $asgarosforum->current_view ) ) {
				return false;
			}
			return 'overview' === $asgarosforum->current_view;
		}

		/**
		 * Ermittelt die Zahl der vom Benutzer verwaltbaren Räume (kurz gecacht).
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return int
		 */
		private function managed_space_count( int $user_id ): int {
			return $this->cached_count(
				'afspaces_managed_count_' . $user_id,
				function () use ( $user_id ): int {
					if ( user_can( $user_id, Capabilities::MANAGE_ALL_SPACES ) ) {
						return count( $this->spaces->list_spaces() );
					}
					return $this->spaces->count_manager_spaces( $user_id );
				}
			);
		}

		/**
		 * Ermittelt die Zahl offener Einladungen (kurz gecacht).
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return int
		 */
		private function pending_count( int $user_id ): int {
			return $this->cached_count(
				'afspaces_pending_count_' . $user_id,
				fn (): int => $this->invitations->count_pending_for_invitee( $user_id )
			);
		}

		/**
		 * Kleiner Transient-Cache, damit die Zahlen nicht bei jedem Forum-Aufruf
		 * frisch aus der Datenbank gelesen werden müssen.
		 *
		 * @param string   $key      Transient-Schlüssel.
		 * @param callable $callback Ermittelt den Wert bei Cache-Miss.
		 * @return int
		 */
		private function cached_count( string $key, callable $callback ): int {
			$ttl = (int) apply_filters( 'afspaces_panel_cache_ttl', 30 );
			if ( $ttl <= 0 ) {
				return (int) $callback();
			}

			$cached = get_transient( $key );
			if ( false !== $cached ) {
				return (int) $cached;
			}

			$value = (int) $callback();
			set_transient( $key, $value, $ttl );
			return $value;
		}

		/**
		 * @param int $user_id Benutzer-ID.
		 * @return bool
		 */
		private function can_create_spaces( int $user_id ): bool {
			$enabled = (bool) get_option( 'afspaces_enable_space_creation', false );
			$enabled = (bool) apply_filters( 'afspaces_enable_space_creation', $enabled, $user_id );
			return $enabled && user_can( $user_id, Capabilities::CREATE_SPACE );
		}
	}
}
