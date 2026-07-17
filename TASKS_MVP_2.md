# TASKS MVP 2 — Persönliche Einladungen

## Ziel

Raumverantwortliche laden bestehende WordPress-Benutzer zu einem Space ein. Die Mitgliedschaft entsteht erst nach Annahme.

## Ergebnis für Benutzer

Eine berechtigte Person kann eine Einladung senden, deren Status verfolgen und sie widerrufen. Eingeladene Personen können im Frontend annehmen oder ablehnen.

## Funktionaler Umfang

### M2.1 Einladungsmodell

- [x] Invitation-Tabelle und Migration erstellen.
- [x] Zustände `pending`, `accepted`, `declined`, `revoked`, `expired` implementieren.
- [x] Ablaufdatum unterstützen.
- [x] doppelte offene Einladung verhindern oder zusammenführen.
- [x] Zustandswechsel als explizite Domain-Operationen implementieren.

### M2.2 Einladung erstellen

- [x] vorhandenen WordPress-Benutzer auswählen.
- [x] persönliche Einladung mit optionaler Nachricht erstellen.
- [x] Berechtigung für genau diesen Space prüfen.
- [x] Einladung nicht automatisch als Mitgliedschaft behandeln.
- [x] bereits bestehende Mitglieder nicht erneut einladen.

### M2.3 Benachrichtigungen

- [x] WordPress-E-Mail über `wp_mail()` senden.
- [x] E-Mail-Inhalt filterbar und übersetzbar machen.
- [x] Link zur sicheren Annahmeseite integrieren.
- [x] optional interne WordPress-Benachrichtigung vorbereiten.
- [x] E-Mail-Fehler sichtbar und wiederholbar behandeln.
- [x] Versand drosseln.

### M2.4 Frontend für eingeladene Personen

- [x] Seite „Meine Forum-Einladungen“ bereitstellen.
- [x] Absender, Space, Ablaufdatum und Nachricht anzeigen.
- [x] „Annehmen“ und „Ablehnen“ anbieten.
- [x] vor Annahme verständlich erklären, welche Mitgliedschaft entsteht.
- [x] Annahme idempotent verarbeiten.
- [x] nach Annahme zur Forumsseite führen.

### M2.5 Einladungsverwaltung

- [x] offene Einladungen pro Space anzeigen.
- [x] Status und Ablaufdatum anzeigen.
- [x] Einladung widerrufen.
- [x] E-Mail erneut senden, ohne neue Einladung zu erzeugen.
- [x] abgelaufene Einladungen kennzeichnen.
- [x] Filter nach Status anbieten.

### M2.6 Mitgliedschaft bei Annahme

- [x] über den Adapter zur primären Asgaros-Gruppe hinzufügen.
- [x] Einladung und Gruppenzuordnung möglichst atomar behandeln.
- [x] bei Adapterfehler keine angenommene Einladung ohne Mitgliedschaft hinterlassen.
- [x] Audit-Einträge für Einladung, Annahme, Ablehnung und Widerruf erzeugen.

### M2.7 Datenschutz

- [x] Einladungen nur beteiligten Personen und berechtigten Managern anzeigen.
- [x] optionale Nachricht bereinigen und escapen.
- [x] keine Einladung an gesperrte oder ausgeschlossene Benutzer senden.
- [x] Lösch- und Exportintegration für Einladungsdaten ergänzen.

## Nicht enthalten

- Einladungen an noch nicht registrierte E-Mail-Adressen,
- mehrfach verwendbare Links,
- offene Registrierung,
- automatische Raumgründung.

## Tests

### Unit

- [x] erlaubte und verbotene Zustandsübergänge.
- [x] Ablaufberechnung.
- [x] Duplikaterkennung.
- [x] Policy für Einladen und Widerrufen.

### Integration

- [x] Einladung wird gespeichert und E-Mail angestoßen.
- [x] Annahme erzeugt Gruppenzuordnung.
- [x] Ablehnung erzeugt keine Gruppenzuordnung.
- [x] Widerruf verhindert spätere Annahme.
- [x] Ablauf verhindert spätere Annahme.
- [x] erneuter Versand verändert den Token nicht ungeprüft.

### REST/Sicherheit

- [x] fremde Benutzer können Einladung nicht annehmen.
- [x] Manager eines anderen Spaces kann Einladung nicht sehen oder widerrufen.
- [x] manipulierte Statuswerte werden abgelehnt.
- [x] Einladungsendpunkte sind gegen CSRF geschützt.
- [x] Versanddrosselung greift.

### End-to-End

- [x] Manager lädt vorhandenen Benutzer ein.
- [x] eingeladener Benutzer sieht Einladung.
- [x] Benutzer nimmt an und erhält Zugriff.
- [x] Benutzer lehnt eine zweite Einladung ab.
- [x] Manager widerruft eine Einladung.
- [ ] abgelaufene Einladung ist nicht mehr annehmbar.

### Accessibility

- [x] Einladungsstatus ist nicht nur farblich erkennbar.
- [ ] Annahme- und Ablehnungsdialoge besitzen korrektes Fokusmanagement.
- [x] E-Mail ist in verständlichem Klartext nutzbar.
- [x] Statusänderungen werden assistiven Technologien mitgeteilt.

## Akzeptanzkriterien

MVP 2 ist abgeschlossen, wenn eine Mitgliedschaft erst nach einer nachvollziehbaren, sicheren Zustimmung der eingeladenen Person entsteht und alle Beteiligten den Status der Einladung verstehen können.
