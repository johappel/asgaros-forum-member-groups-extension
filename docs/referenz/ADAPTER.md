# Adapter-Referenz

Der Asgaros-Adapter ist die einzige Stelle, die Asgaros-Interna kennen darf. Vertrag: [src/Adapters/Asgaros/AsgarosAdapterInterface.php](../../src/Adapters/Asgaros/AsgarosAdapterInterface.php). Implementierung: `src/Adapters/Asgaros/AsgarosAdapter.php`. Domain und Application verwenden keine Asgaros-Klassen direkt. Geprüfte interne APIs und Versionen stehen in [COMPATIBILITY.md](../../COMPATIBILITY.md).

## Verfügbarkeit und Lesen

| Methode | Parameter | Rückgabe / Shape |
| --- | --- | --- |
| `is_available()` | — | `bool` |
| `get_version()` | — | `string|null` |
| `list_manageable_forums(int $actor_user_id)` | WordPress-Benutzer-ID | `array<int,array<string,mixed>>`; normalisierte Foren enthalten mindestens `id`, `category_id`, `name`, `slug`, `parent_forum` |
| `get_forum(int $forum_id)` | Asgaros-Forum-ID | `array{id:int,category_id:int,name:string,slug:string,parent_forum:int}|null` |
| `get_forum_group_ids(int $forum_id)` | Asgaros-Forum-ID | `int[]`; Gruppen liegen bei Asgaros auf Kategorieebene |
| `get_group_name(int $group_id)` | Gruppen-Term-ID | `string|null` |
| `list_accessible_forums()` | — | `array<int,array{id:int,name:string}>` |
| `list_accessible_category_ids()` | — | `int[]` |
| `is_user_in_group(int $user_id, int $group_id)` | WordPress-/Gruppen-ID | `bool` |
| `is_search_request()` | — | `bool`; erkennt die aktuelle Asgaros-Suchansicht |

### Gruppenmitglieder

`list_group_members(int $group_id, array $args = [])` akzeptiert:

| Key | Typ / Default | Bedeutung |
| --- | --- | --- |
| `page` | int, Default `1`, mindestens 1 | 1-basierte Seite |
| `per_page` | int, Default `20`, mindestens 1 | Seitengröße |
| `search` | string, Default leer | Teilstring-Suche in `display_name` oder `user_login` |

Die Implementierung liefert `array{members: array<int,array{user_id:int,display_name:string,user_login:string}>, total:int, page:int, per_page:int}`. Bei nicht verfügbarem Asgaros oder einer leeren Gruppe kann sie ein leeres Array zurückgeben. E-Mail-Adressen werden nicht geliefert.

## Mitgliedschaft schreiben

| Methode | Effekt / Fehlerverhalten |
| --- | --- |
| `add_user_to_group(int $user_id, int $group_id)` | idempotentes Hinzufügen; bei Asgaros-Fehler `DomainException` |
| `remove_user_from_group(int $user_id, int $group_id)` | idempotentes Entfernen; bei Asgaros-Fehler `DomainException` |

## Suche

`search_posts(string $keywords, array $args = [])` akzeptiert:

| Key | Typ / Default | Normalisierung |
| --- | --- | --- |
| `sort` | string, Default `relevance` | nur `relevance` oder `date` |
| `page` | int, Default `1` | mindestens 1 |
| `per_page` | int, Default `20` | mindestens 1, höchstens 100 im Adapter |
| `author_id` | int, Default `0` | optionaler Autorfilter |
| `forum_id` | int, Default `0` | optionaler Asgaros-Forumfilter |
| `date_from` / `date_to` | `YYYY-MM-DD`, Default leer | ungültige Werte werden ignoriert; Tagesanfang/-ende |
| `match_mode` | string, Default `any` | `any` oder `all` |
| `in` | string, Default `all` | `all` oder `title` |

Rückgabe: `array{results: array<int,array{post_id:int,topic_id:int,forum_id:int,author_id:int,post_text:string,post_date:string,topic_name:string,forum_name:string,score:float,url:string}>, total:int}`. Die Ergebnisse bleiben beitragsgenau und enthalten einen Deep-Link. Der Adapter filtert auf zugängliche Kategorien und freigegebene Themen; kurze Suchbegriffe können über den LIKE-Fallback laufen.

