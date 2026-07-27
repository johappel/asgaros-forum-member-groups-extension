# COMPATIBILITY.md

## Zweck

Dieses Dokument wird während der Entwicklung mit konkret geprüften Versionen und internen Asgaros-Schnittstellen aktualisiert.

## Ausgangslage

Asgaros dokumentiert zahlreiche Actions und Filters für Frontend-Erweiterungen sowie Ereignisse beim Hinzufügen oder Entfernen von Benutzern aus Benutzergruppen. Eine vollständige öffentliche CRUD-API für Foren und Benutzergruppen ist daraus nicht ersichtlich.

## Geprüfte Versionen (Stand MVP 1)

- **Getestete WP Local-Instanz:** Asgaros Forum `3.4.0`.
- **Mindestversion (`AFSPACES_MIN_ASGAROS_VERSION`):** `3.0.0` (vorläufig; wird mit Adapter-Recherche in M1.2 präzisiert).
- **Hauptklasse:** `AsgarosForum` (definiert in `asgaros-forum/includes/forum.php`, instanziiert in `asgaros-forum.php`).
- **Versionserkennung:** Konstante `ASGAROS_FORUM_VERSION` sofern definiert, sonst `get_plugin_data()` auf `asgaros-forum/asgaros-forum.php`.
- **Aktivitätsprüfung:** `class_exists('AsgarosForum')` bzw. `is_plugin_active('asgaros-forum/asgaros-forum.php')`.

> Hinweis: Die interne Gruppen- und Foren-API (für M1.2) ist noch nicht geprüft. Schreibende Adapter-Methoden bleiben bis dahin deaktiviert.

## Verpflichtende Prüfung

Vor MVP 1 sind zu dokumentieren:

- unterstützte Asgaros-Versionen,
- interne Klassen und Methoden für Benutzergruppen,
- interne Klassen und Methoden für Foren,
- Datenbanktabellen, die nur indirekt über Adapter angesprochen werden,
- Verhalten bei Plugin-Updates,
- Fallback bei unbekannter Version.

## Kompatibilitätsstrategie

- Versionsprüfung beim Start.
- Lesen darf bei sicherer Abwärtskompatibilität möglich bleiben.
- Schreiben wird bei unbekannter Version deaktiviert.
- Administrator erhält konkrete Diagnose.
- Adaptertests laufen gegen jede offiziell unterstützte Version.
- Änderungen an internen APIs führen zu einer neuen Adapterversion, nicht zu Änderungen in Domain oder UI.

## MVP 3, 3.1 und 3.2 Hinweis

- MVP 3 (sichere Einladungslinks), MVP 3.1 (Beitrittsanfragen) und MVP 3.2 (Arbeitsgruppenmodell) nutzen keine zusätzlichen Asgaros-Interna.
- Sie greifen weiter ausschließlich über den vorhandenen Adapter auf Foren-Metadaten, Gruppenmitgliedschaften und Gruppenzuordnungen zu.
- MVP 4 (Raumgründung) wird bei neuen Asgaros-APIs weiterhin nur über den Adapter arbeiten.

## Forum-Navigation (UX-Integration)

Die Frontend-Verwaltung ist über die Asgaros-Forum-Navigation erreichbar. Dafür werden ausschließlich **dokumentierte, öffentliche Asgaros-Hooks** genutzt (keine internen Klassen):

- Filter `asgarosforum_filter_header_menu` — fügt den Menüpunkt „Räume" in die Forum-Navigation ein (nur für berechtigte, angemeldete Benutzer).
- Action `asgarosforum_overview_custom_content_top` — rendert ein kompaktes Einstiegs-Panel auf der Forum-Übersicht.

Die eigentliche Verwaltung läuft über eine einzelne WordPress-Hub-Seite (Slug `afspaces`) mit dem Shortcode `[afspaces]`. Die Unteransicht wird über den Query-Parameter `afspaces_view` gesteuert (`dashboard`, `members`, `invitations`, `join-requests`, `my-invitations`, `discover`, `create`). Es wird **kein** eigener Asgaros-`current_view` in das Forum-Routing eingehängt, da hierfür keine dokumentierte API existiert.

Alte Einzelseiten (`afspaces-dashboard`, `afspaces-members`, `afspaces-invitations`, `afspaces-my-invitations`) werden per 301 auf die entsprechende Hub-Unteransicht umgeleitet.

Die neue Seite `Einstellungen -> AFSpaces Look & Feel` nutzt ausschließlich WordPress-Settings-APIs und beeinflusst nur AFSpaces-Frontend-CSS (keine Asgaros-Interna).

## Forensuche (post-genaue Suche mit Deep-Links)

Die eigene Forensuche (`AFSpaces\Interface\SearchView`, `AFSpaces\Application\ForumSearchService`) ersetzt die Trefferdarstellung der Asgaros-Bestandssuche, ohne den Asgaros-Core zu verändern. Sie kapselt folgende **internen** Asgaros-Schnittstellen ausschließlich im Adapter (`AsgarosAdapter`, geprüft gegen Asgaros `3.4.0`):

- `AsgarosForum::content->get_categories()` — liefert die für den aktuellen Benutzer zugänglichen Kategorien (Zugriffsprüfung inklusive `category_access` und Benutzergruppen). Wird als alleinige Sichtbarkeitsgrenze der Suche verwendet.
- `AsgarosForum::rewrite->get_post_link( $post_id, $topic_id )` — berechnet den Deep-Link `.../topic/<slug>/?part=<N>#postid-<ID>` mit derselben Sortierung und Seitengröße (`options['posts_per_page']`) wie die Themenansicht.
- Tabellen `$forum->tables->posts`, `$forum->tables->topics`, `$forum->tables->forums` (Zugriff über `$forum->db`, ausschließlich mit `$wpdb->prepare()`).
  - Spalten: `posts(id, text, parent_id=topic_id, forum_id, date, author_id)`, `topics(id, parent_id=forum_id, author_id, name, approved, slug)`, `forums(id, name, parent_id=category_term_id, forum_status)`.
  - Es werden die vorhandenen **FULLTEXT-Indizes** auf `posts.text` und `topics.name` genutzt (`MATCH ... AGAINST ( ... IN BOOLEAN MODE )`), analog zur Asgaros-Bestandssuche.

Verhalten bei inkompatibler/unbekannter Version: Fehlen `content->get_categories()` oder `rewrite->get_post_link()`, liefert der Adapter leere Ergebnisse bzw. leere Links, statt einen Fehler auszulösen (defensive Guards via `method_exists`).

Zugriffsschutz: Es werden ausschließlich freigegebene Themen (`t.approved = 1`) aus zugänglichen Kategorien zurückgegeben. Damit legt die Suche weder Titel noch Textausschnitte aus Foren offen, auf die die suchende Person keinen Zugriff hat.

REST: `GET /wp-json/afspaces/v1/search` (`permission_callback`: angemeldet), Parameter `q`, `sort` (`relevance|date`), `page`, `per_page`; die Zugriffsprüfung erfolgt serverseitig im Adapter.


