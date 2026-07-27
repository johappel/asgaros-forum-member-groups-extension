<?php
/**
 * Wertobjekt für einen einzelnen Suchtreffer.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Search;

if ( ! class_exists( 'AFSpaces\\Search\\SearchHit' ) ) {

	/**
	 * Ein normalisierter Suchtreffer, unabhängig von der Quelle.
	 *
	 * Die Segmente in {@see self::$snippet} stammen aus dem {@see SnippetBuilder}
	 * und müssen von der View kontextgerecht escaped werden.
	 */
	final class SearchHit {

		public const SOURCE_FORUM = 'forum';
		public const SOURCE_WP    = 'wp';

		/**
		 * Quelle des Treffers (forum|wp).
		 *
		 * @var string
		 */
		public string $source;

		/**
		 * Titel (z. B. Thementitel oder Beitragstitel).
		 *
		 * @var string
		 */
		public string $title;

		/**
		 * Direkter Deep-Link zur Fundstelle.
		 *
		 * @var string
		 */
		public string $url;

		/**
		 * Ausschnitt-Segmente (`text` + `mark`).
		 *
		 * @var array<int,array{text:string,mark:bool}>
		 */
		public array $snippet;

		/**
		 * Anzeigename der Autorin/des Autors.
		 *
		 * @var string
		 */
		public string $author_name;

		/**
		 * Bereits lokalisiertes Datum.
		 *
		 * @var string
		 */
		public string $date;

		/**
		 * Kontext-Label (Forum bzw. Arbeitsgruppe oder Beitragstyp).
		 *
		 * @var string
		 */
		public string $context_label;

		/**
		 * Relevanzbewertung.
		 *
		 * @var float
		 */
		public float $score;

		/**
		 * Konstruktor.
		 *
		 * @param string                                  $source        Quelle.
		 * @param string                                  $title         Titel.
		 * @param string                                  $url           Deep-Link.
		 * @param array<int,array{text:string,mark:bool}> $snippet       Ausschnitt-Segmente.
		 * @param string                                  $author_name   Autor.
		 * @param string                                  $date          Datum.
		 * @param string                                  $context_label Kontext-Label.
		 * @param float                                   $score         Relevanz.
		 */
		public function __construct(
			string $source,
			string $title,
			string $url,
			array $snippet,
			string $author_name,
			string $date,
			string $context_label,
			float $score = 0.0
		) {
			$this->source        = $source;
			$this->title         = $title;
			$this->url           = $url;
			$this->snippet       = $snippet;
			$this->author_name   = $author_name;
			$this->date          = $date;
			$this->context_label = $context_label;
			$this->score         = $score;
		}
	}
}
