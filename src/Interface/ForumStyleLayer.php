<?php
/**
 * Asgaros-spezifischer AFSpaces-Style-Layer.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

if ( ! class_exists( 'AFSpaces\\Interface\\ForumStyleLayer' ) ) {

	/**
	 * Lädt AFSpaces-Overrides hinter den Asgaros-Forumstyles.
	 *
	 * Asgaros erzeugt seine custom.css selbst. Deshalb wird diese Datei weder
	 * verändert noch ersetzt; AFSpaces ergänzt stattdessen einen eigenen Layer.
	 */
	final class ForumStyleLayer {

		private const HANDLE = 'afspaces-forum-overrides';

		/**
		 * Bekannte Asgaros-Handles. Nicht registrierte Handles werden ignoriert,
		 * damit unterschiedliche Asgaros-Versionen nicht durch eine fehlende
		 * Dependency blockiert werden.
		 *
		 * @var list<string>
		 */
		private const ASGAROS_STYLE_HANDLES = array(
			'asgarosforum-style',
			'asgarosforum-custom-style',
		);

		/**
		 * Registriert den späten WordPress-Enqueue-Hook.
		 *
		 * @return void
		 */
		public function init(): void {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 999 );
		}

		/**
		 * Enqueued den Override-Layer ausschließlich auf Asgaros-Forumseiten.
		 *
		 * @return void
		 */
		public function enqueue(): void {
			if ( ! function_exists( 'has_shortcode' )
				|| ! function_exists( 'get_post_field' )
				|| ! function_exists( 'get_the_ID' ) ) {
				return;
			}

			$post_id = (int) get_the_ID();
			if ( $post_id < 1 || ! has_shortcode( (string) get_post_field( 'post_content', $post_id ), 'forum' ) ) {
				return;
			}

			$dependencies = array();
			if ( function_exists( 'wp_styles' ) ) {
				$styles = wp_styles();
				if ( is_object( $styles ) && isset( $styles->registered ) && is_array( $styles->registered ) ) {
					$dependencies = self::get_registered_dependencies( array_keys( $styles->registered ) );
				}
			}

			wp_register_style(
				self::HANDLE,
				AFSPACES_URL . 'assets/afspaces-forum-overrides.css',
				$dependencies,
				AFSPACES_VERSION
			);
			wp_enqueue_style( self::HANDLE );
		}

		/**
		 * Ermittelt nur tatsächlich registrierte Dependencies.
		 *
		 * Die späte Priorität stellt zusätzlich sicher, dass nicht erkannte
		 * Asgaros-Handles bereits vor diesem Layer in die Styles-Warteschlange
		 * gelangt sind.
		 *
		 * @param list<string> $registered_handles Registrierte WordPress-Handles.
		 * @return list<string>
		 */
		private static function get_registered_dependencies( array $registered_handles ): array {
			$registered = array_fill_keys( $registered_handles, true );
			$dependencies = array();

			foreach ( array_merge( array( 'afspaces-frontend' ), self::ASGAROS_STYLE_HANDLES ) as $handle ) {
				if ( isset( $registered[ $handle ] ) ) {
					$dependencies[] = $handle;
				}
			}

			return $dependencies;
		}
	}
}
