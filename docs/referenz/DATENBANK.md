# Datenbank-Referenz

Diese Seite beschreibt das physische Schema aus den `install()`-Methoden unter `src/Adapters/Database/`. Der Tabellenpräfix ist beispielhaft `wp_`; tatsächlich wird immer `$wpdb->prefix` verwendet. Die Tabellen werden bei `Activator::activate()` mit `dbDelta()` angelegt.

Es gibt bewusst keine SQL-Fremdschlüssel zu WordPress-, Asgaros- oder AFSpaces-Tabellen. Konsistenz und Cleanup liegen in Repositories, Services und `Uninstaller`; Asgaros-Daten werden bei der Deinstallation nicht gelöscht.

## `afspaces_spaces` — `SpaceRepository::install()`

| Feld | SQL-Typ | NULL/Default | Schlüssel/Index |
| --- | --- | --- | --- |
| `id` | `int unsigned` | NOT NULL, AUTO_INCREMENT | PRIMARY KEY |
| `forum_id` | `int unsigned` | NOT NULL | `KEY forum_id`; zusätzlich `UNIQUE KEY unique_forum_id` durch `ensure_forum_unique_index()` |
| `primary_group_id` | `int unsigned` | NOT NULL | — |
| `owner_user_id` | `bigint(20) unsigned` | NOT NULL | `KEY owner_user_id` |
| `visibility` | `varchar(20)` | NOT NULL, Default `private` | — |
| `status` | `varchar(20)` | NOT NULL, Default `active` | — |
| `rejection_reason` | `text` | NOT NULL | — |
| `created_at` | `datetime` | NOT NULL, Default `0000-00-00 00:00:00` | — |
| `updated_at` | `datetime` | NOT NULL, Default `0000-00-00 00:00:00` | — |

`forum_id` ist die Asgaros-ID; `id` ist die interne Space-ID. Vor dem Unique-Index bereinigt `normalize_duplicate_forums()` vorhandene Dubletten.

## `afspaces_space_managers` — `SpaceRepository::install()`

| Feld | SQL-Typ | NULL/Default | Schlüssel/Index |
| --- | --- | --- | --- |
| `space_id` | `int unsigned` | NOT NULL | Bestandteil PRIMARY KEY (`space_id`, `user_id`) |
| `user_id` | `bigint(20) unsigned` | NOT NULL | Bestandteil PRIMARY KEY; zusätzlich `KEY user_id` |
| `role` | `varchar(20)` | NOT NULL, Default `manager` | — |

Fachliche Rollenwerte sind `owner` und `manager`; dies ist keine WordPress-Rolle.

## `afspaces_space_meta` — `SpaceMetaRepository::install()`

| Feld | SQL-Typ | NULL/Default | Schlüssel/Index |
| --- | --- | --- | --- |
| `space_id` | `int unsigned` | NOT NULL | PRIMARY KEY |
| `description` | `text` | NOT NULL | — |
| `accent_color` | `varchar(7)` | NOT NULL, Default `#2d5d7f` | — |
| `icon` | `varchar(40)` | NOT NULL, Default `users` | — |
| `contact_text` | `text` | NOT NULL | — |
| `directory_visibility` | `varchar(20)` | NOT NULL, Default `listed` | `KEY directory_visibility` |
| `join_policy` | `varchar(20)` | NOT NULL, Default `request` | `KEY join_policy` |
| `join_requests_enabled` | `tinyint(1)` | NOT NULL, Default `1` | — |
| `topic_ids` | `longtext` | NOT NULL | serialisierte Topic-ID-Liste |
| `updated_at` | `datetime` | NOT NULL, Default `0000-00-00 00:00:00` | — |

## `afspaces_invitations` — `InvitationRepository::install()`

| Feld | SQL-Typ | NULL/Default | Schlüssel/Index |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | NOT NULL, AUTO_INCREMENT | PRIMARY KEY |
| `space_id` | `int unsigned` | NOT NULL | `KEY space_id` |
| `inviter_user_id` | `bigint(20) unsigned` | NOT NULL | — |
| `invitee_user_id` | `bigint(20) unsigned` | NOT NULL | `KEY invitee_user_id` |
| `message` | `text` | NOT NULL | — |
| `status` | `varchar(20)` | NOT NULL, Default `pending` | `KEY status` |
| `expires_at` | `datetime` | NOT NULL | `KEY expires_at` |
| `accepted_at` | `datetime` | NULL | — |
| `declined_at` | `datetime` | NULL | — |
| `revoked_at` | `datetime` | NULL | — |
| `last_sent_at` | `datetime` | NULL | — |
| `send_count` | `int unsigned` | NOT NULL, Default `0` | — |
| `created_at` / `updated_at` | `datetime` | NOT NULL, Default `0000-00-00 00:00:00` | — |

## `afspaces_invite_links` — `InviteLinkRepository::install()`

