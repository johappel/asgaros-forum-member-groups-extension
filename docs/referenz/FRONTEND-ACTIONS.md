# Frontend-Actions-Referenz

Alle serverseitigen Formular-Aktionen laufen über ein einziges POST-Feld `afspaces_action` und werden in [src/Interface/FrontendController.php](../../src/Interface/FrontendController.php) in `handle_actions()` verarbeitet.

## Aufrufkontext

- Hooks: `add_action('init', 'handle_actions')` und `add_action('wp_ajax_afspaces_action', 'handle_actions')`.
- Nonce: Feld `_wpnonce` wird gegen `FrontendController::$nonce_action` geprüft; bei Fehler `wp_die`.
- AJAX: gesetzt, wenn `wp_doing_ajax()` oder `afspaces_ajax=1`. Erfolg → `wp_send_json_success`, Domain-Fehler → `wp_send_json_error(..., 400)`.
- Ohne AJAX: Post/Redirect/Get. Zielansicht hängt von der Aktion ab (siehe Spalte Redirect).
- Standardfelder: `space_id` (interne Space-ID), Akteur = `get_current_user_id()`.

## Mitglieder (`MemberService`)

| `afspaces_action` | Felder | Effekt | Redirect |
| --- | --- | --- | --- |
| `add_member` | `user_id` | Mitglied hinzufügen | `members` |
| `remove_member` | `user_id` | Mitglied entfernen | `members` |
| `assign_manager` | `user_id` | Zur/zum Verantwortlichen machen | `members` |
| `revoke_manager` | `user_id` | Verantwortung entziehen | `members` |

## Einladungen (`InvitationService`)

| `afspaces_action` | Felder | Effekt | Redirect |
| --- | --- | --- | --- |
| `create_invitation` | `invitee_user_id`, `message`, `expires_in_days` | Einladung erstellen und senden | `invitations` |
| `revoke_invitation` | `invitation_id` | Einladung widerrufen | `invitations` |
| `resend_invitation` | `invitation_id` | Einladung erneut senden | `invitations` |
| `accept_invitation` | `invitation_token` | Annehmen, dann Redirect ins Forum (`afspaces_forum_url_after_accept`) | Forum |
| `decline_invitation` | `invitation_token` | Ablehnen | `my-invitations` |

## Invite-Links (`InviteLinkService`)

| `afspaces_action` | Felder | Effekt | Redirect |
| --- | --- | --- | --- |
| `create_invite_link` | `approval_mode`, `max_uses`, `expires_in_days`, `allow_registration` | Link erzeugen (URL einmalig anzeigen) | `invitations` |
| `revoke_invite_link` | `invite_link_id` | Link widerrufen | `invitations` |
| `shorten_invite_link` | `invite_link_id`, `shorten_expires_at` | Ablauf verkürzen | `invitations` |
| `use_invite_link` | `invite_link_token` | Beitritt / Anfrage / bereits Mitglied | Forum oder `my-invitations` |
| `request_invite_link_registration` | `invite_link_token`, `privacy_consent` | Registrierung anstoßen | Registrierungs-URL |

## Beitrittsanfragen (`JoinRequestService`)

| `afspaces_action` | Felder | Effekt | Redirect |
| --- | --- | --- | --- |
| `create_join_request` | `request_message` | Anfrage erstellen | `discover` |
| `approve_join_request` | `join_request_id`, `decision_message` | Genehmigen → Mitgliedschaft | `join-requests` |
| `reject_join_request` | `join_request_id`, `decision_message` | Ablehnen | `join-requests` |

## Registrierung & Metadaten

| `afspaces_action` | Service | Felder | Redirect |
| --- | --- | --- | --- |
| `register_space` | `SpaceRegistrationService` | `forum_id` | `dashboard` |
| `save_working_group_meta` | `WorkingGroupService::save_metadata` | Metadatenfelder (`wp_unslash($_POST)`) | `working-group-settings` |
| `save_working_group_settings` | `SpaceLifecycleService` + `WorkingGroupService` | `name`, `description`, `contact_text`, `topic_ids[]`, `icon`, `accent_color`, `visibility`, `join_policy` | `working-group-settings` |

