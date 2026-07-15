# AGENTS.md — Entwicklungsanleitung für Asgaros Forum Spaces

## 1. Auftrag

Erstelle ein eigenständiges WordPress-Plugin, das Asgaros Forum um eine barrierearme Frontend-Verwaltung für Mitglieder, Einladungen und später private Forenräume erweitert.

Arbeite inkrementell entlang dieser Dokumente:

1. `GOAL.md`
2. `TASKS_MVP_1.md`
3. `TASKS_MVP_2.md`
4. `TASKS_MVP_3.md`
5. `TASKS_MVP_4.md`
6. `ARCHITECTURE.md`
7. `SECURITY_PRIVACY.md`
8. `ACCESSIBILITY.md`
9. `TESTING.md`
10. `COMPATIBILITY.md`

Eine spätere MVP-Stufe darf die Architektur der früheren Stufen vorbereiten, aber keine unfertigen Funktionen sichtbar aktivieren.

## 2. Technische Grundregeln

- Eigenständiges Plugin; keine Änderung am Asgaros-Core.
- PHP-Namespace oder eindeutiger Präfix: `AFSpaces` beziehungsweise `afspaces_`.
- Mindestziel: PHP 8.1 und eine aktuell unterstützte WordPress-Version.
- Keine direkte Ausgabe ungefilterter Benutzerdaten.
- Alle schreibenden Requests benötigen Nonce, Authentifizierung, Capability-Prüfung und Objektberechtigungsprüfung.
- Alle Datenbankzugriffe über WordPress-APIs oder `$wpdb->prepare()`.
- Alle Ausgaben kontextgerecht escapen.
- Alle Eingaben validieren und bereinigen.
- Übersetzbare Texte mit WordPress-i18n-Funktionen.
- Keine Remote-CDNs für produktionsnotwendige Assets.
- Keine Abhängigkeit von einem JavaScript-Framework, sofern es nicht begründet und dokumentiert wird.

## 3. Asgaros-Integration

Verwende dokumentierte Asgaros-Hooks, wo sie passen, insbesondere für:

- Frontend-Menüeinträge,
- Inhalte im Forum oder Profil,
- Laden eigener CSS- und JavaScript-Dateien,
- Reaktion auf das Hinzufügen oder Entfernen von Benutzern aus Benutzergruppen.

Die dokumentierten Hooks sind keine vollständige Verwaltungs-API. Deshalb ist eine Adapter-Schicht verpflichtend.

### Adaptervertrag

Definiere ein Interface, beispielsweise:

```php
interface AsgarosAdapterInterface {
    public function is_available(): bool;
    public function get_version(): ?string;
    public function list_manageable_forums(int $actor_user_id): array;
    public function get_forum(int $forum_id): ?array;
    public function get_forum_group_ids(int $forum_id): array;
    public function list_group_members(int $group_id, array $args = []): array;
    public function add_user_to_group(int $user_id, int $group_id): void;
    public function remove_user_from_group(int $user_id, int $group_id): void;
    public function create_group(array $data): int;
    public function create_forum(array $data): int;
    public function assign_group_to_forum(int $forum_id, int $group_id): void;
}
```

Anforderungen:

- Keine Asgaros-internen Klassen außerhalb des Adapters verwenden.
- Interne APIs nur nach Quellcodeprüfung verwenden.
- Jede intern verwendete Methode mit getesteter Asgaros-Version dokumentieren.
- Bei unbekannter oder inkompatibler Version schreibende Funktionen sicher deaktivieren.
- Adapterfehler in verständliche Domain-Ausnahmen übersetzen.

## 4. Domänenmodell

### Space

Ein Space verbindet einen Asgaros-Forumdatensatz mit mindestens einer zugriffssteuernden Asgaros-Benutzergruppe.

Empfohlene Felder:

- `id`
- `forum_id`
- `primary_group_id`
- `owner_user_id`
- `visibility`
- `status`
- `created_at`
- `updated_at`

### Space Manager

Eigene Rolle innerhalb eines einzelnen Spaces:

- `owner`
- `manager`
- optional `moderator_reference`

Ein Space Manager ist nicht automatisch WordPress-Administrator und nicht automatisch globaler Asgaros-Moderator.

### Invitation

Zustände:

- `pending`
- `accepted`
- `declined`
- `revoked`
- `expired`

Einladungen müssen idempotent verarbeitet werden.

## 5. Berechtigungen

Registriere eigene Capabilities, mindestens:

- `afspaces_manage_all_spaces`
- `afspaces_create_space`
- `afspaces_manage_own_space`
- `afspaces_invite_members`
- `afspaces_remove_members`
- `afspaces_create_invite_links`
- `afspaces_moderate_space`

Jede Entscheidung kombiniert:

1. globale Capability,
2. Space-Zuordnung,
3. konkrete Aktion,
4. Zielobjekt,
5. Schutzregeln, etwa „Owner darf sich nicht als letzten Owner entfernen“.

Implementiere zentrale Policy-Klassen. Verteile Berechtigungslogik nicht über Templates und Controller.

## 6. Frontend-Architektur

Stelle eine Frontend-Seite über Shortcode, Block oder Rewrite-Endpunkt bereit. Ein Block ist optional; der Shortcode muss als robuste Basis funktionieren.

