# Adapter-Referenz

Der Asgaros-Adapter ist die einzige Stelle, die Asgaros-Interna kennen darf. Vertrag: [src/Adapters/Asgaros/AsgarosAdapterInterface.php](../../src/Adapters/Asgaros/AsgarosAdapterInterface.php). Implementierung: `src/Adapters/Asgaros/AsgarosAdapter.php`.

Regel aus [ARCHITECTURE.md](../../ARCHITECTURE.md): Domain und Application rufen ausschließlich diese Schnittstelle, nie Asgaros-Klassen direkt. Details der genutzten Asgaros-Interna und geprüften Versionen stehen in [COMPATIBILITY.md](../../COMPATIBILITY.md).

## Verfügbarkeit / Version

| Methode | Rückgabe |
| --- | --- |
| `is_available()` | `bool` |
| `get_version()` | `?string` |
| `is_search_request()` | `bool` (ist die aktuelle Ansicht die Asgaros-Suche?) |

## Lesen: Foren, Gruppen, Mitglieder

| Methode | Rückgabe |
| --- | --- |
| `list_manageable_forums(int $actor_user_id)` | `array` verwaltbare Foren |
| `get_forum(int $forum_id)` | `?array` |
| `get_forum_group_ids(int $forum_id)` | `int[]` |
| `list_group_members(int $group_id, array $args = [])` | `array` (paginiert: `page`, `per_page`, `search`) |
| `is_user_in_group(int $user_id, int $group_id)` | `bool` |
| `list_accessible_forums()` | `array{id,name}[]` |
| `list_accessible_category_ids()` | `int[]` |

## Schreiben: Mitgliedschaft

| Methode | Effekt |
| --- | --- |
| `add_user_to_group(int $user_id, int $group_id)` | Mitglied hinzufügen (wirft `DomainException`) |
| `remove_user_from_group(int $user_id, int $group_id)` | Mitglied entfernen (wirft `DomainException`) |

## Suche

| Methode | Rückgabe |
| --- | --- |
| `search_posts(string $keywords, array $args = [])` | `array{results, total}` (post-genau, kein Topic-Collapse) |
| `get_post_link(int $post_id, int $topic_id)` | Deep-Link `.../topic/<slug>/?part=<N>#postid-<ID>` |
| `count_all_posts()` | `int` (Indexierung) |
| `list_posts_for_index(int $limit, int $offset)` | `array` (Indexierung) |

## Schreiben: Arbeitsgruppen-Gründung (MVP 4)

| Methode | Effekt |
| --- | --- |
| `create_forum_category(array $data)` | `int` neue Kategorie-Term-ID |
| `create_forum(array $data)` | `int` neue Forum-ID |
| `create_group(array $data)` | `int` neue Gruppen-Term-ID |
| `assign_group_to_forum(int $forum_id, int $group_id)` | Zugriffskopplung |
| `set_forum_visibility(int $forum_id, array $data)` | Sichtbarkeit/Zugriff setzen |
| `update_forum(int $forum_id, array $data)` | Name/Beschreibung ändern |
| `delete_forum(int $forum_id)` | Rollback/Löschung |
| `delete_forum_category(int $category_id)` | Rollback/Löschung |
| `delete_group(int $group_id)` | Rollback/Löschung |

Isolationsentscheidung: Jede selbst gegründete Arbeitsgruppe erhält eine eigene Kategorie, Gruppe und ein Forum, weil Asgaros Zugriffe auf Kategorieebene steuert (Details in [COMPATIBILITY.md](../../COMPATIBILITY.md)).

## Schreiben/Lesen: Moderation (MVP 4)

| Methode | Rückgabe/Effekt |
| --- | --- |
| `list_forum_topics(int $forum_id, array $args = [])` | `array{topics,total}` |
| `get_topic_forum(int $topic_id)` | `int` Forum-ID (Ownership-Prüfung) |
| `set_topic_closed(int $topic_id, bool $closed)` | Thema schließen/öffnen |
| `delete_forum_topic(int $topic_id)` | Thema löschen |
| `get_post_location(int $post_id)` | `?array{topic_id, forum_id, is_first}` |
| `delete_forum_post(int $post_id)` | Beitrag löschen |
| `move_topic(int $topic_id, int $target_forum_id)` | Thema verschieben |
| `list_topic_posts(int $topic_id, array $args = [])` | `array{posts,total}` |
| `move_post(int $post_id, int $target_topic_id, int $target_forum_id)` | Beitrag verschieben |

## Neue Adaptermethode hinzufügen

1. Asgaros-Quellcode prüfen und intern genutzte Methode notieren.
2. Signatur ins Interface aufnehmen.
3. In `AsgarosAdapter` implementieren; bei fehlender/inkompatibler Asgaros-Funktion defensiv degradieren (`method_exists`, leere Rückgabe statt Fatal).
4. Alle Test-Stubs erweitern: `FakeSearchAdapter`, `StubSpaceRegistrationAdapter`, `StubSpaceCreationAdapter`, `StubModerationAdapter` (je nach Testbereich).
5. Nutzung und geprüfte Asgaros-Version in [COMPATIBILITY.md](../../COMPATIBILITY.md) dokumentieren.
