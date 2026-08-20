# Referenz

Nachschlagewerk für Entwickler. Jede Seite ist auf schnelles Finden ausgelegt: Symbol, Datei, Zweck, Parameter. Fließtext nur, wo nötig.

## Globale Referenzen

- [FEATURE-TEST-MAPPING.md](FEATURE-TEST-MAPPING.md) — Feature → Quellklasse → Testdatei, schnell beim Ändern finden.
- [REST-API.md](REST-API.md) — alle Endpunkte unter `/wp-json/afspaces/v1`, Permission-Callbacks, Parameter, Service.
- [FRONTEND-ACTIONS.md](FRONTEND-ACTIONS.md) — alle serverseitigen POST-Aktionen (`afspaces_action`), Nonce, Parameter, Service, Redirect.
- [HOOKS.md](HOOKS.md) — konsumierte WordPress- und Asgaros-Hooks sowie eigene AFSpaces-Filter/Actions.
- [USER-IDENTITY.md](USER-IDENTITY.md) — zentrale Auflösung von Anzeigenamen, Avataren und externer Benutzersuche.
- [ADAPTER.md](ADAPTER.md) — vollständiger Asgaros-Adapter-Vertrag (read vs. write) mit Signaturen.
- [SETTINGS-PAGES.md](SETTINGS-PAGES.md) — zentrale AFSpaces-Settingsseite unter Asgaros, Tabs, Optionsschlüssel, Felder, Capability.
- [DOMAINMODELLE.md](DOMAINMODELLE.md) — Zustandsdiagramme und Kernattribute (Space, Invitation, InviteLink, JoinRequest, WorkingGroupMeta).
- [DATENBANK.md](DATENBANK.md) — Kernfelder je Tabelle, Personenbezug, Schema-Änderungspfad.
- [DESIGN-UND-LAYOUT.md](DESIGN-UND-LAYOUT.md) — Gestaltungs-, Farb-, Icon- und Barrierefreiheitsentscheidungen.

## Nach Hauptteilen

- [BEREICH-mitglieder-einladungen.md](BEREICH-mitglieder-einladungen.md) — Mitgliederverwaltung, Einladungen, Invite-Links, Beitrittsanfragen.
- [BEREICH-private-arbeitsgruppen.md](BEREICH-private-arbeitsgruppen.md) — Gründung, Lifecycle, Moderation, Metadaten.
- [BEREICH-suche.md](BEREICH-suche.md) — Keyword-, Hybrid- und semantische Suche, Overlay, Indexer.

## Konstanten-Schnellübersicht

### Plugin

| Wert | Herkunft |
| --- | --- |
| REST-Namespace | `afspaces/v1` (`RestController::register_routes`) |
| Nonce-Action Frontend | Feld `_wpnonce` gegen `FrontendController::$nonce_action` |
| AJAX-Action | `wp_ajax_afspaces_action` → `FrontendController::handle_actions` |
| Plugin-Version | Konstante `AFSPACES_VERSION` |

### Capabilities (`src/Core/Capabilities.php`)

| Konstante | Wert |
| --- | --- |
| `Capabilities::MANAGE_ALL_SPACES` | `afspaces_manage_all_spaces` |
| `Capabilities::CREATE_SPACE` | `afspaces_create_space` |
| `Capabilities::MANAGE_OWN_SPACE` | `afspaces_manage_own_space` |
| `Capabilities::INVITE_MEMBERS` | `afspaces_invite_members` |
| `Capabilities::REMOVE_MEMBERS` | `afspaces_remove_members` |
| `Capabilities::CREATE_INVITE_LINKS` | `afspaces_create_invite_links` |
| `Capabilities::MODERATE_SPACE` | `afspaces_moderate_space` |

Nur die WordPress-Rolle `administrator` erhält diese Caps bei Aktivierung (`Capabilities::register`). Owner und Manager eines einzelnen Spaces erhalten dadurch keine globalen WordPress-Capabilities. Ihre objektbezogenen Rechte werden über `SpaceRepository::is_manager()` und die zentrale `SpacePolicy` geprüft. `MANAGE_ALL_SPACES` ist die globale Administration; `MODERATE_SPACE` erlaubt die globale Freigabe-/Moderationsberechtigung, nicht automatisch die Verwaltung jedes Space-Objekts.

### Capability- und Rollenmodell

| Ebene | Quelle | Bedeutung |
| --- | --- | --- |
| Globale Administration | WordPress-Capability `afspaces_manage_all_spaces` | darf alle Spaces verwalten; wird bei Aktivierung nur Administratoren gegeben |
| Globale Fachberechtigungen | WordPress-Capabilities `afspaces_create_space`, `afspaces_manage_own_space`, `afspaces_invite_members`, `afspaces_remove_members`, `afspaces_create_invite_links`, `afspaces_moderate_space` | steuern globale bzw. policyabhängige Fähigkeiten; sie werden ebenfalls nur Administratoren registriert |
| Space-Rolle | `SpaceRepository::is_manager()` / `SpaceManager` mit `owner` oder `manager` | objektbezogene Verwaltung des zugeordneten Spaces, unabhängig von einer WordPress-Rolle |
| Policy | `SpacePolicy` und Application Services | kombiniert Akteur, Space, Aktion und Schutzregeln, etwa letzten Owner und Selbstentfernung |

