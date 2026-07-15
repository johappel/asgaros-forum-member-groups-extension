# TASKS MVP 2 — Persönliche Einladungen

## Ziel

Raumverantwortliche laden bestehende WordPress-Benutzer zu einem Space ein. Die Mitgliedschaft entsteht erst nach Annahme.

## Ergebnis für Benutzer

Eine berechtigte Person kann eine Einladung senden, deren Status verfolgen und sie widerrufen. Eingeladene Personen können im Frontend annehmen oder ablehnen.

## Funktionaler Umfang

### M2.1 Einladungsmodell

- [ ] Invitation-Tabelle und Migration erstellen.
- [ ] Zustände `pending`, `accepted`, `declined`, `revoked`, `expired` implementieren.
- [ ] Ablaufdatum unterstützen.
- [ ] doppelte offene Einladung verhindern oder zusammenführen.
- [ ] Zustandswechsel als explizite Domain-Operationen implementieren.

### M2.2 Einladung erstellen

- [ ] vorhandenen WordPress-Benutzer auswählen.
- [ ] persönliche Einladung mit optionaler Nachricht erstellen.
- [ ] Berechtigung für genau diesen Space prüfen.
- [ ] Einladung nicht automatisch als Mitgliedschaft behandeln.
- [ ] bereits bestehende Mitglieder nicht erneut einladen.

### M2.3 Benachrichtigungen

- [ ] WordPress-E-Mail über `wp_mail()` senden.
- [ ] E-Mail-Inhalt filterbar und übersetzbar machen.
- [ ] Link zur sicheren Annahmeseite integrieren.
- [ ] optional interne WordPress-Benachrichtigung vorbereiten.
- [ ] E-Mail-Fehler sichtbar und wiederholbar behandeln.
- [ ] Versand drosseln.

### M2.4 Frontend für eingeladene Personen

- [ ] Seite „Meine Forum-Einladungen“ bereitstellen.
- [ ] Absender, Space, Ablaufdatum und Nachricht anzeigen.
- [ ] „Annehmen“ und „Ablehnen“ anbieten.
- [ ] vor Annahme verständlich erklären, welche Mitgliedschaft entsteht.
- [ ] Annahme idempotent verarbeiten.
- [ ] nach Annahme zur Forumsseite führen.

### M2.5 Einladungsverwaltung

- [ ] offene Einladungen pro Space anzeigen.
- [ ] Status und Ablaufdatum anzeigen.
- [ ] Einladung widerrufen.
- [ ] E-Mail erneut senden, ohne neue Einladung zu erzeugen.
- [ ] abgelaufene Einladungen kennzeichnen.
- [ ] Filter nach Status anbieten.

### M2.6 Mitgliedschaft bei Annahme

- [ ] über den Adapter zur primären Asgaros-Gruppe hinzufügen.
- [ ] Einladung und Gruppenzuordnung möglichst atomar behandeln.
- [ ] bei Adapterfehler keine angenommene Einladung ohne Mitgliedschaft hinterlassen.
- [ ] Audit-Einträge für Einladung, Annahme, Ablehnung und Widerruf erzeugen.

### M2.7 Datenschutz

- [ ] Einladungen nur beteiligten Personen und berechtigten Managern anzeigen.
- [ ] optionale Nachricht bereinigen und escapen.
- [ ] keine Einladung an gesperrte oder ausgeschlossene Benutzer senden.
- [ ] Lösch- und Exportintegration für Einladungsdaten ergänzen.

## Nicht enthalten

- Einladungen an noch nicht registrierte E-Mail-Adressen,
- mehrfach verwendbare Links,
- offene Registrierung,
- automatische Raumgründung.

## Tests

### Unit

- [ ] erlaubte und verbotene Zustandsübergänge.
- [ ] Ablaufberechnung.
- [ ] Duplikaterkennung.
- [ ] Policy für Einladen und Widerrufen.

### Integration

- [ ] Einladung wird gespeichert und E-Mail angestoßen.
- [ ] Annahme erzeugt Gruppenzuordnung.
- [ ] Ablehnung erzeugt keine Gruppenzuordnung.
- [ ] Widerruf verhindert spätere Annahme.
- [ ] Ablauf verhindert spätere Annahme.
- [ ] erneuter Versand verändert den Token nicht ungeprüft.

### REST/Sicherheit

- [ ] fremde Benutzer können Einladung nicht annehmen.
- [ ] Manager eines anderen Spaces kann Einladung nicht sehen oder widerrufen.
- [ ] manipulierte Statuswerte werden abgelehnt.
- [ ] Einladungsendpunkte sind gegen CSRF geschützt.
- [ ] Versanddrosselung greift.

### End-to-End

- [ ] Manager lädt vorhandenen Benutzer ein.
- [ ] eingeladener Benutzer sieht Einladung.
- [ ] Benutzer nimmt an und erhält Zugriff.
- [ ] Benutzer lehnt eine zweite Einladung ab.
- [ ] Manager widerruft eine Einladung.
- [ ] abgelaufene Einladung ist nicht mehr annehmbar.

### Accessibility

- [ ] Einladungsstatus ist nicht nur farblich erkennbar.
- [ ] Annahme- und Ablehnungsdialoge besitzen korrektes Fokusmanagement.
- [ ] E-Mail ist in verständlichem Klartext nutzbar.
- [ ] Statusänderungen werden assistiven Technologien mitgeteilt.

## Akzeptanzkriterien

MVP 2 ist abgeschlossen, wenn eine Mitgliedschaft erst nach einer nachvollziehbaren, sicheren Zustimmung der eingeladenen Person entsteht und alle Beteiligten den Status der Einladung verstehen können.
