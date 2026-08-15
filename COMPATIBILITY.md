# COMPATIBILITY.md

## Zweck

Dieses Dokument wird während der Entwicklung mit konkret geprüften Versionen und internen Asgaros-Schnittstellen aktualisiert.

## Ausgangslage

Asgaros dokumentiert zahlreiche Actions und Filters für Frontend-Erweiterungen sowie Ereignisse beim Hinzufügen oder Entfernen von Benutzern aus Benutzergruppen. Eine vollständige öffentliche CRUD-API für Foren und Benutzergruppen ist daraus nicht ersichtlich.

## Geprüfte Versionen (Stand MVP 1)

- **Getestete WP Local-Instanz:** Asgaros Forum `3.4.0`.
- **Mindestversion (`AFSPACES_MIN_ASGAROS_VERSION`):** `3.4.0`.
- **Hauptklasse:** `AsgarosForum` (definiert in `asgaros-forum/includes/forum.php`, instanziiert in `asgaros-forum.php`).
- **Versionserkennung:** Konstante `ASGAROS_FORUM_VERSION` sofern definiert, sonst `get_plugin_data()` auf `asgaros-forum/asgaros-forum.php`.
- **Aktivitätsprüfung:** `class_exists('AsgarosForum')` bzw. `is_plugin_active('asgaros-forum/asgaros-forum.php')`.

### Forum-Styles

Der ForumStyleLayer verwendet die bekannten WordPress-Style-Handles
asgarosforum-style und asgarosforum-custom-style nur dann als Dependency,
wenn sie in der aktuellen Asgaros-Version registriert sind. Der Layer wird
zusätzlich über wp_enqueue_scripts mit Priorität 999 enqueued. Damit bleibt
die Reihenfolge auch bei einer abweichenden Handle-Registrierung updatefest:
Asgaros style.css, Asgaros custom.css, danach
assets/afspaces-forum-overrides.css. Asgaros-Dateien werden nicht verändert.

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

Für Issue #9 entfernt der Adapter bei Asgaros 3.4.0 den registrierten Callback
`AsgarosForumNotifications::show_subscription_navigation()` aus
`asgarosforum_bottom_navigation`. Über den dokumentierten Filter
`asgarosforum_filter_header_menu` wird daraus ein kontextabhängiger Nav-Eintrag
direkt vor `subscription` erzeugt. URL, Nonce und Zustand stammen weiterhin aus
Asgaros; die intern verwendete Notifications-Methode bleibt ausschließlich im
`AsgarosAdapter` gekapselt und degradiert bei fehlender API defensiv.

Die Frontend-Verwaltung ist über die Asgaros-Forum-Navigation erreichbar. Dafür werden ausschließlich **dokumentierte, öffentliche Asgaros-Hooks** genutzt (keine internen Klassen):

- Filter `asgarosforum_filter_header_menu` — fügt den Menüpunkt „Räume" in die Forum-Navigation ein (nur für berechtigte, angemeldete Benutzer).
- Action `asgarosforum_overview_custom_content_top` — rendert ein kompaktes Einstiegs-Panel auf der Forum-Übersicht.

Die eigentliche Verwaltung läuft über eine bei Aktivierung automatisch angelegte WordPress-Hub-Seite mit dem Shortcode `[afspaces]`. Der Standard-Slug ist `afspaces`; WordPress vergibt bei einem Konflikt einen freien eindeutigen Slug. Die Option `afspaces_hub_page_id` ist die primäre Referenz. Titel und Slug dürfen nachträglich geändert werden, ohne dass eine zweite Seite entsteht. Das Meta `_afspaces_managed_page=1` ist der Eigentumsnachweis für Cleanup und Wiederauffinden. Eine fremde Seite mit dem Standard-Slug wird nicht übernommen.

Alte Einzelseiten (`afspaces-dashboard`, `afspaces-members`, `afspaces-invitations`, `afspaces-my-invitations`) werden per 301 auf die entsprechende Hub-Unteransicht umgeleitet.

Die Seiten `Einstellungen -> AFSpaces Look & Feel` und `Einstellungen -> AFSpaces Installation` nutzen ausschließlich WordPress-Settings-APIs. Die Installationsseite steuert das optionale vollständige Cleanup; der Default ist AUS.

Bei Aktivierung werden Voraussetzungen vor Tabellen- oder Seitenerstellung geprüft. AFSpaces initialisiert sich nur mit PHP 8.1+ und aktivem Asgaros Forum 3.4.0+. Die einmalige Admin-Meldung zeigt Hub-Status, erkannte Asgaros-Version und den sicheren Default der deaktivierten Selbstgründung.

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

### Hybride & semantische Suche (Phase 2–4)

