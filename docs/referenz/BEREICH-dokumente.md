# Bereich: Dokumentbibliothek (Gruppendokumente)

Die Dokumentbibliothek kennzeichnet bestehende Asgaros-Anhänge zusätzlich als
„Dokument der Arbeitsgruppe“. Sie führt **keinen** neuen Upload-Typ ein: Dateien
werden weiterhin ausschließlich über die Asgaros-Uploadfunktion hochgeladen.
AFSpaces besitzt nur die zusätzliche Bedeutung, deren Ordnung (Thema),
Sichtbarkeit und Darstellung.

## Fachliches Modell: Anhang vs. Dokument

| Begriff | Bedeutung | Eigentümer |
| --- | --- | --- |
| Asgaros-Anhang | Physische Datei am Forumbeitrag (`forum_posts.uploads`) | Asgaros |
| Asgaros-Topic | Diskussionskontext / Herkunft eines Beitrags | Asgaros |
| Dokument | Metadaten zu einem Anhang (Titel, Sichtbarkeit …) | AFSpaces |
| Dokumentthema (`document_topic`) | Ordnungssystem der Bibliothek (Freitext) | AFSpaces |

Ein Dokumentthema ist unabhängig vom Asgaros-Topic-Titel und wird nicht
automatisch daraus abgeleitet.

## Komponenten

| Zweck | Datei |
| --- | --- |
| Domain-Modell + Validierung | `src/Domain/SpaceDocument.php` |
| Persistenz (Tabelle `afspaces_space_documents`) | `src/Adapters/Database/SpaceDocumentRepository.php` |
| Lesender Asgaros-Zugriff (Uploads/Kontext) | `src/Adapters/Asgaros/DocumentSourceInterface.php` (implementiert von `AsgarosAdapter`) |
| Anwendungslogik + Rechte + Sichtbarkeit | `src/Application/DocumentService.php` |
| Vollständige Dokumentansicht (Hub) | `src/Interface/DocumentsView.php` |
| Forum-Integration + Toolbox-Vorschau + Cleanup | `src/Interface/ForumDocumentControls.php` |
| Bereich in der Gruppen-Detailansicht | `src/Interface/WorkingGroupView.php` |

## Datenmodell

Tabelle `{$wpdb->prefix}afspaces_space_documents` (siehe
[DATENBANK.md](DATENBANK.md)):

- natürlicher Schlüssel `space_id + asgaros_post_id + filename` (Unique),
- Felder `id`, `space_id`, `asgaros_post_id`, `filename`, `title`,
  `document_topic`, `visibility`, `created_by`, `created_at`, `updated_at`,
- Indizes auf `space_id`, `asgaros_post_id`, `(space_id, document_topic)`,
  `(space_id, created_at)`.

Es wird **keine** Datei-URL gespeichert; sie wird deterministisch aus der
Asgaros-Struktur erzeugt (`AsgarosAdapter::get_upload_file_url()`).

## Sichtbarkeit

`SpaceDocument`-Konstanten:

- `VISIBILITY_MEMBERS` (`members`, Standard): nur Arbeitsgruppenmitglieder.
- `VISIBILITY_AUTHENTICATED` (`authenticated`): alle angemeldeten Nutzer.
- `VISIBILITY_PUBLIC` (`public`): vorbereitet, standardmäßig **nicht** wählbar;
  nur aktiv, wenn der Filter `afspaces_documents_public_enabled` `true` liefert.
  Nicht freigeschaltete `public`-Werte werden auf `authenticated` zurückgestuft.

`DocumentService::can_view_document()`:

- Verwaltende (`SpacePolicy::can_manage`) sehen alle Dokumente ihres Space.
- `members`: nur Gruppenmitglieder (`primary_group_id` +
  `DocumentSourceInterface::is_user_in_group`).
- `authenticated`: jeder angemeldete Nutzer.
- `public`: alle, sofern freigeschaltet.

## Trennung von Dokument- und Forumsberechtigung

Ein Dokument kann eine großzügigere Sichtbarkeit besitzen als der zugehörige
Forumbeitrag. `DocumentService::document_view_model()` gibt interne
Kontextinformationen (Themenname, „Zum Forumsbeitrag“) **nur** aus, wenn
`can_read_forum_context()` zutrifft:

- `can_manage`, oder
- Gruppenmitglied, oder
- `space.visibility === 'public'`, oder
- `space.visibility === 'protected'` und angemeldet.

