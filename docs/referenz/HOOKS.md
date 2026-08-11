# Hook-Referenz

Konsumierte Fremd-Hooks und eigene AFSpaces-Hooks. Alle Angaben belegt aus dem Quellcode.

## Konsumierte WordPress-Hooks

| Hook | Registriert in | Zweck |
| --- | --- | --- |
| `init` | `FrontendController::init` | POST-Aktionen verarbeiten (`handle_actions`) |
| `wp_ajax_afspaces_action` | `FrontendController::init` | AJAX-Variante der POST-Aktionen |
| `wp_enqueue_scripts` | `FrontendController`, `ForumNavigation`, `SearchModal` | Frontend-Assets laden |
| `wp_footer` | `SearchModal::init` | Such-Overlay rendern |
| `rest_api_init` | `Plugin::init` | REST-Routen registrieren |
| `template_redirect` | `SpacesHubController::init` | Legacy-Seiten (`redirect_legacy_pages`) und Forensuche (`redirect_forum_search`) umleiten |
| `admin_menu` | Settings-Pages | Optionsseiten registrieren |
| `admin_init` | Settings-Pages | `register_setting` |
| `admin_post_afspaces_search_reindex` | `SearchSettingsPage::init` | manueller Reindex |
| `save_post`, `trashed_post`, `deleted_post` | `SearchIndexer::init` | Index inkrementell pflegen |
| `afspaces_reindex_search` (Cron) | `SearchIndexer` | täglicher Reindex, geplant via `SearchIndexer::schedule` |
| `wp_privacy_personal_data_exporters` | `Plugin::init` | Einladungsdaten exportieren |
| `wp_privacy_personal_data_erasers` | `Plugin::init` | persönliche Einladungsnachrichten löschen |
| `wp_privacy_personal_data_exporters` | `Plugin::init` | Beitrittsanfragen exportieren |
| `wp_privacy_personal_data_erasers` | `Plugin::init` | persönliche Nachrichten von Beitrittsanfragen löschen, Statusdaten behalten |
| `admin_notices` | `Plugin::init` | einmalige Aktivierungs- und Einrichtungsmeldung |

## Konsumierte Asgaros-Hooks

Nur dokumentierte, öffentliche Asgaros-Hooks. Interne Asgaros-Methoden liegen ausschließlich im Adapter (siehe [ADAPTER.md](ADAPTER.md)).

| Hook | Registriert in | Zweck |
| --- | --- | --- |
| `asgarosforum_filter_header_menu` (Filter) | `ForumNavigation::init` | Menüpunkt „Arbeitsgruppen" ins Forum |
| `asgarosforum_content_header` (Action) | `ForumNavigation::init` | Einstiegs-Panel rendern |
| `asgarosforum_content_top` (Action) | `ForumNavigation::init` | Kategorie-Farben ausgeben |
| `asgarosforum_after_post_message` (Action) | `ForumModerationControls::init` | Inline-Moderationskontrollen je Beitrag (Prio 20, 2 Args) |
| `asgarosforum_filter_user_groups_taxonomy_name` (Filter) | Adapter | Taxonomiename der Benutzergruppen auflösen |

## Eigene AFSpaces-Filter

| Filter | Quelle | Parameter | Zweck |
| --- | --- | --- | --- |
| `afspaces_forum_url_after_accept` | `FrontendController` | `$url, $space, $invitation` | Ziel nach Einladungsannahme |
| `afspaces_forum_url_after_invite_link` | `InviteLinkService` | `$url, $space, $link, $actor` | Ziel nach Invite-Link-Beitritt |
| `afspaces_allow_invite_link_registration` | `InviteLinkService` | `$enabled, $space` | Registrierung über Link erlauben |
| `afspaces_is_user_blocked_for_invite` | `InvitationService` | `$blocked, $user_id, $space_id` | Einladung sperren |
| `afspaces_invitation_mail_subject` | `InvitationService` | `$subject, $invitation, $space` | E-Mail-Betreff |
| `afspaces_invitation_mail_body` | `InvitationService` | `$body, $invitation, $space, $accept_url` | E-Mail-Text |
| `afspaces_central_notification_email` | `JoinRequestService` | `$email, $request` | zentrale Benachrichtigungsadresse |
| `afspaces_working_group_topics_taxonomy` | `WorkingGroupService` | `$taxonomy` | ACF-Themen-Taxonomie |
| `afspaces_space_forum_url` | mehrere Views | `$url, $space, $forum, $user` | Forum-Link einer Arbeitsgruppe |
| `afspaces_forum_home_url` | `SpacesHubController` | `$url` | Forum-Startseite |
| `afspaces_hub_navigation_tabs` | `SpacesHubController` | `$tabs, $view, $space_id, $actor` | Top-Navigation erweitern |
| `afspaces_hub_space_navigation_tabs` | `SpacesHubController` | `$tabs, $view, $space_id, $actor` | Space-Kontext-Navigation erweitern |
| `afspaces_panel_cache_ttl` | `ForumNavigation` | `$ttl` | Cache-Dauer Einstiegs-Panel |
| `afspaces_enable_space_creation` | `ForumNavigation` | `$enabled, $user_id` | Gründung im Panel anzeigen |
| `afspaces_profile_post_types` | `Plugin`, `FrontendController` | `array` | Profil-CPT-Erkennung |
| `afspaces_profile_user_id` | `Plugin` | `$user_id, $explicit_user_id` | Profil-Zielperson überschreiben |
| `afspaces_wp_search_args` | `WpPostSearch` | `$args, $keywords` | `WP_Query`-Argumente der WP-Suche |

## Eigene AFSpaces-Actions

| Action | Quelle | Parameter | Zweck |
| --- | --- | --- | --- |
| `afspaces_invitation_notification_created` | `InvitationService` | `$invitation` | Haken für zusätzliche Benachrichtigungen |
| `afspaces_invite_link_registration_captcha` | `MyInvitationsView` | `$link, $space` | CAPTCHA-Hook vor Link-Registrierung |

## Neue Hooks einführen

- Filter/Action mit Präfix `afspaces_` benennen.
- Parameterreihenfolge stabil halten; neue Parameter hinten anhängen.
- Bei sicherheitsrelevanten Filtern (URLs, Berechtigung) serverseitige Prüfung nicht durch den Filter ersetzen.
