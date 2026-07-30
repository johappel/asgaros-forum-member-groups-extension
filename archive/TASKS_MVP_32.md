# TASKS MVP 3.2 — Arbeitsgruppenmodell fuer efabiNet

## Ziel

Aus dem bestehenden Space-Modell wird ein fuer efabiNet sichtbares Arbeitsgruppenmodell. Eine Arbeitsgruppe besteht fachlich aus:

- einem Asgaros-Forum,
- einer primaeren Asgaros-Benutzergruppe,
- einer AFSpaces-Verwaltungsschicht,
- Verantwortlichen fuer Mitglieder, Einladungen und Beitrittsanfragen,
- zusaetzlichen Metadaten fuer Darstellung und Auffindbarkeit.

Asgaros bleibt weiterhin die massgebliche Quelle fuer Mitgliedschaft und Zugriffsrechte. AFSpaces erweitert dieses Modell nur um Verwaltung, Sichtbarkeit, Metadaten, Einladungen, Beitrittsanfragen und Audit.

## Begriffliche Festlegung fuer efabiNet

Technisch darf die Domain intern weiterhin `Space` heissen. Im sichtbaren Frontend fuer efabiNet gelten jedoch folgende Begriffe als Zielstandard:

- `Space` -> `Arbeitsgruppe`
- `Meine Raeume` -> `Meine Arbeitsgruppen`
- `Raeume entdecken` -> `Arbeitsgruppen entdecken`
- `Space Manager` oder `Raumverantwortliche` -> `Arbeitsgruppenverantwortliche`
- `Space Members` -> `Mitglieder`
- `Join Request` -> `Beitrittsanfrage`

Abweichungen davon muessen fachlich begruendet sein. Fuer Nutzer:innen soll das Modell als soziale Arbeitsgruppe und nicht als technischer Forumraum erscheinen.

## Technische Grundlage in diesem Branch

- [x] Ein Space verbindet bereits ein bestehendes Asgaros-Forum mit einer primaeren Asgaros-Benutzergruppe.
- [x] Mitgliedschaft wird ueber Asgaros-Gruppenzuordnungen gelesen, erstellt und entfernt.
- [x] Owner und Manager sind als eigene Verantwortlichkeitsrollen oberhalb der Mitgliedschaft vorhanden.
- [x] Einladungen, Invite-Links und Beitrittsanfragen sind als eigene Plugin-Konzepte implementiert.
- [x] Die Hub-Seite besitzt bereits die Unteransichten Mitglieder, Einladungen, Beitrittsanfragen, Meine Einladungen und Arbeitsgruppen entdecken.
- [x] REST-, Policy-, Audit- und Frontend-Grundlagen koennen fuer das Arbeitsgruppenmodell weiterverwendet werden.

## Ergebnis fuer Benutzer

Eine berechtigte Person kann:

1. Arbeitsgruppen statt technischer Raeume verstehen und auffinden,
2. sehen, wer die Arbeitsgruppenverantwortlichen einer Arbeitsgruppe sind,
3. Beitrittsanfragen stellen und deren Status nachvollziehen,
4. als Arbeitsgruppenverantwortliche Mitglieder, Einladungen und Anfragen verwalten,
5. die eigene Arbeitsgruppen-Mitgliedschaft im Profil wiederfinden,
6. erkennen, welche Rolle Arbeitsgruppenverantwortung und welche Rolle Forum-Moderation hat.

## Funktionaler Umfang

### M3.2.1 Begriffe und Informationsarchitektur

- [ ] sichtbare Frontend-Begriffe konsequent auf `Arbeitsgruppe` umstellen, wo fachlich Gruppen gemeint sind.
- [ ] technische Begriffe wie `Space` nur intern in Code, DB und Architektur belassen.
- [ ] `Meine Raeume` in eine fachlich passende Bezeichnung wie `Meine Arbeitsgruppen` ueberfuehren.
- [ ] `Raeume entdecken` in eine fachlich passende Bezeichnung wie `Arbeitsgruppen entdecken` ueberfuehren.
- [ ] `Raumverantwortliche` konsistent als `Arbeitsgruppenverantwortliche` anzeigen.
- [ ] Breadcrumbs, Buttons, Hinweise und Fehlermeldungen ohne technische Begriffe formulieren.

### M3.2.2 Arbeitsgruppen-Metadaten

