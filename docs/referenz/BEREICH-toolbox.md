# Bereich: Werkzeugkasten (Toolbox)

Der Werkzeugkasten stellt Mitgliedern einer Arbeitsgruppe einen zentralen
Einstiegspunkt „Toolbox“ im Asgaros-Forum-Menü bereit. Ein Klick öffnet einen
Dialog mit den konfigurierten Links und einem Einstieg für die – in einem
Folgeauftrag umzusetzende – Dokumentenansicht.

## Komponenten

| Zweck | Datei |
| --- | --- |
| Domain-Modell + Validierung | `src/Domain/SpaceLink.php` |
| Persistenz (Tabelle `afspaces_space_links`) | `src/Adapters/Database/SpaceLinkRepository.php` |
| Anwendungslogik + Rechteprüfung | `src/Application/ToolboxService.php` |
| Forum-Menüpunkt + Dialog | `src/Interface/ForumToolbox.php` |
| Linkverwaltung (Hub-View) | `src/Interface/ToolboxLinksView.php` |

## Forum-Integration

- `ForumToolbox::init()` registriert die Asgaros-Action
  `asgarosforum_custom_header_menu` (Menüpunkt), `wp_footer` (Dialog) und
  `wp_enqueue_scripts` (Assets nur auf Forumseiten einer Arbeitsgruppe).
- Der aktuelle Forumkontext wird über `AsgarosAdapter::get_current_forum_id()`
  ermittelt. Asgaros setzt `current_forum` auch in Topic-, Antwort-, Neues-Thema-
  und Bearbeiten-Ansichten, sodass die Toolbox im gesamten Forumkontext sichtbar
  ist.
- Die Zuordnung Forum → Arbeitsgruppe erfolgt über
  `SpaceRepository::get_space_by_forum()`, das Primär- und Zusatzforen
  (Tabelle `afspaces_space_forums`) berücksichtigt. Nur aktive Spaces
  (`status = active`) zeigen die Toolbox.
- Auf Foren ohne Arbeitsgruppe erscheint der Menüpunkt nicht.

## Dialog

- Server-seitig im Footer gerendert; ohne JavaScript über den Anker
  `#afspaces-toolbox-dialog` (CSS `:target`) erreichbar. `assets/afspaces.js`
  ergänzt Modal-Verhalten, Fokusfalle sowie Schließen per `Escape`, Schließen-
  Button und Backdrop.
- Bereich **Links**: Liste der konfigurierten Links (Titel, optionale
  Beschreibung, optionales Icon). URLs mit `esc_url()`, Texte mit `esc_html()`
  ausgegeben. Leerzustand: „Für diese Arbeitsgruppe wurden noch keine Links
  eingerichtet.“
- Bereich **Dokumente**: aktuell ein klar gekennzeichneter, noch nicht aktiver
  Einstieg. Der Filter `afspaces_toolbox_documents_content`
  (`string $content`, `Space $space`, `int $actor_user_id`) ist die
  Erweiterungsstelle für die spätere Dokumentenansicht; gibt er nicht-leeres,
  bereits escaptes HTML zurück, ersetzt dieses den Platzhalter.

## Linkverwaltung

- Hub-View `SpacesUrls::VIEW_TOOLBOX` (`toolbox-links`), als Kontext-Tab
  „Toolbox“ nur für Verwaltungsberechtigte sichtbar.
- Rechteprüfung ausschließlich über `SpacePolicy::can_manage` (keine parallele
  Rollenlogik).

| Methode (`ToolboxService`) | Signatur |
| --- | --- |
| `resolve_space_by_forum` | `(int $forum_id): ?Space` |
| `list_links` | `(int $space_id): SpaceLink[]` |
| `add_link` | `(int $space_id, int $actor, array $input): int` |
| `update_link` | `(int $space_id, int $actor, int $link_id, array $input): void` |
| `delete_link` | `(int $space_id, int $actor, int $link_id): void` |
| `reorder_links` | `(int $space_id, int $actor, array $ordered_ids): void` |
| `move_link` | `(int $space_id, int $actor, int $link_id, string $direction): void` |

- Frontend-Actions: `add_toolbox_link`, `update_toolbox_link`,
  `delete_toolbox_link`, `move_toolbox_link` (siehe
  [FRONTEND-ACTIONS.md](FRONTEND-ACTIONS.md)).
- Validierung: `SpaceLink::sanitize_title()`, `SpaceLink::sanitize_url()`
  (nur `http`/`https` bzw. site-relative Pfade; `javascript:`, `data:` und
  schemalose `//host`-URLs werden verworfen), `SpaceLink::sanitize_icon()`.

## Geplante Dokumentenfunktion (Folgeauftrag)

Noch **nicht** umgesetzt. Der Folgeauftrag soll:

- alle in den Foren dieser Arbeitsgruppe (Primär- und Zusatzforen) hochgeladenen
  Dateien erfassen,
- sie in einer gemeinsamen Liste darstellen,
- mindestens nach Dateiname (alphabetisch) und Upload-Datum sortierbar machen,
- später die Navigation vom Dokument zum zugehörigen Forumbeitrag ermöglichen.

Anbindung erfolgt über den bestehenden Filter
`afspaces_toolbox_documents_content` bzw. den vorhandenen `data-afspaces-toolbox-documents`-Button, ohne den bestehenden Dialog neu zu bauen.

## Tests

- `tests/SpaceLinkDomainTest.php` — URL-/Titel-/Icon-Validierung.
- `tests/ToolboxServiceTest.php` — Rechteprüfung, CRUD, Sortierung,
  Forum→Space-Auflösung.
