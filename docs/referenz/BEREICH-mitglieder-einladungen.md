# Bereich: Mitglieder & Einladungen

Deckt Mitgliederverwaltung, persönliche Einladungen, Invite-Links und Beitrittsanfragen ab. Für Routen siehe [REST-API.md](REST-API.md), für POST-Aktionen [FRONTEND-ACTIONS.md](FRONTEND-ACTIONS.md).

## Mitgliederverwaltung

- Service: `src/Application/MemberService.php`
- Policy: `src/Domain/SpacePolicy.php`
- View: `src/Interface/MembersView.php`
- Persistenz: Mitgliedschaft in Asgaros (`AsgarosAdapter`), Manager in `SpaceRepository`

| Methode (`MemberService`) | Signatur |
| --- | --- |
| `add_member` | `(int $space_id, int $actor_user_id, int $target_user_id): void` |
| `remove_member` | `(int $space_id, int $actor_user_id, int $target_user_id): void` |
| `assign_manager` | `(int $space_id, int $actor_user_id, int $target_user_id): void` |
| `revoke_manager` | `(int $space_id, int $actor_user_id, int $target_user_id): void` |

Hinweise:
- Gruppenermittlung bevorzugt `Space::primary_group_id`, Fallback `get_forum_group_ids` (siehe Regressionsnotiz in [COMPATIBILITY.md](../../COMPATIBILITY.md)).
- Hinzufügen/Entfernen sind idempotent; Audit über `AuditRepository`.
- Letzter Owner ist gegen Selbstentfernung geschützt (`SpacePolicy`).

## Bestehende Foren registrieren

- Service: `src/Application/SpaceRegistrationService.php`
- Ansicht: `FrontendController::render_dashboard()`
- Gruppennamen: `AsgarosAdapter::get_group_name(int $group_id)`

`list_registrable_forums()` liefert neben den internen `group_ids` die aufgelösten
`group_names`, `primary_group_name`, `status` (`registrable`, `setup_required`,
`registered`) und `status_rank`. Die Oberfläche zeigt nur Gruppennamen und sortiert
zuerst nach Handlungspriorität, innerhalb des Status alphabetisch nach Forumname.
Ein fehlender oder nicht auflösbarer primärer Gruppen-Term verhindert die Registrierung;
die serverseitige Aktion prüft diese Voraussetzung ebenfalls.

## Persönliche Einladungen

- Modell: `src/Domain/Invitation.php` (Zustände `pending`, `accepted`, `declined`, `revoked`, `expired`)
- Service: `src/Application/InvitationService.php`
- Repository: `src/Adapters/Database/InvitationRepository.php` (`wp_afspaces_invitations`)
- Views: `src/Interface/InvitationsView.php`, `src/Interface/MyInvitationsView.php`

| Methode (`InvitationService`) | Signatur |
| --- | --- |
| `create_invitation` | `(int $space_id, int $actor, int $invitee_user_id, string $message, int $expires_in_days): Invitation` |
| `list_space_invitations` | `(int $space_id, int $actor, ?string $status = null): array` |
| `list_my_invitations` | `(int $invitee_user_id, ?string $status = null): array` |
| `revoke_invitation` | `(int $invitation_id, int $actor): void` |
| `resend_invitation` | `(int $invitation_id, int $actor): void` |
| `accept_invitation_by_token` | `(string $token, int $actor): Invitation` |
| `decline_invitation_by_token` | `(string $token, int $actor): void` |

E-Mail/Hooks: `afspaces_invitation_mail_subject`, `afspaces_invitation_mail_body`, `afspaces_invitation_notification_created`, `afspaces_is_user_blocked_for_invite` (siehe [HOOKS.md](HOOKS.md)). Mitgliedschaft entsteht erst bei Annahme.

## Invite-Links

- Modell: `src/Domain/InviteLink.php` (Status `active`, `revoked`, `expired`, `exhausted`; Modi `MODE_AUTO_JOIN`, `MODE_APPROVAL_REQUIRED`)
- Token: `src/Application/InviteLinkToken.php` (`generate`, `hash`) — nur Hash wird gespeichert
- Service: `src/Application/InviteLinkService.php`
- Repository: `src/Adapters/Database/InviteLinkRepository.php` (`wp_afspaces_invite_links`)

| Methode (`InviteLinkService`) | Signatur |
| --- | --- |
| `create_link` | `(int $space_id, int $actor, array $args): array{link,token,url}` — nur bei aktivem Space |
| `list_links` | `(int $space_id, int $actor, ?string $status = null): InviteLink[]` |
| `revoke_link` | `(int $link_id, int $actor): void` |
| `shorten_expiry` | `(int $link_id, int $actor, string $expires_at): InviteLink` |
| `preview_link` | `(string $token, int $actor = 0): array` (Zustände `login_required`, `already_member`, `approval_required`, `ready`) |
| `use_link` | `(string $token, int $actor): array{result,space_id,forum_url}` |

`create_link`-Args: `approval_mode`, `max_uses`, `expires_in_days`, `allow_registration`. Registrierung nur bei aktivierter Policy (`afspaces_allow_invite_link_registration`).

## Beitrittsanfragen

- Modell: `src/Domain/JoinRequest.php`
- Service: `src/Application/JoinRequestService.php`
- Repository: `src/Adapters/Database/JoinRequestRepository.php` (`wp_afspaces_join_requests`)
- Views: `src/Interface/DiscoverView.php`, `src/Interface/JoinRequestsView.php`

| Methode (`JoinRequestService`) | Signatur |
| --- | --- |
| `create_request` | `(int $space_id, int $actor, string $message = ''): JoinRequest` |
| `list_space_requests` | `(int $space_id, int $actor, ?string $status = null): array` |
| `list_my_requests` | `(int $requester_user_id, ?string $status = null): array` |
| `approve_request` | `(int $request_id, int $actor, string $decision_message): void` |
| `reject_request` | `(int $request_id, int $actor, string $decision_message): void` |

Idempotenz: eine offene Anfrage pro Nutzer/Space. Genehmigung erzeugt echte Asgaros-Mitgliedschaft. Zentrale Benachrichtigung via `afspaces_central_notification_email`.

## Tests

- `tests/InvitationDomainTest.php`, `tests/InviteLinkDomainTest.php`, `tests/JoinRequestDomainTest.php`, `tests/SpacePolicyTest.php`
- E2E: `e2e/tests/member-management.spec.ts`, `invitations.spec.ts`, `invite-links.spec.ts`, `join-requests.spec.ts`
