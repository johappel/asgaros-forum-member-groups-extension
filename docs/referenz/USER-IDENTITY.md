# User Identity Integration

AFSpaces verwendet WordPress-Benutzer-IDs als technische Identität. Anzeigename, Avatar und Suchidentität können von externen Profil-Systemen angepasst werden, ohne dass AFSpaces diese Systeme kennt.

## Zentrale API

Implementierung: `src/Application/UserIdentityService.php` (`UserIdentityService`).

| Methode | Ergebnis |
| --- | --- |
| `get_display_name(int $user_id)` | Anzeigename aus `WP_User`, danach gefiltert |
| `get_profile_url(int $user_id)` | kanonische externe Profil-URL oder leerer String |
| `user_exists(int $user_id)` | technische Existenzprüfung ohne Avatar- oder Namensdarstellung |
| `get_avatar_url(int $user_id, int $size = 40)` | URL aus `get_avatar_url()`, danach gefiltert |
| `get_avatar_html(int $user_id, int $size = 40, array $args = [])` | sicher erzeugtes `<img>`-Markup mit `src`, `alt`, `width`, `height` und `class="avatar ..."` |
| `get_identity(int $user_id)` | normalisierte ID, Anzeigename, Login und Avatar-URL |
| `search_users(string $search, int $page = 1, int $per_page = 20)` | paginierte, deduplizierte Treffer ohne E-Mail-Adressen |

## Filterkette

Der Anzeigename folgt dieser Reihenfolge:

`WP_User->display_name` → `asgarosforum_filter_username` → `afspaces_user_display_name` → AFSpaces-Ausgabe.

`afspaces_user_avatar_url` erhält `(string $url, int $user_id, int $size)`. Der Rückgabewert wird vor dem HTML-Markup als URL escaped.

`afspaces_user_profile_url` erhält `(string $url, int $user_id, WP_User $user)`. Externe
Mitglieder- oder Community-Systeme können darüber ihre kanonische Profil-URL liefern.
Ohne Provider bleibt der Standardwert ein leerer String; AFSpaces erzeugt keine
Ersatz-URL und kennt keine externe URL-Struktur.

`afspaces_user_search_results` erhält `array{user_ids:int[],total:int}`, den Suchbegriff, Seite, Seitengröße und ein gemeinsames Kandidatenlimit. Externe Provider liefern ausschließlich WordPress-User-IDs zurück; sie sollen für dieses Kandidatenfenster ab Seite 1 liefern. AFSpaces führt die Kandidaten zusammen, dedupliziert sie und schneidet erst danach die angeforderte Seite aus.

Ohne externe Filter bleibt das WordPress-Verhalten erhalten. Technische Prüfungen, E-Mail-Adressen und Berechtigungen verwenden weiterhin die WordPress-User-ID bzw. das `WP_User`-Objekt.
