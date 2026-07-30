# Datenbank-Referenz

Kernfelder je Tabelle. Schema aus den `install()`-Methoden unter `src/Adapters/Database/`. Präfix `wp_` beispielhaft. Anlage über `Activator::activate`, Nachziehen über `Plugin::maybe_upgrade`.

## `afspaces_spaces`

`SpaceRepository::install`. Verknüpft einen verwalteten Kontext mit einem Asgaros-Forum.

| Feld | Typ | Bedeutung |
| --- | --- | --- |
| `id` | int PK | interne Space-ID (nicht die Asgaros-forum_id) |
| `forum_id` | int | Asgaros-Forum (UNIQUE via `unique_forum_id`) |
| `primary_group_id` | int | primäre Zugriffsgruppe |
| `owner_user_id` | bigint | Owner |
| `visibility` | varchar(20) | `private` \| `protected` \| `public` |
| `status` | varchar(20) | `pending` \| `active` \| `archived` \| `rejected` \| `deleted` |
| `rejection_reason` | text | Begründung bei Ablehnung (Migration ab 0.2.0) |
| `created_at`, `updated_at` | datetime | Zeitstempel |

## `afspaces_space_managers`

`SpaceRepository::install`. Owner-/Managerzuordnung.

| Feld | Typ | Bedeutung |
| --- | --- | --- |
| `space_id` | int | Teil des PK |
| `user_id` | bigint | Teil des PK |
| `role` | varchar(20) | `owner` \| `manager` |

## `afspaces_space_meta`

`SpaceMetaRepository::install`. Arbeitsgruppen-Metadaten (PK = `space_id`).

| Feld | Typ | Default | Bedeutung |
| --- | --- | --- | --- |
| `space_id` | int PK | — | Space |
| `description` | text | — | Beschreibung |
| `accent_color` | varchar(7) | `#2d5d7f` | Akzentfarbe (Hex) |
| `icon` | varchar(40) | `users` | Icon-Schlüssel |
| `contact_text` | text | — | Kontakt/Ansprechperson |
| `directory_visibility` | varchar(20) | `listed` | `listed` \| `members` \| `hidden` |
| `join_policy` | varchar(20) | `request` | `request` \| `invite_only` \| `closed` |
| `join_requests_enabled` | tinyint | `1` | nur mit `join_policy=request` wirksam |
| `topic_ids` | longtext | — | zugeordnete ACF-Themen (serialisiert) |
| `updated_at` | datetime | — | Zeitstempel |

## `afspaces_invitations`

`InvitationRepository::install`.

| Feld | Typ | Bedeutung |
| --- | --- | --- |
| `id` | bigint PK | — |
| `space_id` | int | Space |
| `inviter_user_id` | bigint | Einladende Person |
| `invitee_user_id` | bigint | Eingeladene Person |
| `message` | text | optionale Nachricht |
| `status` | varchar(20) | `pending` \| `accepted` \| `declined` \| `revoked` \| `expired` |
| `expires_at` | datetime | Ablauf |
| `accepted_at`, `declined_at`, `revoked_at` | datetime NULL | Übergangszeitpunkte |
| `last_sent_at` | datetime NULL | letzter Versand |
| `send_count` | int | Anzahl Versände (Drossel) |
| `created_at`, `updated_at` | datetime | Zeitstempel |

## `afspaces_invite_links`

`InviteLinkRepository::install`.

| Feld | Typ | Bedeutung |
| --- | --- | --- |
| `id` | bigint PK | — |
| `space_id` | int | Space |
| `creator_user_id` | bigint | Ersteller |
| `token_hash` | char(64) | Hash (UNIQUE) — kein Klartext-Token |
| `status` | varchar(20) | gespeichert nur `active` \| `revoked` |
| `approval_mode` | varchar(30) | `auto_join` \| `approval_required` \| `existing_users_only` |
| `max_uses` | int | `0` = unbegrenzt |
| `use_count` | int | bisherige Nutzungen |
| `allow_registration` | tinyint | Registrierung erlaubt |
| `expires_at` | datetime | Ablauf (leitet `expired` ab) |
| `revoked_at` | datetime NULL | Widerrufszeitpunkt |
| `created_at`, `updated_at` | datetime | Zeitstempel |

