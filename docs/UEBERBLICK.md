# Überblick

AFSpaces ist ein eigenständiges WordPress-Plugin, das Asgaros Forum um eine frontend-first Verwaltung privater, geschützter oder öffentlich sichtbarer Arbeitsgruppen erweitert. Asgaros bleibt die maßgebliche Quelle für Foren, Themen, Beiträge, Benutzergruppen und den eigentlichen Forenzugriff. AFSpaces ergänzt darauf eine Verwaltungs-, Einladungs-, Beitritts-, Such- und Moderationsschicht.

## Installation und Lifecycle

Nach der Aktivierung wird die zentrale Hub-Seite **Arbeitsgruppen** automatisch mit `[afspaces]` angelegt. Die gespeicherte Option `afspaces_hub_page_id` bleibt auch nach redaktionellen Änderungen an Titel oder Slug die primäre Referenz. Eine fremde Seite mit dem Slug `afspaces` wird nicht übernommen.

Die Deinstallation bewahrt AFSpaces-Daten standardmäßig. Nur die Einstellung `afspaces_cleanup_on_uninstall` erlaubt ein vollständiges Cleanup; Asgaros-Foren, Kategorien, Gruppen, Beiträge und Mitgliedschaften bleiben in jedem Fall erhalten.

## Aktuelles Produktbild

Im aktuellen Stand deckt das Plugin die folgenden Arbeitsbereiche bereits ab:

- Registrierung bestehender Asgaros-Foren als verwaltbare Arbeitsgruppen.
- Mitgliederverwaltung im Frontend, inklusive Suche, Hinzufügen, Entfernen, Policies und Audit-Log.
- Persönliche Einladungen für bestehende WordPress-Benutzer.
- Sichere Einladungslinks mit Token-Hashing, Limits, Ablaufdaten und optionalen Freigabemodi.
- Beitrittsanfragen für geschlossene Arbeitsgruppen.
- Forum-integrierte Hub-Navigation mit Dashboard, Profil, Entdecken, Einladungen, Beitrittsanfragen und Verwaltungsansichten.
- Eigene, arbeitsgruppenbezogene Moderation für Themen und Beiträge ohne globale Asgaros-Moderatorrechte.
- Kontrollierte Selbstgründung neuer Arbeitsgruppen inklusive Freigabeprozess, Lifecycle und Rollback bei Teilfehlern.
- Eigene Suchplattform für Forum und optionale WordPress-Inhalte, inklusive Deep-Links auf einzelne Beiträge.

## Terminologie

Intern bleibt der technische Kern weitgehend beim Begriff Space. Sichtbar im Frontend wird das Modell jedoch zunehmend als Arbeitsgruppe beschrieben.

- Space: internes Domänenmodell für eine verwaltete Gruppe.
- Arbeitsgruppe: sichtbarer Fachbegriff im Frontend.
- Owner: besitzt die weitreichendsten Rechte für eine Arbeitsgruppe.
- Manager: verwaltet Mitglieder, Einladungen und Moderation im Rahmen der Policy.
- Mitglied: besitzt Zugriff auf Forum und Inhalte, aber keine Verwaltungsrechte.
- Einladung: persönliche Einladung an einen bestehenden WordPress-Benutzer.
- Invite Link: tokenbasierter Link mit Nutzungs- und Ablaufregeln.
- Join Request: Beitrittsanfrage eines bereits angemeldeten Benutzers.

## Zentrale Benutzerpfade

### 1. Bestehendes Forum registrieren und verwalten

Ein Administrator registriert ein vorhandenes Asgaros-Forum als Space. Danach erscheint es im Dashboard verwaltbarer Arbeitsgruppen, kann Managern zugeordnet werden und wird vollständig über die Hub-Seite gepflegt.

### 2. Mitglieder direkt pflegen