- **WordPress-Beiträge:** `WpPostSearch` nutzt ausschließlich `WP_Query` (kein Asgaros-Interna). SearchWP verbessert die Suche transparent im Native-Modus, ist aber keine Voraussetzung; es besteht keine harte Abhängigkeit zu SearchWP oder Relevanssi.
- **Fusion:** `HybridSearchService` + `ResultFusion` (Reciprocal Rank Fusion) führen Foren-, WP- und semantische Ranglisten zusammen. Rein interne Logik, keine externen APIs.
- **Indexierung:** `AsgarosAdapter::list_posts_for_index()` / `count_all_posts()` lesen dieselben Tabellen (`posts`/`topics`/`forums`) wie die Keyword-Suche (nur `t.approved = 1`). `SearchIndexer` läuft über `wp_cron` (`afspaces_reindex_search`, täglich) sowie die dokumentierten WP-Hooks `save_post`, `trashed_post`, `deleted_post`.
- **Embedding-API:** `EmbeddingClient` spricht eine OpenRouter-kompatible API via `wp_remote_post` an (Modell-Default `perplexity/pplx-embed-v1-0.6b`). Standardmäßig deaktiviert; siehe SECURITY_PRIVACY.md.
- **Eigene Tabelle:** `wp_afspaces_search_index` (Embeddings) via `dbDelta`; bei ausdrücklich aktiviertem vollständigem Cleanup entfernt. Kein Bezug zu Asgaros-Tabellen.
- **Verhalten ohne Konfiguration:** Ist kein API-Schlüssel gesetzt, liefern Vektorsuche und Reindex einen sauberen No-op (keine Fehler); die Keyword-/Hybridsuche bleibt voll funktionsfähig.
- **Relevanzschwelle:** Semantische Treffer unter einer konfigurierbaren Cosine-Mindestähnlichkeit (`semantic_min_score`, Default `0.30`) werden verworfen. Kalibrierung an der Testinstanz: echte Treffer ≈ 0.48–0.54, verwandte ≈ 0.36–0.42, Rauschen ≈ 0.20–0.28 — daher blendet 0.30 unpassende „Treffer“ (z. B. für Begriffe ohne Korpusbezug) aus.

### Ersatz der eingebauten Asgaros-Suche

Die Asgaros-Bestandssuche wird durch die AFSpaces-Suche ersetzt: `SpacesHubController::redirect_forum_search()` leitet per `template_redirect` (302) von der Asgaros-Suchansicht auf `.../afspaces/?afspaces_view=search&afspaces_q=<keywords>` um. Die Erkennung der Suchansicht ist im Adapter gekapselt (`AsgarosAdapter::is_search_request()` prüft `$asgarosforum->current_view === 'search'`); es werden keine Asgaros-Interna außerhalb des Adapters verwendet. Schleifenschutz: Auf der Hub-Seite selbst wird nicht umgeleitet.





## Interne Asgaros-APIs für MVP 4 (Selbstgründung, geprüft gegen 3.4.0)

Die Raumgründung nutzt folgende interne Asgaros-Funktionen (Quellcode geprüft in
`asgaros-forum/includes/forum-content.php` und `forum-usergroups.php`):

- `AsgarosForum::content->insert_forum($category_id, $name, $description, $parent_forum, $icon, $order, $status='normal')`
  → liefert die neue `forum_id`.
- Forenkategorie = WP-Term der Taxonomie `asgarosforum-category` (Literal, ungefiltert).
  Zugriffslevel über Term-Meta `category_access` (`everyone` | `loggedin` | `moderator`),
  Sortierung über Term-Meta `order`.
- `AsgarosForumPermissions::isModerator( $user_id )` wird für den globalen
  Moderationsausnahmepfad des Schreibschutzes verwendet.
- `AsgarosForumUserGroups::insertUserGroup($parent_category_id, $name, $color, $visibility, $auto_add, $icon)`
  legt eine Benutzergruppe (Term der Taxonomie `asgarosforum-usergroup`) an. Der Rückgabewert
  enthält NICHT die Term-ID; diese wird über `get_term_by('name', …, $taxonomy)` ermittelt.
  Der Taxonomiename wird über den Filter `asgarosforum_filter_user_groups_taxonomy_name`
  aufgelöst (nicht über die private Eigenschaft `$taxonomyName`).
- `AsgarosForumUserGroups::insertUserGroupsOfForumCategory($category_id, $ids)`
  bzw. `getUserGroupsIDsOfForumCategory($category_id)` verwalten die zugriffssteuernde
  Zuordnung (Term-Meta `usergroups` der Kategorie).
- `get_term($group_id, $taxonomy)` liest den Namen einer Benutzergruppe für die
  Verwaltungsansicht; der Taxonomiename wird über den Filter
  `asgarosforum_filter_user_groups_taxonomy_name` aufgelöst. Fehlt der Term, degradieren
  Adapter und Registrierungsansicht defensiv.
- Löschung: `wp_delete_term($group_id, $usergroup_taxonomy)`,
  `wp_delete_term($category_id, 'asgarosforum-category')` und direktes Löschen der
  Forum-Zeile aus `tables->forums`.

### Zugriffsmodell und Isolationsentscheidung

Asgaros steuert den Zugriff auf **Kategorie-Ebene** (`canUserAccessForumCategory`: bei
nichtleeren `usergroups` ist nur die Schnittmenge der Benutzergruppen zugriffsberechtigt;
Administratoren umgehen die Prüfung). Ein einzelnes Forum kann NICHT unabhängig von seiner
Kategorie zugriffsbeschränkt werden.

