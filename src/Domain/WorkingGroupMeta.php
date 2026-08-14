<?php
/**
 * Metadaten fuer das sichtbare Arbeitsgruppenmodell.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Domain;

if ( ! class_exists( 'AFSpaces\\Domain\\WorkingGroupMeta' ) ) {

	/**
	 * Kapselt optionale, aber fachlich sichtbare Zusatzdaten eines Spaces.
	 */
	class WorkingGroupMeta {

		/**
		 * Verbindliche Corporate-Design-Palette für Arbeitsgruppen.
		 *
		 * @var list<string>
		 */
		private const ACCENT_COLORS = array(
			'#77429e',
			'#2d5d7f',
			'#f5ae35',
			'#5563a5',
		);

		public const JOIN_POLICY_REQUEST = 'request';
		public const JOIN_POLICY_INVITE_ONLY = 'invite_only';
		public const JOIN_POLICY_CLOSED = 'closed';

		public int $space_id;
		public string $description;
		public string $accent_color;
		public string $icon;
		public string $contact_text;
		public string $join_policy;
		public bool $join_requests_enabled;
		/** @var int[] */
		public array $topic_ids;

		/**
		 * @param array<string,mixed> $data Rohdaten.
		 */
		public function __construct( array $data ) {
			$defaults = self::defaults();
			$data     = array_merge( $defaults, $data );

			$this->space_id = (int) $data['space_id'];
			$this->description = (string) $data['description'];
			$this->accent_color = self::normalize_accent_color( $data['accent_color'] );
			$this->icon = (string) $data['icon'];
			$this->contact_text = (string) $data['contact_text'];
			$this->join_policy = (string) $data['join_policy'];
			$this->join_requests_enabled = self::to_bool( $data['join_requests_enabled'] );
			$this->topic_ids = self::normalize_topic_ids( $data['topic_ids'] );
		}

		/**
		 * @return array<string,mixed>
		 */
		public static function defaults(): array {
			return array(
				'space_id'              => 0,
				'description'           => '',
				'accent_color'          => '#2d5d7f',
				'icon'                  => 'users',
				'contact_text'          => '',
				'join_policy'           => self::JOIN_POLICY_REQUEST,
				'join_requests_enabled' => true,
				'topic_ids'             => array(),
			);
		}

		/**
		 * Gibt die erlaubten Arbeitsgruppenfarben zurück.
		 *
		 * @return list<string>
		 */
		public static function accent_colors(): array {
			return self::ACCENT_COLORS;
		}

		/**
		 * Normalisiert eine Farbe auf die Corporate-Design-Palette.
		 *
		 * Historische oder manipulierte Fremdwerte werden bewusst auf den
		 * Standardwert zurückgeführt.
		 *
		 * @param mixed $value Rohwert.
		 * @return string
		 */
		public static function normalize_accent_color( $value ): string {
			$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
			return in_array( $value, self::ACCENT_COLORS, true )
				? $value
				: (string) self::defaults()['accent_color'];
		}

		/**
		 * @param int $space_id Space-ID.
		 * @return self
		 */
		public static function defaults_for_space( int $space_id ): self {
			$defaults = self::defaults();
			$defaults['space_id'] = $space_id;
			return new self( $defaults );
		}

		/**
		 * @return array<string,mixed>
		 */
		public function to_array(): array {
			return array(
				'space_id'              => $this->space_id,
				'description'           => $this->description,
				'accent_color'          => $this->accent_color,
				'icon'                  => $this->icon,
				'contact_text'          => $this->contact_text,
				'join_policy'           => $this->join_policy,
				'join_requests_enabled' => $this->join_requests_enabled,
				'topic_ids'             => $this->topic_ids,
			);
		}

		/**
		 * @param mixed $value Rohwert.
		 * @return bool
		 */
		private static function to_bool( $value ): bool {
			if ( is_bool( $value ) ) {
				return $value;
			}

			return in_array( $value, array( 1, '1', 'true', 'yes', 'on' ), true );
		}

		/**
		 * @param mixed $value Rohwert.
		 * @return int[]
		 */
		private static function normalize_topic_ids( $value ): array {
			if ( is_string( $value ) ) {
				$decoded = json_decode( $value, true );
				$value   = is_array( $decoded ) ? $decoded : array();
			}

			if ( ! is_array( $value ) ) {
				return array();
			}

			$topic_ids = array_map( 'intval', $value );
			$topic_ids = array_values( array_unique( array_filter( $topic_ids, static fn( int $id ): bool => $id > 0 ) ) );

			return $topic_ids;
		}
	}
}