- [ ] Arbeitsgruppenbeschreibung als eigenes Metadatum speichern.
- [ ] Farbe und optionales Symbol/Icon pro Arbeitsgruppe speichern.
- [ ] Ansprechperson oder Kontakttext der Arbeitsgruppenverantwortlichen pro Arbeitsgruppe pflegbar machen.
- [ ] Sichtbarkeits- und Beitrittslogik als Metadaten explizit machen.
- [ ] Flag `Beitrittsanfragen erlaubt` pro Arbeitsgruppe verwalten.
- [ ] fehlende Metadaten fuer bestehende Spaces mit sicheren Standardwerten behandeln.

### M3.2.3 Arbeitsgruppen entdecken und Uebersicht

- [ ] Discover-Ansicht zu einer echten Arbeitsgruppenuebersicht ausbauen.
- [ ] auch nicht beitretbare oder geschlossene Arbeitsgruppen sichtbar machen, sofern die Policy das erlaubt.
- [ ] Schlosssymbol, Beschreibung, Farbe, Symbol und Arbeitsgruppenverantwortliche in der Liste anzeigen.
- [ ] Status fuer den aktuellen Benutzer anzeigen: Mitglied, eingeladen, Anfrage offen, Anfrage abgelehnt, keine Zugehoerigkeit.
- [ ] Button `Beitritt anfragen` nur dort anzeigen, wo die Policy es erlaubt.
- [ ] Such- und Filterlogik fuer groessere Arbeitsgruppenlisten vorsehen.

### M3.2.4 Verantwortlichkeit und Moderation trennen

- [ ] Arbeitsgruppenverantwortung und Asgaros-Forum-Moderation als getrennte fachliche Rollen dokumentieren.
- [ ] keine automatische globale Forum-Moderation allein aus der Space-Manager-Rolle ableiten.
- [ ] optional konfigurierbare Verknuepfung dokumentieren, falls efabiNet beide Rollen gemeinsam vergeben will.
- [ ] im Frontend klar erklaeren, welche Aktionen Arbeitsgruppenverantwortliche ausfuehren duerfen.
- [ ] im Frontend klar erklaeren, welche Moderationsaktionen weiterhin ausserhalb von AFSpaces liegen.

### M3.2.5 Benachrichtigungen und Eskalation

- [ ] Beitrittsanfragen an die Arbeitsgruppenverantwortlichen benachrichtigen.
- [ ] optional zusaetzliche Benachrichtigung an eine zentrale efabiNet-Adresse konfigurierbar machen.
- [ ] Benachrichtigungen fuer Genehmigung und Ablehnung inhaltlich an den Arbeitsgruppenbegriff anpassen.
- [ ] Drosselung und Idempotenz fuer Benachrichtigungen beibehalten.
- [ ] Audit-Ereignisse fuer Benachrichtigungsversand und Entscheidungspfad nachvollziehbar halten.

### M3.2.6 Profilintegration

- [ ] Mitgliedschaften einer Person in ihrem efabiNet-Profil anzeigen.
- [ ] Rollen als Arbeitsgruppenverantwortliche im Profil optional sichtbar machen.
- [ ] nur Arbeitsgruppen anzeigen, die fuer den Profilbetrachter sichtbar sein duerfen.
- [ ] Direktlink vom Profil in die jeweilige Arbeitsgruppe oder ins zugehoerige Forum anbieten.
- [ ] leere und datenschutzsensible Zustaende verstaendlich behandeln.

### M3.2.7 ACF- und Themenintegration

- [ ] bestehende ACF-Taxonomie `Themen` als optionales Arbeitsgruppen-Metadatum anbinden.
- [ ] Zuordnung einer oder mehrerer Themen zu einer Arbeitsgruppe speichern.
- [ ] Discover-Ansicht nach Themen filterbar machen.
- [ ] Themenzuordnung validieren, damit nur erlaubte Taxonomieeintraege gespeichert werden.
- [ ] Darstellung der Themen in Uebersicht und Profil fachlich verstaendlich machen.

### M3.2.8 Migration und Kompatibilitaet

- [ ] bestehende registrierte Spaces ohne Datenverlust weiterverwendbar halten.
- [ ] fehlende Metadaten fuer Bestands-Spaces beim Lesen robust abfangen.
- [ ] bestehende Einladungen und Beitrittsanfragen ohne Migrationsbruch weiter funktionieren lassen.
- [ ] keine zweite Quelle fuer Mitgliedschaft oder Zugriffsrechte einfuehren.
- [ ] bestehende REST- und Frontend-URLs stabil halten oder sauber umleiten.

### M3.2.9 Arbeitsgruppenverwaltung im Frontend

