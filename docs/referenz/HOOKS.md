# Hook-Referenz

Diese Referenz ist aus den `add_action()`, `add_filter()`, `do_action()` und `apply_filters()`-Aufrufen im aktuellen Code abgeleitet. AFSpaces-eigene Filter und Actions sind vorgesehene Erweiterungspunkte. Eine langfristige Stabilitätszusage oder ein `@since`-Versprechen wird dort nicht ergänzt, wo es im Projekt nicht belastbar festgelegt ist.

## WordPress-Hooks

| Hook | Ort / Argumente | Rückgabe / Default | Zweck |
| --- | --- | --- | --- |
| `init` | `FrontendController::init`; keine Argumente | — | serverseitige Frontend-Actions verarbeiten |
| `wp_ajax_afspaces_action` | `FrontendController::init`; keine Argumente | — | AJAX-Variante derselben Frontend-Actions |
| `rest_api_init` | `Plugin::init`; keine Argumente | — | REST-Routen registrieren |
| `wp_enqueue_scripts` | `FrontendController`, `ForumNavigation`, `SearchModal`; keine Argumente | — | Frontend-Assets laden |
| `wp_footer` | `SearchModal::init`; keine Argumente | — | Such-Overlay ausgeben |
| `template_redirect` | `SpacesHubController::init`; keine Argumente | — | Legacy-Seiten und die Asgaros-Suche umleiten |
| `admin_menu` / `admin_init` | Settings-Pages; keine Argumente | — | Admin-Seiten und Settings registrieren |
| `admin_post_afspaces_search_reindex` | `SearchSettingsPage::init`; keine Argumente | — | manuellen Reindex ausführen |
| `save_post` | `SearchIndexer::init`; WordPress liefert Post-ID, Post-Objekt und Update-Flag | — | Indexeintrag aktualisieren |
| `trashed_post` / `deleted_post` | `SearchIndexer::init`; Post-ID | — | Indexeintrag entfernen |
| `afspaces_reindex_search` | `SearchIndexer::CRON_HOOK`; keine Argumente | — | geplanter Reindex |
| `wp_privacy_personal_data_exporters` | `Plugin::init`; Exporter-Array | Exporter-Array | Invitation- und Join-Request-Exporter registrieren |
| `wp_privacy_personal_data_erasers` | `Plugin::init`; Eraser-Array | Eraser-Array | persönliche Nachrichten löschen |
| `admin_notices` | `Plugin::init`; keine Argumente | — | einmalige Aktivierungs-/Einrichtungsmeldung |

## Asgaros-Hooks

| Hook | Ort / Argumente | Rückgabe / Default | Status |
| --- | --- | --- | --- |
| `asgarosforum_filter_header_menu` | `ForumNavigation::init`; Menü-Array | Menü-Array | öffentliche Asgaros-Integration |
| `asgarosforum_content_header` | `ForumNavigation::init`; keine Argumente | — | Einstiegs-Panel rendern |
| `asgarosforum_content_top` | `ForumNavigation::init`; keine Argumente | — | Kategorie-Farbmarkierungen rendern |
| `asgarosforum_after_post_message` | `ForumModerationControls::init`, Priorität 20, 2 Argumente: `int $author_id`, `int $post_id` | — | raumbezogene Moderationskontrollen rendern |
| `asgarosforum_filter_user_groups_taxonomy_name` | `AsgarosAdapter`; aktueller Taxonomiename | Taxonomiename, Default `asgarosforum-usergroup` | interne Adapter-Auflösung; nicht als AFSpaces-Fach-API verwenden |

## AFSpaces-Filter

Alle folgenden Filter sind im aktuellen Code öffentliche Erweiterungspunkte. Filter dürfen niemals die serverseitige Authentifizierung, Policy oder Objektprüfung ersetzen.

