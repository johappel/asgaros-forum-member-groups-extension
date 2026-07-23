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

		public function render( int $profile_user_id = 0 ): string {
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

				$items[] = array(
					'space_id'     => $space->id,
					'name'         => (string) $forum['name'],
					'description'  => '' !== $meta->description ? $meta->description : (string) ( $forum['description'] ?? '' ),
					'role'         => $is_profile_manager ? __( 'Arbeitsgruppenverantwortlich', 'afspaces' ) : __( 'Mitglied', 'afspaces' ),
					'can_manage'   => $viewer_is_manager,
					'topics'       => $this->working_groups->topic_names( $meta ),
				);
			}

			$heading = $viewer === $profile_user_id
				? __( 'Mein Arbeitsgruppenprofil', 'afspaces' )
				: sprintf( __( 'Arbeitsgruppenprofil: %s', 'afspaces' ), $profile_user->display_name );

			ob_start();
			?>
			<section class="afspaces-profile-view" aria-labelledby="afspaces-profile-view-heading">
				<h2 id="afspaces-profile-view-heading"><?php echo esc_html( $heading ); ?></h2>
				<p><?php echo esc_html__( 'Hier siehst du sichtbare Mitgliedschaften und Verantwortlichkeiten im Arbeitsgruppenmodell.', 'afspaces' ); ?></p>
				<?php if ( empty( $items ) ) : ?>
					<p><?php echo esc_html( $viewer === $profile_user_id ? __( 'Für dein Profil sind aktuell keine sichtbaren Arbeitsgruppen hinterlegt.', 'afspaces' ) : __( 'Für dieses Profil sind keine sichtbaren Arbeitsgruppen freigegeben.', 'afspaces' ) ); ?></p>
				<?php else : ?>
					<ul class="afspaces-space-list">
						<?php foreach ( $items as $item ) : ?>
							<li class="afspaces-space-item afspaces-working-group-card">
								<h3><a href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_GROUP, array( 'space_id' => $item['space_id'] ) ) ); ?>"><?php echo esc_html( $item['name'] ); ?></a></h3>
								<p><span class="afspaces-tag"><?php echo esc_html( $item['role'] ); ?></span></p>
								<?php if ( '' !== $item['description'] ) : ?>
									<p><?php echo esc_html( $item['description'] ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $item['topics'] ) ) : ?>
									<p><strong><?php echo esc_html__( 'Themen:', 'afspaces' ); ?></strong> <?php echo esc_html( implode( ', ', $item['topics'] ) ); ?></p>
								<?php endif; ?>
								<div class="afspaces-space-actions">
									<a class="afspaces-button afspaces-button-secondary" href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_GROUP, array( 'space_id' => $item['space_id'] ) ) ); ?>"><?php echo esc_html__( 'Arbeitsgruppe ansehen', 'afspaces' ); ?></a>
									<?php if ( $item['can_manage'] ) : ?>
										<a class="afspaces-button" href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_MEMBERS, array( 'space_id' => $item['space_id'] ) ) ); ?>"><?php echo esc_html__( 'Verwaltung öffnen', 'afspaces' ); ?></a>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
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