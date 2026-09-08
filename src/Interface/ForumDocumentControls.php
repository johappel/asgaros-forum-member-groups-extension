<?php
/**
 * Bindet die Dokumentfunktion direkt in das Asgaros-Forum ein.
 *
 * Unter jeder Asgaros-Uploadliste eines Beitrags erscheint der Einstieg
 * „Als Gruppendokument aufnehmen“ bzw. bei bereits erfassten Dateien die
 * Verwaltung. Außerdem liefert diese Klasse die kompakte Dokumentvorschau für
 * den Werkzeugkasten-Dialog und räumt Metadaten gelöschter Beiträge auf.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Database\SpaceDocumentRepository;
use AFSpaces\Application\DocumentService;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceDocument;

if ( ! class_exists( 'AFSpaces\\Interface\\ForumDocumentControls' ) ) {

	/**
	 * Verbindet die Dokumentbibliothek mit dem Asgaros-Beitrags- und Toolbox-Kontext.
	 */
	class ForumDocumentControls {

		private DocumentService $documents;
		private SpaceDocumentRepository $repository;

		/**
		 * Konstruktor.
		 */
		public function __construct( DocumentService $documents, SpaceDocumentRepository $repository ) {
			$this->documents  = $documents;
			$this->repository = $repository;
		}

		/**
		 * Registriert die verwendeten Asgaros- und AFSpaces-Hooks.
		 *
		 * @return void
		 */
		public function init(): void {
			// Aktionen unterhalb der Asgaros-Uploadliste eines Beitrags.
			add_action( 'asgarosforum_after_post_message', array( $this, 'render_post_actions' ), 20, 2 );

			// Metadaten verwaister Beiträge aufräumen.
			add_action( 'asgarosforum_after_delete_post', array( $this, 'cleanup_deleted_post' ), 10, 1 );

			// Kompakte Dokumentvorschau im Werkzeugkasten-Dialog.
			add_filter( 'afspaces_toolbox_documents_content', array( $this, 'render_toolbox_preview' ), 10, 3 );
		}

		/**
		 * Rendert die Dokumentaktionen unter der Uploadliste eines Beitrags.
		 *
		 * @param int $author_id Verfasser-ID des Beitrags.
		 * @param int $post_id   Asgaros-Beitrags-ID.
		 * @return void
		 */
		public function render_post_actions( $author_id, $post_id ): void {
			$post_id = (int) $post_id;
			$actor   = get_current_user_id();
			if ( $post_id < 1 || $actor < 1 ) {
				return;
			}

			$context = $this->documents->resolve_document_context( $post_id, $actor );
			if ( null === $context ) {
				return;
			}

			/** @var Space $space */
			$space     = $context['space'];
			$redirect  = (string) ( $context['post_link'] ?? '' );
			$has_rows  = false;

			ob_start();
			echo '<div class="afspaces-post-documents" data-afspaces-post-documents>';
			foreach ( $context['uploads'] as $row ) {
				$filename = (string) $row['filename'];
				$document = $row['document'] instanceof SpaceDocument ? $row['document'] : null;
				$can_use  = ! empty( $row['can_use'] );

				if ( null === $document && ! $can_use ) {
					continue;
				}

				$has_rows = true;
				echo '<div class="afspaces-post-document-row">';
				echo '<span class="afspaces-post-document-file"><span class="fas fa-file" aria-hidden="true"></span> ' . esc_html( $filename ) . '</span>';
				echo '<span class="afspaces-post-document-status">';

				if ( null !== $document ) {
					echo '<span class="afspaces-post-document-flag"><span class="fas fa-check" aria-hidden="true"></span> ' . esc_html__( 'Gruppendokument', 'afspaces' ) . '</span>';

					if ( $can_use ) {
						echo '<span class="afspaces-post-document-sep" aria-hidden="true">&middot;</span>';
						echo $this->render_edit_disclosure( $space, $document, $redirect );
						echo '<span class="afspaces-post-document-sep" aria-hidden="true">&middot;</span>';
						echo $this->render_remove_form( $space->id, $document->id, $redirect );
					}
				} elseif ( $can_use ) {
					echo $this->render_add_disclosure( $space, $post_id, $filename, $redirect );
				}

				echo '</span>';
				echo '</div>';
			}
			echo '</div>';
			$html = (string) ob_get_clean();

			if ( $has_rows ) {
				echo $html; // Bereits mit esc_* aufgebaut.
			}
		}

		/**
		 * Kompakte Vorschau der zuletzt hinzugefügten Dokumente für den Toolbox-Dialog.
		 *
		 * @param string $content Bisheriger Inhalt.
		 * @param Space  $space   Aktuelle Arbeitsgruppe.
		 * @param int    $actor   Aktuelle Benutzer-ID.
		 * @return string Bereits escaptes HTML oder der unveränderte Inhalt.
		 */
		public function render_toolbox_preview( string $content, $space, $actor ): string {
			if ( ! $space instanceof Space ) {
				return $content;
			}

			$actor     = (int) $actor;
			$documents = $this->documents->list_documents(
				$space->id,
				$actor,
				array( 'sort' => DocumentService::SORT_DATE_DESC )
			);

			$all_url = SpacesUrls::hub_url( SpacesUrls::VIEW_DOCUMENTS, array( 'space_id' => $space->id ) );

			ob_start();
			if ( empty( $documents ) ) {
				echo '<p class="afspaces-toolbox-empty">' . esc_html__( 'Für diese Arbeitsgruppe wurden noch keine Dokumente aufgenommen.', 'afspaces' ) . '</p>';
			} else {
				echo '<p class="afspaces-toolbox-documents-lead">' . esc_html__( 'Zuletzt hinzugefügt', 'afspaces' ) . '</p>';
				echo '<ul class="afspaces-toolbox-documents-list">';
				foreach ( array_slice( $documents, 0, 5 ) as $document ) {
					$model = $this->documents->document_view_model( $document, $actor );
					echo '<li class="afspaces-toolbox-document-item">';
					if ( '' !== $model['url'] ) {
						echo '<a href="' . esc_url( $model['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $model['title'] ) . '</a>';
					} else {
						echo esc_html( $model['title'] );
					}
					$meta = array();
					if ( '' !== $model['topic'] ) {
						$meta[] = $model['topic'];
					}
					if ( '' !== $model['created_at'] ) {
						$meta[] = $this->format_date( $model['created_at'] );
					}
					if ( ! empty( $meta ) ) {
						echo ' <span class="afspaces-toolbox-document-meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>';
					}
					echo '</li>';
				}
				echo '</ul>';
			}

			echo '<p class="afspaces-toolbox-documents-all"><a class="afspaces-button afspaces-button-secondary" href="' . esc_url( $all_url ) . '">' . esc_html__( 'Alle Dokumente öffnen', 'afspaces' ) . '</a></p>';

			return (string) ob_get_clean();
		}

		/**
		 * Entfernt Dokument-Metadaten eines gelöschten Beitrags.
		 *
		 * @param int $post_id Asgaros-Beitrags-ID.
		 * @return void
		 */
		public function cleanup_deleted_post( $post_id ): void {
			$post_id = (int) $post_id;
			if ( $post_id > 0 ) {
				$this->repository->delete_by_post( $post_id );
			}
		}

		/**
		 * „Als Gruppendokument aufnehmen“ als aufklappbares Formular (No-JS-tauglich).
		 *
		 * @param Space  $space    Arbeitsgruppe.
		 * @param int    $post_id  Beitrags-ID.
		 * @param string $filename Dateiname.
		 * @param string $redirect Rücksprung-URL.
		 * @return string
		 */
		private function render_add_disclosure( Space $space, int $post_id, string $filename, string $redirect ): string {
			$can_publish = $space instanceof Space && $this->can_publish( $space->id );

			ob_start();
			?>
			<details class="afspaces-post-document-add">
				<summary class="afspaces-post-document-action afspaces-post-document-action--add"><span class="fas fa-plus" aria-hidden="true"></span> <?php echo esc_html__( 'Als Gruppendokument aufnehmen', 'afspaces' ); ?></summary>
				<form method="post" class="afspaces-post-document-form">
					<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
					<input type="hidden" name="afspaces_action" value="add_document" />
					<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
					<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
					<input type="hidden" name="filename" value="<?php echo esc_attr( $filename ); ?>" />
					<?php if ( '' !== $redirect ) : ?>
						<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>" />
					<?php endif; ?>
					<?php echo $this->render_fields( 'add-' . $post_id . '-' . md5( $filename ), null, $can_publish, $filename ); ?>
					<p class="afspaces-field">
						<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Aufnehmen', 'afspaces' ); ?></button>
					</p>
				</form>
			</details>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Bearbeiten-Disclosure für ein bereits erfasstes Dokument.
		 *
		 * @param Space         $space    Arbeitsgruppe.
		 * @param SpaceDocument $document Dokument.
		 * @param string        $redirect Rücksprung-URL.
		 * @return string
		 */
		private function render_edit_disclosure( Space $space, SpaceDocument $document, string $redirect ): string {
			$can_publish = $this->can_publish( $space->id );

			ob_start();
			?>
			<details class="afspaces-post-document-edit">
				<summary class="afspaces-post-document-action"><?php echo esc_html__( 'Bearbeiten', 'afspaces' ); ?></summary>
				<form method="post" class="afspaces-post-document-form">
					<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
					<input type="hidden" name="afspaces_action" value="update_document" />
					<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
					<input type="hidden" name="document_id" value="<?php echo esc_attr( (string) $document->id ); ?>" />
					<?php if ( '' !== $redirect ) : ?>
						<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>" />
					<?php endif; ?>
					<?php echo $this->render_fields( 'edit-' . $document->id, $document, $can_publish, $document->filename ); ?>
					<p class="afspaces-field">
						<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Speichern', 'afspaces' ); ?></button>
					</p>
				</form>
			</details>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * „Aus Dokumenten entfernen“-Formular (löscht nur die Metadaten).
		 *
		 * @param int    $space_id    Space-ID.
		 * @param int    $document_id Dokument-ID.
		 * @param string $redirect    Rücksprung-URL.
		 * @return string
		 */
		private function render_remove_form( int $space_id, int $document_id, string $redirect ): string {
			ob_start();
			?>
			<form method="post" class="afspaces-inline-form afspaces-post-document-remove" data-afspaces-confirm="<?php echo esc_attr__( 'Dieses Dokument aus der Bibliothek entfernen? Die Datei bleibt im Forum erhalten.', 'afspaces' ); ?>">
				<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
				<input type="hidden" name="afspaces_action" value="remove_document" />
				<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
				<input type="hidden" name="document_id" value="<?php echo esc_attr( (string) $document_id ); ?>" />
				<?php if ( '' !== $redirect ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>" />
				<?php endif; ?>
				<button type="submit" class="afspaces-post-document-action afspaces-post-document-action--danger"><?php echo esc_html__( 'Aus Dokumenten entfernen', 'afspaces' ); ?></button>
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
		 * @param string             $filename    Dateiname (für Standardtitel-Hinweis).
		 * @return string
		 */
		private function render_fields( string $id_prefix, ?SpaceDocument $document, bool $can_publish, string $filename ): string {
			$title      = $document ? $document->title : '';
			$topic      = $document ? $document->document_topic : '';
			$visibility = $document ? $document->visibility : SpaceDocument::VISIBILITY_MEMBERS;
			$options    = SpaceDocument::visibility_options();
			$default    = SpaceDocument::default_title_from_filename( $filename );

			ob_start();
			?>
			<p class="afspaces-field">
				<label for="afspaces-pdoc-title-<?php echo esc_attr( $id_prefix ); ?>"><?php echo esc_html__( 'Titel (optional)', 'afspaces' ); ?></label>
				<input type="text" id="afspaces-pdoc-title-<?php echo esc_attr( $id_prefix ); ?>" name="title" maxlength="200" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr( $default ); ?>" />
			</p>
			<p class="afspaces-field">
				<label for="afspaces-pdoc-topic-<?php echo esc_attr( $id_prefix ); ?>"><?php echo esc_html__( 'Thema (optional)', 'afspaces' ); ?></label>
				<input type="text" id="afspaces-pdoc-topic-<?php echo esc_attr( $id_prefix ); ?>" name="document_topic" maxlength="120" value="<?php echo esc_attr( $topic ); ?>" placeholder="<?php echo esc_attr__( 'z. B. Protokolle', 'afspaces' ); ?>" />
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
		 * Prüft, ob der aktuelle Nutzer erweiterte Sichtbarkeit veröffentlichen darf.
		 *
		 * @param int $space_id Space-ID.
		 * @return bool
		 */
		private function can_publish( int $space_id ): bool {
			return $this->documents->can_publish_for( $space_id, get_current_user_id() );
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
	}
}
