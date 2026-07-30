# TASKS MVP 3 — Sichere Einladungslinks

## Ziel

Raumverantwortliche erstellen widerrufbare Einladungslinks mit klaren Bedingungen. Ein Link kann für bestehende Benutzer und optional für eine kontrollierte Registrierung verwendet werden.

## Funktionaler Umfang

### M3.1 Linkmodell

- [x] eigenes Invite-Link-Modell erstellen.
- [x] kryptografisch sicheren Zufallstoken erzeugen.
- [x] nur Token-Hash speichern.
- [x] Ablaufdatum unterstützen.
- [x] maximale Nutzungszahl unterstützen.
- [x] Status `active`, `revoked`, `expired`, `exhausted` implementieren.
- [x] Ersteller und Erstellzeitpunkt speichern.

### M3.2 Link erstellen

- [x] Frontend-Formular für Linkbedingungen erstellen.
- [x] Standardwerte sicher wählen.
- [x] unbegrenzte Links nur bei expliziter administrativer Freigabe erlauben.
- [x] Link nach Erstellung genau einmal vollständig anzeigen und kopierbar machen.
- [x] keine Tokens in Audit-Logs oder allgemeinen Listen speichern.

### M3.3 Link verwenden

- [x] Token serverseitig prüfen.
- [x] abgelaufene, widerrufene und ausgeschöpfte Links verständlich ablehnen.
- [x] angemeldeten Benutzer zur Annahme auffordern.
- [x] nicht angemeldete Benutzer zur Anmeldung führen und Rücksprung erhalten.
- [x] optional Registrierung nur bei aktivierter Policy anbieten.
- [x] vorhandene Mitgliedschaft idempotent behandeln.

### M3.4 Freigabemodi

Mindestens:

- [x] automatische Aufnahme nach Annahme,
- [x] Beitrittsanfrage mit manueller Freigabe,
- [x] nur bestehende WordPress-Benutzer.

Optional:

- [x] Registrierung neuer Benutzer erlaubt,
- [ ] E-Mail-Domain-Einschränkung,
- [ ] Einladungscode zusätzlich zum Link.

### M3.5 Linkverwaltung

- [x] aktive Links mit Bedingungen anzeigen.
- [x] Nutzungszahl anzeigen.
- [x] Link widerrufen.
- [x] Ablaufdatum verkürzen.
- [x] Token niemals erneut aus dem Hash rekonstruieren.
- [x] bei verlorenem Link neuen Link erstellen statt Token anzeigen.

### M3.6 Missbrauchsschutz

- [x] Rate-Limit für Tokenprüfungen.
- [x] Rate-Limit für Annahmeversuche.
- [x] verdächtige Versuche protokollieren, ohne Token zu speichern.
- [x] optional CAPTCHA-Hook bei öffentlicher Registrierung vorsehen.
- [x] keine Information preisgeben, ob ein bestimmter Benutzer Mitglied ist.
- [x] sichere Weiterleitungen ohne Open Redirect.

### M3.7 Registrierung

Nur umsetzen, wenn zentral aktiviert:

- [x] WordPress-Registrierung respektieren.
- [x] minimale notwendige Felder verwenden.
- [x] Zustimmung zu geltenden Datenschutzinformationen einholen.
- [x] E-Mail-Verifikation beziehungsweise bestehende WordPress-Flows berücksichtigen.
- [x] nach Registrierung Linkbedingung erneut serverseitig prüfen.

## Tests

### Unit

- [x] Token-Hashing und Vergleich.
- [x] Ablauf und maximale Nutzungszahl.
- [x] Statusableitung.
- [x] Policy für Freigabemodi.

### Integration

- [x] gültiger Link erzeugt Mitgliedschaft.
- [x] Nutzungszähler wird transaktionssicher erhöht.
- [x] letzter erlaubter Gebrauch funktioniert genau einmal.
- [x] widerrufener Link wird abgelehnt.
- [x] Token erscheint nicht in gespeicherten Logs.
- [x] Beitrittsanfrage erzeugt noch keine Mitgliedschaft.

### REST/Sicherheit

- [x] erratene Tokens sind praktisch ausgeschlossen.
- [x] Timing-sicherer Vergleich wird verwendet.
- [x] Brute-Force-Drosselung greift.
- [x] Open Redirect ist ausgeschlossen.
- [x] anonyme Antworten geben keine internen Details preis.
- [x] Race Condition bei maximaler Nutzungszahl ist getestet.

### End-to-End

- [x] Manager erstellt Link mit Ablauf und Nutzungslimit.
- [x] bestehender Benutzer tritt bei.
- [x] nicht angemeldeter Benutzer meldet sich an und kehrt zurück.
- [x] ausgeschöpfter Link zeigt verständliche Meldung.
- [x] Manager widerruft Link.
- [x] optionaler Freigabeflow funktioniert.

### Accessibility

- [x] Linkbedingungen sind verständlich beschriftet.
- [x] Kopierfunktion besitzt sichtbare und angekündigte Rückmeldung.
- [x] Fehlermeldungen vermeiden rein technische Tokenbegriffe.
- [x] Registrierungs- und Annahmefluss ist vollständig per Tastatur nutzbar.

## Akzeptanzkriterien

MVP 3 ist abgeschlossen, wenn Raumverantwortliche kontrollierte Links erstellen und widerrufen können, ohne dass Token, Registrierung oder Nutzungslimits Sicherheitslücken oder unverständliche Zustände erzeugen.
