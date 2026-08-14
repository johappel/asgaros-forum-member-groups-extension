# REST-API-Referenz

Alle Routen werden in `AFSpaces\Interface\RestController::register_routes()` unter dem Namespace `afspaces/v1` registriert. Die Basis-URL ist `/wp-json/afspaces/v1`.

## Authentifizierung und Berechtigungen

Für eingeloggte Requests verwendet WordPress seine normale Session. Schreibende Browser-Requests müssen zusätzlich den WordPress-REST-Nonce (`X-WP-Nonce`, Action `wp_rest`) mitsenden. Die Objektprüfung erfolgt immer serverseitig.

| Callback | Verhalten |
| --- | --- |
| `can_manage` | Anmeldung, vorhandener `space_id` und entweder Capability `afspaces_manage_all_spaces` oder eine Space-Zuordnung in `SpaceRepository::is_manager()` |
| `can_search` | Anmeldung und Capability `afspaces_manage_all_spaces` oder `afspaces_manage_own_space` |
| `can_respond_to_invitation` | nur Anmeldung; die konkrete Token-/Space-Prüfung erfolgt im Application Service |
| `can_create_space` | Anmeldung und `SpaceCreationService::can_user_create()` |
| `can_moderate_space` | Anmeldung und Capability `afspaces_manage_all_spaces` oder `afspaces_moderate_space` |
| `__return_true` | öffentlich; aktuell nur die allgemeine Suche und die Invite-Link-Vorschau |

Die sieben AFSpaces-Capabilities werden bei Aktivierung ausschließlich der WordPress-Rolle `administrator` hinzugefügt. Owner und Manager einzelner Spaces erhalten nicht automatisch WordPress-Capabilities; ihre Rechte kommen aus der Space-Zuordnung und den Policies. Details stehen in [INDEX.md](INDEX.md).

### Fehlerformat

WordPress liefert Fehler als `WP_Error`-JSON mit `code`, `message` und `data.status`. Allgemeine Berechtigungsfehler sind:

- `afspaces_rest_unauthorized` — HTTP 401, Anmeldung erforderlich.
- `afspaces_rest_forbidden` — HTTP 403, keine passende globale oder objektbezogene Berechtigung.
- `afspaces_rest_not_found` — HTTP 404, der Space ist nicht vorhanden.

Controller-Callbacks übersetzen Domain-Fehler in bereichsspezifische `afspaces_rest_*`-Codes und meist HTTP 400. Invite-Link-Vorschau und -Nutzung liefern HTTP 429, wenn der Domain-Fehler auf zu viele Versuche hinweist. Nicht jede Route verwendet dieselben Domain-Codes; die folgenden Tabellen nennen die im Controller sichtbaren Codes.

`space_id` ist immer die interne AFSpaces-ID aus `afspaces_spaces.id`, nicht die Asgaros-`forum_id`. `forum_id` erscheint nur als Fremd-ID in Antworten oder wenn der Code ausdrücklich eine Asgaros-ID erwartet.

## Spaces und Arbeitsgruppen

Bei Arbeitsgruppen akzeptiert accent_color ausschließlich die Palette aus
WorkingGroupMeta::accent_colors(). Fremde Werte werden serverseitig auf
den Standard #2d5d7f normalisiert.

| Methode | Route | Permission | Service | Request |
| --- | --- | --- | --- | --- |
| POST | `/spaces` | `can_create_space` | `SpaceCreationService::create` | `name` string, erforderlich; `description` string, optional; `visibility` string, optional |
| PATCH* | `/spaces/{space_id}` | `can_manage` | `SpaceLifecycleService` | `name`, `visibility`, `status` jeweils optional; `status` darf nur `archived`, `active` oder `deleted` sein |
| POST | `/spaces/{space_id}/approve` | `can_moderate_space` | `SpaceLifecycleService::approve` | — |
| POST | `/spaces/{space_id}/reject` | `can_moderate_space` | `SpaceLifecycleService::reject` | `rejection_reason` string, optional |
| GET | `/spaces/discover` | `can_respond_to_invitation` | Repositories, `WorkingGroupService` | `search` string, optional; `topic_id` integer, optional |
| GET | `/spaces/{space_id}/working-group` | `can_respond_to_invitation` | `WorkingGroupService` | — |
| PATCH* | `/spaces/{space_id}/working-group` | `can_manage` | `WorkingGroupService::save_metadata` | `description`, `accent_color`, `icon`, `contact_text`, `directory_visibility`, `join_policy`, `join_requests_enabled`, `topic_ids`; Werte werden im Service normalisiert |
| GET | `/profiles/{user_id}/working-groups` | `can_respond_to_invitation` | `WorkingGroupService` und Repositories | — |

Antworten:

- `POST /spaces`: HTTP 201 mit `{id, forum_id, status, visibility}`.
- Space-Update: HTTP 200 mit `{id, status, visibility}`.
- Freigabe/Ablehnung: HTTP 200 mit `{id, status}`.
- Discover: HTTP 200 mit `{spaces: [...]}`; je Eintrag gehören unter anderem `space_id`, `forum_name`, `description`, `accent_color`, `icon`, `join_policy`, `current_user_status` und `can_request_join` dazu.
- Arbeitsgruppen-Detail: HTTP 200 mit Space-/Metadaten, `responsibles`, `topic_names`, `current_user_status` und `can_request_join`.
- Profil: HTTP 200 mit `{user_id, working_groups: [...]}`.

