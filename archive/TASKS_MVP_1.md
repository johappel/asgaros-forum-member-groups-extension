# TASKS MVP 1 — Frontend-Mitgliederverwaltung

## Ziel

Raumverantwortliche verwalten Mitglieder bestehender Asgaros-Foren vollständig im Frontend. Es werden noch keine Einladungen versendet und keine neuen Foren erstellt.

## Ergebnis für Benutzer

Eine berechtigte Person kann:

1. ihre verwaltbaren Foren sehen,
2. die Mitglieder eines Forums aufrufen,
3. vorhandene WordPress-Benutzer suchen,
4. Benutzer direkt hinzufügen,
5. Mitglieder entfernen,
6. Änderungen und deren Ergebnis verstehen.

## Funktionaler Umfang

### M1.1 Plugin-Grundgerüst

- [x] Plugin-Header, Autoloading und Namespaces anlegen.
- [x] Aktivierungs-, Deaktivierungs- und Deinstallationsstrategie definieren.
- [x] Abhängigkeit zu Asgaros Forum prüfen.
- [x] Verständliche Admin-Mitteilung bei fehlendem oder inkompatiblem Asgaros anzeigen.
- [x] Versionskonstanten für Plugin und Datenbankschema einführen.

### M1.2 Asgaros-Adapter

- [x] Adapterinterface erstellen.
- [x] installierte Asgaros-Version erkennen.
- [x] bestehende Foren lesen.
- [x] zugeordnete Benutzergruppen lesen.
- [x] Gruppenmitglieder paginiert lesen.
- [x] Benutzer einer Gruppe hinzufügen.
- [x] Benutzer aus einer Gruppe entfernen.
- [x] Fehler in Domain-Ausnahmen übersetzen.
- [x] eingesetzte interne Asgaros-APIs dokumentieren.

### M1.3 Space-Zuordnung

- [x] Datenmodell für Space und Space Manager erstellen.
- [x] bestehende Asgaros-Foren administrativ als Space registrierbar machen.
- [x] primäre Zugriffsgruppe pro Space festlegen.
- [x] Owner und Manager einem Space zuordnen.
- [x] letzten Owner vor Selbstentfernung schützen.

### M1.4 Capabilities und Policies

- [x] Capabilities bei Aktivierung registrieren.
- [x] globale Administratorrechte abbilden.
- [x] Space-spezifische Managerrechte implementieren.
- [x] zentrale Policy für Anzeigen, Hinzufügen und Entfernen erstellen.
- [x] Schutz gegen Bearbeitung fremder Spaces implementieren.

### M1.5 Frontend-Dashboard

- [x] Shortcode `[afspaces_dashboard]` bereitstellen.
- [x] Liste der verwaltbaren Räume anzeigen.
- [x] Anzahl Mitglieder anzeigen.
- [x] Link „Mitglieder verwalten“ anzeigen.
- [x] leere Zustände verständlich darstellen.
- [x] Zugriff ohne Berechtigung verständlich ablehnen.

### M1.6 Mitgliederansicht

- [x] Mitglieder paginiert anzeigen.
- [x] Anzeigename, Rolle im Space und Status darstellen.
- [x] Suche und Filter bereitstellen.
- [x] WordPress-Benutzer serverseitig suchen.
- [x] bereits zugeordnete Personen kennzeichnen.
- [x] Benutzer über Button oder Auswahl hinzufügen.
- [x] Mitglied nach Bestätigung entfernen.
- [x] Bulk-Hinzufügen optional, Bulk-Entfernen zunächst nicht erforderlich.

### M1.7 Optionale Drag-and-drop-Ansicht

- [x] erst nach funktionierender Standardbedienung implementieren.
- [x] gleiche Aktionen über Buttons ermöglichen.
- [x] Tastaturbedienung für Verschieben anbieten oder Drag-and-drop als rein optional kennzeichnen.
- [x] Statusänderungen für Screenreader ansagen.
- [x] nach einem Drop serverseitige Bestätigung abwarten und Fehler rückgängig darstellen.

### M1.8 Audit-Log

