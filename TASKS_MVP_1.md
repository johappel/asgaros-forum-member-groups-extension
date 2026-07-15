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

- [ ] Adapterinterface erstellen.
- [ ] installierte Asgaros-Version erkennen.
- [ ] bestehende Foren lesen.
- [ ] zugeordnete Benutzergruppen lesen.
- [ ] Gruppenmitglieder paginiert lesen.
- [ ] Benutzer einer Gruppe hinzufügen.
- [ ] Benutzer aus einer Gruppe entfernen.
- [ ] Fehler in Domain-Ausnahmen übersetzen.
- [ ] eingesetzte interne Asgaros-APIs dokumentieren.

### M1.3 Space-Zuordnung

- [ ] Datenmodell für Space und Space Manager erstellen.
- [ ] bestehende Asgaros-Foren administrativ als Space registrierbar machen.
- [ ] primäre Zugriffsgruppe pro Space festlegen.
- [ ] Owner und Manager einem Space zuordnen.
- [ ] letzten Owner vor Selbstentfernung schützen.

### M1.4 Capabilities und Policies

- [ ] Capabilities bei Aktivierung registrieren.
- [ ] globale Administratorrechte abbilden.
- [ ] Space-spezifische Managerrechte implementieren.
- [ ] zentrale Policy für Anzeigen, Hinzufügen und Entfernen erstellen.
- [ ] Schutz gegen Bearbeitung fremder Spaces implementieren.

### M1.5 Frontend-Dashboard

- [ ] Shortcode `[afspaces_dashboard]` bereitstellen.
- [ ] Liste der verwaltbaren Räume anzeigen.
- [ ] Anzahl Mitglieder anzeigen.
- [ ] Link „Mitglieder verwalten“ anzeigen.
- [ ] leere Zustände verständlich darstellen.
- [ ] Zugriff ohne Berechtigung verständlich ablehnen.

### M1.6 Mitgliederansicht

- [ ] Mitglieder paginiert anzeigen.
- [ ] Anzeigename, Rolle im Space und Status darstellen.
- [ ] Suche und Filter bereitstellen.
- [ ] WordPress-Benutzer serverseitig suchen.
- [ ] bereits zugeordnete Personen kennzeichnen.
- [ ] Benutzer über Button oder Auswahl hinzufügen.
- [ ] Mitglied nach Bestätigung entfernen.
- [ ] Bulk-Hinzufügen optional, Bulk-Entfernen zunächst nicht erforderlich.

### M1.7 Optionale Drag-and-drop-Ansicht

- [ ] erst nach funktionierender Standardbedienung implementieren.
- [ ] gleiche Aktionen über Buttons ermöglichen.
- [ ] Tastaturbedienung für Verschieben anbieten oder Drag-and-drop als rein optional kennzeichnen.
- [ ] Statusänderungen für Screenreader ansagen.
- [ ] nach einem Drop serverseitige Bestätigung abwarten und Fehler rückgängig darstellen.

### M1.8 Audit-Log

- [ ] Hinzufügen und Entfernen protokollieren.
- [ ] Akteur, Space, Zielbenutzer, Aktion und Zeitpunkt speichern.
- [ ] keine unnötigen Profildaten duplizieren.
- [ ] Aufbewahrungsdauer konfigurierbar machen.

### M1.9 Fehler und Rückmeldungen

- [ ] Erfolgsmeldung mit betroffener Person und Raum anzeigen.
- [ ] Fehler mit konkreter nächster Handlung anzeigen.
- [ ] doppelte Zuordnung idempotent behandeln.
- [ ] Entfernen eines nicht vorhandenen Mitglieds idempotent behandeln.
- [ ] Race Conditions bei parallelen Änderungen berücksichtigen.

## Nicht enthalten

- E-Mail-Einladungen,
- Einladungsannahme,
- öffentliche Einladungslinks,
- Raumgründung durch Benutzer,
- vollständige Beitragsmoderation.

## Tests

### Unit

- [ ] Policy erlaubt Verwaltung des eigenen Spaces.
- [ ] Policy verweigert Verwaltung eines fremden Spaces.
- [ ] letzter Owner kann nicht entfernt werden.
- [ ] Adapterausnahmen werden korrekt normalisiert.

### Integration

- [ ] Hinzufügen erzeugt Asgaros-Gruppenzuordnung.
- [ ] Entfernen löscht Asgaros-Gruppenzuordnung.
- [ ] Audit-Eintrag wird erzeugt.
- [ ] Benutzerlisten sind paginiert.
- [ ] inkompatible Asgaros-Version deaktiviert Schreiboperationen.

### REST/Sicherheit

- [ ] anonymer Zugriff auf Schreibendpunkte scheitert.
- [ ] fehlende oder ungültige Nonce scheitert.
- [ ] Manager eines anderen Spaces scheitert.
- [ ] manipulierte `space_id`, `group_id` und `user_id` werden abgefangen.
- [ ] Suche gibt keine unzulässigen E-Mail-Daten preis.

### End-to-End

- [ ] Administrator registriert einen bestehenden Raum.
- [ ] Manager öffnet Dashboard und Mitgliederliste.
- [ ] Manager sucht und fügt Benutzer hinzu.
- [ ] hinzugefügter Benutzer kann das geschützte Forum erreichen.
- [ ] Manager entfernt Benutzer.
- [ ] entfernter Benutzer verliert den Zugriff.
- [ ] gesamter Kernpfad funktioniert ohne JavaScript.

### Accessibility

- [ ] alle Aktionen per Tastatur erreichbar.
- [ ] Fokus bleibt nach Hinzufügen oder Entfernen sinnvoll erhalten.
- [ ] Fehlermeldungen werden einem Eingabefeld zugeordnet.
- [ ] axe meldet keine kritischen oder ernsten Verstöße.
- [ ] Ansicht bleibt bei 200 Prozent Zoom nutzbar.

## Akzeptanzkriterien

MVP 1 ist abgeschlossen, wenn eine Raumverantwortliche ohne Backendzugriff bestehende WordPress-Benutzer sicher einem Forum hinzufügen und wieder entfernen kann und die Kernaufgabe ohne Maus ausführbar ist.
