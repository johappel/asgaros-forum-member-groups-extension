<?php
/**
 * Integration in die Asgaros-Forum-Navigation.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\JoinRequestRepository;
use AFSpaces\Adapters\Database\InvitationRepository;
use AFSpaces\Adapters\Database\SpaceMetaRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\SpaceCreationService;
use AFSpaces\Application\SpaceLifecycleService;
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
		private JoinRequestRepository $join_requests;
		private AsgarosAdapterInterface $asgaros;
		private SpaceMetaRepository $meta;
		private SpaceCreationService $space_creation;
		private SpaceLifecycleService $space_lifecycle;

		/**
		 * Konstruktor.
		 */
		public function __construct( SpaceRepository $spaces, InvitationRepository $invitations, JoinRequestRepository $join_requests, AsgarosAdapterInterface $asgaros, SpaceMetaRepository $meta, SpaceCreationService $space_creation, SpaceLifecycleService $space_lifecycle ) {
			$this->spaces        = $spaces;
			$this->invitations   = $invitations;
			$this->join_requests = $join_requests;
			$this->asgaros       = $asgaros;
			$this->meta          = $meta;
			$this->space_creation = $space_creation;
			$this->space_lifecycle = $space_lifecycle;
		}

		/**
		 * Registriert die Asgaros-Hooks.
		 *
		 * @return void
		 */
		public function init(): void {
			add_filter( 'asgarosforum_filter_header_menu', array( $this, 'add_menu_entry' ) );
			// Issue #9: Das bestehende Asgaros-Control bleibt einmalig und wird
			// vom unteren Menü in die jeweilige Forum-/Themenansicht verschoben.
			$this->asgaros->relocate_subscription_navigation();
			// Rendert innerhalb von #af-wrapper direkt unterhalb der Forum-Navigation.
			add_action( 'asgarosforum_content_header', array( $this, 'render_overview_panel' ) );
			// Färbt die Forenkategorien in den Farben der Arbeitsgruppen (auch für Gäste).
			add_action( 'asgarosforum_content_top', array( $this, 'render_category_colors' ) );
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

			// Chip: Summe der Dinge, die meine Aufmerksamkeit brauchen
			// (offene Einladungen an mich, offene Beitrittsanfragen in meinen
			// verwalteten Gruppen, offene Freigaben für Moderatoren).
			$pending_approvals = $this->space_lifecycle->count_pending_for_actor( $user_id );
			$badge = $this->pending_count( $user_id )
				+ $this->pending_join_request_count( $user_id )
				+ $pending_approvals;

			$menu_label = esc_html( WorkingGroupTerminology::label( WorkingGroupTerminology::PLURAL ) );
			if ( $badge > 0 ) {
				$menu_label = sprintf(
					/* translators: %d: Anzahl offener Vorgänge */
					esc_html__( 'Arbeitsgruppen (%d)', 'afspaces' ),
					$badge
				);
			}

			$menu_entries['afspaces'] = array(
				'menu_class'        => 'afspaces-link',
				'menu_link_text'    => $menu_label,
			'menu_url'          => SpacesUrls::hub_url( SpacesUrls::VIEW_DASHBOARD ),
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
			$user_id = get_current_user_id();
			if ( 0 === $user_id ) {
				return;
			}

			$is_admin      = user_can( $user_id, Capabilities::MANAGE_ALL_SPACES );
			$managed_count = $this->managed_space_count( $user_id );
			$pending_count = $this->pending_count( $user_id );
			$pending_join_request_count = $this->pending_join_request_count( $user_id );
			$pending_join_request_space_id = $this->pending_join_request_space_id( $user_id );
			$can_create    = $this->can_create_spaces( $user_id );
			$can_discover  = is_user_logged_in();
			$pending_approvals = $this->space_lifecycle->count_pending_for_actor( $user_id );

			echo '<section class="afspaces-forum-panel" id="afspaces-forum-panel" style="display: none;" aria-labelledby="afspaces-forum-panel-heading">';
			printf(
				'<h2 id="afspaces-forum-panel-heading" class="afspaces-forum-panel-heading">%s</h2>',
				esc_html( WorkingGroupTerminology::label( WorkingGroupTerminology::PLURAL ) )
			);
			echo '<ul class="afspaces-forum-panel-links">';

			// 1. Für alle sichtbar: Zugang zu den eigenen Arbeitsgruppen.
			printf(
				'<li><a class="afspaces-button" href="%1$s">%2$s</a></li>',
				esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_DASHBOARD ) ),
				esc_html( $is_admin ? __( 'Arbeitsgruppen verwalten', 'afspaces' ) : WorkingGroupTerminology::label( WorkingGroupTerminology::MY_PLURAL ) )
			);

			// 2. Wer noch keine Arbeitsgruppe verwaltet und gründen darf, sieht den Gründen-Button.
			if ( $can_create && 0 === $managed_count ) {
				printf(
					'<li><a class="afspaces-button" href="%1$s">%2$s</a></li>',
					esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_CREATE ) ),
					esc_html__( 'Arbeitsgruppe gründen', 'afspaces' )
				);
			}

			// 3. Entdecken – für alle angemeldeten Personen.
			if ( $can_discover ) {
				printf(
					'<li><a class="afspaces-button afspaces-button-secondary" href="%1$s">%2$s</a></li>',
					esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_DISCOVER ) ),
					esc_html( WorkingGroupTerminology::label( WorkingGroupTerminology::DISCOVER ) )
				);
			}

			// 4. Einladungen nur, wenn es welche gibt.
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
			}

			// --- Administrative Aktionen (nur für Verantwortliche/Moderatoren) ganz unten. ---

			// 5. Offene Beitrittsanfragen für verwaltete Arbeitsgruppen.
			if ( $pending_join_request_count > 0 ) {
				$requests_url = $pending_join_request_space_id > 0
					? SpacesUrls::hub_url( SpacesUrls::VIEW_JOIN_REQUESTS, array( 'space_id' => $pending_join_request_space_id ) )
					: SpacesUrls::hub_url( SpacesUrls::VIEW_DASHBOARD );

				printf(
					'<li><a class="afspaces-button afspaces-button-danger" href="%1$s">%2$s</a></li>',
					esc_url( $requests_url ),
					esc_html(
						sprintf(
							/* translators: %d: Anzahl offener Beitrittsanfragen */
							_n( 'Beitrittsanfrage (%d offen)', 'Beitrittsanfragen (%d offen)', $pending_join_request_count, 'afspaces' ),
							$pending_join_request_count
						)
					)
				);
			}

			// 6. Freigaben – nur bei einer konkreten, zuständigen offenen Freigabe.
			if ( $pending_approvals > 0 ) {
				$approvals_label = sprintf(
					/* translators: %d: Anzahl ausstehender Freigaben */
					_n( 'Freigaben (%d)', 'Freigaben (%d)', $pending_approvals, 'afspaces' ),
					$pending_approvals
				);

				printf(
					'<li><a class="afspaces-button%1$s" href="%2$s">%3$s</a></li>',
					' afspaces-button-danger',
					esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_APPROVALS ) ),
					esc_html( $approvals_label )
				);
			}

			echo '</ul>';
			echo '</section>';

		// Toggle-JavaScript: Zeigt/verbirgt das Panel auf Klick des Menüpunkts.
		?>
		<script type="module">
			document.addEventListener( 'DOMContentLoaded', function() {
				const menuLink = document.querySelector( 'a.afspaces-link' );
				const panel = document.getElementById( 'afspaces-forum-panel' );
				// Ohne Panel (z. B. andere Kontexte) navigiert der Link regulär zur Hub-Seite.
				if ( ! menuLink || ! panel ) return;

				menuLink.addEventListener( 'click', function( e ) {
					e.preventDefault();
					const isVisible = panel.style.display !== 'none';
					panel.style.display = isVisible ? 'none' : 'block';
					menuLink.classList.toggle( 'is-active', ! isVisible );
					if ( ! isVisible ) {
						panel.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
					}
				} );
			} );
		</script>
		<?php
	}

		/**
		 * Färbt die Forenkategorien in den Farben der zugehörigen Arbeitsgruppen.
		 *
		 * @return void
		 */
		public function render_category_colors(): void {
			$spaces = $this->spaces->list_spaces();
			$active = array_filter( $spaces, static fn( $s ): bool => 'active' === $s->status );
			if ( empty( $active ) ) {
				return;
			}

			$ids   = array_map( static fn( $s ): int => (int) $s->id, $active );
			$metas = $this->meta->list_for_spaces( $ids );

			$by_category = array();
			foreach ( $active as $space ) {
				$forum = $this->asgaros->get_forum( $space->forum_id );
				if ( empty( $forum ) ) {
					continue;
				}
				$category_id = (int) ( $forum['category_id'] ?? 0 );
				if ( $category_id < 1 ) {
					continue;
				}
				$meta   = $metas[ $space->id ] ?? null;
				$accent = $meta ? sanitize_hex_color( $meta->accent_color ) : '';
				if ( ! $accent ) {
					continue;
				}
				$by_category[ $category_id ] = $accent;
			}

			if ( empty( $by_category ) ) {
				return;
			}

			$css = '';
			foreach ( $by_category as $category_id => $accent ) {
				$css .= sprintf(
					'#af-wrapper #forum-category-%1$d,#af-wrapper #forum-category-%1$d .title-element{background-color:%2$s !important;border-color:%2$s !important;}',
					$category_id,
					$accent
				);
			}

			echo '<style id="afspaces-category-colors">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS aus sanitize_hex_color + Integer.
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
			AppearanceSettingsPage::enqueue_inline_style();

			// JavaScript für Bestätigungsdialoge der Moderationsaktionen im Forum.
			wp_enqueue_script(
				'afspaces-frontend',
				AFSPACES_URL . 'assets/afspaces.js',
				array(),
				AFSPACES_VERSION,
				true
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
		 * Ermittelt die Zahl offener Beitrittsanfragen für verwaltete Räume.
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return int
		 */
		private function pending_join_request_count( int $user_id ): int {
			return $this->cached_count(
				'afspaces_pending_join_requests_' . $user_id,
				function () use ( $user_id ): int {
					$space_ids = $this->managed_space_ids( $user_id );
					if ( empty( $space_ids ) ) {
						return 0;
					}
					return $this->join_requests->count_pending_for_spaces( $space_ids );
				}
			);
		}

		/**
		 * Liefert eine Space-ID mit offener Beitrittsanfrage für Deep-Links.
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return int
		 */
		private function pending_join_request_space_id( int $user_id ): int {
			return $this->cached_count(
				'afspaces_pending_join_space_' . $user_id,
				function () use ( $user_id ): int {
					$space_ids = $this->managed_space_ids( $user_id );
					if ( empty( $space_ids ) ) {
						return 0;
					}
					return $this->join_requests->first_space_with_pending( $space_ids );
				}
			);
		}

		/**
		 * Ermittelt die verwalteten Space-IDs eines Benutzers.
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return int[]
		 */
		private function managed_space_ids( int $user_id ): array {
			if ( user_can( $user_id, Capabilities::MANAGE_ALL_SPACES ) ) {
				return array_values(
					array_map(
						static fn( $space ): int => (int) $space->id,
						$this->spaces->list_spaces()
					)
				);
			}

			return $this->spaces->list_manager_space_ids( $user_id );
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
			$can_create = $this->space_creation->can_user_create( $user_id );
			return (bool) apply_filters( 'afspaces_enable_space_creation', $can_create, $user_id );
		}
	}
}
