# TASKS MVP 3 — Sichere Einladungslinks

## Ziel

Raumverantwortliche erstellen widerrufbare Einladungslinks mit klaren Bedingungen. Ein Link kann für bestehende Benutzer und optional für eine kontrollierte Registrierung verwendet werden.

## Funktionaler Umfang

### M3.1 Linkmodell

- [ ] eigenes Invite-Link-Modell erstellen.
- [ ] kryptografisch sicheren Zufallstoken erzeugen.
- [ ] nur Token-Hash speichern.
- [ ] Ablaufdatum unterstützen.
- [ ] maximale Nutzungszahl unterstützen.
- [ ] Status `active`, `revoked`, `expired`, `exhausted` implementieren.
- [ ] Ersteller und Erstellzeitpunkt speichern.

### M3.2 Link erstellen

- [ ] Frontend-Formular für Linkbedingungen erstellen.
- [ ] Standardwerte sicher wählen.
- [ ] unbegrenzte Links nur bei expliziter administrativer Freigabe erlauben.
- [ ] Link nach Erstellung genau einmal vollständig anzeigen und kopierbar machen.
- [ ] keine Tokens in Audit-Logs oder allgemeinen Listen speichern.

### M3.3 Link verwenden

- [ ] Token serverseitig prüfen.
- [ ] abgelaufene, widerrufene und ausgeschöpfte Links verständlich ablehnen.
- [ ] angemeldeten Benutzer zur Annahme auffordern.
- [ ] nicht angemeldete Benutzer zur Anmeldung führen und Rücksprung erhalten.
- [ ] optional Registrierung nur bei aktivierter Policy anbieten.
- [ ] vorhandene Mitgliedschaft idempotent behandeln.

### M3.4 Freigabemodi

Mindestens:

- [ ] automatische Aufnahme nach Annahme,
- [ ] Beitrittsanfrage mit manueller Freigabe,
- [ ] nur bestehende WordPress-Benutzer.

Optional:

- [ ] Registrierung neuer Benutzer erlaubt,
- [ ] E-Mail-Domain-Einschränkung,
- [ ] Einladungscode zusätzlich zum Link.

### M3.5 Linkverwaltung

- [ ] aktive Links mit Bedingungen anzeigen.
- [ ] Nutzungszahl anzeigen.
- [ ] Link widerrufen.
- [ ] Ablaufdatum verkürzen.
- [ ] Token niemals erneut aus dem Hash rekonstruieren.
- [ ] bei verlorenem Link neuen Link erstellen statt Token anzeigen.

### M3.6 Missbrauchsschutz

- [ ] Rate-Limit für Tokenprüfungen.
- [ ] Rate-Limit für Annahmeversuche.
- [ ] verdächtige Versuche protokollieren, ohne Token zu speichern.
- [ ] optional CAPTCHA-Hook bei öffentlicher Registrierung vorsehen.
- [ ] keine Information preisgeben, ob ein bestimmter Benutzer Mitglied ist.
- [ ] sichere Weiterleitungen ohne Open Redirect.

### M3.7 Registrierung

Nur umsetzen, wenn zentral aktiviert:

- [ ] WordPress-Registrierung respektieren.
- [ ] minimale notwendige Felder verwenden.
- [ ] Zustimmung zu geltenden Datenschutzinformationen einholen.
- [ ] E-Mail-Verifikation beziehungsweise bestehende WordPress-Flows berücksichtigen.
- [ ] nach Registrierung Linkbedingung erneut serverseitig prüfen.

## Tests

### Unit

- [ ] Token-Hashing und Vergleich.
- [ ] Ablauf und maximale Nutzungszahl.
- [ ] Statusableitung.
- [ ] Policy für Freigabemodi.

### Integration

- [ ] gültiger Link erzeugt Mitgliedschaft.
- [ ] Nutzungszähler wird transaktionssicher erhöht.
- [ ] letzter erlaubter Gebrauch funktioniert genau einmal.
- [ ] widerrufener Link wird abgelehnt.
- [ ] Token erscheint nicht in gespeicherten Logs.
- [ ] Beitrittsanfrage erzeugt noch keine Mitgliedschaft.

### REST/Sicherheit

- [ ] erratene Tokens sind praktisch ausgeschlossen.
- [ ] Timing-sicherer Vergleich wird verwendet.
- [ ] Brute-Force-Drosselung greift.
- [ ] Open Redirect ist ausgeschlossen.
- [ ] anonyme Antworten geben keine internen Details preis.
- [ ] Race Condition bei maximaler Nutzungszahl ist getestet.

### End-to-End

- [ ] Manager erstellt Link mit Ablauf und Nutzungslimit.
- [ ] bestehender Benutzer tritt bei.
- [ ] nicht angemeldeter Benutzer meldet sich an und kehrt zurück.
- [ ] ausgeschöpfter Link zeigt verständliche Meldung.
- [ ] Manager widerruft Link.
- [ ] optionaler Freigabeflow funktioniert.

### Accessibility

- [ ] Linkbedingungen sind verständlich beschriftet.
- [ ] Kopierfunktion besitzt sichtbare und angekündigte Rückmeldung.
- [ ] Fehlermeldungen vermeiden rein technische Tokenbegriffe.
- [ ] Registrierungs- und Annahmefluss ist vollständig per Tastatur nutzbar.

## Akzeptanzkriterien

MVP 3 ist abgeschlossen, wenn Raumverantwortliche kontrollierte Links erstellen und widerrufen können, ohne dass Token, Registrierung oder Nutzungslimits Sicherheitslücken oder unverständliche Zustände erzeugen.
