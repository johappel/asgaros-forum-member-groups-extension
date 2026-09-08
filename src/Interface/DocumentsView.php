<?php
/**
 * Vollständige Dokumentansicht einer Arbeitsgruppe.
 *
 * Zeigt ausschließlich Dokumente, die der aktuelle Nutzer gemäß ihrer
 * Sichtbarkeit sehen darf. Interner Forumkontext wird nur für Personen mit
 * Leserecht am ursprünglichen Beitrag ausgegeben.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\DocumentService;
use AFSpaces\Domain\SpaceDocument;

if ( ! class_exists( 'AFSpaces\\Interface\\DocumentsView' ) ) {

	/**
	 * Rendert die zentrale Dokumentliste mit Ansichten, Sortierung und Filtern.
	 */
	class DocumentsView {

		public const PARAM_VIEW   = 'afspaces_doc_view';
		public const PARAM_SORT   = 'afspaces_doc_sort';
		public const PARAM_TOPIC  = 'afspaces_doc_topic';
		public const PARAM_SEARCH = 'afspaces_doc_q';

		public const VIEW_ALL     = 'all';
		public const VIEW_TOPICS  = 'topics';
		public const VIEW_NEWEST  = 'newest';

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private DocumentService $documents;

		/**
		 * Konstruktor.
		 */
		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, DocumentService $documents ) {
			$this->spaces    = $spaces;
			$this->asgaros   = $asgaros;
			$this->documents = $documents;
		}

		/**
		 * Rendert die Dokumentansicht.
		 *
		 * @param int $space_id Space-ID.
		 * @return string
		 */
		public function render( int $space_id ): string {
			$actor = get_current_user_id();

			$space = $this->spaces->get_space( $space_id );
			if ( ! $space || 'active' !== $space->status ) {
				return $this->notice( __( 'Arbeitsgruppe nicht gefunden.', 'afspaces' ) );
			}

			$view   = $this->current_view();
			$sort   = self::VIEW_NEWEST === $view ? DocumentService::SORT_DATE_DESC : $this->current_sort();
			$topic  = isset( $_GET[ self::PARAM_TOPIC ] ) ? SpaceDocument::sanitize_topic( wp_unslash( (string) $_GET[ self::PARAM_TOPIC ] ) ) : '';
			$search = isset( $_GET[ self::PARAM_SEARCH ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ self::PARAM_SEARCH ] ) ) : '';

			$documents = $this->documents->list_documents(
				$space_id,
				$actor,
				array(
					'sort'   => $sort,
					'topic'  => $topic,
					'search' => $search,
				)
			);
			$topics    = $this->documents->list_topics( $space_id, $actor );

			ob_start();
			?>
			<section class="afspaces-documents" aria-labelledby="afspaces-documents-heading">
				<?php echo $this->render_message(); ?>
				<h2 id="afspaces-documents-heading"><?php echo esc_html__( 'Dokumente', 'afspaces' ); ?></h2>
				<p class="afspaces-documents-intro">
					<?php echo esc_html__( 'Hier sammeln sich die als Gruppendokument gekennzeichneten Dateien aus den Foren dieser Arbeitsgruppe.', 'afspaces' ); ?>
				</p>

				<?php echo $this->render_controls( $space_id, $view, $sort, $topic, $search, $topics ); ?>

				<p class="afspaces-documents-count" role="status" aria-live="polite">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: Anzahl der Dokumente */
							_n( '%d Dokument', '%d Dokumente', count( $documents ), 'afspaces' ),
							count( $documents )
						)
					);
					?>
				</p>

				<?php
				if ( empty( $documents ) ) {
					if ( '' !== $topic || '' !== $search ) {
						echo '<p class="afspaces-documents-empty">' . esc_html__( 'Zu Ihrer Suche bzw. Filterung passen keine Dokumente. Setzen Sie die Filter zurück, um alle sichtbaren Dokumente anzuzeigen.', 'afspaces' ) . '</p>';
					} else {
						echo '<p class="afspaces-documents-empty">' . esc_html__( 'Es sind keine für Sie sichtbaren Dokumente vorhanden.', 'afspaces' ) . '</p>';
					}
				} elseif ( self::VIEW_TOPICS === $view ) {
					echo $this->render_grouped( $documents, $actor, $space_id );
				} else {
					echo $this->render_flat( $documents, $actor, $space_id );
				}
				?>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Aktuelle Ansicht aus der Anfrage.
		 *
		 * @return string
		 */
		private function current_view(): string {
			$view = isset( $_GET[ self::PARAM_VIEW ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::PARAM_VIEW ] ) ) : self::VIEW_ALL;
			return in_array( $view, array( self::VIEW_ALL, self::VIEW_TOPICS, self::VIEW_NEWEST ), true ) ? $view : self::VIEW_ALL;
		}

		/**
		 * Aktuelle Sortierung aus der Anfrage.
		 *
		 * @return string
		 */
		private function current_sort(): string {
			$sort    = isset( $_GET[ self::PARAM_SORT ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::PARAM_SORT ] ) ) : '';
			$allowed = array(
				DocumentService::SORT_DATE_DESC,
				DocumentService::SORT_DATE_ASC,
				DocumentService::SORT_NAME_ASC,
				DocumentService::SORT_NAME_DESC,
			);
			return in_array( $sort, $allowed, true ) ? $sort : DocumentService::SORT_DATE_DESC;
		}

		/**
		 * Rendert die Ansichts-/Sortier-/Filterleiste (No-JS-tauglich als GET-Form).
		 *
		 * @param int      $space_id Space-ID.
		 * @param string   $view     Aktive Ansicht.
		 * @param string   $sort     Aktive Sortierung.
		 * @param string   $topic    Aktiver Themenfilter.
		 * @param string   $search   Aktuelle Suche.
		 * @param string[] $topics   Verfügbare Themen.
		 * @return string
		 */
		private function render_controls( int $space_id, string $view, string $sort, string $topic, string $search, array $topics ): string {
			$views = array(
				self::VIEW_ALL    => __( 'Alle', 'afspaces' ),
				self::VIEW_TOPICS => __( 'Nach Themen', 'afspaces' ),
				self::VIEW_NEWEST => __( 'Neueste', 'afspaces' ),
			);
			$sorts = array(
				DocumentService::SORT_DATE_DESC => __( 'Upload-Datum (neueste zuerst)', 'afspaces' ),
				DocumentService::SORT_DATE_ASC  => __( 'Upload-Datum (älteste zuerst)', 'afspaces' ),
				DocumentService::SORT_NAME_ASC  => __( 'Dateiname A–Z', 'afspaces' ),
				DocumentService::SORT_NAME_DESC => __( 'Dateiname Z–A', 'afspaces' ),
			);

			ob_start();
			?>
			<form method="get" class="afspaces-documents-controls" role="search">
				<input type="hidden" name="<?php echo esc_attr( SpacesUrls::VIEW_PARAM ); ?>" value="<?php echo esc_attr( SpacesUrls::VIEW_DOCUMENTS ); ?>" />
				<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />

				<p class="afspaces-field">
					<label for="afspaces-doc-view"><?php echo esc_html__( 'Ansicht', 'afspaces' ); ?></label>
					<select id="afspaces-doc-view" name="<?php echo esc_attr( self::PARAM_VIEW ); ?>">
						<?php foreach ( $views as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $view, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<p class="afspaces-field">
					<label for="afspaces-doc-sort"><?php echo esc_html__( 'Sortierung', 'afspaces' ); ?></label>
					<select id="afspaces-doc-sort" name="<?php echo esc_attr( self::PARAM_SORT ); ?>"<?php echo self::VIEW_NEWEST === $view ? ' disabled' : ''; ?>>
						<?php foreach ( $sorts as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $sort, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<?php if ( ! empty( $topics ) ) : ?>
					<p class="afspaces-field">
						<label for="afspaces-doc-topic"><?php echo esc_html__( 'Thema', 'afspaces' ); ?></label>
						<select id="afspaces-doc-topic" name="<?php echo esc_attr( self::PARAM_TOPIC ); ?>">
							<option value=""><?php echo esc_html__( 'Alle Themen', 'afspaces' ); ?></option>
							<?php foreach ( $topics as $topic_option ) : ?>
								<option value="<?php echo esc_attr( $topic_option ); ?>"<?php selected( strcasecmp( $topic, $topic_option ) === 0 ); ?>><?php echo esc_html( $topic_option ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				<?php endif; ?>

				<p class="afspaces-field">
					<label for="afspaces-doc-q"><?php echo esc_html__( 'Suche', 'afspaces' ); ?></label>
					<input type="search" id="afspaces-doc-q" name="<?php echo esc_attr( self::PARAM_SEARCH ); ?>" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Titel, Dateiname oder Thema', 'afspaces' ); ?>" />
				</p>

				<p class="afspaces-field afspaces-documents-controls-submit">
					<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Anwenden', 'afspaces' ); ?></button>
				</p>
			</form>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert eine flache Dokumentliste.
		 *
		 * @param SpaceDocument[] $documents Dokumente.
		 * @param int             $actor     Betrachter.
		 * @param int             $space_id  Space-ID.
		 * @return string
		 */
		private function render_flat( array $documents, int $actor, int $space_id ): string {
			ob_start();
			echo '<ul class="afspaces-documents-list">';
			foreach ( $documents as $document ) {
				echo $this->render_document_item( $document, $actor, $space_id );
			}
			echo '</ul>';
			return (string) ob_get_clean();
		}

		/**
		 * Rendert eine nach Dokumentthemen gruppierte Liste.
		 *
		 * @param SpaceDocument[] $documents Dokumente.
		 * @param int             $actor     Betrachter.
		 * @param int             $space_id  Space-ID.
		 * @return string
		 */
		private function render_grouped( array $documents, int $actor, int $space_id ): string {
			$groups = array();
			foreach ( $documents as $document ) {
				$key            = '' !== trim( $document->document_topic ) ? $document->document_topic : __( 'Ohne Thema', 'afspaces' );
				$groups[ $key ] = $groups[ $key ] ?? array();
				$groups[ $key ][] = $document;
			}
			uksort( $groups, 'strcasecmp' );

			ob_start();
			foreach ( $groups as $topic_name => $group_documents ) {
				echo '<section class="afspaces-documents-group" aria-label="' . esc_attr( $topic_name ) . '">';
				echo '<h3 class="afspaces-documents-group-title">' . esc_html( $topic_name ) . '</h3>';
				echo '<ul class="afspaces-documents-list">';
				foreach ( $group_documents as $document ) {
					echo $this->render_document_item( $document, $actor, $space_id );
				}
				echo '</ul>';
				echo '</section>';
			}
			return (string) ob_get_clean();
		}

		/**
		 * Rendert einen einzelnen Dokumenteintrag.
		 *
		 * @param SpaceDocument $document Dokument.
		 * @param int           $actor    Betrachter.
		 * @param int           $space_id Space-ID.
		 * @return string
		 */
		private function render_document_item( SpaceDocument $document, int $actor, int $space_id ): string {
			$model = $this->documents->document_view_model( $document, $actor );

			$can_edit = get_current_user_id() > 0 && (
				user_can( $actor, \AFSpaces\Core\Capabilities::MANAGE_ALL_SPACES )
				|| $this->spaces->is_manager( $space_id, $actor )
				|| $document->created_by === $actor
			);

			ob_start();
			?>
			<li class="afspaces-documents-item">
				<div class="afspaces-documents-item-main">
					<span class="afspaces-documents-icon fas fa-file" aria-hidden="true"></span>
					<div class="afspaces-documents-item-body">
						<p class="afspaces-documents-title">
							<?php if ( '' !== $model['url'] ) : ?>
								<a href="<?php echo esc_url( $model['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $model['title'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $model['title'] ); ?>
							<?php endif; ?>
						</p>
						<p class="afspaces-documents-meta">
							<?php if ( '' !== $model['extension'] ) : ?>
								<span class="afspaces-documents-type"><?php echo esc_html( strtoupper( $model['extension'] ) ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $model['topic'] ) : ?>
								<span class="afspaces-documents-topic"><?php echo esc_html( $model['topic'] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $model['created_at'] ) : ?>
								<span class="afspaces-documents-date"><?php echo esc_html( $this->format_date( $model['created_at'] ) ); ?></span>
							<?php endif; ?>
							<span class="afspaces-documents-visibility"><?php echo esc_html( $this->visibility_label( $model['visibility'] ) ); ?></span>
						</p>
						<?php if ( ! empty( $model['show_forum_context'] ) && '' !== $model['topic_name'] ) : ?>
							<p class="afspaces-documents-origin">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: Name des Forumsthemas */
										__( 'Aus dem Thema „%s“', 'afspaces' ),
										$model['topic_name']
									)
								);
								?>
								<?php if ( '' !== $model['post_link'] ) : ?>
									&middot; <a href="<?php echo esc_url( $model['post_link'] ); ?>"><?php echo esc_html__( 'Zum Forumsbeitrag', 'afspaces' ); ?></a>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</div>
				</div>

				<div class="afspaces-documents-item-actions">
					<?php if ( '' !== $model['url'] ) : ?>
						<a class="afspaces-button afspaces-button-secondary" href="<?php echo esc_url( $model['url'] ); ?>" target="_blank" rel="noopener noreferrer">
							<span class="fas fa-external-link-alt" aria-hidden="true"></span>
							<?php echo esc_html__( 'Dokument öffnen', 'afspaces' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $can_edit ) : ?>
						<details class="afspaces-documents-edit">
							<summary class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Bearbeiten', 'afspaces' ); ?></summary>
							<?php echo $this->render_edit_form( $document, $space_id ); ?>
						</details>
						<form method="post" class="afspaces-inline-form" data-afspaces-confirm="<?php echo esc_attr__( 'Dieses Dokument wirklich aus der Bibliothek entfernen? Die Datei bleibt im Forum erhalten.', 'afspaces' ); ?>">
							<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
							<input type="hidden" name="afspaces_action" value="remove_document" />
							<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
							<input type="hidden" name="document_id" value="<?php echo esc_attr( (string) $document->id ); ?>" />
							<button type="submit" class="afspaces-button afspaces-button-danger"><?php echo esc_html__( 'Aus Dokumenten entfernen', 'afspaces' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</li>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert das Bearbeiten-Formular eines Dokuments.
		 *
		 * @param SpaceDocument $document Dokument.
		 * @param int           $space_id Space-ID.
		 * @return string
		 */
		private function render_edit_form( SpaceDocument $document, int $space_id ): string {
			$can_publish = user_can( get_current_user_id(), \AFSpaces\Core\Capabilities::MANAGE_ALL_SPACES )
				|| $this->spaces->is_manager( $space_id, get_current_user_id() );

			ob_start();
			?>
			<form method="post" class="afspaces-documents-edit-form">
				<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
				<input type="hidden" name="afspaces_action" value="update_document" />
				<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
				<input type="hidden" name="document_id" value="<?php echo esc_attr( (string) $document->id ); ?>" />
				<?php echo $this->render_fields( 'edit-' . $document->id, $document, $can_publish ); ?>
				<p class="afspaces-field">
					<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Änderungen speichern', 'afspaces' ); ?></button>
				</p>
			</form>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert die gemeinsamen Formularfelder (Titel, Thema, Sichtbarkeit).
		 *
		 * @param string             $id_prefix   Eindeutiger Präfix.
		 * @param SpaceDocument|null $document    Vorbelegung.
		 * @param bool               $can_publish Darf erweiterte Sichtbarkeit setzen.
		 * @return string
		 */
		public function render_fields( string $id_prefix, ?SpaceDocument $document, bool $can_publish ): string {
			$title      = $document ? $document->title : '';
			$topic      = $document ? $document->document_topic : '';
			$visibility = $document ? $document->visibility : SpaceDocument::VISIBILITY_MEMBERS;
			$options    = SpaceDocument::visibility_options();

			ob_start();
			?>
			<p class="afspaces-field">
				<label for="afspaces-doc-title-<?php echo esc_attr( $id_prefix ); ?>"><?php echo esc_html__( 'Titel', 'afspaces' ); ?></label>
				<input type="text" id="afspaces-doc-title-<?php echo esc_attr( $id_prefix ); ?>" name="title" maxlength="200" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr__( 'Standard: Dateiname', 'afspaces' ); ?>" />
			</p>
			<p class="afspaces-field">
				<label for="afspaces-doc-topic-<?php echo esc_attr( $id_prefix ); ?>"><?php echo esc_html__( 'Thema (optional)', 'afspaces' ); ?></label>
				<input type="text" id="afspaces-doc-topic-<?php echo esc_attr( $id_prefix ); ?>" name="document_topic" maxlength="120" value="<?php echo esc_attr( $topic ); ?>" placeholder="<?php echo esc_attr__( 'z. B. Protokolle', 'afspaces' ); ?>" />
			</p>
			<fieldset class="afspaces-field afspaces-documents-visibility-field">
				<legend><?php echo esc_html__( 'Sichtbarkeit', 'afspaces' ); ?></legend>
				<?php foreach ( $options as $value => $label ) : ?>
					<?php $disabled = ( SpaceDocument::VISIBILITY_MEMBERS !== $value && ! $can_publish ); ?>
					<label class="afspaces-field-radio">
						<input type="radio" name="visibility" value="<?php echo esc_attr( $value ); ?>"<?php checked( $visibility, $value ); ?><?php echo $disabled ? ' disabled' : ''; ?> />
						<?php echo esc_html( $label ); ?>
						<?php if ( $disabled ) : ?>
							<span class="description">(<?php echo esc_html__( 'nur für Verantwortliche', 'afspaces' ); ?>)</span>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Übersetzt einen Sichtbarkeitswert in eine Beschriftung.
		 *
		 * @param string $visibility Sichtbarkeit.
		 * @return string
		 */
		private function visibility_label( string $visibility ): string {
			switch ( $visibility ) {
				case SpaceDocument::VISIBILITY_PUBLIC:
					return __( 'Öffentlich', 'afspaces' );
				case SpaceDocument::VISIBILITY_AUTHENTICATED:
					return __( 'Alle angemeldeten Nutzer', 'afspaces' );
				case SpaceDocument::VISIBILITY_MEMBERS:
				default:
					return __( 'Arbeitsgruppenmitglieder', 'afspaces' );
			}
		}

		/**
		 * Formatiert ein Datum lokalisiert.
		 *
		 * @param string $mysql_date MySQL-Datum.
		 * @return string
		 */
		private function format_date( string $mysql_date ): string {
			$timestamp = strtotime( $mysql_date );
			if ( false === $timestamp ) {
				return $mysql_date;
			}
			$format = function_exists( 'get_option' ) ? (string) get_option( 'date_format', 'd.m.Y' ) : 'd.m.Y';
			if ( function_exists( 'date_i18n' ) ) {
				return (string) date_i18n( $format, $timestamp );
			}
			return gmdate( $format, $timestamp );
		}

		/**
		 * @param string $text Meldung.
		 * @return string
		 */
		private function notice( string $text ): string {
			return sprintf( '<p class="afspaces-notice" role="status">%s</p>', esc_html( $text ) );
		}

		/**
		 * Gibt eine gespeicherte Statusmeldung aus der Session aus.
		 *
		 * @return string
		 */
		private function render_message(): string {
			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}

			if ( empty( $_SESSION['afspaces_message'] ) ) {
				return '';
			}

			$msg = $_SESSION['afspaces_message'];
			unset( $_SESSION['afspaces_message'] );

			$role = ( 'error' === $msg['type'] ) ? 'alert' : 'status';
			return sprintf(
				'<div class="afspaces-message afspaces-message-%1$s" role="%2$s" aria-live="polite">%3$s</div>',
				esc_attr( $msg['type'] ),
				esc_attr( $role ),
				esc_html( $msg['message'] )
			);
		}
	}
}
