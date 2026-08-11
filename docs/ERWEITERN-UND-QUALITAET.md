# Erweitern und Qualität sichern

## Entwickler-Schnellstart

Der kanonische Einstieg benötigt keine lokale WordPress- oder LocalWP-Installation:

```bash
composer install
composer test
vendor/bin/phpunit -c phpunit.xml.dist
vendor/bin/phpunit -c phpunit-integration.xml.dist
```

Die Integrationstests benötigen zusätzlich eine vorbereitete WordPress-/Asgaros-Testumgebung. Wenn diese nicht vorhanden ist, den Unit-Testlauf als technische Prüfung ausführen und die Integrationstests als nicht verfügbar dokumentieren.

Ein optionaler Windows-/LocalWP-Aufruf kann je nach lokaler PHP-Installation so aussehen:

```powershell
$php = "C:\path\to\php.exe"
& $php -c "tests/php-cli.ini" vendor/bin/phpunit -c phpunit.xml.dist
```

## Referenz zuerst

Für konkrete Signaturen, Routen, Hooks, Optionsschlüssel und Bereichsübersichten die Nachschlage-Referenz nutzen: [referenz/INDEX.md](referenz/INDEX.md). Diese Seite beschreibt das Vorgehen, die Referenz liefert die Details.

## Änderungsstrategie

Für neue Features oder Anpassungen sollte die Arbeit in AFSpaces fast immer entlang derselben Reihenfolge erfolgen:

1. Fachliche Regel oder Policy klären.
2. Bestehenden Application Service oder die passende View als Startanker wählen.
3. Falls nötig den AsgarosAdapter oder ein Repository erweitern.
4. FrontendController oder RestController anpassen.
5. Nur danach Views, Navigation und JavaScript ergänzen.
6. Tests und Dokumentation im selben Zug nachziehen.

## Harte Leitplanken

- Keine direkten Änderungen am Asgaros-Core.
- Keine Asgaros-Interna außerhalb des Adapter-Layers verwenden.
- Schreiboperationen immer mit Nonce, Authentifizierung, Capability- und Objektprüfung absichern.
- Berechtigungslogik nicht in Templates oder JavaScript verstreuen.
- Tokens niemals im Klartext speichern.
- Keine zweite Quelle für Forenzugriffe neben Asgaros einführen.
- Neue Asgaros-Methoden immer in COMPATIBILITY.md dokumentieren.

## Typische Änderungswege

### Neue Hub-Ansicht hinzufügen

1. Neue View unter src/Interface anlegen.
2. View-Namen und URL-Helfer in SpacesUrls ergänzen.
3. Routing in SpacesHubController ergänzen.
4. Falls Form-Posts nötig sind: Aktion in FrontendController ergänzen.
5. Falls REST nötig ist: Endpunkt in RestController registrieren.

### Neue Space-bezogene Geschäftsregel hinzufügen

1. Prüfen, ob die Regel in SpacePolicy, SpaceLifecycle oder SpaceCreationPolicy gehört.
2. Den betroffenen Application Service anpassen.
3. Tests zuerst oder direkt mit ändern.
4. Sichtbare Meldungen in der View oder im Controller nachziehen.

### Neue Asgaros-Interaktion hinzufügen

1. Asgaros-Quellcode prüfen.
2. Adapter-Interface erweitern.
3. AsgarosAdapter implementieren.
4. Alle Test-Stubs anpassen.
5. COMPATIBILITY.md ergänzen.

### Neue Daten speichern

1. Prüfen, ob ein bestehendes Repository erweitert werden kann.
2. Install- und Upgrade-Pfad berücksichtigen.
3. Datenschutz- und Exportfragen sofort mitdenken.
4. Audit nur sparsam und ohne unnötige Duplikate erweitern.

## Test- und Prüfpfade

### Unit-Tests

Composer stellt mindestens einen Basis-Testlauf bereit:

```powershell
composer test
```

### Integrationstests gegen die lokale WordPress-Instanz

```powershell
$php = "C:\path\to\php.exe"
& $php -c "tests/php-cli.ini" tests/setup-integration-data.php
& $php -c "tests/php-cli.ini" vendor/bin/phpunit -c phpunit-integration.xml.dist
```

Wichtige Randbedingungen aus der bestehenden Testumgebung:

- Lokale Site forums unter http://forums.test/.
- Asgaros 3.4.0 als referenzierte Testversion.
- DB_HOST im Integration-Bootstrap wird für die CLI auf TCP umgebogen.
- Die globale Asgaros-Instanz muss im Testkontext explizit bereitgestellt werden.

### End-to-End-Tests

Im Ordner e2e liegt ein eigenständiges Playwright-Setup.

```powershell
Push-Location e2e
npm test
npm run test:nojs
Pop-Location
```

Abgedeckte Bereiche umfassen unter anderem:

- Mitgliederverwaltung
- Einladungen
- Invite Links
- Join Requests
- Accessibility-Smokes
- Forum-Navigation
- Dashboard-Verhalten bei verwaisten Spaces

## Sicherheits- und Accessibility-Prüfung

Bei jeder Änderung sollten mindestens diese Fragen aktiv geprüft werden:

- Kann ein Benutzer mit manipulierten IDs ein fremdes Objekt erreichen?
- Greift die serverseitige Policy weiterhin auch dann, wenn UI-Elemente direkt nachgebaut werden?
- Wird jede Ausgabe kontextgerecht escaped?
- Werden neue personenbezogene Daten minimiert, exportierbar und löschbar behandelt?
- Bleibt der Kernpfad ohne JavaScript nutzbar?
- Ist Tastaturbedienung inklusive Fokusführung und Statusmeldungen intakt?

## Besondere Architekturfallen im Bestand

- Die interne Space-ID ist nicht identisch mit der Asgaros-forum_id. Viele URLs und Controller erwarten die AFSpaces-Space-ID.
- Für Mitgliederzählung und Verwaltungslogik ist meist primary_group_id des Space belastbarer als eine Ableitung über Kategorien-Meta.
- Pending-Arbeitsgruppen dürfen nicht versehentlich wie aktive Gruppen behandelt werden.
- Asgaros-Moderationsrechte sind global; lokale Moderation daher ausschließlich über AFSpaces-Services erweitern.
- Änderungen an der Suche betreffen oft sowohl REST als auch Overlay und serverseitige Suchseite.

## Dokumentationspflege

Dokumentation ist Teil der Definition of Done, nicht ein optionaler Nachtrag. Bei jeder Codeänderung wird die betroffene Referenz im selben Arbeitsschritt gepflegt (verbindlich in `AGENTS.md`, Abschnitt 14).

### Referenz (`docs/referenz/`) — pflichtgebunden an Code

| Änderung im Code | Pflicht-Update |
| --- | --- |
| REST-Route/Parameter/Permission | [referenz/REST-API.md](referenz/REST-API.md) |
| POST-Aktion (`FrontendController`) | [referenz/FRONTEND-ACTIONS.md](referenz/FRONTEND-ACTIONS.md) |
| Hook/Filter | [referenz/HOOKS.md](referenz/HOOKS.md) |
| Adapter | [referenz/ADAPTER.md](referenz/ADAPTER.md) + COMPATIBILITY.md |
| Optionsseite/-feld | [referenz/SETTINGS-PAGES.md](referenz/SETTINGS-PAGES.md) |
| Domänenzustand/-übergang | [referenz/DOMAINMODELLE.md](referenz/DOMAINMODELLE.md) |
| Tabellenschema | [referenz/DATENBANK.md](referenz/DATENBANK.md) |
| Farbe/Icon/Layout | [referenz/DESIGN-UND-LAYOUT.md](referenz/DESIGN-UND-LAYOUT.md) |

### Überblicksdokumente (`docs/`)

Zusätzlich bei größeren Änderungen und bei Integrations- oder Sicherheitsänderungen auch die Root-Spezifikationen pflegen.

Typische Fälle:

- Neuer MVP-Teil oder neue größere Funktion: FEATURE-STATUS.md.
- Neue Klassen- oder Request-Architektur: ARCHITEKTUR.md.
- Neue Suchlogik oder neue Suchparameter: SUCHE.md und referenz/BEREICH-suche.md.
- Neue Test- oder Änderungsregeln: ERWEITERN-UND-QUALITAET.md.

Eine Änderung gilt erst als fertig, wenn Code, Tests und die betroffenen Referenz- und Überblicksdokumente konsistent sind.