**Entscheidung:** Jeder selbstgegründete Raum erhält daher eine **eigene, dedizierte
Forenkategorie** samt eigener Benutzergruppe und genau einem Forum. Nur so ist die in
`SECURITY_PRIVACY.md` geforderte Isolation privater Räume gewährleistet. Die administrativ
konfigurierbare Sichtbarkeit steuert das Zugriffslevel der dedizierten Kategorie:

- `private`  → `category_access=loggedin` + Gruppe als Zugriffssperre (nur Mitglieder).
- `protected`→ `category_access=loggedin`, Gruppe nur zur Mitgliederverwaltung (alle Angemeldeten lesen).
- `public`   → `category_access=everyone`.

Asgaros 3.4.0 prüft beim Einfügen von Themen und Beiträgen standardmäßig nur
Anmeldung, Forumstatus und globale Forenrechte; `category_access=loggedin`
begrenzt das Lesen, aber nicht das Schreiben. Deshalb nutzt AFSpaces zusätzlich
die dokumentierten Filter `asgarosforum_filter_insert_custom_validation` und
`asgarosforum_filter_check_access`, gekapselt in
`Application\ForumContentWritePolicy`. Die Policy erlaubt in `private` und
`protected` nur Gruppenmitglieder oder globale Asgaros-Moderatoren. Bei fehlendem
aktuellen Forum-/Asgaros-Kontext wirkt der Schutz nur für nicht von AFSpaces
registrierte Foren; registrierte Räume werden ohne gültigen Mitglieds- oder
Moderationsnachweis nicht schreibbar.

Räume mit Freigabepflicht (`pending`) werden bis zur Freigabe **immer** über die Gruppe
beschränkt, unabhängig von der Zielsichtbarkeit, damit vor der Freigabe kein ungewollter
öffentlicher Zugriff entsteht.

## Topic-Pinning in Asgaros 3.4.0

### Transaktion und Rollback

Asgaros speichert den lokalen Sticky-Status in der Spalte `sticky` der
Topics-Tabelle. Die interne Methode `AsgarosForum::set_sticky()` ist für
AFSpaces nicht geeignet: Sie prüft globale Asgaros-Moderatorrechte und
verweigert Topics in privaten Foren. `AsgarosAdapter::set_topic_pinned()`
kapselt deshalb das vorbereitete `$forum->db->update()` ausschließlich im
Adapter und setzt nur `sticky = 1` (lokal) oder `sticky = 0`; der globale Wert
`2` wird nicht verwendet. Die raumbezogene Policy- und Objektprüfung erfolgt
vorher im `SpaceModerationService`.

### Moderations-UI-Deduplizierung gegen Asgaros 3.4.0

Die native Moderationsdarstellung wurde gegen
`includes/forum-permissions.php` und `includes/forum.php` geprüft. Der
Adapter kapselt folgende aktionsbezogene Methoden:

- `can_delete_topic()` für „Thema löschen“.
- `can_delete_post()` für „Beitrag löschen“.
- `can_open_topic()` und `can_close_topic()` für Öffnen/Schließen.
- `can_pin_topic()` für Anpinnen und Abpinnen.
- `isModerator()` für „Thema verschieben“, weil Asgaros dort keine eigene
  `can_move_topic()`-Methode nutzt.

Für Topic-/Post-Aktionen, die Asgaros nur bei freigegebenen Themen in der
aktuellen Topic-Ansicht rendert, prüft der Adapter zusätzlich
`approval->is_topic_approved()`. Das verhindert, dass AFSpaces einen lokalen
Link ausblendet, wenn der native Button in diesem Kontext gar nicht erscheint.
Asgaros stellt in dieser Ansicht keinen nativen „Beitrag verschieben“-Button
bereit; diese lokale AFSpaces-Aktion wird daher nicht dedupliziert.

Die Zuordnung und die UI-Entscheidung liegen in
`AsgarosAdapter::can_perform_moderation_action()` sowie
`ModerationActionVisibility::should_render_local_action()`. Bei fehlenden
Methoden oder unbekannten Versionen wird konservativ `false` für die native
Aktion zurückgegeben. Die lokalen Handler bleiben unabhängig davon geschützt.

Kategorie, Gruppe und Forum werden nacheinander angelegt; bei einem Teilfehler entfernt der
`SpaceCreationService` die bereits erstellten Artefakte in umgekehrter Reihenfolge
(Forum → Gruppe → Kategorie) und löscht den Space-Datensatz. Live gegen Asgaros 3.4.0
verifiziert (Anlegen, Zugriffsbeschränkung, Löschung inkl. Bereinigung).

### Schema-Migration

Die Spaces-Tabelle erhielt die Spalte `rejection_reason`. Bestehende Installationen ziehen sie
über `SpaceRepository::install()` in `Plugin::maybe_upgrade()` nach (Plugin-Version `0.2.0`).
