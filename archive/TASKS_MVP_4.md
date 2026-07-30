# TASKS MVP 4 — Private Forenräume selbst gründen

## Ziel

Berechtigte WordPress-Benutzer können innerhalb administrativ festgelegter Grenzen im Frontend einen privaten Asgaros-Forumraum erstellen und verwalten.

## Funktionaler Umfang

### M4.1 Globale Richtlinien

- [x] Funktion zentral aktivierbar machen.
- [x] erlaubte WordPress-Rollen oder Capabilities festlegen.
- [x] maximale aktive Räume pro Benutzer festlegen.
- [ ] Zielkategorie für neue Asgaros-Foren festlegen. *(Ersetzt durch dedizierte Kategorie je Raum, siehe COMPATIBILITY.md — Isolationspflicht.)*
- [x] erlaubte Sichtbarkeitsmodi festlegen.
- [x] Freigabepflicht aktivierbar machen.
- [x] Namens-, Größen- und Inhaltsgrenzen definieren.

### M4.2 Raumassistent

Mehrstufig, aber mit einer zugänglichen Ein-Seiten-Alternative:

- [x] Name und Beschreibung.
- [x] Sichtbarkeit.
- [ ] optionale Startmitglieder oder Einladungen.
- [x] Zusammenfassung vor Erstellung.
- [x] verständliche Erklärung der Verantwortlichkeit.
- [x] Abbruch ohne Teilobjekte.

### M4.3 Erstellung

- [x] Asgaros-Benutzergruppe erstellen.
- [x] Asgaros-Forum in konfigurierter Kategorie erstellen.
- [x] Gruppe dem Forum zuordnen.
- [x] Space-Datensatz erstellen.
- [x] Ersteller als Owner und Mitglied zuordnen.
- [x] alle Schritte transaktionsähnlich mit Rollback behandeln.
- [x] Teilfehler erkennen und bereinigen.

### M4.4 Freigabeprozess

Falls aktiviert:

- [x] Space zunächst als `pending` speichern.
- [ ] Administratoren benachrichtigen.
- [x] Freigeben oder ablehnen.
- [x] bei Ablehnung Begründung anzeigen.
- [x] vor Freigabe keinen ungewollten öffentlichen Zugriff erlauben.

### M4.5 Raumverwaltung

- [x] Name und Beschreibung innerhalb der Policy ändern.
- [x] weitere Manager bestimmen.
- [x] Owner-Übertragung mit Bestätigung.
- [x] Sichtbarkeit nur innerhalb erlaubter Modi ändern.
- [x] Raum archivieren.
- [x] Raum reaktivieren, wenn zulässig.
- [x] Raum löschen mit klarer Warnung und definierter Aufbewahrung.

### M4.6 Lebenszyklus

Status mindestens:

- `pending`
- `active`
- `archived`
- `rejected`
- `deleted`

Aufgaben:

- [x] Übergänge zentral validieren.
- [ ] inaktive Räume optional markieren.
- [x] automatische Löschung niemals ohne Vorwarnung.
- [x] Verhalten von Themen und Beiträgen bei Archivierung definieren.
- [x] Datenexport vor endgültiger Löschung ermöglichen oder dokumentieren.

### M4.7 Quoten und Missbrauchsschutz

- [x] Raumlimit atomar prüfen.
- [x] Erstellungsfrequenz drosseln.
- [x] reservierte oder missbräuchliche Namen verhindern.
- [x] Administrator kann Erstellung sperren.
- [x] Administrator kann Owner ersetzen.
- [x] Meldemöglichkeit oder administrativer Eskalationsweg dokumentieren.

### M4.8 Vorlagen

Optional nach funktionierendem Kern:

- [ ] Raumvorlagen administrativ definieren.
- [ ] Standardbeschreibung und Sichtbarkeit vorgeben.
- [ ] Vorlage darf keine unerlaubten Rechte vergeben.
- [ ] Zielkategorie und Limits bleiben zentral kontrolliert.

## Tests

### Unit

- [x] Raumlimit und Capability-Policy.
- [x] erlaubte Statusübergänge.
- [ ] Owner-Übertragung.
- [x] Schutz des letzten Owners.
- [x] Sichtbarkeitspolicy.

### Integration

- [x] Erstellung erzeugt Forum, Gruppe, Zuordnung und Space. *(Live gegen Asgaros 3.4.0 verifiziert.)*
- [x] Fehler bei jedem Einzelschritt löst Rollback oder Cleanup aus. *(Unit: SpaceCreationServiceTest.)*
- [x] Ersteller wird Owner und Mitglied.
- [x] Archivierung verändert Zugriff wie spezifiziert.
- [x] Löschung behandelt Asgaros-Inhalte entsprechend dokumentierter Policy.
- [x] Freigabeprozess verhindert vorzeitigen Zugriff.

### REST/Sicherheit

- [x] Benutzer ohne Capability kann keinen Raum erstellen.
- [x] Raumlimit kann nicht durch parallele Requests umgangen werden.
- [x] Zielkategorie kann nicht manipuliert werden. *(Kein clientseitiger Kategorie-Parameter; Struktur wird serverseitig erzeugt.)*
- [x] unerlaubte Sichtbarkeit wird abgewiesen.
- [x] fremde Räume können nicht geändert oder gelöscht werden.

### End-to-End

- [ ] berechtigter Benutzer erstellt privaten Raum.
- [ ] Benutzer versteht Zusammenfassung und Folgen.
- [ ] Raum erscheint im Dashboard.
- [ ] Owner lädt Mitglied ein.
- [ ] Owner überträgt Verantwortung.
- [ ] Raum wird archiviert und optional reaktiviert.
- [ ] Freigabeflow funktioniert, wenn aktiviert.

### Accessibility

- [x] Assistent besitzt verständliche Überschriftenstruktur.
- [x] Schrittstatus wird nicht nur visuell vermittelt.
- [x] Validierungsfehler werden zusammengefasst und den Feldern zugeordnet.
- [x] Ein-Seiten- oder No-JS-Alternative funktioniert.
- [x] destruktive Aktionen verlangen verständliche Bestätigung.

## Akzeptanzkriterien

MVP 4 ist abgeschlossen, wenn berechtigte Benutzer einen privaten Raum ohne Backendzugriff erstellen können, während Administratoren Struktur, Limits, Freigabe und Lebenszyklus zuverlässig kontrollieren.
