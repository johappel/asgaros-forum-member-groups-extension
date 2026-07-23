<?php
/**
 * Frontend-Ansicht zum Entdecken von Arbeitsgruppen und Senden von Beitrittsanfragen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\InvitationService;
use AFSpaces\Application\JoinRequestService;
use AFSpaces\Application\WorkingGroupService;
use AFSpaces\Domain\WorkingGroupMeta;

if ( ! class_exists( 'AFSpaces\\Interface\\DiscoverView' ) ) {

	/**
	 * Rendert die Discovery-Liste fuer sichtbare Arbeitsgruppen.
	 */
	class DiscoverView {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private InvitationService $invitations;
		private JoinRequestService $requests;
		private WorkingGroupService $working_groups;

		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, InvitationService $invitations, JoinRequestService $requests, WorkingGroupService $working_groups ) {
			$this->spaces = $spaces;
			$this->asgaros = $asgaros;
			$this->invitations = $invitations;
			$this->requests = $requests;
			$this->working_groups = $working_groups;
		}

		/**
		 * @return string
		 */
		public function render(): string {
			if ( ! is_user_logged_in() ) {
				return sprintf( '<p class="afspaces-notice" role="status">%s</p>', esc_html__( 'Bitte melde dich an.', 'afspaces' ) );
			}

			$actor = get_current_user_id();
			$all_spaces = $this->spaces->list_spaces();
			$my_invitations = $this->invitations->list_my_invitations( $actor );
			$my_requests = $this->requests->list_my_requests( $actor );
			$request_by_space = $this->latest_request_by_space( $my_requests );
			$invitation_by_space = $this->latest_invitation_by_space( $my_invitations );
			$search = isset( $_GET['wg_search'] ) ? sanitize_text_field( wp_unslash( $_GET['wg_search'] ) ) : '';
			$topic_filter = isset( $_GET['topic_id'] ) ? (int) $_GET['topic_id'] : 0;
			$topics = $this->working_groups->list_topics();

			$discoverable = array();
			foreach ( $all_spaces as $space ) {
				if ( 'active' !== $space->status ) {
					continue;
				}

				$forum = $this->asgaros->get_forum( $space->forum_id );
				if ( empty( $forum ) ) {
					continue;
				}

				$meta = $this->working_groups->get_metadata( $space->id );
				$is_manager = $this->spaces->is_manager( $space->id, $actor );
				$is_member = $this->asgaros->is_user_in_group( $actor, $space->primary_group_id );
				if ( ! $this->working_groups->can_view_group( $meta, $is_member, $is_manager ) ) {
					continue;
				}

				if ( '' !== $search ) {
					$haystack = strtolower( implode( ' ', array( (string) ( $forum['name'] ?? '' ), $meta->description, $meta->contact_text ) ) );
					if ( false === strpos( $haystack, strtolower( $search ) ) ) {
						continue;
					}
				}

				if ( $topic_filter > 0 && ! in_array( $topic_filter, $meta->topic_ids, true ) ) {
					continue;
				}

				$discoverable[] = array(
					'space'        => $space,
					'forum'        => $forum,
					'meta'         => $meta,
					'request'      => $request_by_space[ $space->id ] ?? null,
					'invitation'   => $invitation_by_space[ $space->id ] ?? null,
					'is_member'    => $is_member,
					'is_manager'   => $is_manager,
					'responsibles' => $this->working_groups->list_responsibles( $space->id ),
					'topics'       => $this->working_groups->topic_names( $meta ),
				);
			}

			ob_start();
			?>
			<section class="afspaces-discover" aria-labelledby="afspaces-discover-heading">
				<h2 id="afspaces-discover-heading"><?php echo esc_html( WorkingGroupTerminology::label( WorkingGroupTerminology::DISCOVER ) ); ?></h2>
				<?php echo $this->render_message(); ?>
				<p><?php echo esc_html__( 'Hier findest du sichtbare Arbeitsgruppen, erkennst deinen Status und kannst bei Bedarf einen Beitritt anfragen.', 'afspaces' ); ?></p>

				<form method="get" class="afspaces-filter afspaces-discover-filter" aria-label="<?php echo esc_attr__( 'Arbeitsgruppen filtern', 'afspaces' ); ?>">
					<input type="hidden" name="afspaces_view" value="<?php echo esc_attr( SpacesUrls::VIEW_DISCOVER ); ?>" />
					<label for="wg-search"><?php echo esc_html__( 'Suche', 'afspaces' ); ?></label>
					<input type="search" id="wg-search" name="wg_search" value="<?php echo esc_attr( $search ); ?>" />
					<?php if ( ! empty( $topics ) ) : ?>
						<label for="wg-topic"><?php echo esc_html__( 'Thema', 'afspaces' ); ?></label>
						<select id="wg-topic" name="topic_id">
							<option value="0"><?php echo esc_html__( 'Alle Themen', 'afspaces' ); ?></option>
							<?php foreach ( $topics as $topic ) : ?>
								<option value="<?php echo esc_attr( (string) $topic['id'] ); ?>" <?php selected( $topic_filter, (int) $topic['id'] ); ?>><?php echo esc_html( (string) $topic['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
					<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Filtern', 'afspaces' ); ?></button>
				</form>

				<?php if ( empty( $discoverable ) ) : ?>
					<p><?php echo esc_html__( 'Derzeit sind keine passenden Arbeitsgruppen für dich sichtbar.', 'afspaces' ); ?></p>
				<?php else : ?>
					<ul class="afspaces-space-list">
						<?php foreach ( $discoverable as $item ) : ?>
							<?php
							$space = $item['space'];
							$forum = $item['forum'];
							$meta = $item['meta'];
							$request = $item['request'];
							$invitation = $item['invitation'];
							$can_request = $this->working_groups->can_request_join( $meta, (bool) $item['is_member'], (bool) $item['is_manager'], null !== $request && 'pending' === $request->status, null !== $invitation && 'pending' === $invitation->effective_status() );
							?>
							<li class="afspaces-space-item afspaces-working-group-card" style="--afspaces-accent: <?php echo esc_attr( $meta->accent_color ); ?>;">
								<h3><a href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_GROUP, array( 'space_id' => $space->id ) ) ); ?>"><?php echo esc_html( (string) ( $forum['name'] ?? sprintf( 'Arbeitsgruppe #%d', $space->id ) ) ); ?></a></h3>
								<p><strong><?php echo esc_html__( 'Status:', 'afspaces' ); ?></strong> <span class="afspaces-tag"><?php echo esc_html( $this->status_label( $item ) ); ?></span></p>
								<?php if ( '' !== $meta->description ) : ?>
									<p><?php echo esc_html( $meta->description ); ?></p>
								<?php elseif ( ! empty( $forum['description'] ) ) : ?>
									<p><?php echo esc_html( (string) $forum['description'] ); ?></p>
								<?php endif; ?>
								<p><strong><?php echo esc_html__( 'Beitritt:', 'afspaces' ); ?></strong> <?php echo esc_html( $this->join_policy_label( $meta ) ); ?></p>
								<?php if ( ! empty( $item['responsibles'] ) ) : ?>
									<p><strong><?php echo esc_html__( 'Arbeitsgruppenverantwortliche:', 'afspaces' ); ?></strong> <?php echo esc_html( implode( ', ', array_map( static fn( array $responsible ): string => (string) $responsible['display_name'], $item['responsibles'] ) ) ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $item['topics'] ) ) : ?>
									<p><strong><?php echo esc_html__( 'Themen:', 'afspaces' ); ?></strong> <?php echo esc_html( implode( ', ', $item['topics'] ) ); ?></p>
								<?php endif; ?>

								<?php if ( null !== $request && 'pending' === $request->status ) : ?>
									<p><?php echo esc_html__( 'Deine Beitrittsanfrage ist offen.', 'afspaces' ); ?></p>
								<?php elseif ( null !== $request && 'approved' === $request->status ) : ?>
									<p><?php echo esc_html__( 'Deine letzte Anfrage wurde genehmigt.', 'afspaces' ); ?></p>
								<?php elseif ( null !== $request && 'rejected' === $request->status ) : ?>
									<p><?php echo esc_html__( 'Deine letzte Anfrage wurde abgelehnt. Du kannst erneut anfragen.', 'afspaces' ); ?></p>
								<?php endif; ?>

								<?php if ( $can_request ) : ?>
									<form method="post" class="afspaces-inline-form">
										<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
										<input type="hidden" name="afspaces_action" value="create_join_request" />
										<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
										<label>
											<span class="screen-reader-text"><?php echo esc_html__( 'Nachricht für Arbeitsgruppenverantwortliche', 'afspaces' ); ?></span>
											<input type="text" name="request_message" maxlength="500" placeholder="<?php echo esc_attr__( 'Optionale Nachricht', 'afspaces' ); ?>" />
										</label>
										<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Beitritt anfragen', 'afspaces' ); ?></button>
									</form>
								<?php else : ?>
									<p><?php echo esc_html( $this->availability_hint( $item, $meta ) ); ?></p>
								<?php endif; ?>
								<p><a class="afspaces-button afspaces-button-secondary" href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_GROUP, array( 'space_id' => $space->id ) ) ); ?>"><?php echo esc_html__( 'Arbeitsgruppe ansehen', 'afspaces' ); ?></a></p>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
			<?php

			return (string) ob_get_clean();
		}

		/**
		 * @param array<int,\AFSpaces\Domain\JoinRequest> $requests Anfragen.
		 * @return array<int,\AFSpaces\Domain\JoinRequest>
		 */
		private function latest_request_by_space( array $requests ): array {
			$map = array();
			foreach ( $requests as $request ) {
				if ( ! isset( $map[ $request->space_id ] ) ) {
					$map[ $request->space_id ] = $request;
				}
			}

			return $map;
		}

		/**
		 * @param array<int,mixed> $invitations Einladungen.
		 * @return array<int,mixed>
		 */
		private function latest_invitation_by_space( array $invitations ): array {
			$map = array();
			foreach ( $invitations as $invitation ) {
				if ( ! isset( $map[ $invitation->space_id ] ) ) {
					$map[ $invitation->space_id ] = $invitation;
				}
			}

			return $map;
		}

		/**
		 * @param array<string,mixed> $item View-Item.
		 * @return string
		 */
		private function status_label( array $item ): string {
			if ( ! empty( $item['is_manager'] ) ) {
				return __( 'Arbeitsgruppenverantwortlich', 'afspaces' );
			}

			if ( ! empty( $item['is_member'] ) ) {
				return __( 'Mitglied', 'afspaces' );
			}

			if ( ! empty( $item['invitation'] ) && 'pending' === $item['invitation']->effective_status() ) {
				return __( 'Eingeladen', 'afspaces' );
			}

			if ( ! empty( $item['request'] ) && 'pending' === $item['request']->status ) {
				return __( 'Anfrage offen', 'afspaces' );
			}

			if ( ! empty( $item['request'] ) && 'rejected' === $item['request']->status ) {
				return __( 'Anfrage abgelehnt', 'afspaces' );
			}

			return __( 'Keine Zugehörigkeit', 'afspaces' );
		}

		/**
		 * @param WorkingGroupMeta $meta Metadaten.
		 * @return string
		 */
		private function join_policy_label( WorkingGroupMeta $meta ): string {
			if ( ! $meta->join_requests_enabled ) {
				return __( 'Beitrittsanfragen deaktiviert', 'afspaces' );
			}

			switch ( $meta->join_policy ) {
				case WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY:
					return __( 'Nur auf Einladung', 'afspaces' );
				case WorkingGroupMeta::JOIN_POLICY_CLOSED:
					return __( 'Geschlossen', 'afspaces' );
				default:
					return __( 'Per Anfrage beitretbar', 'afspaces' );
			}
		}

		/**
		 * @param array<string,mixed> $item View-Item.
		 * @param WorkingGroupMeta    $meta Metadaten.
		 * @return string
		 */
		private function availability_hint( array $item, WorkingGroupMeta $meta ): string {
			if ( ! empty( $item['is_manager'] ) ) {
				return __( 'Du verwaltest diese Arbeitsgruppe bereits.', 'afspaces' );
			}

			if ( ! empty( $item['is_member'] ) ) {
				return __( 'Du bist bereits Mitglied dieser Arbeitsgruppe.', 'afspaces' );
			}

			if ( ! empty( $item['invitation'] ) && 'pending' === $item['invitation']->effective_status() ) {
				return __( 'Für dich liegt bereits eine Einladung vor.', 'afspaces' );
			}

			if ( ! empty( $item['request'] ) && 'pending' === $item['request']->status ) {
				return __( 'Deine Beitrittsanfrage ist bereits offen.', 'afspaces' );
			}

			if ( WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY === $meta->join_policy ) {
				return __( 'Diese Arbeitsgruppe ist sichtbar, aber nur per Einladung beitretbar.', 'afspaces' );
			}

			return __( 'Diese Arbeitsgruppe ist sichtbar, aber aktuell nicht beitretbar.', 'afspaces' );
		}

		/**
		 * @return string
		 */
		private function render_message(): string {
			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}

			if ( empty( $_SESSION['afspaces_message'] ) || ! is_array( $_SESSION['afspaces_message'] ) ) {
				return '';
			}

			$type = isset( $_SESSION['afspaces_message']['type'] ) ? (string) $_SESSION['afspaces_message']['type'] : 'success';
			$text = isset( $_SESSION['afspaces_message']['message'] ) ? (string) $_SESSION['afspaces_message']['message'] : '';
			unset( $_SESSION['afspaces_message'] );

			if ( '' === $text ) {
				return '';
			}

			$class = 'error' === $type ? 'afspaces-notice afspaces-notice-error' : 'afspaces-notice afspaces-notice-success';
			$role  = 'error' === $type ? 'alert' : 'status';

			return sprintf( '<div class="%1$s" role="%2$s">%3$s</div>', esc_attr( $class ), esc_attr( $role ), esc_html( $text ) );
		}
	}
}