Empfohlene Ansichten:

- Übersicht verwalteter Räume,
- Mitglieder,
- offene Einladungen,
- Einladungsannahme,
- Linkverwaltung,
- Raumassistent.

Progressive Enhancement:

- Grundfunktionen müssen mit serverseitigen Formularen funktionieren.
- JavaScript verbessert Suche, Dialoge und Drag-and-drop.
- Bei JavaScript-Fehlern darf keine Kernfunktion unbrauchbar werden.

## 7. REST-API

Nutze versionierte Endpunkte unter `/wp-json/afspaces/v1`.

Jeder Endpunkt benötigt:

- `permission_callback`,
- Schema und Validierung,
- normalisierte Fehlercodes,
- keine unnötigen personenbezogenen Daten,
- Paginierung bei Benutzerlisten,
- Rate-Limits oder Drosselung bei Einladungen und Suche.

Beispielhafte Endpunkte:

```text
GET    /spaces
GET    /spaces/{id}
GET    /spaces/{id}/members
POST   /spaces/{id}/members
DELETE /spaces/{id}/members/{user_id}
POST   /spaces/{id}/invitations
POST   /invitations/{token}/accept
POST   /invitations/{token}/decline
DELETE /invitations/{id}
POST   /spaces/{id}/invite-links
DELETE /invite-links/{id}
POST   /spaces
PATCH  /spaces/{id}
```

## 8. Datenschutz

- In der Nutzersuche standardmäßig Anzeigename und optional Benutzername zeigen.
- E-Mail-Adressen nur bei entsprechender Berechtigung und Notwendigkeit anzeigen.
- Kein offenes Durchsuchen aller WordPress-Benutzer durch normale Mitglieder.
- Suchergebnisse begrenzen und serverseitig filtern.
- Tokens niemals im Klartext speichern.
- Audit-Logs sparsam und mit Löschfrist speichern.
- Datenschutzexport und -löschung über WordPress Privacy Tools integrieren, sobald eigene personenbezogene Daten gespeichert werden.

## 9. Barrierefreiheit

Die Anforderungen aus `ACCESSIBILITY.md` sind Akzeptanzkriterien, keine spätere Optimierung.

Insbesondere:

- Drag-and-drop nie als alleinige Bedienung.
- Jede Liste als semantische Liste oder Tabelle.
- Modale Dialoge mit Fokusmanagement und Escape-Funktion.
- Statusmeldungen über geeignete Live-Regionen.
- Bestätigungen nicht ausschließlich über Toasts.
- Keine unbeschrifteten Icon-Buttons.
- Touch-Ziele ausreichend groß.

## 10. Tests vor Abschluss jeder Aufgabe

Führe mindestens aus:

- PHP-Syntaxprüfung,
- PHPCS mit WordPress Coding Standards,
- PHPUnit-Tests,
- WordPress-Integrationstests,
- REST-Berechtigungstests,
- Playwright-End-to-End-Tests,
- automatisierte Accessibility-Tests mit axe,
- Tests ohne JavaScript,
- Tests mit Tastaturbedienung,
- Kompatibilitätstest gegen die festgelegten Asgaros-Versionen.

Eine Aufgabe ist erst abgeschlossen, wenn:

1. Code implementiert ist,
2. Tests vorhanden sind,
3. Tests erfolgreich laufen,
4. Dokumentation aktualisiert ist,
5. keine offenen Sicherheits- oder Accessibility-Todos verborgen bleiben.

## 11. Definition of Done

Für jede MVP-Stufe:

- sämtliche Akzeptanzkriterien der zugehörigen Task-Datei erfüllt,
- keine Core-Modifikationen,
- Deinstallation und Datenhaltung dokumentiert,
- Übersetzungsdatei erzeugbar,
- Fehlerzustände verständlich,
- Rollen- und Rechteprüfungen serverseitig getestet,
- Keyboard- und Screenreader-Kernpfade geprüft,
- Upgrade-Pfad für Datenbankschema vorhanden,
- Changelog-Eintrag erstellt.

## 12. Arbeitsweise des Coding-Agenten

Vor jeder Implementierung:

1. relevanten Asgaros-Quellcode und aktuelle Hooks prüfen,
2. Annahmen in `COMPATIBILITY.md` dokumentieren,
3. kleine vertikale Funktionseinheit auswählen,
4. Tests zuerst oder zusammen mit dem Code schreiben,
5. keine neue Abhängigkeit ohne Begründung einführen.

Nach jeder Einheit:

1. Tests ausführen,
2. Sicherheits- und Accessibility-Auswirkungen prüfen,
3. Task-Checkboxen aktualisieren,
4. technische Entscheidung dokumentieren,
5. keine spätere MVP-Funktion ungefragt vorziehen.

## 13. Verbotene Abkürzungen

- direkte Änderungen an Asgaros-Dateien,
- Berechtigungsprüfung nur im Browser,
- Klartext-Einladungstokens,
- Laden aller WordPress-Benutzer ohne Pagination,
- E-Mail-Versand ohne Drosselung,
- Drag-and-drop ohne Alternative,
- globale Moderatorrechte als Ersatz für Space-spezifische Verwaltung,
- automatische Raumgründung ohne Limits und Policy-Prüfung,
- stille Fehler ohne verständliche Rückmeldung.