Owner oder Manager öffnen die Mitgliederansicht, durchsuchen WordPress-Benutzer serverseitig und fügen Personen direkt zur primären Asgaros-Gruppe hinzu oder entfernen sie wieder. Alle Schreiboperationen laufen serverseitig über Nonce, Policy und Objektberechtigung.

### 3. Personen einladen

Einladungen können persönlich oder über Link-Flows angelegt werden. Persönliche Einladungen bleiben bis zur Annahme folgenlos. Invite Links können direkte Mitgliedschaft oder Beitrittsanfrage auslösen, abhängig von den Bedingungen des Links.

### 4. Arbeitsgruppen entdecken und Beitritt anfragen

Nicht-Mitglieder können sichtbare Arbeitsgruppen über die Discover-Ansicht sehen. Wenn die Policy es erlaubt, können sie eine Beitrittsanfrage stellen. Manager entscheiden dann über Genehmigung oder Ablehnung. Genehmigung führt zur echten Asgaros-Gruppenzuordnung.

### 5. Neue Arbeitsgruppe gründen

Berechtigte Benutzer können im Frontend eine neue Arbeitsgruppe anlegen. AFSpaces erzeugt dabei dedizierte Asgaros-Artefakte für Isolation: Kategorie, Benutzergruppe und Forum. Je nach Konfiguration wird der Raum direkt aktiv oder zunächst als pending angelegt.

### 6. Themen und Beiträge im eigenen Raum moderieren

Da Asgaros globale Moderationsrechte verwendet, vergibt AFSpaces bewusst keine globalen Moderatorrollen an Space-Verantwortliche. Stattdessen stellt das Plugin eine eigene, raumgebundene Moderationsschicht bereit, die nur Objekte des eigenen Forums bearbeiten darf.

## Sichtbare Frontend-Struktur

Die komplette Frontend-Verwaltung ist in der Hub-Seite mit dem Slug afspaces gebündelt. Die Unteransichten werden über afspaces_view geroutet. Die exakten Konstanten und Query-Parameter stehen in [referenz/INDEX.md](referenz/INDEX.md) (Abschnitt Hub-Views).

Wichtige Ansichten (afspaces_view):

- dashboard
- working-group
- working-group-settings
- members
- invitations
- join-requests
- my-invitations
- discover
- profile
- moderation
- create
- approvals
- search

Zusätzlich hängt sich AFSpaces über dokumentierte Asgaros-Hooks in das Forum ein:

- Menüeintrag zur Arbeitsgruppenverwaltung.
- Einstiegs-Panel auf Forenseiten.
- Inline-Moderationskontrollen bei Beiträgen.
- Such-Trigger für das globale Such-Overlay.

## Aktuelle Schwerpunkte und bewusste Grenzen

- Der Plugin-Kern ist funktional breit: Mitglieder, Einladungen, Links, Beitrittsanfragen, Arbeitsgruppenmodell, Selbstgründung, Moderation und Suche sind vorhanden.
- Einige Bereiche sind absichtlich noch nicht vollständig ausgebaut, obwohl dafür Architektur vorbereitet wurde. Dazu gehören vor allem offene Restpunkte aus MVP 3.2 und einzelne Folgearbeiten aus MVP 2, MVP 3.1 und MVP 4.
- Die Suche ist kein kosmetisches Add-on, sondern eine eigenständige Plattform im Plugin und sollte bei Änderungen als eigener Funktionsbereich behandelt werden.
- Alle serverseitigen Zugriffsentscheidungen sollen weiter zentral in Policies und Services liegen, nicht in Views oder JavaScript.

## Weiterlesen

- Code-Einstiegspunkte und Request-Flows: [ARCHITEKTUR.md](ARCHITEKTUR.md).
- Umsetzungsstand je MVP: [FEATURE-STATUS.md](FEATURE-STATUS.md).
- Konkrete Signaturen, Routen, Hooks, Domain-Zustände und Tabellen: [referenz/INDEX.md](referenz/INDEX.md).
