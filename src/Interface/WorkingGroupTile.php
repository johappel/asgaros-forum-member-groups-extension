<?php
/**
 * Wiederverwendbare Arbeitsgruppen-Kachel (Discover + Profil).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Application\UserIdentityService;

if ( ! class_exists( 'AFSpaces\\Interface\\WorkingGroupTile' ) ) {

	/**
	 * Rendert eine einheitliche Arbeitsgruppen-Kachel mit Mitglieder-Accordion.
	 *
	 * Bewusst ohne Status- und Beitritts-Policy-Ausgabe und ohne Beitritts-
	 * formular: Das Beitreten geschieht ausschliesslich in der Detailansicht
	 * (VIEW_GROUP). Die Kachel dient der Uebersicht und Wiedererkennung.
	 */
	final class WorkingGroupTile {

		/**
		 * @param array<string,mixed> $args {
		 *     name:         string  Anzeigename der Arbeitsgruppe.
		 *     url:          string  Link zur Detailansicht.
		 *     description:  string  Kurzbeschreibung.
		 *     icon:         string  FontAwesome-Icon-Klasse.
		 *     accent:       string  Akzentfarbe (Hex).
		 *     member_count: int     Anzahl Mitglieder.
		 *     members:      array   Liste [ user_id, display_name ].
		 *     role:         string  Optionales Rollen-Badge (z. B. "Mitglied").
		 *     topics:       array   Optionale Themenliste (string[]).
		 *     forum_url:    string  Optionaler Link zum zugehoerigen Forum.
		 *     can_view_forum: bool  Ob der Forum-Button angezeigt werden darf.
		 * }
		 * @return string
		 */
		public static function render( array $args, ?UserIdentityService $identity = null ): string {
			$identity     = $identity ?: new UserIdentityService();
			$name         = (string) ( $args['name'] ?? '' );
			$url          = (string) ( $args['url'] ?? '' );
			$description  = (string) ( $args['description'] ?? '' );
			$icon         = (string) ( $args['icon'] ?? 'fas fa-users' );
			$accent       = (string) ( $args['accent'] ?? '' );
			$member_count = (int) ( $args['member_count'] ?? 0 );
			$members      = isset( $args['members'] ) && is_array( $args['members'] ) ? $args['members'] : array();
			$role         = (string) ( $args['role'] ?? '' );
			$topics       = isset( $args['topics'] ) && is_array( $args['topics'] ) ? $args['topics'] : array();
			$forum_url    = (string) ( $args['forum_url'] ?? '' );
			$can_view_forum = ! empty( $args['can_view_forum'] ) && '' !== $forum_url;

			ob_start();
			?>
			<li class="afspaces-space-item afspaces-group-tile" style="--afspaces-accent: <?php echo esc_attr( $accent ); ?>;">
				<div class="afspaces-group-tile-head">
					<span class="afspaces-working-group-icon afspaces-group-avatar" aria-hidden="true"><i class="<?php echo esc_attr( $icon ); ?>"></i></span>
					<div class="afspaces-group-tile-heading">
						<h3 class="afspaces-group-tile-title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a></h3>
						<?php if ( '' !== $role ) : ?>
							<span class="afspaces-tag afspaces-role-tag"><?php echo esc_html( $role ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( '' !== $description ) : ?>
					<p class="afspaces-group-tile-desc"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $topics ) ) : ?>
					<p class="afspaces-group-tile-topics"><?php echo esc_html( implode( ', ', array_map( 'strval', $topics ) ) ); ?></p>
				<?php endif; ?>

				<details class="afspaces-members-accordion">
					<summary class="afspaces-members-summary">
						<span class="afspaces-members-summary-count"><?php echo esc_html( WorkingGroupTerminology::membership_count( $member_count ) ); ?></span>
						<span class="afspaces-members-summary-hint" aria-hidden="true"></span>
					</summary>
					<?php if ( empty( $members ) ) : ?>
						<p class="afspaces-members-empty"><?php echo esc_html__( 'Keine Mitglieder sichtbar.', 'afspaces' ); ?></p>
					<?php else : ?>
						<ul class="afspaces-members-list afspaces-members-grid">
							<?php foreach ( $members as $member ) : ?>
								<?php $profile_url = SpacesUrls::hub_url( SpacesUrls::VIEW_PROFILE, array( 'user_id' => (int) $member['user_id'] ) ); ?>
								<li class="afspaces-member-card">
									<a class="afspaces-member-link" href="<?php echo esc_url( $profile_url ); ?>">
						<span class="afspaces-member-avatar" aria-hidden="true"><?php echo $identity->get_avatar_html( (int) $member['user_id'], 40 ); ?></span>
						<span class="afspaces-member-name"><?php echo esc_html( $identity->get_display_name( (int) $member['user_id'] ) ?: (string) $member['display_name'] ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</details>

				<div class="afspaces-group-tile-actions">
					<a class="afspaces-button afspaces-button-secondary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'Arbeitsgruppe', 'afspaces' ); ?></a>
					<?php if ( $can_view_forum ) : ?>
						<a class="afspaces-button" href="<?php echo esc_url( $forum_url ); ?>"><?php echo esc_html__( 'Forum anzeigen', 'afspaces' ); ?></a>
					<?php endif; ?>
				</div>
			</li>
			<?php
			return (string) ob_get_clean();
		}
	}
}
