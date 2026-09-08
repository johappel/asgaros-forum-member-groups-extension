<?php
/**
 * Verwaltung der erlaubten Upload-Dateitypen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Core;

if ( ! class_exists( 'AFSpaces\\Core\\FileUploadSettings' ) ) {

	/**
	 * Registriert zusätzliche Dateiendungen für Uploads.
	 *
	 * Erweitert sowohl WordPress `upload_mimes` (Media Library) als auch
	 * Asgaros Forum `allowed_filetypes` (Forum-Anhänge).
	 *
	 * @since 2.11.1
	 */
	final class FileUploadSettings {

		/**
		 * Initialisiert die Upload-Einstellungen.
		 *
		 * @return void
		 */
		public function init(): void {
			add_filter( 'upload_mimes', array( $this, 'allow_additional_mimes' ), 10 );
			// Priorität 5 läuft vor AsgarosForumUploads::initialize() (init/10), das die Liste cached.
			add_action( 'init', array( $this, 'extend_asgaros_filetypes' ), 5 );
		}

		/**
		 * Kombiniert die WordPress-Standard-MIME-Typen mit AFSpaces-Erweiterungen.
		 *
		 * @param array<string, string> $mimes WordPress-MIME-Typen.
		 * @return array<string, string>
		 */
		public function allow_additional_mimes( array $mimes ): array {
			$additional = $this->get_allowed_mimes();
			return array_merge( $mimes, $additional );
		}

		/**
		 * Erweitert die Asgaros-Forum-Optionen mit zusätzlichen Dateiendungen.
		 *
		 * Asgaros speichert erlaubte Dateitypen als komma-separierte Liste in der Option
		 * `allowed_filetypes`. Diese Methode wird auf `init` (Priorität 20) aufgerufen,
		 * nachdem Asgaros seine Optionen geladen hat, und fügt die AFSpaces-Endungen hinzu.
		 *
		 * @global object $asgarosforum
		 * @return void
		 */
		public function extend_asgaros_filetypes(): void {
			global $asgarosforum;

			if ( ! isset( $asgarosforum ) || ! is_object( $asgarosforum ) ) {
				return;
			}

			if ( ! isset( $asgarosforum->options['allowed_filetypes'] ) ) {
				return;
			}

			$additional = $this->get_allowed_extensions();

			// Sichere existierende Endungen.
			$existing = array_map(
				'trim',
				array_filter( explode( ',', (string) $asgarosforum->options['allowed_filetypes'] ) )
			);

			// Kombiniere und dedupliziere.
			$combined = array_unique( array_merge( $existing, $additional ) );
			sort( $combined );

			// Update Asgaros-Option.
			$asgarosforum->options['allowed_filetypes'] = implode( ',', $combined );
		}

		/**
		 * Gibt die AFSpaces-Standard-Dateitypen zurück, erweiterbar über Filter.
		 *
		 * Kann über den Filter `afspaces_additional_upload_mimes` erweitert werden.
		 *
		 * @return array<string, string> Dateiendung => MIME-Typ.
		 */
		public function get_allowed_mimes(): array {
			$mimes = array(
				'md'   => 'text/markdown',
				// MS Office
				'doc'  => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'xls'  => 'application/vnd.ms-excel',
				'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'ppt'  => 'application/vnd.ms-powerpoint',
				'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				// LibreOffice
				'odt'  => 'application/vnd.oasis.opendocument.text',
				'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
				'odp'  => 'application/vnd.oasis.opendocument.presentation',
			);

			/**
			 * Erlaubt die Erweiterung der Upload-MIME-Typen.
			 *
			 * @param array<string, string> $mimes Dateiendung => MIME-Typ.
			 * @return array<string, string>
			 */
			return (array) apply_filters( 'afspaces_additional_upload_mimes', $mimes );
		}

		/**
		 * Gibt nur die Endungen (Schlüssel) der erlaubten Dateitypen zurück.
		 *
		 * @return array<int, string> Dateiendungen (z.B. ['md', 'pdf']).
		 */
		private function get_allowed_extensions(): array {
			return array_keys( $this->get_allowed_mimes() );
		}
	}
}