Typische Fehler sind `afspaces_rest_space_create_failed`, `afspaces_rest_space_update_failed`, `afspaces_rest_invalid_status`, `afspaces_rest_space_approve_failed`, `afspaces_rest_space_reject_failed`, `afspaces_rest_working_group_not_found`, `afspaces_rest_working_group_forum_missing`, `afspaces_rest_working_group_forbidden`, `afspaces_rest_working_group_update_failed` und `afspaces_rest_profile_not_found`.

\* `WP_REST_Server::EDITABLE` akzeptiert WordPress-seitig die editierbaren Methoden (`POST`, `PUT`, `PATCH`); fachlich ist die Route eine PATCH-Aktion.

## Mitglieder

| Methode | Route | Permission | Service/Adapter | Request |
| --- | --- | --- | --- | --- |
| GET | `/spaces/{space_id}/members` | `can_manage` | `AsgarosAdapter::list_group_members` | `page` integer, Default 1; `per_page` integer, Default 20; `search` string, optional |
| POST | `/spaces/{space_id}/members` | `can_manage` | `MemberService::add_member` | `user_id` integer, erforderlich, mindestens 1 |
| DELETE | `/spaces/{space_id}/members/{user_id}` | `can_manage` | `MemberService::remove_member` | Pfadparameter `user_id`, integer, mindestens 1 |
| GET | `/users/search` | `can_search` | `MemberService::search_users` | `search` string, optional, maximal 60 Zeichen; `page` Default 1; `per_page` Default 20 |

Antworten:

- Mitgliederliste und Benutzersuche: HTTP 200 mit `{members, total, page, per_page}`. Jeder sichere Benutzer-Eintrag enthält nur `user_id`, `display_name` und `user_login`; E-Mail-Adressen werden nicht ausgegeben.
- Hinzufügen: HTTP 201 mit `{status: "added", user_id}`.
- Entfernen: HTTP 200 mit `{status: "removed", user_id}`.
- `get_members` kann `afspaces_rest_space_not_found` (404) oder `afspaces_rest_no_group` (409) liefern.
- Schreibfehler werden als `afspaces_rest_add_failed` oder `afspaces_rest_remove_failed` (400) ausgegeben.

## Persönliche Einladungen

| Methode | Route | Permission | Service | Request |
| --- | --- | --- | --- | --- |
| GET | `/spaces/{space_id}/invitations` | `can_manage` | `InvitationService::list_space_invitations` | `status` string, optional |
| POST | `/spaces/{space_id}/invitations` | `can_manage` | `InvitationService::create_invitation` | `invitee_user_id` integer, erforderlich, mindestens 1; `message` string, optional; `expires_in_days` integer, Default 7 |
| DELETE | `/spaces/{space_id}/invitations/{invitation_id}` | `can_manage` | `InvitationService::revoke_invitation` | Pfadparameter `invitation_id` |
| POST | `/spaces/{space_id}/invitations/{invitation_id}/resend` | `can_manage` | `InvitationService::resend_invitation` | Pfadparameter `invitation_id` |
| POST | `/invitations/{token}/accept` | `can_respond_to_invitation` | `InvitationService::accept_invitation_by_token` | Pfadparameter `token`, URL-sicheres Token (`A-Z`, `a-z`, `0-9`, `-`, `_`) |
| POST | `/invitations/{token}/decline` | `can_respond_to_invitation` | `InvitationService::decline_invitation_by_token` | Pfadparameter `token`, URL-sicheres Token |

Antworten:

- Liste: HTTP 200 mit `{invitations: [...]}`; Einträge enthalten `id`, `space_id`, Benutzer-IDs, effektiven `status`, `expires_at`, `message` und `send_count`.
- Erstellen: HTTP 201 mit `{id, status, expires_at}`.
- Widerruf: HTTP 200 mit `{status: "revoked"}`.
- Erneuter Versand: HTTP 200 mit `{status: "resent"}`.
- Annehmen/Ablehnen: HTTP 200 mit `{status, space_id}`.

Domain-Fehler werden als `afspaces_rest_invitation_list_failed`, `afspaces_rest_invitation_create_failed`, `afspaces_rest_invitation_revoke_failed`, `afspaces_rest_invitation_resend_failed`, `afspaces_rest_invitation_accept_failed` oder `afspaces_rest_invitation_decline_failed` mit HTTP 400 geliefert. Die Token-Routen sind nicht öffentlich: Auch bei bekanntem Token muss die anfragende Person angemeldet sein und zum Token passen. Tokens werden im Plugin nur gehasht gespeichert.

## Einladungslinks

