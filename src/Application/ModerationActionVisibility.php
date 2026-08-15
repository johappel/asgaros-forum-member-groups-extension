<?php
/**
 * Entscheidet, ob eine lokale Moderationsaktion zusätzlich zur nativen
 * Asgaros-Oberfläche sichtbar sein soll.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;

if ( ! class_exists( 'AFSpaces\\Application\\ModerationActionVisibility' ) ) {

	/**
	 * Trennt lokale Objektberechtigung von der UI-Deduplizierung.
	 */
	final class ModerationActionVisibility {

		private AsgarosAdapterInterface $asgaros;

		public function __construct( AsgarosAdapterInterface $asgaros ) {
			$this->asgaros = $asgaros;
		}

		/**
		 * Eine lokale Aktion wird nur angezeigt, wenn sie lokal erlaubt ist und
		 * Asgaros nicht bereits denselben Bedienweg anbietet.
		 *
		 * @param string $action          Eine MODERATION_ACTION_ Konstante.
		 * @param bool   $local_allowed   Ergebnis der AFSpaces-Policy.
		 * @param int    $user_id         Aktuelle Benutzer-ID.
		 * @param int    $topic_id        Themen-ID, falls relevant.
		 * @param int    $post_id         Beitrags-ID, falls relevant.
		 * @return bool
		 */
		public function should_render_local_action( string $action, bool $local_allowed, int $user_id, int $topic_id = 0, int $post_id = 0 ): bool {
			if ( ! $local_allowed || $user_id < 1 ) {
				return false;
			}

			return ! $this->asgaros->can_perform_moderation_action( $action, $user_id, $topic_id, $post_id );
		}
	}
}