Effektiver Status (`expired`/`exhausted`) wird zur Laufzeit über `InviteLink::effective_status()` berechnet.

## `afspaces_join_requests`

`JoinRequestRepository::install`.

| Feld | Typ | Bedeutung |
| --- | --- | --- |
| `id` | bigint PK | — |
| `space_id` | int | Space |
| `requester_user_id` | bigint | anfragende Person |
| `request_message` | text | Begründung |
| `status` | varchar(20) | `pending` \| `approved` \| `rejected` |
| `decider_user_id` | bigint | entscheidende Person |
| `decision_message` | text | Entscheidungsnachricht |
| `approved_at`, `rejected_at` | datetime NULL | Übergangszeitpunkte |
| `created_at`, `updated_at` | datetime | Zeitstempel |

## `afspaces_audit`

`AuditRepository::install`. Sparsames Änderungsprotokoll.

| Feld | Typ | Bedeutung |
| --- | --- | --- |
| `id` | bigint PK | — |
| `space_id` | int | Space |
| `actor_user_id` | bigint | Akteur |
| `target_user_id` | bigint | Zielperson (0 wenn nicht personenbezogen) |
| `action` | varchar(40) | z. B. `space_archived`, `invite_link_created` |
| `object_type` | varchar(40) | z. B. `member`, `space`, `invite_link` |
| `created_at` | datetime | Zeitstempel |

Keine Tokens oder sensiblen Inhalte im Audit. Personenbezogene Einladungsnachrichten werden über den Privacy-Eraser entfernt.

## `afspaces_search_index`

`SearchIndexRepository::install`. Semantischer Index.

| Feld | Typ | Bedeutung |
| --- | --- | --- |
| `id` | bigint PK | — |
| `source_type` | varchar(10) | `forum` \| `wp` |
| `source_id` | bigint | Quell-ID (UNIQUE mit `source_type`) |
| `topic_id`, `category_id` | bigint | Kontext für Zugriffsfilter |
| `is_private` | tinyint | private Arbeitsgruppe? |
| `title`, `excerpt` | text/longtext | Anzeige-/Ähnlichkeitstext |
| `context_label` | varchar(255) | Badge-Text |
| `author_name` | varchar(255) | Autor |
| `item_date` | datetime | Sortierung |
| `content_hash` | char(40) | Skip unveränderter Inhalte |
| `embedding` | longblob | float32-Vektor (`VectorMath::pack`) |
| `dims` | int | Vektordimensionen |
| `updated_at` | datetime | Zeitstempel |

## Personenbezug & Löschung

| Tabelle | Personenbezug | Löschung |
| --- | --- | --- |
| `afspaces_invitations` | Einlader/Eingeladene, Nachricht | Privacy-Eraser entfernt Nachrichten (`InvitationRepository::erase_personal_messages_for_user`) |
| `afspaces_join_requests` | Anfragende/Entscheidende | offener Restpunkt (siehe [FEATURE-STATUS.md](../FEATURE-STATUS.md)) |
| `afspaces_audit` | Akteur/Ziel-IDs | Aufbewahrung sparsam, konfigurierbar |
| `afspaces_search_index` | Autorname, Text | bei Deinstallation entfernt |

## Schema ändern

1. Feld in der passenden `install()`-Methode ergänzen (`dbDelta`-kompatibel).
2. Sicherstellen, dass `Plugin::maybe_upgrade` `install()` für Bestandsinstallationen aufruft.
3. Neue personenbezogene Daten in Privacy-Exporter/-Eraser berücksichtigen.
4. Uninstaller prüfen (`src/Core/Uninstaller.php`).