- [x] Hinzufügen und Entfernen protokollieren.
- [x] Akteur, Space, Zielbenutzer, Aktion und Zeitpunkt speichern.
- [x] keine unnötigen Profildaten duplizieren.
- [x] Aufbewahrungsdauer konfigurierbar machen.

### M1.9 Fehler und Rückmeldungen

- [x] Erfolgsmeldung mit betroffener Person und Raum anzeigen.
- [x] Fehler mit konkreter nächster Handlung anzeigen.
- [x] doppelte Zuordnung idempotent behandeln.
- [x] Entfernen eines nicht vorhandenen Mitglieds idempotent behandeln.
- [x] Race Conditions bei parallelen Änderungen berücksichtigen.

## Nicht enthalten

- E-Mail-Einladungen,
- Einladungsannahme,
- öffentliche Einladungslinks,
- Raumgründung durch Benutzer,
- vollständige Beitragsmoderation.

## Tests

### Unit

- [x] Policy erlaubt Verwaltung des eigenen Spaces.
- [x] Policy verweigert Verwaltung eines fremden Spaces.
- [x] letzter Owner kann nicht entfernt werden.
- [x] Adapterausnahmen werden korrekt normalisiert.

### Integration

- [x] Hinzufügen erzeugt Asgaros-Gruppenzuordnung.
- [x] Entfernen löscht Asgaros-Gruppenzuordnung.
- [x] Audit-Eintrag wird erzeugt.
- [x] Benutzerlisten sind paginiert.
- [x] inkompatible Asgaros-Version deaktiviert Schreiboperationen.

### REST/Sicherheit

- [x] anonymer Zugriff auf Schreibendpunkte scheitert.
- [x] fehlende oder ungültige Nonce scheitert.
- [x] Manager eines anderen Spaces scheitert.
- [x] manipulierte `space_id`, `group_id` und `user_id` werden abgefangen.
- [x] Suche gibt keine unzulässigen E-Mail-Daten preis.

### End-to-End

- [x] Administrator registriert einen bestehenden Raum.
- [x] Manager öffnet Dashboard und Mitgliederliste.
- [x] Manager sucht und fügt Benutzer hinzu.
- [x] hinzugefügter Benutzer kann das geschützte Forum erreichen.
- [x] Manager entfernt Benutzer.
- [x] entfernter Benutzer verliert den Zugriff.
- [x] gesamter Kernpfad funktioniert ohne JavaScript.

### Accessibility

- [x] alle Aktionen per Tastatur erreichbar.
- [x] Fokus bleibt nach Hinzufügen oder Entfernen sinnvoll erhalten.
- [x] Fehlermeldungen werden einem Eingabefeld zugeordnet.
- [x] axe meldet keine kritischen oder ernsten Verstöße.
- [x] Ansicht bleibt bei 200 Prozent Zoom nutzbar.

## Akzeptanzkriterien

MVP 1 ist abgeschlossen, wenn eine Raumverantwortliche ohne Backendzugriff bestehende WordPress-Benutzer sicher einem Forum hinzufügen und wieder entfernen kann und die Kernaufgabe ohne Maus ausführbar ist.

## Implementierungsnotizen (nach 2026-07-16)

### Bugfixes und UX-Verbesserungen
- **Dashboard-Link korrigiert**: Der Link "Mitglieder verwalten" verweist nun korrekt auf die Mitgliederverwaltungsseite (`/afspaces-members/`) statt zurück zum Dashboard.
- **Rückwärtsnavigation**: Ein "← Zurück zu Meine Räume"-Link wurde zur Mitgliederverwaltungsseite hinzugefügt für bessere Navigation.
- **Orphaned Spaces filtern**: Das Dashboard filtert nun Spaces mit nicht-existierenden Forum-IDs aus (verhindert "Unbekanntes Forum"-Einträge).
- **CSS-Verbesserungen**: Back-Link mit Zugänglichkeitsfokus (Fokus-Outline, min-height 44px).

### Test-Abdeckung
- **10/10 E2E-Tests bestanden** (5 Accessibility + 4 Member Management + 1 Dashboard Orphaned Spaces Filter)
- Keine WCAG-2.1-AA-Verstöße
- Funktioniert ohne JavaScript (Keyboard-Navigation verifiziert)
- Zoom-Resilience bei 200% bestätigt