| Feld | SQL-Typ | NULL/Default | Schlüssel/Index |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | NOT NULL, AUTO_INCREMENT | PRIMARY KEY |
| `space_id` | `int unsigned` | NOT NULL | `KEY space_id` |
| `creator_user_id` | `bigint(20) unsigned` | NOT NULL | — |
| `token_hash` | `char(64)` | NOT NULL | UNIQUE KEY `token_hash` |
| `status` | `varchar(20)` | NOT NULL, Default `active` | `KEY status` |
| `approval_mode` | `varchar(30)` | NOT NULL, Default `auto_join` | — |
| `max_uses` | `int unsigned` | NOT NULL, Default `1` | — |
| `use_count` | `int unsigned` | NOT NULL, Default `0` | — |
| `allow_registration` | `tinyint(1)` | NOT NULL, Default `0` | — |
| `expires_at` | `datetime` | NOT NULL | `KEY expires_at` |
| `revoked_at` | `datetime` | NULL | — |
| `created_at` / `updated_at` | `datetime` | NOT NULL, Default `0000-00-00 00:00:00` | — |

Gespeichert wird nur der 64-stellige Hash. `expired` und `exhausted` sind abgeleitete Laufzeitstatuswerte von `InviteLink::effective_status()`, keine gespeicherten `status`-Werte.

## `afspaces_join_requests` — `JoinRequestRepository::install()`

| Feld | SQL-Typ | NULL/Default | Schlüssel/Index |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | NOT NULL, AUTO_INCREMENT | PRIMARY KEY |
| `space_id` | `int unsigned` | NOT NULL | `KEY space_id` |
| `requester_user_id` | `bigint(20) unsigned` | NOT NULL | `KEY requester_user_id` |
| `request_message` | `text` | NOT NULL | — |
| `status` | `varchar(20)` | NOT NULL, Default `pending` | `KEY status` |
| `decider_user_id` | `bigint(20) unsigned` | NOT NULL, Default `0` | — |
| `decision_message` | `text` | NOT NULL | — |
| `approved_at` / `rejected_at` | `datetime` | NULL | — |
| `created_at` / `updated_at` | `datetime` | NOT NULL, Default `0000-00-00 00:00:00` | — |

## `afspaces_audit` — `AuditRepository::install()`

| Feld | SQL-Typ | NULL/Default | Schlüssel/Index |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | NOT NULL, AUTO_INCREMENT | PRIMARY KEY |
| `space_id` | `int unsigned` | NOT NULL | `KEY space_id` |
| `actor_user_id` | `bigint(20) unsigned` | NOT NULL | — |
| `target_user_id` | `bigint(20) unsigned` | NOT NULL, Default `0` | — |
| `action` | `varchar(40)` | NOT NULL | — |
| `object_type` | `varchar(40)` | NOT NULL, Default `member` | — |
| `created_at` | `datetime` | NOT NULL, Default `0000-00-00 00:00:00` | `KEY created_at` |

Audit-Einträge enthalten keine Tokens oder Nachrichtentexte.

## `afspaces_search_index` — `SearchIndexRepository::install()`

| Feld | SQL-Typ | NULL/Default | Schlüssel/Index |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | NOT NULL, AUTO_INCREMENT | PRIMARY KEY |
| `source_type` | `varchar(10)` | NOT NULL, Default `forum` | Teil von UNIQUE KEY `source`; zusätzlich `KEY source_type` |
| `source_id` | `bigint(20) unsigned` | NOT NULL | Teil von UNIQUE KEY `source` (`source_type`, `source_id`) |
| `topic_id` | `bigint(20) unsigned` | NOT NULL, Default `0` | — |
| `category_id` | `bigint(20) unsigned` | NOT NULL, Default `0` | `KEY category_id` |
| `is_private` | `tinyint(1)` | NOT NULL, Default `0` | — |
| `title` | `text` | NULL | — |
| `context_label` | `varchar(255)` | NOT NULL, Default leer | — |
| `excerpt` | `longtext` | NULL | — |
| `author_name` | `varchar(255)` | NOT NULL, Default leer | — |
| `item_date` | `datetime` | NOT NULL, Default `0000-00-00 00:00:00` | — |
| `content_hash` | `char(40)` | NOT NULL, Default leer | — |
| `embedding` | `longblob` | NULL | — |
| `dims` | `int unsigned` | NOT NULL, Default `0` | — |
| `updated_at` | `datetime` | NOT NULL, Default `0000-00-00 00:00:00` | — |

## Installation, Upgrade und Löschung

- `Activator::activate()` installiert alle acht Tabellen und registriert die Capabilities.
- `Plugin::maybe_upgrade()` stellt die Hub-Seite wieder her, ruft `SpaceRepository::install()` und `SearchIndexRepository::install()` erneut auf und plant den Reindex. `dbDelta()` ist dabei der vorhandene Upgrade-Mechanismus.
- `SpaceRepository::install()` ergänzt den eindeutigen `forum_id`-Index nach der Dublettenbereinigung.
- `Uninstaller::uninstall()` löscht Tabellen nur bei ausdrücklichem Opt-in über `afspaces_cleanup_on_uninstall`; ohne Opt-in bleiben sie bestehen.
- Es gibt keine Fremdschlüssel und keine automatische Löschung zugehöriger Asgaros-Foren, Gruppen, Kategorien oder Beiträge.

## Personenbezug und Privacy

Einladungen und Beitrittsanfragen enthalten Benutzer-IDs und Nachrichten. Die Privacy-Integration exportiert die relevanten Daten und leert persönliche Nachrichten beim Eraser, während Status- und Nachweisdaten erhalten bleiben. Der Suchindex kann Autorennamen und Inhalte enthalten und wird nur beim ausdrücklich aktivierten vollständigen Cleanup gelöscht.
