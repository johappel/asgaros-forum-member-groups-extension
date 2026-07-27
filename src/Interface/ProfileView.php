<?php
/**
 * Profilansicht fuer Arbeitsgruppen-Mitgliedschaften.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\WorkingGroupService;
use AFSpaces\Core\Capabilities;

if ( ! class_exists( 'AFSpaces\\Interface\\ProfileView' ) ) {

	/**
	 * Zeigt sichtbare Mitgliedschaften und Verantwortlichkeiten eines Profils.
	 */
	class ProfileView {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private WorkingGroupService $working_groups;

		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, WorkingGroupService $working_groups ) {
			$this->spaces = $spaces;
			$this->asgaros = $asgaros;
			$this->working_groups = $working_groups;
		}

		public function render( int $profile_user_id = 0, bool $compact = false ): string {
			if ( ! is_user_logged_in() ) {
				return $this->notice( __( 'Bitte melde dich an.', 'afspaces' ) );
			}

			$viewer = get_current_user_id();
			$profile_user_id = $profile_user_id > 0 ? $profile_user_id : $viewer;
			$profile_user = get_userdata( $profile_user_id );
			if ( ! $profile_user ) {
				return $this->notice( __( 'Dieses Profil wurde nicht gefunden.', 'afspaces' ) );
			}

			$items = array();
			foreach ( $this->spaces->list_spaces() as $space ) {
				$forum = $this->asgaros->get_forum( $space->forum_id );
				if ( empty( $forum ) ) {
					continue;
				}

				$is_profile_manager = $this->spaces->is_manager( $space->id, $profile_user_id );
				$is_profile_member = $this->asgaros->is_user_in_group( $profile_user_id, $space->primary_group_id );
				if ( ! $is_profile_manager && ! $is_profile_member ) {
					continue;
				}

				$meta = $this->working_groups->get_metadata( $space->id );
				$viewer_is_manager = $this->spaces->is_manager( $space->id, $viewer ) || user_can( $viewer, Capabilities::MANAGE_ALL_SPACES );
				$viewer_is_member = $this->asgaros->is_user_in_group( $viewer, $space->primary_group_id );
				if ( ! $this->working_groups->can_view_group( $meta, $viewer_is_member, $viewer_is_manager, $viewer === $profile_user_id ) ) {
					continue;
				}

				$members = $this->asgaros->list_group_members( $space->primary_group_id, array( 'per_page' => 100 ) );
				$member_count = (int) ( $members['total'] ?? 0 );
				$member_list = isset( $members['members'] ) && is_array( $members['members'] ) ? $members['members'] : array();

				$forum_slug = sanitize_title( (string) ( $forum['slug'] ?? '' ) );
				$forum_url = '' !== $forum_slug ? home_url( '/forum/forum/' . $forum_slug . '/' ) : home_url( '/forum/' );
				$forum_url = (string) apply_filters( 'afspaces_space_forum_url', $forum_url, $space, $forum, $viewer );

				$items[] = array(
					'space_id'     => $space->id,
					'name'         => (string) $forum['name'],
					'description'  => '' !== $meta->description ? $meta->description : (string) ( $forum['description'] ?? '' ),
					'role'         => $is_profile_manager ? __( 'Arbeitsgruppenverantwortlich', 'afspaces' ) : __( 'Mitglied', 'afspaces' ),
					'can_manage'   => $viewer_is_manager,
					'member_count' => $member_count,
					'members'      => $member_list,
					'icon'         => WorkingGroupService::icon_class( $meta->icon ),
					'accent'       => $meta->accent_color,
					'topics'       => $this->working_groups->topic_names( $meta ),
					'forum_url'    => $forum_url,
					'can_view_forum' => $viewer_is_member || $viewer_is_manager,
				);
			}

			$is_own = $viewer === $profile_user_id;
			$heading = $is_own
				? __( 'Mein Arbeitsgruppenprofil', 'afspaces' )
				: sprintf( __( 'Arbeitsgruppen von %s', 'afspaces' ), $profile_user->display_name );

			ob_start();
			?>
			<section class="afspaces-profile-view" aria-labelledby="afspaces-profile-view-heading">
				<?php if ( ! $compact ) : ?>
					<h2 id="afspaces-profile-view-heading"><?php echo esc_html( $heading ); ?></h2>
					<p><?php echo esc_html__( 'Hier siehst du sichtbare Mitgliedschaften und Verantwortlichkeiten im Arbeitsgruppenmodell.', 'afspaces' ); ?></p>
				<?php else : ?>
					<h3 id="afspaces-profile-view-heading" class="screen-reader-text"><?php echo esc_html( $heading ); ?></h3>
				<?php endif; ?>
				<?php if ( empty( $items ) ) : ?>
					<p class="afspaces-empty"><?php echo esc_html( $is_own ? __( 'Für dein Profil sind aktuell keine sichtbaren Arbeitsgruppen hinterlegt.', 'afspaces' ) : __( 'Für dieses Profil sind keine sichtbaren Arbeitsgruppen freigegeben.', 'afspaces' ) ); ?></p>
				<?php else : ?>
					<ul class="afspaces-space-list afspaces-group-tiles afspaces-profile-groups">
						<?php
						foreach ( $items as $item ) {
							echo WorkingGroupTile::render(
								array(
									'name'         => $item['name'],
									'url'          => SpacesUrls::hub_url( SpacesUrls::VIEW_GROUP, array( 'space_id' => $item['space_id'] ) ),
									'description'  => $item['description'],
									'icon'         => $item['icon'],
									'accent'       => $item['accent'],
									'member_count' => $item['member_count'],
									'members'      => $item['members'],
									'role'         => $item['role'],
									'topics'       => $item['topics'],
									'forum_url'    => $item['forum_url'],
									'can_view_forum' => $item['can_view_forum'],
								)
							);
						}
						?>
					</ul>
				<?php endif; ?>
			</section>
			<?php

			return (string) ob_get_clean();
		}

		private function notice( string $text ): string {
			return sprintf( '<p class="afspaces-notice" role="status">%s</p>', esc_html( $text ) );
		}
	}
}