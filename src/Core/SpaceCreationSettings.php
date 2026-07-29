<?php
/**
 * Globale Richtlinien für die Selbstgründung von Räumen (MVP 4).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Core;

if ( ! class_exists( 'AFSpaces\\Core\\SpaceCreationSettings' ) ) {

	/**
	 * Wertobjekt der administrativen Gründungsrichtlinien.
	 *
	 * Der Konstruktor nimmt ein reines Array entgegen, damit die Klasse ohne
	 * WordPress unit-getestet werden kann. Zum Laden/Speichern aus der
	 * WordPress-Option dienen {@see load()} und {@see save()}.
	 */
	final class SpaceCreationSettings {

		public const OPTION = 'afspaces_creation_options';

		public const VISIBILITY_PRIVATE   = 'private';
		public const VISIBILITY_PROTECTED = 'protected';
		public const VISIBILITY_PUBLIC    = 'public';

		public bool $enabled;

		/** @var string[] */
		public array $allowed_roles;

		public int $max_spaces_per_user;

		/** @var string[] */
		public array $allowed_visibilities;

		/** @var string[] */
		public array $regular_visibilities;

		public bool $require_approval;

		public int $name_min_length;

		public int $name_max_length;

		public int $description_max_length;

		/** @var string[] */
		public array $reserved_names;

		public int $rate_limit_seconds;

		public string $default_icon;

		/**
		 * @param array<string,mixed> $data Rohdaten.
		 */
		public function __construct( array $data = array() ) {
			$defaults = self::defaults();

			$this->enabled                = (bool) ( $data['enabled'] ?? $defaults['enabled'] );
			$this->allowed_roles          = self::string_list( $data['allowed_roles'] ?? $defaults['allowed_roles'] );
			$this->max_spaces_per_user    = max( 0, (int) ( $data['max_spaces_per_user'] ?? $defaults['max_spaces_per_user'] ) );
			$this->allowed_visibilities   = self::filter_visibilities( $data['allowed_visibilities'] ?? $defaults['allowed_visibilities'] );
			$this->regular_visibilities   = self::filter_visibilities( $data['regular_visibilities'] ?? $defaults['regular_visibilities'] );
			$this->require_approval       = (bool) ( $data['require_approval'] ?? $defaults['require_approval'] );
			$this->name_min_length        = max( 1, (int) ( $data['name_min_length'] ?? $defaults['name_min_length'] ) );
			$this->name_max_length        = max( $this->name_min_length, (int) ( $data['name_max_length'] ?? $defaults['name_max_length'] ) );
			$this->description_max_length = max( 0, (int) ( $data['description_max_length'] ?? $defaults['description_max_length'] ) );
			$this->reserved_names         = self::normalize_reserved( $data['reserved_names'] ?? $defaults['reserved_names'] );
			$this->rate_limit_seconds     = max( 0, (int) ( $data['rate_limit_seconds'] ?? $defaults['rate_limit_seconds'] ) );
			$this->default_icon           = (string) ( $data['default_icon'] ?? $defaults['default_icon'] );

			if ( empty( $this->allowed_visibilities ) ) {
				$this->allowed_visibilities = array( self::VISIBILITY_PRIVATE );
			}

			// Sichtbarkeiten normaler Nutzer dürfen nie über die global erlaubten hinausgehen.
			$this->regular_visibilities = array_values( array_intersect( $this->regular_visibilities, $this->allowed_visibilities ) );
			if ( empty( $this->regular_visibilities ) ) {
				$this->regular_visibilities = array( self::VISIBILITY_PRIVATE );
				if ( ! in_array( self::VISIBILITY_PRIVATE, $this->allowed_visibilities, true ) ) {
					$this->regular_visibilities = array( $this->allowed_visibilities[0] );
				}
			}
		}

		/**
		 * Standardwerte der Richtlinien.
		 *
		 * @return array<string,mixed>
		 */
		public static function defaults(): array {
			return array(
				'enabled'                => false,
				'allowed_roles'          => array(),
				'max_spaces_per_user'    => 3,
				'allowed_visibilities'   => array( self::VISIBILITY_PRIVATE ),
				'regular_visibilities'   => array( self::VISIBILITY_PRIVATE ),
				'require_approval'       => true,
				'name_min_length'        => 3,
				'name_max_length'        => 60,
				'description_max_length' => 2000,
				'reserved_names'         => array( 'admin', 'administrator', 'moderator', 'system', 'support', 'afspaces' ),
				'rate_limit_seconds'     => 300,
				'default_icon'           => 'users',
			);
		}

		/**
		 * Gibt alle erlaubten Sichtbarkeitswerte zurück.
		 *
		 * @return string[]
		 */
		public static function all_visibilities(): array {
			return array( self::VISIBILITY_PRIVATE, self::VISIBILITY_PROTECTED, self::VISIBILITY_PUBLIC );
		}

		/**
		 * Lädt die Richtlinien aus der WordPress-Option.
		 *
		 * @return self
		 */
		public static function load(): self {
			$stored = get_option( self::OPTION, array() );
			return new self( is_array( $stored ) ? $stored : array() );
		}

		/**
		 * Speichert die Richtlinien in der WordPress-Option.
		 *
		 * @return void
		 */
		public function save(): void {
			update_option( self::OPTION, $this->to_array() );
		}

		/**
		 * Gibt die Richtlinien als Array zurück.
		 *
		 * @return array<string,mixed>
		 */
		public function to_array(): array {
			return array(
				'enabled'                => $this->enabled,
				'allowed_roles'          => $this->allowed_roles,
				'max_spaces_per_user'    => $this->max_spaces_per_user,
				'allowed_visibilities'   => $this->allowed_visibilities,
				'regular_visibilities'   => $this->regular_visibilities,
				'require_approval'       => $this->require_approval,
				'name_min_length'        => $this->name_min_length,
				'name_max_length'        => $this->name_max_length,
				'description_max_length' => $this->description_max_length,
				'reserved_names'         => $this->reserved_names,
				'rate_limit_seconds'     => $this->rate_limit_seconds,
				'default_icon'           => $this->default_icon,
			);
		}

		/**
		 * Prüft, ob eine Sichtbarkeit erlaubt ist.
		 *
		 * @param string $visibility Sichtbarkeitswert.
		 * @return bool
		 */
		public function is_visibility_allowed( string $visibility ): bool {
			return in_array( $visibility, $this->allowed_visibilities, true );
		}

		/**
		 * Gibt die für einen Nutzer erlaubten Sichtbarkeiten zurück.
		 *
		 * Privilegierte Nutzer (Moderatoren/Administratoren) dürfen alle global
		 * erlaubten Sichtbarkeiten wählen; normale Nutzer sind auf die
		 * konfigurierte Teilmenge beschränkt (z. B. nur „privat").
		 *
		 * @param bool $privileged Nutzer ist Moderator/Administrator.
		 * @return string[]
		 */
		public function visibilities_for( bool $privileged ): array {
			return $privileged ? $this->allowed_visibilities : $this->regular_visibilities;
		}

		/**
		 * Prüft, ob ein Name reserviert ist.
		 *
		 * @param string $name Rohname.
		 * @return bool
		 */
		public function is_reserved_name( string $name ): bool {
			$needle = self::casefold( $name );
			return '' !== $needle && in_array( $needle, $this->reserved_names, true );
		}

		/**
		 * Normalisiert eine Liste von Zeichenketten.
		 *
		 * @param mixed $value Rohwert.
		 * @return string[]
		 */
		private static function string_list( $value ): array {
			if ( is_string( $value ) ) {
				$value = preg_split( '/[\r\n,]+/', $value ) ?: array();
			}
			if ( ! is_array( $value ) ) {
				return array();
			}
			$result = array();
			foreach ( $value as $item ) {
				$item = trim( (string) $item );
				if ( '' !== $item ) {
					$result[] = $item;
				}
			}
			return array_values( array_unique( $result ) );
		}

		/**
		 * Normalisiert reservierte Namen (klein geschrieben, ohne Leerzeichen-Rand).
		 *
		 * @param mixed $value Rohwert.
		 * @return string[]
		 */
		private static function normalize_reserved( $value ): array {
			$list = self::string_list( $value );
			$out  = array();
			foreach ( $list as $item ) {
				$folded = self::casefold( $item );
				if ( '' !== $folded ) {
					$out[] = $folded;
				}
			}
			return array_values( array_unique( $out ) );
		}

		/**
		 * Filtert auf gültige Sichtbarkeitswerte.
		 *
		 * @param mixed $value Rohwert.
		 * @return string[]
		 */
		private static function filter_visibilities( $value ): array {
			$list  = self::string_list( $value );
			$valid = self::all_visibilities();
			return array_values( array_intersect( $list, $valid ) );
		}

		/**
		 * Wandelt einen Namen in eine vergleichbare Kleinschreibung um.
		 *
		 * @param string $value Rohname.
		 * @return string
		 */
		private static function casefold( string $value ): string {
			$value = trim( $value );
			if ( function_exists( 'mb_strtolower' ) ) {
				return mb_strtolower( $value, 'UTF-8' );
			}
			return strtolower( $value );
		}
	}
}