`can_manage` im REST-Controller akzeptiert globale Administration oder eine Space-Manager-Zuordnung. `can_search` prüft dagegen ausdrücklich die globalen Capabilities `MANAGE_ALL_SPACES` oder `MANAGE_OWN_SPACE`; eine bloße Manager-Zuordnung ersetzt diese Prüfung nicht. `can_moderate_space` prüft `MANAGE_ALL_SPACES` oder `MODERATE_SPACE` und ist keine Space-Owner-Rolle.

### Datenbanktabellen (Präfix `wp_`)

| Tabelle | Repository | Install/Upgrade |
| --- | --- | --- |
| `afspaces_spaces` | `SpaceRepository` | `Activator::activate`, `Plugin::maybe_upgrade` |
| `afspaces_space_managers` | `SpaceRepository` | dito |
| `afspaces_space_forums` | `SpaceRepository` | dito |
| `afspaces_space_meta` | `SpaceMetaRepository` | `Activator::activate` |
| `afspaces_invitations` | `InvitationRepository` | `Activator::activate` |
| `afspaces_invite_links` | `InviteLinkRepository` | `Activator::activate` |
| `afspaces_join_requests` | `JoinRequestRepository` | `Activator::activate` |
| `afspaces_audit` | `AuditRepository` | `Activator::activate` |
| `afspaces_search_index` | `SearchIndexRepository` | `Activator::activate`, `Plugin::maybe_upgrade` |

Deinstallation bewahrt Tabellen und Optionen standardmäßig. Vollständiges Cleanup erfolgt nur über `afspaces_cleanup_on_uninstall` und `src/Core/Uninstaller.php`; Asgaros-Daten bleiben unangetastet.

### Admin-Optionsschlüssel

| Option | Quelle |
| --- | --- |
| `afspaces_hub_page_id` | `SpacesUrls::HUB_PAGE_OPTION` |
| `afspaces_activation_notice` | `Activator::activate`, einmalige Admin-Einrichtungsmeldung |
| `afspaces_cleanup_on_uninstall` | `InstallationSettingsPage::OPTION`, Opt-in für vollständiges Cleanup |
| `afspaces_group_managers_can_create_forums` | `ForumManagementSettings::OPTION`, Opt-in für zusätzliche Space-Foren |
| `afspaces_installed_version` | `Plugin::maybe_upgrade` |
| `afspaces_appearance_options` | `AppearanceSettingsPage` |
| `afspaces_creation_options` | `SpaceCreationSettings::OPTION` |
| `afspaces_search_options` | `SearchSettings::OPTION_KEY` |
| `afspaces_enable_space_creation` | Legacy-Flag, via Filter (`ForumNavigation`) |
| `afspaces_central_notification_email` | zentrale Benachrichtigungsadresse (`JoinRequestService`) |

### Hub-Views (`src/Interface/SpacesUrls.php`)

| Konstante | `afspaces_view` | benötigt `space_id` |
| --- | --- | --- |
| `VIEW_DASHBOARD` | `dashboard` (Default) | nein |
| `VIEW_MEMBERS` | `members` | ja |
| `VIEW_INVITATIONS` | `invitations` | ja |
| `VIEW_JOIN_REQUESTS` | `join-requests` | ja |
| `VIEW_MY_INVITATIONS` | `my-invitations` | nein |
| `VIEW_DISCOVER` | `discover` | nein |
| `VIEW_GROUP` | `working-group` | ja |
| `VIEW_PROFILE` | `profile` | nein |
| `VIEW_SETTINGS` | `working-group-settings` | ja |
| `VIEW_CREATE` | `create` | nein |
| `VIEW_APPROVALS` | `approvals` | nein |
| `VIEW_MODERATION` | `moderation` | ja |
| `VIEW_SEARCH` | `search` | nein |

Links immer über `SpacesUrls::hub_url( $view, $args )` bauen. Alte Einzelseiten werden per `SpacesUrls::legacy_slug_map` und `SpacesHubController::redirect_legacy_pages` umgeleitet.

### Shortcodes

| Shortcode | Registriert in | Zweck |
| --- | --- | --- |
| `[afspaces]` | `SpacesHubController::init` | Hub-Router |
| `[afspaces_dashboard]` | `FrontendController::init` | Dashboard |
| `[afspaces_members]` | `Plugin::init` | Mitgliederansicht (braucht `?space_id=`) |
| `[afspaces_invitations]` | `Plugin::init` | Einladungsansicht (braucht `?space_id=`) |
| `[afspaces_my_invitations]` | `Plugin::init` | Meine Einladungen |
| `[afspaces_search]` | `Plugin::init` | Suchseite |
| `[afspaces_profile]` | `Plugin::init` | Arbeitsgruppenprofil (zusätzlicher parameter: user_id=) |
| `[afspaces_search_button]` | `SearchModal::init` | Overlay-Trigger (Button) |
| `[afspaces_search_link]` | `SearchModal::init` | Overlay-Trigger (Link) |

> Wichtig: `space_id` ist immer die interne AFSpaces-Space-ID aus `wp_afspaces_spaces`, nicht die Asgaros-`forum_id`.
