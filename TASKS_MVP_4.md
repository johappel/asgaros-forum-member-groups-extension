# TASKS MVP 4 — Private Forenräume selbst gründen

## Ziel

Berechtigte WordPress-Benutzer können innerhalb administrativ festgelegter Grenzen im Frontend einen privaten Asgaros-Forumraum erstellen und verwalten.

## Funktionaler Umfang

### M4.1 Globale Richtlinien

- [ ] Funktion zentral aktivierbar machen.
- [ ] erlaubte WordPress-Rollen oder Capabilities festlegen.
- [ ] maximale aktive Räume pro Benutzer festlegen.
- [ ] Zielkategorie für neue Asgaros-Foren festlegen.
- [ ] erlaubte Sichtbarkeitsmodi festlegen.
- [ ] Freigabepflicht aktivierbar machen.
- [ ] Namens-, Größen- und Inhaltsgrenzen definieren.

### M4.2 Raumassistent

Mehrstufig, aber mit einer zugänglichen Ein-Seiten-Alternative:

- [ ] Name und Beschreibung.
- [ ] Sichtbarkeit.
- [ ] optionale Startmitglieder oder Einladungen.
- [ ] Zusammenfassung vor Erstellung.
- [ ] verständliche Erklärung der Verantwortlichkeit.
- [ ] Abbruch ohne Teilobjekte.

### M4.3 Erstellung

- [ ] Asgaros-Benutzergruppe erstellen.
- [ ] Asgaros-Forum in konfigurierter Kategorie erstellen.
- [ ] Gruppe dem Forum zuordnen.
- [ ] Space-Datensatz erstellen.
- [ ] Ersteller als Owner und Mitglied zuordnen.
- [ ] alle Schritte transaktionsähnlich mit Rollback behandeln.
- [ ] Teilfehler erkennen und bereinigen.

### M4.4 Freigabeprozess

Falls aktiviert:

- [ ] Space zunächst als `pending` speichern.
- [ ] Administratoren benachrichtigen.
- [ ] Freigeben oder ablehnen.
- [ ] bei Ablehnung Begründung anzeigen.
- [ ] vor Freigabe keinen ungewollten öffentlichen Zugriff erlauben.

### M4.5 Raumverwaltung

- [ ] Name und Beschreibung innerhalb der Policy ändern.
- [ ] weitere Manager bestimmen.
- [ ] Owner-Übertragung mit Bestätigung.
- [ ] Sichtbarkeit nur innerhalb erlaubter Modi ändern.
- [ ] Raum archivieren.
- [ ] Raum reaktivieren, wenn zulässig.
- [ ] Raum löschen mit klarer Warnung und definierter Aufbewahrung.

### M4.6 Lebenszyklus

Status mindestens:

- `pending`
- `active`
- `archived`
- `rejected`
- `deleted`

Aufgaben:

- [ ] Übergänge zentral validieren.
- [ ] inaktive Räume optional markieren.
- [ ] automatische Löschung niemals ohne Vorwarnung.
- [ ] Verhalten von Themen und Beiträgen bei Archivierung definieren.
- [ ] Datenexport vor endgültiger Löschung ermöglichen oder dokumentieren.

### M4.7 Quoten und Missbrauchsschutz

- [ ] Raumlimit atomar prüfen.
- [ ] Erstellungsfrequenz drosseln.
- [ ] reservierte oder missbräuchliche Namen verhindern.
- [ ] Administrator kann Erstellung sperren.
- [ ] Administrator kann Owner ersetzen.
- [ ] Meldemöglichkeit oder administrativer Eskalationsweg dokumentieren.

### M4.8 Vorlagen

Optional nach funktionierendem Kern:

- [ ] Raumvorlagen administrativ definieren.
- [ ] Standardbeschreibung und Sichtbarkeit vorgeben.
- [ ] Vorlage darf keine unerlaubten Rechte vergeben.
- [ ] Zielkategorie und Limits bleiben zentral kontrolliert.

## Tests

### Unit

- [ ] Raumlimit und Capability-Policy.
- [ ] erlaubte Statusübergänge.
- [ ] Owner-Übertragung.
- [ ] Schutz des letzten Owners.
- [ ] Sichtbarkeitspolicy.

### Integration

- [ ] Erstellung erzeugt Forum, Gruppe, Zuordnung und Space.
- [ ] Fehler bei jedem Einzelschritt löst Rollback oder Cleanup aus.
- [ ] Ersteller wird Owner und Mitglied.
- [ ] Archivierung verändert Zugriff wie spezifiziert.
- [ ] Löschung behandelt Asgaros-Inhalte entsprechend dokumentierter Policy.
- [ ] Freigabeprozess verhindert vorzeitigen Zugriff.

### REST/Sicherheit

- [ ] Benutzer ohne Capability kann keinen Raum erstellen.
- [ ] Raumlimit kann nicht durch parallele Requests umgangen werden.
- [ ] Zielkategorie kann nicht manipuliert werden.
- [ ] unerlaubte Sichtbarkeit wird abgewiesen.
- [ ] fremde Räume können nicht geändert oder gelöscht werden.

### End-to-End

- [ ] berechtigter Benutzer erstellt privaten Raum.
- [ ] Benutzer versteht Zusammenfassung und Folgen.
- [ ] Raum erscheint im Dashboard.
- [ ] Owner lädt Mitglied ein.
- [ ] Owner überträgt Verantwortung.
- [ ] Raum wird archiviert und optional reaktiviert.
- [ ] Freigabeflow funktioniert, wenn aktiviert.

### Accessibility

- [ ] Assistent besitzt verständliche Überschriftenstruktur.
- [ ] Schrittstatus wird nicht nur visuell vermittelt.
- [ ] Validierungsfehler werden zusammengefasst und den Feldern zugeordnet.
- [ ] Ein-Seiten- oder No-JS-Alternative funktioniert.
- [ ] destruktive Aktionen verlangen verständliche Bestätigung.

## Akzeptanzkriterien

MVP 4 ist abgeschlossen, wenn berechtigte Benutzer einen privaten Raum ohne Backendzugriff erstellen können, während Administratoren Struktur, Limits, Freigabe und Lebenszyklus zuverlässig kontrollieren.