| Filter | Parameter | Rückgabewert / Default | Quelle und Zweck |
| --- | --- | --- | --- |
| `afspaces_forum_url_after_accept` | `string $url`, `Space $space`, `Invitation $invitation` | string; Default `home_url('/forum/')` | Ziel nach persönlicher Einladung |
| `afspaces_forum_url_after_invite_link` | `string $url`, `Space $space`, `InviteLink $link`, `int $actor_user_id` | string; Default `home_url('/forum/')` | Ziel nach Invite-Link-Nutzung |
| `afspaces_allow_invite_link_registration` | `bool $enabled`, `Space $space` | bool; Default WordPress-Option `users_can_register` | Registrierung über Invite-Link erlauben |
| `afspaces_allow_unlimited_invite_links` | `bool $allowed`, `int $space_id`, `int $actor_user_id` | bool; Default `SpacePolicy::can_create_unlimited_invite_links()` | unbegrenzte Link-Nutzung für den Ersteller erlauben |
| `afspaces_is_user_blocked_for_invite` | `bool $blocked`, `int $user_id`, `int $space_id` | bool; Default vorhandene Block-/Ausschlussprüfung | Einladungen für eine Person sperren |
| `afspaces_invitation_mail_subject` | `string $subject`, `Invitation $invitation`, `Space $space` | string; erzeugter Standardbetreff | E-Mail-Betreff anpassen |
| `afspaces_invitation_mail_body` | `string $body`, `Invitation $invitation`, `Space $space`, `string $accept_url` | string; erzeugter Standardtext | E-Mail-Inhalt anpassen |
| `afspaces_central_notification_email` | `string $email`, `JoinRequest $request` | string; Default Option `afspaces_central_notification_email` oder leer | zusätzliche Adresse für Join-Request-Benachrichtigungen |
| `afspaces_working_group_topics_taxonomy` | `string $taxonomy` | string; Default `themen` | Topic-Taxonomie für Arbeitsgruppen |
| `afspaces_space_forum_url` | `string $url`, `Space $space`, `array<string,mixed>|null $forum`, `int $user_id` | string; zuvor ermittelter Forum-Link | Arbeitsgruppen-Forum-Link in Views/Moderation |
| `afspaces_forum_home_url` | `string $url` | string; Default `home_url('/forum/')` | Forum-Startseite in Breadcrumbs/Navi |
| `afspaces_hub_navigation_tabs` | `array<int,array<string,mixed>> $tabs`, `string $view`, `int $space_id`, `int $actor` | Tab-Array; AFSpaces-Standardtabs | globale Hub-Navigation erweitern |
| `afspaces_hub_space_navigation_tabs` | `array<int,array<string,mixed>> $tabs`, `string $view`, `int $space_id`, `int $actor` | Tab-Array; AFSpaces-Standardtabs | Space-Kontextnavigation erweitern |
| `afspaces_panel_cache_ttl` | `int $ttl` | int Sekunden; Default `30`; Werte `<= 0` deaktivieren Cache | Forum-Einstiegs-Panel |
| `afspaces_enable_space_creation` | `bool $enabled`, `int $user_id` | bool; Default Option `afspaces_enable_space_creation` | Gründungsoption im Forum-Panel anzeigen |
| `afspaces_profile_post_types` | `array<int,string> $post_types` | Array; Default `['profil']` | Profil-CPTs für die Profilauflösung |
| `afspaces_profile_user_id` | `int $user_id`, `int $explicit_user_id` | int; zuvor erkannte ID | Profil-Zielperson überschreiben |
| `afspaces_wp_search_args` | `array<string,mixed> $args`, `string $keywords` | WP_Query-Argument-Array | WordPress-Suchabfrage erweitern |

Tab-Definitionen für die beiden Hub-Filter verwenden die vom Renderer gelesenen Schlüssel `view`, `label`, `url` und `active`. Zusätzliche Schlüssel dürfen transportiert werden, werden aber vom AFSpaces-Renderer nicht automatisch ausgegeben.

## AFSpaces-Actions

| Action | Argumente | Zeitpunkt / Zweck |
| --- | --- | --- |
| `afspaces_invitation_notification_created` | `Invitation $invitation` | nach erfolgreichem Versand einer Einladungs-E-Mail |
| `afspaces_invite_link_registration_captcha` | `InviteLink $link`, `Space $space` | vor dem Registrierungsformular für Invite-Links; CAPTCHA-Markup einfügen |

## Pflegehinweis

Bei einem neuen Hook müssen die tatsächliche Aufrufstelle, Argumentreihenfolge, der Default und die Rückgabeart zuerst im Code geprüft und anschließend hier ergänzt werden. Bei Änderungen an REST, Frontend-Actions, Adapter oder Datenbank gilt zusätzlich die Zuordnung in `AGENTS.md`, Abschnitt 14.
