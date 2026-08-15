# Bereich: Private Arbeitsgruppen

Deckt Selbstgründung, Lebenszyklus, Freigabe, Moderation und Arbeitsgruppen-Metadaten ab.

## Gründung

- Service: `src/Application/SpaceCreationService.php`
- Policy: `src/Domain/SpaceCreationPolicy.php`
- Settings: `src/Core/SpaceCreationSettings.php` (Option `afspaces_creation_options`)
- View: `src/Interface/CreateSpaceView.php`
- Admin: `src/Interface/SpaceCreationSettingsPage.php`

| Methode (`SpaceCreationService`) | Signatur |
| --- | --- |
| `get_settings` | `(): SpaceCreationSettings` |
| `can_user_create` | `(int $actor_user_id): bool` |
| `create` | `(int $actor_user_id, array $input): Space` |

`create`-Input: `name`, `description`, `visibility`. Ablauf transaktionsähnlich mit Rollback (Kategorie → Gruppe → Forum, bei Fehler rückwärts). Isolation über dedizierte Asgaros-Kategorie pro Arbeitsgruppe (siehe [ADAPTER.md](ADAPTER.md) und [COMPATIBILITY.md](../../COMPATIBILITY.md)).

Berechtigung (`SpaceCreationPolicy::assert_can_create`): Die globale Aktivierung ist eine notwendige Voraussetzung, auch für Administratoren. Danach gilt Capability `CREATE_SPACE` ODER freigeschaltete Rolle; bei leerer Rollenliste alle angemeldeten Nutzer. Zusätzlich Limits: max. aktive Räume, Rate-Limit, reservierte Namen, erlaubte Sichtbarkeiten.

## Lebenszyklus

- Domain: `src/Domain/SpaceLifecycle.php` — Status `pending`, `active`, `archived`, `rejected`, `deleted`
- Service: `src/Application/SpaceLifecycleService.php`

| Methode (`SpaceLifecycleService`) | Signatur |
| --- | --- |
| `rename` | `(int $space_id, int $actor, string $name): void` |
| `change_visibility` | `(int $space_id, int $actor, string $visibility): void` |
| `transfer_owner` | `(int $space_id, int $actor, int $new_owner_id): void` |
| `archive` | `(int $space_id, int $actor): void` → `forum_status = closed` |
| `reactivate` | `(int $space_id, int $actor): void` → `forum_status = normal` |
| `delete` | `(int $space_id, int $actor): void` |
| `approve` | `(int $space_id, int $actor): void` → `forum_status = normal` |
| `reject` | `(int $space_id, int $actor, string $reason): void` |

Übergänge werden zentral über `SpaceLifecycle` validiert (`assert_transition`). Pending-Räume bleiben restriktiv: keine Einladungen, keine normale Mitgliederverwaltung, `forum_status = closed`.

## Freigabe

- View: `src/Interface/SpacesHubController.php` (View `approvals`)
- Berechtigung: `MANAGE_ALL_SPACES` oder `MODERATE_SPACE`
- Navigation: `SpaceLifecycleService::count_pending_for_actor()` prüft dieselbe
  Berechtigung wie `list_pending()` und liefert nur bei mindestens einer offenen
  Freigabe einen Tab bzw. Button mit Zähler. Die Zählung nutzt
  `SpaceRepository::count_spaces_by_status()` und lädt für die Navigation keine
  vollständigen Space-Datensätze.
- REST: `POST /spaces/{id}/approve`, `POST /spaces/{id}/reject`
- Spalte `rejection_reason` in `wp_afspaces_spaces` (Migration via `Plugin::maybe_upgrade`)

## Moderation (raum-begrenzt)

`SpaceModerationService::pin_topic()` und `unpin_topic()` ergänzen die
raumbezogene Themenmoderation. Die Aktion funktioniert auch in privaten
Asgaros-Foren, in denen Asgaros die native Pin-Schaltfläche ausblendet.
Arbeitsgruppenverantwortliche erhalten dadurch keine globalen Asgaros-
Moderatorrechte.

- Policy: `SpacePolicy::can_moderate` (= `can_manage`, NICHT global)
- Service: `src/Application/SpaceModerationService.php`
- Hub-View: `src/Interface/ModerationView.php`
- Forum-Inline: `src/Interface/ForumModerationControls.php` (Hook `asgarosforum_after_post_message`)

| Methode (`SpaceModerationService`) | Signatur |
| --- | --- |
| `can_moderate` | `(int $space_id, int $actor): bool` |
| `list_topics` | `(int $space_id, int $actor, array $args = []): array` |
| `close_topic` / `reopen_topic` | `(int $space_id, int $actor, int $topic_id): void` |
| `pin_topic` / `unpin_topic` | `(int $space_id, int $actor, int $topic_id): void` |
| `delete_topic` | `(int $space_id, int $actor, int $topic_id): void` |
| `delete_post` | `(int $space_id, int $actor, int $post_id): void` |
| `move_topic` | `(int $space_id, int $actor, int $topic_id, int $target_space_id): void` |
| `move_post` | `(int $space_id, int $actor, int $post_id, int $target_topic_id): void` |
| `list_move_targets` / `list_post_move_targets` | Zielauswahl für Verschieben |

Wichtig: Jede Operation prüft Objektzugehörigkeit gegen `space.forum_id` (kein Zugriff auf Fremd-Foren). Es werden bewusst KEINE globalen Asgaros-Moderatorrechte vergeben (Begründung in [COMPATIBILITY.md](../../COMPATIBILITY.md)).

## Arbeitsgruppen-Metadaten

- Domain: `src/Domain/WorkingGroupMeta.php`
- Service: `src/Application/WorkingGroupService.php`
- Repository: `src/Adapters/Database/SpaceMetaRepository.php` (`wp_afspaces_space_meta`)
- Views: `src/Interface/WorkingGroupView.php`, `src/Interface/WorkingGroupSettingsView.php`, `src/Interface/WorkingGroupTile.php`
- Terminologie: `src/Interface/WorkingGroupTerminology.php`

Die Einstellungsansicht trennt Leserecht und Mitgliedschaft. `private` bedeutet
„Nur Mitglieder der Arbeitsgruppe“, `protected` bedeutet „Alle angemeldeten
Personen“ mit Leserecht ohne automatische Mitgliedschaft. Das native Asgaros-
Leserecht würde bei `protected` allein auch das Schreiben ermöglichen; deshalb
prüft `Application\ForumContentWritePolicy` Themen- und Beitrags-Requests
serverseitig erneut und lässt dort nur Mitglieder oder globale Asgaros-
Moderatoren zu.

| Methode (`WorkingGroupService`) | Signatur |
| --- | --- |
| `get_metadata` | `(int $space_id): WorkingGroupMeta` |
| `save_metadata` | `(int $space_id, int $actor, array $input): WorkingGroupMeta` |
| `list_topics` | `(): array` |
| `topic_names` | `(WorkingGroupMeta $meta): array` |

ACF-Themen-Taxonomie via Filter `afspaces_working_group_topics_taxonomy`.

## Tests

- `tests/SpaceCreationPolicyTest.php`, `tests/SpaceCreationServiceTest.php`, `tests/SpaceLifecycleTest.php`
- `tests/SpaceModerationServiceTest.php`, `tests/SpaceRegistrationServiceTest.php`
- `tests/WorkingGroupMetaTest.php`, `tests/WorkingGroupTerminologyTest.php`
