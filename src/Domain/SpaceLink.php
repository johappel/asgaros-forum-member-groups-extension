<?php
/**
 * Domain-Modell für einen Toolbox-Link einer Arbeitsgruppe.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Domain;

if ( ! class_exists( 'AFSpaces\\Domain\\SpaceLink' ) ) {

	/**
	 * Ein konfigurierbarer Link im Werkzeugkasten (Toolbox) einer Arbeitsgruppe.
	 */
	class SpaceLink {

		public int $id;
		public int $space_id;
		public string $title;
		public string $url;
		public string $description;
		public string $icon;
		public int $sort_order;
		public bool $open_new_tab;
		public string $created_at;
		public string $updated_at;

		/**
		 * @param array<string,mixed> $data Rohdaten aus der Datenbank.
		 */
		public function __construct( array $data ) {
			$this->id           = (int) ( $data['id'] ?? 0 );
			$this->space_id     = (int) ( $data['space_id'] ?? 0 );
			$this->title        = (string) ( $data['title'] ?? '' );
			$this->url          = (string) ( $data['url'] ?? '' );
			$this->description  = (string) ( $data['description'] ?? '' );
			$this->icon         = (string) ( $data['icon'] ?? '' );
			$this->sort_order   = (int) ( $data['sort_order'] ?? 0 );
			$this->open_new_tab = ! empty( $data['open_new_tab'] );
			$this->created_at   = (string) ( $data['created_at'] ?? '' );
			$this->updated_at   = (string) ( $data['updated_at'] ?? '' );
		}

		/**
		 * Erlaubte Icon-Schlüssel für Toolbox-Links.
		 *
		 * @return array<string,string> Schlüssel => Beschriftung.
		 */
		public static function icon_options(): array {
			return array(
				''          => __( 'Kein Symbol', 'afspaces' ),
				'link'      => __( 'Verknüpfung', 'afspaces' ),
				'file'      => __( 'Dokument', 'afspaces' ),
				'folder'    => __( 'Ordner', 'afspaces' ),
				'calendar'  => __( 'Kalender', 'afspaces' ),
				'download'  => __( 'Download', 'afspaces' ),
				'video'     => __( 'Video', 'afspaces' ),
				'book'      => __( 'Wissen', 'afspaces' ),
				'globe'     => __( 'Website', 'afspaces' ),
				'toolbox'   => __( 'Werkzeug', 'afspaces' ),
			);
		}

		/**
		 * Font-Awesome-Klasse für einen Icon-Schlüssel.
		 *
		 * @param string $icon Icon-Schlüssel.
		 * @return string Leerer String, wenn kein Symbol gewählt wurde.
		 */
		public static function icon_class( string $icon ): string {
			$map = array(
				'link'     => 'fas fa-link',
				'file'     => 'fas fa-file-alt',
				'folder'   => 'fas fa-folder',
				'calendar' => 'fas fa-calendar-alt',
				'download' => 'fas fa-download',
				'video'    => 'fas fa-video',
				'book'     => 'fas fa-book',
				'globe'    => 'fas fa-globe',
				'toolbox'  => 'fas fa-toolbox',
			);

			return $map[ $icon ] ?? '';
		}

		/**
		 * Normalisiert einen Icon-Schlüssel auf eine erlaubte Auswahl.
		 *
		 * @param string $icon Roh-Eingabe.
		 * @return string
		 */
		public static function sanitize_icon( string $icon ): string {
			$icon = strtolower( trim( $icon ) );
			return array_key_exists( $icon, self::icon_options() ) ? $icon : '';
		}

		/**
		 * Prüft und normalisiert einen Titel.
		 *
		 * @param string $title Roh-Titel.
		 * @return string Bereinigter Titel (kann leer sein).
		 */
		public static function sanitize_title( string $title ): string {
			$title = trim( preg_replace( '/\s+/u', ' ', $title ) ?? '' );
			if ( function_exists( 'sanitize_text_field' ) ) {
				$title = (string) sanitize_text_field( $title );
			}
			return $title;
		}

		/**
		 * Validiert und normalisiert eine URL.
		 *
		 * Erlaubt ausschließlich absolute http(s)-URLs sowie site-relative
		 * Pfade, die mit "/" beginnen. Andere Schemata (z. B. `javascript:`
		 * oder `data:`) werden verworfen, um XSS über gespeicherte Links zu
		 * verhindern.
		 *
		 * @param string $url Roh-URL.
		 * @return string Bereinigte URL oder leerer String bei ungültiger Eingabe.
		 */
		public static function sanitize_url( string $url ): string {
			$url = trim( $url );
			if ( '' === $url ) {
				return '';
			}

			// Site-relative Pfade sind erlaubt.
			if ( '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
				return function_exists( 'esc_url_raw' ) ? (string) esc_url_raw( $url ) : $url;
			}

			$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				return '';
			}

			$host = parse_url( $url, PHP_URL_HOST );
			if ( empty( $host ) ) {
				return '';
			}

			if ( function_exists( 'esc_url_raw' ) ) {
				$clean = (string) esc_url_raw( $url, array( 'http', 'https' ) );
				return $clean;
			}

			return $url;
		}
	}
}
