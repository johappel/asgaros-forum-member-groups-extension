<?php
/**
 * Prüft die Laufzeitvoraussetzungen (insbesondere Asgaros Forum).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Core;

if ( ! class_exists( 'AFSpaces\\Core\\Requirements' ) ) {

	/**
	 * Verwaltet Plugin-Abhängigkeiten und Mindestversionen.
	 */
	class Requirements {

		/**
		 * Ergebnis der letzten Prüfung.
		 *
		 * @var bool|null
		 */
		private ?bool $is_met = null;

		/**
		 * Fehlermeldungen der letzten Prüfung.
		 *
		 * @var string[]
		 */
		private array $messages = array();

		/**
		 * Prüft alle Voraussetzungen.
		 *
		 * @return bool
		 */
		public function check(): bool {
			$this->messages = array();

			if ( ! $this->is_asgaros_active() ) {
				$this->messages[] = __( 'Asgaros Forum ist nicht installiert oder aktiviert. Asgaros Forum Spaces benötigt Asgaros Forum.', 'afspaces' );
			} elseif ( ! $this->is_asgaros_version_supported() ) {
				$this->messages[] = sprintf(
					/* translators: 1: gefundene Version, 2: mindestens benötigte Version */
					__( 'Asgaros Forum Version %1$s wird nicht unterstützt. Bitte aktualisiere auf mindestens Version %2$s.', 'afspaces' ),
					$this->get_asgaros_version() ?? __( 'unbekannt', 'afspaces' ),
					AFSPACES_MIN_ASGAROS_VERSION
				);
			}

			$this->is_met = empty( $this->messages );
			return $this->is_met;
		}

		/**
		 * Gibt zurück, ob Asgaros Forum aktiv ist.
		 *
		 * @return bool
		 */
		public function is_asgaros_active(): bool {
			return class_exists( 'AsgarosForum', false )
				|| function_exists( 'is_plugin_active' ) && is_plugin_active( 'asgaros-forum/asgaros-forum.php' );
		}

		/**
		 * Liest die installierte Asgaros-Version, sofern verfügbar.
		 *
		 * @return string|null
		 */
		public function get_asgaros_version(): ?string {
			if ( defined( 'ASGAROS_FORUM_VERSION' ) ) {
				return (string) ASGAROS_FORUM_VERSION;
			}

			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$file = WP_PLUGIN_DIR . '/asgaros-forum/asgaros-forum.php';
			if ( ! file_exists( $file ) ) {
				return null;
			}

			$data = get_plugin_data( $file, false, false );
			return isset( $data['Version'] ) ? (string) $data['Version'] : null;
		}

		/**
		 * Prüft, ob die gefundene Asgaros-Version unterstützt wird.
		 *
		 * @return bool
		 */
		public function is_asgaros_version_supported(): bool {
			$version = $this->get_asgaros_version();
			if ( null === $version ) {
				return false;
			}
			return version_compare( $version, AFSPACES_MIN_ASGAROS_VERSION, '>=' );
		}

		/**
		 * Gibt die gesammelten Fehlermeldungen zurück.
		 *
		 * @return string[]
		 */
		public function get_messages(): array {
			return $this->messages;
		}

		/**
		 * Zeigt eine Admin-Mitteilung mit den Fehlern an.
		 *
		 * @return void
		 */
		public function show_admin_notice(): void {
			add_action(
				'admin_notices',
				function (): void {
					foreach ( $this->get_messages() as $message ) {
						printf(
							'<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
							esc_html__( 'Asgaros Forum Spaces', 'afspaces' ),
							esc_html( $message )
						);
					}
				}
			);
		}
	}
}