`list_posts_for_index(int $limit, int $offset)` liefert Indexierungszeilen mit `post_id`, `topic_id`, `forum_id`, `category_id`, `is_private`, `author_id`, `post_date`, `post_text`, `topic_name` und `forum_name`. `count_all_posts()` liefert die Anzahl der indexierbaren Beiträge. `get_post_link(int $post_id, int $topic_id)` liefert einen String oder leer, wenn die Asgaros-Rewrite-API fehlt.

## Arbeitsgruppen-Gründung

Alle Methoden schreiben über Asgaros-interne APIs und werfen bei fehlender Kompatibilität oder einem Schreibfehler `AFSpaces\Core\DomainException`.

| Methode | Erwartete Array-Keys / Werte | Rückgabe |
| --- | --- | --- |
| `create_forum_category(array $data)` | `name` string, erforderlich; `access` `everyone|loggedin|moderator`, Default `loggedin`; `order` int, optional | neue Kategorie-Term-ID `int` |
| `create_forum(array $data)` | `category_id` int > 0, `name` string erforderlich, `description` string optional, `icon` string optional (Default `fas fa-comments`), `order` int optional (Default 1) | neue Forum-ID `int` |
| `create_group(array $data)` | `name` string erforderlich, `color` string optional (Default `#2d5d7f`), `icon` string optional | neue Gruppen-Term-ID `int` |
| `assign_group_to_forum(int $forum_id, int $group_id)` | keine Array-Parameter | ordnet die Gruppe der Forum-Kategorie zu; erhält bestehende Gruppen |
| `set_forum_visibility(int $forum_id, array $data)` | `access` `everyone|loggedin|moderator`, Default `loggedin`; `restrict` bool, Default false; `group_id` int optional | `void`; setzt Kategorie-Zugriff und Gruppenrestriktion |
| `update_forum(int $forum_id, array $data)` | beliebige Teilmenge aus `name` string, `description` string, `forum_status` string | `void`; leeres Array ist No-op |
| `delete_forum(int $forum_id)` | — | `void`; Rollback-/Cleanup-Hilfe |
| `delete_forum_category(int $category_id)` | — | `void`; löscht den Asgaros-Term |
| `delete_group(int $group_id)` | — | `void`; löscht die Asgaros-Gruppe |

Die Isolation privater Räume erfolgt über eine dedizierte Asgaros-Kategorie, Gruppe und ein Forum, weil Asgaros Zugriffe auf Kategorieebene steuert.

## Moderation

| Methode | Parameter / Rückgabe |
| --- | --- |
| `list_forum_topics(int $forum_id, array $args = [])` | Args `page`, `per_page`; `array{topics:array<int,array<string,mixed>>,total:int}` |
| `get_topic_forum(int $topic_id)` | `int` Forum-ID, `0` falls unbekannt |
| `set_topic_closed(int $topic_id, bool $closed)` | `void` |
| `delete_forum_topic(int $topic_id)` | `void` |
| `get_post_location(int $post_id)` | `array{topic_id:int,forum_id:int,is_first:bool}|null` |
| `delete_forum_post(int $post_id)` | `void` |
| `move_topic(int $topic_id, int $target_forum_id)` | `void` |
| `list_topic_posts(int $topic_id, array $args = [])` | Args `page`, `per_page`; `array{posts:array<int,array<string,mixed>>,total:int}` |
| `move_post(int $post_id, int $target_topic_id, int $target_forum_id)` | `void` |

## Neue Adaptermethode

1. Asgaros-Quellcode und die getestete Version prüfen.
2. Signatur und PHPDoc-Shape ins Interface aufnehmen.
3. In `AsgarosAdapter` implementieren und bei unbekannter API defensiv degradieren (`method_exists`, leere Rückgabe oder `DomainException` je nach Operation).
4. Alle betroffenen Test-Stubs erweitern.
5. Nutzung und Kompatibilität in [COMPATIBILITY.md](../../COMPATIBILITY.md) sowie diese Referenz nachziehen.