## Arbeitsgruppen-Gründung & Lifecycle

| `afspaces_action` | Service | Felder | Redirect |
| --- | --- | --- | --- |
| `create_space` | `SpaceCreationService::create` | `name`, `description`, `visibility`, `accept_responsibility` | `working-group-settings` (bei Fehler `create`) |
| `rename_space` | `SpaceLifecycleService::rename` | `name` | `working-group-settings` |
| `change_space_visibility` | `SpaceLifecycleService::change_visibility` | `visibility` | `working-group-settings` |
| `transfer_space_owner` | `SpaceLifecycleService::transfer_owner` | `new_owner_id` | `working-group-settings` |
| `archive_space` | `SpaceLifecycleService::archive` | — | `working-group-settings` |
| `reactivate_space` | `SpaceLifecycleService::reactivate` | — | `working-group-settings` |
| `delete_space` | `SpaceLifecycleService::delete` | — | `dashboard` |
| `approve_space` | `SpaceLifecycleService::approve` | — | `approvals` |
| `reject_space` | `SpaceLifecycleService::reject` | `rejection_reason` | `approvals` |

`save_working_group_settings` ist die gemeinsame Frontend-Aktion für normale
Einstellungen. Sie prüft vor dem ersten Schreibzugriff Metadaten, Namen und
Sichtbarkeit. `join_policy=request` wird intern als
`join_policy=request` plus `join_requests_enabled=1` gespeichert; `invite_only`
und `closed` setzen `join_requests_enabled=0`. Die älteren Einzelaktionen
`save_working_group_meta`, `rename_space` und `change_space_visibility` bleiben
für bestehende Aufrufer erhalten.

## Moderation (`SpaceModerationService`)

Die Pin-/Löse-Aktionen sind serverseitig durch Login, Nonce, `SpacePolicy` und
die Topic-zu-Forum-Prüfung geschützt. Ein vom Client gelieferter `space_id`
begründet keine Berechtigung; die Verantwortlichen erhalten keine globalen
Asgaros-Moderatorrechte.

Zusätzliche Topic-Aktionen:

| Aktion | Felder | Effekt |
| --- | --- | --- |
| `moderate_pin_topic` | `topic_id` | Thema im eigenen Forum oben halten |
| `moderate_unpin_topic` | `topic_id` | Pinstatus des Themas entfernen |

| `afspaces_action` | Felder | Effekt |
| --- | --- | --- |
| `moderate_close_topic` | `topic_id` | Thema schließen |
| `moderate_reopen_topic` | `topic_id` | Thema öffnen |
| `moderate_delete_topic` | `topic_id` | Thema löschen |
| `moderate_delete_post` | `post_id` | Beitrag löschen |
| `moderate_move_topic` | `topic_id`, `target_space_id` | Thema verschieben |
| `moderate_move_post` | `post_id`, `target_topic_id` | Beitrag verschieben |

Moderations-Redirect: Standard `moderation`-View; wurde die Aktion aus dem Forum ausgelöst, kehrt `redirect_to` (validiert via `wp_validate_redirect`) ins Forum zurück.

## Neue Aktion hinzufügen

1. Zweig in `handle_actions()` ergänzen und Parameter sanitisieren (`sanitize_text_field` / `absint` / `sanitize_textarea_field`).
2. Geschäftslogik im passenden Application-Service kapseln (Policy und Objektberechtigung dort prüfen).
3. Erfolgsmeldung via `set_message('success', ...)`.
4. Redirect-Ziel im PRG-Block ergänzen.
5. Bei AJAX-Nutzung Aktion in `assets/afspaces.js` (`nonAjaxActions` bzw. AJAX-Pfad) berücksichtigen.
