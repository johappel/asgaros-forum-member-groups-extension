<?php
/**
 * Zentrale Plugin-Klasse.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces;

use AFSpaces\Core\Requirements;

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

			// Weitere Initialisierung folgt in späteren MVP-Schritten.
			// Hier wird bewusst noch keine Asgaros-spezifische Logik aktiviert.
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
