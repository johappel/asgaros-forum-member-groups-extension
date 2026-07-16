<?php
/**
 * Zentrale Plugin-Klasse.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces;

use AFSpaces\Adapters\Asgaros\AsgarosAdapter;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\MemberService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\Requirements;
use AFSpaces\Domain\SpacePolicy;
use AFSpaces\Interface\FrontendController;
use AFSpaces\Interface\MembersView;
use AFSpaces\Interface\RestController;

if ( ! class_exists( 'AFSpaces\\Plugin' ) ) {

	/**
	 * Hauptklasse des Plugins.
	 */
	final class Plugin {

		/**
		 * Singleton-Instanz.
		 *
		 * @var self|null
		 */
		private static ?self $instance = null;

		/**
		 * Requirements-Prüfer.
		 *
		 * @var Requirements
		 */
		private Requirements $requirements;

		/**
		 * Gibt die Singleton-Instanz zurück.
		 *
		 * @return self
		 */
		public static function instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Konstruktor (private wegen Singleton).
		 */
		private function __construct() {
			$this->requirements = new Requirements();
		}

		/**
		 * Initialisiert das Plugin.
		 *
		 * @return void
		 */
		public static function init(): void {
			$plugin = self::instance();

			if ( ! $plugin->requirements->check() ) {
				$plugin->requirements->show_admin_notice();
				return;
			}

			$spaces  = new SpaceRepository();
			$asgaros = new AsgarosAdapter( $plugin->requirements );
			$policy  = new SpacePolicy( $spaces );
			$audit   = new AuditRepository();
			$members = new MemberService( $spaces, $asgaros, $policy, $audit );

			$frontend = new FrontendController( $spaces, $asgaros, $members );
			$frontend->init();

			// Mitgliederansicht in denselben Shortcode integrieren.
			add_shortcode(
				'afspaces_members',
				static function () use ( $spaces, $asgaros, $members ): string {
					if ( ! isset( $_GET['space_id'] ) ) {
						return '';
					}
					$view = new MembersView( $spaces, $asgaros, $members );
					return $view->render( (int) $_GET['space_id'] );
				}
			);

			// REST-API registrieren.
			$rest = new RestController( $spaces, $asgaros, $members );
			add_action( 'rest_api_init', array( $rest, 'register_routes' ) );
		}

		/**
		 * Gibt den Requirements-Prüfer zurück.
		 *
		 * @return Requirements
		 */
		public function get_requirements(): Requirements {
			return $this->requirements;
		}
	}
}