- [ ] Arbeitsgruppen-Metadaten fuer Arbeitsgruppenverantwortliche im Frontend bearbeitbar machen.
- [ ] sichtbare Arbeitsgruppenverantwortliche im Frontend anzeigen.
- [ ] Arbeitsgruppenbeschreibung in den Verwaltungs- und Uebersichtsansichten konsistent anzeigen.
- [ ] klare Hinweise geben, wenn eine Arbeitsgruppe nur sichtbar, aber nicht beitretbar ist.
- [ ] optionale spaetere Ergaenzung fuer Farb-/Icon-Auswahl in die bestehende Look-and-Feel-Strategie einordnen.

## Nicht enthalten

- vollstaendige Asgaros-Beitragsmoderation innerhalb von AFSpaces,
- ein zweites, konkurrierendes Rollen- oder Rechtesystem neben Asgaros,
- automatische Vergabe globaler Moderationsrechte an alle Arbeitsgruppenverantwortlichen,
- beliebige freie Metadaten ohne Policy und Validierung,
- Ersetzung des bestehenden Space-Kerns durch ein separates neues Plugin.

## Tests

### Unit

- [ ] Terminologie-Resolver fuer sichtbare Begriffe.
- [ ] Validierung von Arbeitsgruppen-Metadaten.
- [ ] Policy fuer Sichtbarkeit und Beitrittsanfragen.
- [ ] Trennung von Arbeitsgruppenverantwortung und Forum-Moderation.
- [ ] Profilsichtbarkeit fuer Arbeitsgruppenlisten.

### Integration

- [ ] Metadaten werden gespeichert und korrekt geladen.
- [ ] Discover-Ansicht zeigt nur zulaessige Arbeitsgruppen.
- [ ] Profilansicht zeigt die korrekten Mitgliedschaften und Rollen als Arbeitsgruppenverantwortliche oder Mitglied.
- [ ] Benachrichtigungen an Arbeitsgruppenverantwortliche und optionale zentrale Adresse greifen korrekt.
- [ ] bestehende Spaces funktionieren ohne Metadatenmigration weiter.

### REST/Sicherheit

- [ ] unberechtigte Benutzer koennen Arbeitsgruppen-Metadaten nicht aendern.
- [ ] nicht sichtbare Arbeitsgruppen leaken keine unzulaessigen Informationen.
- [ ] Themen- und Metadatenfelder werden serverseitig validiert.
- [ ] Join-Request- und Einladungsbenachrichtigungen koennen nicht durch manipulierte Requests umgeleitet werden.
- [ ] Profilendpunkte geben keine verborgenen Mitgliedschaften unberechtigt preis.

### End-to-End

- [ ] Benutzer entdeckt sichtbare, geschlossene Arbeitsgruppen im Frontend.
- [ ] Benutzer erkennt Status, Beschreibung, Arbeitsgruppenverantwortliche und Beitrittsoption.
- [ ] Benutzer stellt eine Beitrittsanfrage fuer eine Arbeitsgruppe.
- [ ] Arbeitsgruppenverantwortliche sehen und bearbeiten die Anfrage im Arbeitsgruppenkontext.
- [ ] Profil zeigt die Mitgliedschaft nach Genehmigung.
- [ ] Arbeitsgruppenverantwortliche verwalten Metadaten im Frontend ohne Backendwechsel.

### Accessibility

- [ ] Arbeitsgruppenlisten sind semantisch und mit Tastatur voll bedienbar.
- [ ] Farbe oder Symbol sind nie die einzige Bedeutungsvermittlung.
- [ ] Status einer Beitrittsanfrage ist fuer Screenreader klar erkennbar.
- [ ] Profil- und Discover-Ansichten bleiben bei 200 Prozent Zoom nutzbar.
- [ ] Fehlermeldungen und Hinweise zu Metadatenfeldern sind verstaendlich zugeordnet.

## Akzeptanzkriterien

MVP 3.2 ist abgeschlossen, wenn AFSpaces fuer Nutzer:innen sichtbar als Arbeitsgruppenmodell auftritt, Asgaros weiterhin die massgebliche Quelle fuer Mitgliedschaft und Zugriff bleibt, Arbeitsgruppenverantwortung und Forum-Moderation fachlich sauber getrennt sind, Arbeitsgruppen im Frontend entdeckt und beantragt werden koennen, relevante Metadaten und Profilansichten vorhanden sind und die neuen Flows durch Unit-, Integration-, REST-, E2E- und Accessibility-Tests abgesichert sind.