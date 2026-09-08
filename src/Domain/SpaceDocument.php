<?php
/**
 * Domain-Modell für ein Arbeitsgruppen-Dokument.
 *
 * Ein Dokument ist kein neuer Upload-Typ: Die Datei bleibt ein Asgaros-Anhang
 * eines Forumbeitrags. AFSpaces speichert lediglich die zusätzliche Bedeutung
 * „Gruppendokument“ mit Ordnung (Thema), Sichtbarkeit und Darstellung.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Domain;

if ( ! class_exists( 'AFSpaces\\Domain\\SpaceDocument' ) ) {

	/**
	 * Kennzeichnet einen bestehenden Asgaros-Anhang als Dokument einer Arbeitsgruppe.
	 */
	class SpaceDocument {

		/**
		 * Nur Mitglieder der Arbeitsgruppe.
		 */
		public const VISIBILITY_MEMBERS = 'members';

		/**
		 * Alle angemeldeten Nutzer.
		 */
		public const VISIBILITY_AUTHENTICATED = 'authenticated';

		/**
		 * Auch nicht angemeldete Besucher (vorbereitet, standardmäßig deaktiviert).
		 */
		public const VISIBILITY_PUBLIC = 'public';

		private const MAX_TITLE_LENGTH = 200;
		private const MAX_TOPIC_LENGTH = 120;

		public int $id;
		public int $space_id;
		public int $asgaros_post_id;
		public string $filename;
		public string $title;
		public string $document_topic;
		public string $visibility;
		public int $created_by;
		public string $created_at;
		public string $updated_at;

		/**
		 * @param array<string,mixed> $data Rohdaten aus der Datenbank.
		 */
		public function __construct( array $data ) {
			$this->id              = (int) ( $data['id'] ?? 0 );
			$this->space_id        = (int) ( $data['space_id'] ?? 0 );
			$this->asgaros_post_id = (int) ( $data['asgaros_post_id'] ?? 0 );
			$this->filename        = (string) ( $data['filename'] ?? '' );
			$this->title           = (string) ( $data['title'] ?? '' );
			$this->document_topic  = (string) ( $data['document_topic'] ?? '' );
			$this->visibility      = self::sanitize_visibility( (string) ( $data['visibility'] ?? self::VISIBILITY_MEMBERS ) );
			$this->created_by      = (int) ( $data['created_by'] ?? 0 );
			$this->created_at      = (string) ( $data['created_at'] ?? '' );
			$this->updated_at      = (string) ( $data['updated_at'] ?? '' );
		}

		/**
		 * Gibt das Modell als Array zurück.
		 *
		 * @return array<string,mixed>
		 */
		public function to_array(): array {
			return array(
				'id'              => $this->id,
				'space_id'        => $this->space_id,
				'asgaros_post_id' => $this->asgaros_post_id,
				'filename'        => $this->filename,
				'title'           => $this->title,
				'document_topic'  => $this->document_topic,
				'visibility'      => $this->visibility,
				'created_by'      => $this->created_by,
				'created_at'      => $this->created_at,
				'updated_at'      => $this->updated_at,
			);
		}

		/**
		 * Erlaubte Sichtbarkeiten für die Auswahl im Formular.
		 *
		 * `public` ist bewusst nicht enthalten, solange die öffentliche
		 * Sichtbarkeit nicht über {@see self::public_enabled()} freigeschaltet ist.
		 *
		 * @return array<string,string> Schlüssel => Beschriftung.
		 */
		public static function visibility_options(): array {
			$options = array(
				self::VISIBILITY_MEMBERS       => __( 'Arbeitsgruppenmitglieder', 'afspaces' ),
				self::VISIBILITY_AUTHENTICATED => __( 'Alle angemeldeten Nutzer', 'afspaces' ),
			);

			if ( self::public_enabled() ) {
				$options[ self::VISIBILITY_PUBLIC ] = __( 'Alle Besucher (öffentlich)', 'afspaces' );
			}

			return $options;
		}

		/**
		 * Gibt an, ob die öffentliche Sichtbarkeit freigeschaltet ist.
		 *
		 * Standardmäßig deaktiviert; kann über den Filter
		 * `afspaces_documents_public_enabled` aktiviert werden.
		 *
		 * @return bool
		 */
		public static function public_enabled(): bool {
			return (bool) apply_filters( 'afspaces_documents_public_enabled', false );
		}

		/**
		 * Normalisiert eine Sichtbarkeit auf einen erlaubten Wert.
		 *
		 * Nicht freigeschaltete öffentliche Sichtbarkeit wird auf den
		 * restriktiveren Wert „nur angemeldete Nutzer“ zurückgestuft.
		 *
		 * @param string $visibility Roh-Eingabe.
		 * @return string
		 */
		public static function sanitize_visibility( string $visibility ): string {
			$visibility = strtolower( trim( $visibility ) );

			if ( self::VISIBILITY_PUBLIC === $visibility ) {
				return self::public_enabled() ? self::VISIBILITY_PUBLIC : self::VISIBILITY_AUTHENTICATED;
			}

			if ( self::VISIBILITY_AUTHENTICATED === $visibility ) {
				return self::VISIBILITY_AUTHENTICATED;
			}

			return self::VISIBILITY_MEMBERS;
		}

		/**
		 * Prüft und normalisiert einen Dokumenttitel.
		 *
		 * @param string $title Roh-Titel.
		 * @return string Bereinigter Titel (kann leer sein).
		 */
		public static function sanitize_title( string $title ): string {
			$title = trim( preg_replace( '/\s+/u', ' ', $title ) ?? '' );
			if ( function_exists( 'sanitize_text_field' ) ) {
				$title = (string) sanitize_text_field( $title );
			}

			if ( function_exists( 'mb_substr' ) ) {
				return mb_substr( $title, 0, self::MAX_TITLE_LENGTH );
			}

			return substr( $title, 0, self::MAX_TITLE_LENGTH );
		}

		/**
		 * Prüft und normalisiert ein Dokumentthema.
		 *
		 * @param string $topic Roh-Thema.
		 * @return string Bereinigtes Thema (kann leer sein).
		 */
		public static function sanitize_topic( string $topic ): string {
			$topic = trim( preg_replace( '/\s+/u', ' ', $topic ) ?? '' );
			if ( function_exists( 'sanitize_text_field' ) ) {
				$topic = (string) sanitize_text_field( $topic );
			}

			if ( function_exists( 'mb_substr' ) ) {
				return mb_substr( $topic, 0, self::MAX_TOPIC_LENGTH );
			}

			return substr( $topic, 0, self::MAX_TOPIC_LENGTH );
		}

		/**
		 * Leitet einen Standardtitel aus einem Dateinamen ab (ohne Endung).
		 *
		 * @param string $filename Dateiname.
		 * @return string
		 */
		public static function default_title_from_filename( string $filename ): string {
			$base = $filename;
			if ( function_exists( 'wp_basename' ) ) {
				$base = (string) wp_basename( $filename );
			} else {
				$base = basename( $filename );
			}

			$dot = strrpos( $base, '.' );
			if ( false !== $dot && $dot > 0 ) {
				$base = substr( $base, 0, $dot );
			}

			$base = trim( str_replace( array( '_', '-' ), ' ', $base ) );

			return self::sanitize_title( $base );
		}

		/**
		 * Ermittelt die Dateiendung (klein geschrieben, ohne Punkt).
		 *
		 * @param string $filename Dateiname.
		 * @return string
		 */
		public static function file_extension( string $filename ): string {
			$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
			return preg_replace( '/[^a-z0-9]/', '', $ext ) ?? '';
		}
	}
}