| Methode | Route | Permission | Service | Request |
| --- | --- | --- | --- | --- |
| GET | `/spaces/{space_id}/invite-links` | `can_manage` | `InviteLinkService::list_links` | — |
| POST | `/spaces/{space_id}/invite-links` | `can_manage` | `InviteLinkService::create_link` | `approval_mode` string, optional; `max_uses` integer, Default 1; `expires_in_days` integer, Default 7; `allow_registration` boolean, optional |
| DELETE | `/spaces/{space_id}/invite-links/{link_id}` | `can_manage` | `InviteLinkService::revoke_link` | Pfadparameter `link_id` |
| PATCH* | `/spaces/{space_id}/invite-links/{link_id}` | `can_manage` | `InviteLinkService::shorten_expiry` | `expires_at` string, erforderlich; neues Datum muss gültig, zukünftig und kürzer als das bisherige sein |
| GET | `/invite-links/preview/{token}` | `__return_true` | `InviteLinkService::preview_link` | Pfadparameter `token`, URL-sicheres Token |
| POST | `/invite-links/use/{token}` | `can_respond_to_invitation` | `InviteLinkService::use_link` | Pfadparameter `token`, URL-sicheres Token |

Antworten:

- Liste: HTTP 200 mit `{invite_links: [...]}`. Einträge enthalten `id`, `space_id`, `creator_user_id`, effektiven `status`, `approval_mode`, `max_uses`, `use_count`, `allow_registration` und `expires_at`.
- Erstellen: HTTP 201 mit Link-Metadaten und einmalig zurückgegebenem `url`; der Klartext-Token wird nicht in der Datenbank gespeichert.
- Widerruf: HTTP 200 mit `{status: "revoked"}`.
- Ablaufverkürzung: HTTP 200 mit `{status, expires_at}`.
- Vorschau: HTTP 200 mit `{forum_name, state, can_register, action_label, status_message}`. Sie ist öffentlich, gibt aber keine private Raum-Mitgliederdaten aus.
- Nutzung: HTTP 200 mit dem Service-Ergebnis `{result, space_id, forum_url}`; `result` ist typischerweise `joined`, `request_created` oder `already_member`.

Domain-Fehler werden als `afspaces_rest_invite_link_list_failed`, `afspaces_rest_invite_link_create_failed`, `afspaces_rest_invite_link_revoke_failed`, `afspaces_rest_invite_link_update_failed`, `afspaces_rest_invite_link_preview_failed` oder `afspaces_rest_invite_link_use_failed` ausgegeben. Vorschau/Nutzung können bei Rate-Limit HTTP 429 liefern; sonst HTTP 400.

## Beitrittsanfragen

| Methode | Route | Permission | Service | Request |
| --- | --- | --- | --- | --- |
| GET | `/spaces/{space_id}/join-requests` | `can_manage` | `JoinRequestService::list_space_requests` | — |
| POST | `/spaces/{space_id}/join-requests` | `can_respond_to_invitation` | `JoinRequestService::create_request` | `request_message` string, optional |
| POST | `/spaces/{space_id}/join-requests/{request_id}/approve` | `can_manage` | `JoinRequestService::approve_request` | `decision_message` string, optional |
| POST | `/spaces/{space_id}/join-requests/{request_id}/reject` | `can_manage` | `JoinRequestService::reject_request` | `decision_message` string, optional |

Antworten:

- Liste: HTTP 200 mit `{join_requests: [...]}` und IDs, Nachrichten, Status und Entscheider-ID.
- Erstellen: HTTP 201 mit `{id, space_id, requester_user_id, status}`.
- Entscheiden: HTTP 200 mit `{id, status}`.
- Fehlercodes: `afspaces_rest_join_request_list_failed`, `afspaces_rest_join_request_create_failed`, `afspaces_rest_join_request_approve_failed`, `afspaces_rest_join_request_reject_failed` (jeweils 400 für Domain-Fehler).

## Suche

| Methode | Route | Permission | Service | Request |
| --- | --- | --- | --- | --- |
| GET | `/search` | `__return_true` | `HybridSearchService::search` | `q` erforderlich, maximal 200 Zeichen; `sort` Default `relevance` (`relevance`/`date`); `scope` Default `all` (`all`/`forum`/`wp`); `semantic` Default `false`; `author` Default 0; `author_name` Default leer; `forum` Default 0; `date_from`/`date_to` Default leer; `mode` Default `any` (`any`/`all`); `in` Default `all` (`all`/`title`); `page` Default 1; `per_page` Default 10 |

HTTP 200: `{results, total, page, per_page, total_pages, semantic_used}`. Jeder Treffer enthält `source`, `title`, `url`, `author`, `date`, `context`, `snippet` und `score`. Die Forumssuche berücksichtigt nur zugängliche Kategorien; semantische Suche wird für nicht eingeloggte Besucher trotz Parameter nicht aktiviert.

## Referenzpflege

Neue oder geänderte Routen werden zuerst in `RestController::register_routes()` mit Schema und `permission_callback` umgesetzt, danach hier, in [FEATURE-TEST-MAPPING.md](FEATURE-TEST-MAPPING.md) und bei Bedarf in [BEREICH-suche.md](BEREICH-suche.md) nachgezogen. Geschäftslogik gehört in Application Services, nicht in den Controller.
