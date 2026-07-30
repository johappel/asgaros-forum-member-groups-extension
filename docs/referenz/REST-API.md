# REST-API-Referenz

Alle Endpunkte liegen unter dem Namespace `afspaces/v1` und werden in [src/Interface/RestController.php](../../src/Interface/RestController.php) über `register_routes()` registriert (gehängt an `rest_api_init` in `Plugin::init`).

Basis-URL: `/wp-json/afspaces/v1`

## Authentifizierung

Schreibende Endpunkte benötigen einen gültigen `X-WP-Nonce` (Nonce `wp_rest`) und einen angemeldeten Benutzer. Die eigentliche Objektberechtigung liegt im jeweiligen `permission_callback`.

| permission_callback | Regel |
| --- | --- |
| `can_manage` | eingeloggt, Space existiert, `MANAGE_ALL_SPACES` oder Manager des Space (`SpaceRepository::is_manager`) |
| `can_search` | eingeloggt und `MANAGE_ALL_SPACES` oder `MANAGE_OWN_SPACE` |
| `can_respond_to_invitation` | nur eingeloggt |
| `can_create_space` | eingeloggt und `SpaceCreationService::can_user_create` |
| `can_moderate_space` | eingeloggt und `MANAGE_ALL_SPACES` oder `MODERATE_SPACE` |
| `__return_true` | öffentlich (nur `/search`) |

Fehlercodes: `afspaces_rest_unauthorized` (401), `afspaces_rest_forbidden` (403), `afspaces_rest_not_found` (404). Domain-Fehler werden als `400` mit Nachricht ausgegeben.

## Endpunkte

### Spaces / Arbeitsgruppen

| Methode | Route | Callback | Permission | Wichtige Parameter |
| --- | --- | --- | --- | --- |
| POST | `/spaces` | `create_space` | `can_create_space` | `name` (req), `description`, `visibility` |
| PATCH | `/spaces/{space_id}` | `update_space` | `can_manage` | `name`, `visibility`, `status` |
| POST | `/spaces/{space_id}/approve` | `approve_space` | `can_moderate_space` | — |
| POST | `/spaces/{space_id}/reject` | `reject_space` | `can_moderate_space` | `rejection_reason` |
| GET | `/spaces/discover` | `get_discover_spaces` | `can_respond_to_invitation` | `search`, `topic_id` |
| GET | `/spaces/{space_id}/working-group` | `get_working_group` | `can_respond_to_invitation` | — |
| PATCH | `/spaces/{space_id}/working-group` | `update_working_group` | `can_manage` | Metadatenfelder |
| GET | `/profiles/{user_id}/working-groups` | `get_profile_working_groups` | `can_respond_to_invitation` | — |

Services: `SpaceCreationService`, `SpaceLifecycleService`, `WorkingGroupService`.

### Mitglieder

| Methode | Route | Callback | Permission | Parameter |
| --- | --- | --- | --- | --- |
| GET | `/spaces/{space_id}/members` | `get_members` | `can_manage` | `members_args()` (Paginierung/Suche) |
| POST | `/spaces/{space_id}/members` | `add_member` | `can_manage` | `user_id` (req, min 1) |
| DELETE | `/spaces/{space_id}/members/{user_id}` | `remove_member` | `can_manage` | `user_id` (req, min 1) |
| GET | `/users/search` | `search_users` | `can_search` | `search` (≤60), `page`, `per_page` |

Service: `MemberService`.

### Einladungen

| Methode | Route | Callback | Permission | Parameter |
| --- | --- | --- | --- | --- |
| GET | `/spaces/{space_id}/invitations` | `get_invitations` | `can_manage` | `status` |
| POST | `/spaces/{space_id}/invitations` | `create_invitation` | `can_manage` | `invitee_user_id` (req), `message` |

Service: `InvitationService`. Weitere Einladungsaktionen (annehmen/ablehnen/widerrufen/erneut senden) laufen über Frontend-POST-Actions, siehe [FRONTEND-ACTIONS.md](FRONTEND-ACTIONS.md).

### Beitrittsanfragen

| Methode | Route | Callback | Permission | Parameter |
| --- | --- | --- | --- | --- |
| GET | `/spaces/{space_id}/join-requests` | `get_join_requests` | `can_manage` | — |
| POST | `/spaces/{space_id}/join-requests` | `create_join_request` | `can_respond_to_invitation` | `request_message` |
| POST | `/spaces/{space_id}/join-requests/{request_id}/approve` | `approve_join_request` | `can_manage` | `decision_message` |
| POST | `/spaces/{space_id}/join-requests/{request_id}/reject` | `reject_join_request` | `can_manage` | `decision_message` |

Service: `JoinRequestService`.

### Suche

| Methode | Route | Callback | Permission |
| --- | --- | --- | --- |
| GET | `/search` | `search_forum` | `__return_true` (öffentlich) |

Service: `HybridSearchService`. Parameter siehe [BEREICH-suche.md](BEREICH-suche.md).

## Parameter `/search` (vollständig)

| Parameter | Typ | Default | Validierung |
| --- | --- | --- | --- |
| `q` | string | — (req) | ≤ 200 Zeichen |
| `sort` | string | `relevance` | `relevance` \| `date` |
| `scope` | string | `all` | `all` \| `forum` \| `wp` |
| `semantic` | bool | `false` | nur wirksam für eingeloggte Nutzer |
| `author` | int | `0` | `absint` |
| `author_name` | string | `''` | Fuzzy-Auflösung via `WP_User_Query` |
| `forum` | int | `0` | Asgaros-Forum-ID |
| `date_from` | string | `''` | `YYYY-MM-DD` |
| `date_to` | string | `''` | `YYYY-MM-DD` |
| `mode` | string | `any` | `any` \| `all` |
| `in` | string | `all` | `all` \| `title` |
| `page` | int | `1` | `absint` |
| `per_page` | int | `10` | `absint` |

Antwortfelder: `results[]`, `total`, `page`, `per_page`, `total_pages`, `semantic_used`.

## Änderungen an der REST-API

1. Route in `RestController::register_routes` ergänzen.
2. Callback-Methode plus passenden `permission_callback` implementieren.
3. Args mit `sanitize_callback` und wo möglich `validate_callback` deklarieren.
4. Geschäftslogik in einen Application-Service auslagern, nicht in den Controller.
5. Fehlercodes an das bestehende Schema angleichen (`afspaces_rest_*`).
