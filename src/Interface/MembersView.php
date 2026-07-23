<?php
/**
 * Mitgliederansicht (Liste, Suche, Hinzufügen, Entfernen).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\MemberService;

if ( ! class_exists( 'AFSpaces\\Interface\\MembersView' ) ) {

	/**
	 * Rendert die Mitgliederliste und Suchmaske.
	 */
	class MembersView {

		/**
		 * @var SpaceRepository
		 */
		private SpaceRepository $spaces;

		/**
		 * @var AsgarosAdapterInterface
		 */
		private AsgarosAdapterInterface $asgaros;

		/**
		 * @var MemberService
		 */
		private MemberService $members;

		/**
		 * @var string
		 */
		private string $nonce_action = 'afspaces_member_action';

		/**
		 * Konstruktor.
		 *
		 * @param SpaceRepository         $spaces  Space-Repository.
		 * @param AsgarosAdapterInterface $asgaros Asgaros-Adapter.
		 * @param MemberService           $members Mitglieder-Service.
		 */
		public function __construct(
			SpaceRepository $spaces,
			AsgarosAdapterInterface $asgaros,
			MemberService $members
		) {
			$this->spaces  = $spaces;
			$this->asgaros = $asgaros;
			$this->members = $members;
		}

		/**
		 * Rendert die Mitgliederansicht für einen Space.
		 *
		 * @param int $space_id Space-ID.
		 * @return string
		 */
		public function render( int $space_id ): string {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				return $this->notice( __( 'Diese Arbeitsgruppe existiert nicht.', 'afspaces' ) );
			}

			$forum = $this->asgaros->get_forum( $space->forum_id );
			$forum_name = (string) ( $forum['name'] ?? '' );
			if ( '' === $forum_name ) {
				$forum_name = sprintf( __( 'Arbeitsgruppe #%d', 'afspaces' ), $space->id );
			}

			$actor = get_current_user_id();
			if ( ! $this->can_manage( $space_id, $actor ) ) {
				return $this->notice( __( 'Du bist nicht berechtigt, diese Arbeitsgruppe zu verwalten.', 'afspaces' ) );
			}

			$group_ids = $this->asgaros->get_forum_group_ids( $space->forum_id );
			if ( empty( $group_ids ) ) {
				return $this->notice( __( 'Für dieses Forum ist keine Zugriffsgruppe konfiguriert.', 'afspaces' ) );
			}
			$group_id = (int) $group_ids[0];

			$page    = isset( $_GET['afp_page'] ) ? max( 1, (int) $_GET['afp_page'] ) : 1;
			$search  = isset( $_GET['afp_search'] ) ? sanitize_text_field( wp_unslash( $_GET['afp_search'] ) ) : '';
			$per_page = 20;

			$members = $this->asgaros->list_group_members( $group_id, array(
				'page'     => $page,
				'per_page' => $per_page,
				'search'   => $search,
			) );

			$total_members = (int) ( $members['total'] ?? 0 );
			if ( '' !== $search ) {
				$unfiltered = $this->asgaros->list_group_members( $group_id, array(
					'page'     => 1,
					'per_page' => 1,
					'search'   => '',
				) );
				$total_members = (int) ( $unfiltered['total'] ?? $total_members );
			}

			$manager_roles = array();
			foreach ( $this->spaces->get_managers( $space_id ) as $manager ) {
				$manager_roles[ (int) $manager->user_id ] = (string) $manager->role;
			}

			$search_results = array();
			if ( '' !== $search ) {
				$search_results = $this->members->search_users( $search, 1, 20 )['members'] ?? array();
			}

			ob_start();
			?>
			<section class="afspaces-members" aria-labelledby="afspaces-members-heading">
				<h2 id="afspaces-members-heading"><?php echo esc_html( sprintf( __( 'Mitglieder - %s', 'afspaces' ), $forum_name ) ); ?></h2>
				<?php echo $this->render_message(); ?>
				<p><?php echo esc_html__( 'Arbeitsgruppenverantwortliche verwalten hier Mitgliedschaften. Die Moderation von Forenbeiträgen bleibt weiterhin in Asgaros getrennt.', 'afspaces' ); ?></p>

				<div class="afspaces-section-card content-container">
					<div class="title-element afspaces-section-title"><?php echo esc_html__( 'Mitglieder suchen', 'afspaces' ); ?></div>
					<form method="get" class="afspaces-search" role="search" aria-label="<?php echo esc_attr__( 'Mitglieder suchen', 'afspaces' ); ?>">
						<label for="afp_search"><?php echo esc_html__( 'Person suchen', 'afspaces' ); ?></label>
						<input type="search" id="afp_search" name="afp_search" value="<?php echo esc_attr( $search ); ?>" />
						<input type="hidden" name="afspaces_view" value="<?php echo esc_attr( SpacesUrls::VIEW_MEMBERS ); ?>" />
						<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
						<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Suchen', 'afspaces' ); ?></button>
					</form>
				</div>

				<?php if ( ! empty( $search_results ) ) : ?>
					<section class="afspaces-section-card content-container" aria-labelledby="afspaces-search-results-heading">
						<div id="afspaces-search-results-heading" class="title-element afspaces-section-title"><?php echo esc_html__( 'Suchergebnisse', 'afspaces' ); ?></div>
						<ul class="afspaces-search-results">
							<?php foreach ( $search_results as $user ) : ?>
								<?php $is_member = $this->asgaros->is_user_in_group( (int) $user['user_id'], $group_id ); ?>
								<li>
									<span><?php echo esc_html( $user['display_name'] ); ?></span>
									<?php if ( $is_member ) : ?>
										<span class="afspaces-tag"><?php echo esc_html__( 'bereits Mitglied', 'afspaces' ); ?></span>
									<?php else : ?>
										<form method="post" class="afspaces-inline-form">
											<?php echo wp_nonce_field( $this->nonce_action, '_wpnonce', true, false ); ?>
											<input type="hidden" name="afspaces_action" value="add_member" />
											<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
											<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user['user_id'] ); ?>" />
											<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Hinzufügen', 'afspaces' ); ?></button>
										</form>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<section class="afspaces-section-card content-container" aria-labelledby="afspaces-current-members-heading">
					<div id="afspaces-current-members-heading" class="title-element afspaces-section-title"><?php echo esc_html__( 'Aktuelle Mitglieder', 'afspaces' ); ?></div>
				<?php if ( empty( $members['members'] ) ) : ?>
					<?php if ( '' !== $search ) : ?>
						<p><?php echo esc_html__( 'Für die aktuelle Suche wurden keine bestehenden Mitglieder gefunden.', 'afspaces' ); ?></p>
					<?php elseif ( 0 === $total_members ) : ?>
						<p><?php echo esc_html__( 'Diese Arbeitsgruppe hat noch keine Mitglieder.', 'afspaces' ); ?></p>
					<?php else : ?>
						<p><?php echo esc_html__( 'Auf dieser Seite wurden keine Mitglieder gefunden.', 'afspaces' ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<table class="afspaces-member-table">
						<caption class="screen-reader-text"><?php echo esc_html__( 'Liste der Arbeitsgruppenmitglieder', 'afspaces' ); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html__( 'Name', 'afspaces' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Rolle in der Arbeitsgruppe', 'afspaces' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Aktion', 'afspaces' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $members['members'] as $member ) : ?>
								<?php
								$user_id = (int) $member['user_id'];
								$role = (string) ( $manager_roles[ $user_id ] ?? '' );
								$is_owner = ( 'owner' === $role );
								$is_manager = ( 'manager' === $role );
								?>
								<tr>
									<td><a href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_PROFILE, array( 'user_id' => $user_id ) ) ); ?>"><?php echo esc_html( $member['display_name'] ); ?></a></td>
									<td>
										<?php if ( $is_owner ) : ?>
											<span class="afspaces-tag"><?php echo esc_html__( 'Owner', 'afspaces' ); ?></span>
										<?php elseif ( $is_manager ) : ?>
											<span class="afspaces-tag"><?php echo esc_html__( 'Arbeitsgruppenverantwortlich', 'afspaces' ); ?></span>
										<?php else : ?>
											<span class="afspaces-tag"><?php echo esc_html__( 'Mitglied', 'afspaces' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<div class="afspaces-inline-form" role="group" aria-label="<?php echo esc_attr__( 'Mitgliedsaktionen', 'afspaces' ); ?>">
											<?php if ( ! $is_owner && ! $is_manager ) : ?>
												<form method="post" class="afspaces-inline-form">
													<?php echo wp_nonce_field( $this->nonce_action, '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="assign_manager" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $member['user_id'] ); ?>" />
													<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Als Arbeitsgruppenverantwortliche festlegen', 'afspaces' ); ?></button>
												</form>
											<?php elseif ( $is_manager ) : ?>
												<form method="post" class="afspaces-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Arbeitsgruppenverantwortung wirklich entziehen?', 'afspaces' ) ); ?>');">
													<?php echo wp_nonce_field( $this->nonce_action, '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="revoke_manager" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $member['user_id'] ); ?>" />
													<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Arbeitsgruppenverantwortung entziehen', 'afspaces' ); ?></button>
												</form>
											<?php endif; ?>

											<form method="post" class="afspaces-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Diese Person wirklich entfernen?', 'afspaces' ) ); ?>');">
												<?php echo wp_nonce_field( $this->nonce_action, '_wpnonce', true, false ); ?>
												<input type="hidden" name="afspaces_action" value="remove_member" />
												<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
												<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $member['user_id'] ); ?>" />
												<button type="submit" class="afspaces-button afspaces-button-danger"><?php echo esc_html__( 'Entfernen', 'afspaces' ); ?></button>
											</form>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php echo $this->pagination( $space_id, $page, (int) ceil( ( $members['total'] ?? 0 ) / $per_page ), $search ); ?>
				<?php endif; ?>
				</section>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Prüft die Verwaltungsberechtigung.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $actor    Benutzer-ID.
		 * @return bool
		 */
		private function can_manage( int $space_id, int $actor ): bool {
			if ( user_can( $actor, 'afspaces_manage_all_spaces' ) ) {
				return true;
			}
			return $this->spaces->is_manager( $space_id, $actor );
		}

		/**
		 * Rendert Paginierung.
		 *
		 * @param int    $space_id Space-ID.
		 * @param int    $page     Aktuelle Seite.
		 * @param int    $pages    Gesamtseiten.
		 * @param string $search   Suchbegriff.
		 * @return string
		 */
		private function pagination( int $space_id, int $page, int $pages, string $search ): string {
			if ( $pages <= 1 ) {
				return '';
			}
			$links = '';
			for ( $i = 1; $i <= $pages; $i++ ) {
				$url = SpacesUrls::hub_url(
					SpacesUrls::VIEW_MEMBERS,
					array(
						'space_id'   => $space_id,
						'afp_page'   => $i,
						'afp_search' => $search,
					)
				);
				$current = ( $i === $page ) ? ' aria-current="page"' : '';
				$links .= sprintf(
					'<a href="%1$s"%2$s>%3$s</a> ',
					esc_url( $url ),
					$current,
					esc_html( (string) $i )
				);
			}
			return sprintf(
				'<nav class="afspaces-pagination" aria-label="%1$s">%2$s</nav>',
				esc_attr__( 'Seitennavigation', 'afspaces' ),
				$links
			);
		}

		/**
		 * Gibt eine einfache Hinweismeldung zurück.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		private function notice( string $text ): string {
			return sprintf(
				'<p class="afspaces-notice" role="status">%s</p>',
				esc_html( $text )
			);
		}

		/**
		 * Rendert Session-Nachrichten (PRG-Rückmeldung).
		 *
		 * @return string
		 */
		private function render_message(): string {
			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}

			if ( empty( $_SESSION['afspaces_message'] ) ) {
				return '';
			}

			$msg = $_SESSION['afspaces_message'];
			unset( $_SESSION['afspaces_message'] );

			$role = ( 'error' === $msg['type'] ) ? 'alert' : 'status';
			return sprintf(
				'<div class="afspaces-message afspaces-message-%1$s" role="%2$s" aria-live="polite">%3$s</div>',
				esc_attr( $msg['type'] ),
				esc_attr( $role ),
				esc_html( $msg['message'] )
			);
		}
	}
}