Andernfalls sehen Berechtigte die Datei, aber keinen Forumstitel, Topic-Titel,
Autor oder Beitragslink.

## Berechtigungen

- Aufnehmen (`create_document`): `can_manage` **oder** (Mitglied **und** eigener
  Beitrag). Erweiterte Sichtbarkeit (> `members`) erfordert
  `SpacePolicy::can_publish_document` (= `can_manage`).
- Bearbeiten/Entfernen: `can_manage` **oder** Ersteller des Dokuments.
- „Aus Dokumenten entfernen“ löscht nur die AFSpaces-Metadaten; die Asgaros-Datei
  bleibt erhalten.

Zusätzliche Policy-Methoden: `SpacePolicy::can_manage_documents()`,
`SpacePolicy::can_publish_document()` (siehe [HOOKS.md](HOOKS.md) für Filter).

## Asgaros-Integration

Über `DocumentSourceInterface` (implementiert vom `AsgarosAdapter`):

- `get_post_uploads(post_id)` – physisch vorhandene Anhangdateien,
- `post_upload_exists(post_id, filename)` – Registrierung + physische Existenz,
- `get_upload_file_url(post_id, filename)` – deterministische Download-URL
  (`.../uploads/asgarosforum/<post_id>/<filename>`),
- `resolve_post_context(post_id)` – `topic_id`, `forum_id`, `topic_name`,
  `author_id`,
- `get_post_link(post_id, topic_id)` – Deep-Link zum Beitrag,
- `is_user_in_group(user_id, group_id)` – Mitgliedschaft.

Sicherheitsprüfung beim Aufnehmen (`DocumentService::require_valid_attachment`):
Space aktiv, Beitrag existiert, Beitrag gehört zu Primär- **oder** Zusatzforum
(`SpaceRepository::is_forum_in_space`), Dateiname per `wp_basename` /
`sanitize_file_name` bereinigt und tatsächlich am Beitrag vorhanden.

## Hooks

Konsumiert (Asgaros):

- `asgarosforum_after_post_message` (`author_id`, `post_id`) – rendert die
  Dokumentaktionen unter der Uploadliste jedes Beitrags.
- `asgarosforum_after_delete_post` (`post_id`) – entfernt verwaiste
  Dokument-Metadaten. Beim Löschen eines Themas feuert Asgaros diesen Hook je
  Beitrag; zusätzlich filtert `DocumentService::list_documents()` defensiv.

Eigene Filter:

- `afspaces_toolbox_documents_content` – kompakte Toolbox-Vorschau.
- `afspaces_documents_public_enabled` (Standard `false`) – öffentliche
  Sichtbarkeit freischalten.

## Ansichten

Vollständige Dokumentansicht (`SpacesUrls::VIEW_DOCUMENTS`, `documents`):

- Ansichten „Alle“, „Nach Themen“, „Neueste“ (GET `afspaces_doc_view`),
- Sortierung Upload-Datum auf-/absteigend, Dateiname A–Z/Z–A (`afspaces_doc_sort`),
- Themenfilter (`afspaces_doc_topic`) und einfache Suche (`afspaces_doc_q`,
  Titel/Dateiname/Thema),
- No-JS-tauglich als GET-Formular; Aufnehmen/Bearbeiten über `<details>`.

Erreichbar aus der Forum-Toolbox (kompakte Vorschau) und der Gruppen-Detailansicht
(`WorkingGroupView`, Bereich „Dokumente“) – beide nutzen denselben
`DocumentService` und dieselben Sichtbarkeitsprüfungen.

## Frontend-Actions

`add_document`, `update_document`, `remove_document` (siehe
[FRONTEND-ACTIONS.md](FRONTEND-ACTIONS.md)). Aus dem Forum ausgelöste Aktionen
kehren via `redirect_to` ins Forum zurück, sonst in die Dokumentansicht.

## Tests

- `tests/SpaceDocumentDomainTest.php` – Sichtbarkeits-/Titel-/Themen-/
  Dateinamens-Normalisierung, öffentliche Sichtbarkeit hinter Filter.
- `tests/DocumentServiceTest.php` – Erstellung, Idempotenz, Primär-/Zusatzforum,
  fremdes Forum abgelehnt, Rechte (Mitglied/Autor/Verantwortliche/Nicht-Mitglied/
  Admin), Sichtbarkeit, interner Kontextschutz, Sortierung/Filter, Lifecycle
  (Datei/Beitrag/Forum entfernt), Toolbox-/Kontext-Auflösung.
