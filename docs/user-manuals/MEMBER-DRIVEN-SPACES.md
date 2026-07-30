# Asgaros Forum Spaces — Leitfaden für Arbeitsgruppen

Willkommen! Diese Seite erklärt, wie **Arbeitsgruppen** (genannt „Spaces") das Asgaros-Forum erweitern – und wie du sie verwaltest.

---

## 🎯 Was ist ein Space?

Ein **Space** ist ein **privater oder geschützter Forenraum** für eine kleine Gruppe von Menschen. Im Gegensatz zu öffentlichen Foren-Kategorien:

- **Nur Mitglieder** sehen die Themen und Beiträge.
- **Ein Raumverantwortlicher** oder ein Team verwaltet die Mitglieder.
- **Klare Regeln** für den Zugriff – keine Geheimnisse, keine Überraschungen.

### Beispiele für Spaces

- **Projektteam**: Dein Team arbeitet an Projekt X. Statt E-Mail oder Slack brauchst du ein stilles Forum. → Space!
- **Geschäftsführungsrunde**: Sensible Entscheidungen, nur für Vorstand. → Space!
- **Mentoring-Gruppe**: Zehn Nachwuchskräfte lernen von drei erfahrenen Mentoren. → Space!
- **Nachbarschaftshilfe**: Eine Straße, 30 Haushalte, private Organisation. → Space!

---

## 🛠️ Funktionsweise

### 1️⃣ Ein Space wird erstellt oder aktiviert

Ein WordPress-Administrator oder jemand mit Gründungsrecht erstellt einen neuen Space (oder ordnet ein bestehendes Forum dem System zu).

### 2️⃣ Mitglieder werden hinzugefügt oder eingeladen

Der **Raumverantwortliche** kann:

- ✅ Bestehende WordPress-Benutzer direkt hinzufügen,
- ✅ Persönliche Einladungen versenden,
- ✅ einen Einladungslink teilen,
- ✅ Beitrittswünsche freigeben.

### 3️⃣ Mitglieder sehen den Space im Forum

Nur Mitglieder können Themen erstellen und antworten. Nicht-Mitglieder sehen den Space gar nicht.

### 4️⃣ Raumverantwortliche moderieren

Der Raumverantwortliche kann:

- Themen verschieben oder löschen,
- Beiträge verschieben oder löschen,
- Mitglieder entfernen,
- Mitglieder befördern (zu weiteren Raumverantwortlichen),
- offene Einladungen widerrufen.

---

## 👥 Rollen und Aufgaben

### 🔑 Der Owner (Eigentümer)

- Gründer oder Initiator des Spaces.
- **Vollständige Kontrolle**: Kann alles verwalten, kann den Raum löschen.
- Kann andere zu **Managern** ernennen.
- Kann sich selbst abwählen (aber nur, wenn mindestens ein anderer Owner vorhanden ist).

**Deine Aufgaben:**
- Ziel und Regeln des Spaces definieren.
- Manager auswählen und einarbeiten.
- Große Entscheidungen treffen (z. B. Raum archivieren oder löschen).

### 👔 Der Manager (Raumverantwortlicher)

- Von Owner oder anderen Managern ernannt.
- **Täglich**: Mitglieder verwalten, Einladungen versenden, Beiträge moderieren.
- Kann **nicht** den Raum löschen oder Sichtbarkeit ändern.
- Kann nicht selbst weitere Manager ernennen (nur der Owner).

**Deine Aufgaben:**
- neue Mitglieder einladen,
- Themen und Beiträge ordnen oder entfernen,
- bei Konflikten moderieren,
- Offene Einladungen verwalten.

### 👤 Der Member (Mitglied)

- Benutzer, der zum Space hinzugefügt oder eingeladen wurde.
- **Lesen und schreiben** im Space.
- Sieht nur sein/ihre eigenen Beiträge in Moderation.

**Deine Aufgaben:**
- Am Raum teilnehmen.
- (Falls du der Raum-Manager bist: siehe oben.)

### 🤔 Eingeladene (noch nicht Mitglied)

- Hat eine Einladung erhalten, aber nicht angenommen.
- Kann die Einladung im Frontend sehen: „Meine Einladungen".
- Status: Annahme oder Ablehnung.

---

## 📋 Szenarien — Dein Weg zur Selbstverwaltung

Das System unterstützt **verschiedene Selbstverwaltungsgrade**. Dein Administrator entscheidet, welche Optionen aktiviert sind.

### Szenario A: „Gehobene Sekretärin"  
*Einfach: Admin richtet alles ein, du verwaltest nur Mitglieder.*

**Was der Admin macht:**
- erstellt den Space,
- ernennt dich zum Manager,
- verwaltet Sichtbarkeit und Archivierung.

**Was du machst:**
- ✅ Mitglieder hinzufügen (aus vordefinierten Benutzer-Listen),
- ✅ Mitglieder entfernen,
- ✅ Einladungen versenden und widerrufen,
- ✅ Beiträge moderieren (verschieben, löschen),
- ❌ Raum löschen,
- ❌ Sichtbarkeit ändern.

**Wann paßt das?**
- Es gibt einen zentralen Admin, der Vertrauen hat.
- Du brauchst flexible Mitgliederverwaltung, aber keine absoluten Kontrollrechte.

---

### Szenario B: „Selbstständiger Manager"  
*Mittel: Du gründest den Raum, bestimmst Manager, übernimmst strategische Kontrolle.*

**Was du machst:**
- ✅ Raum selbst erstellen (Name, Beschreibung, Sichtbarkeit),
- ✅ andere Manager ernennen oder entlassen,
- ✅ Mitglieder verwalten (siehe Szenario A),
- ✅ Raum archivieren (wenn du ihn später reaktivieren möchtest),
- ✅ Raum löschen,
- ❌ technische Einstellungen ändern (Admin bleibt zuständig).

**Wann paßt das?**
- Du willst einen Raum gründen, brauchst aber ein Team zur Verwaltung.
- Der Raum ist längerfristig angelegt (Projekt, Abteilung).

---

### Szenario C: „Vollständige Kontrolle"  
*Komplex: Du verwaltest alles inkl. Kategorisierung, Policies, Zugriffsgrenzen.*

**Was du machst:**
- ✅ Alles aus Szenario B,
- ✅ Zugriffstyp konfigurieren:
  - „Nur existierende WordPress-Benutzer" → Einladung nötig,
  - „Mit Registrierung" → Neue Benutzer können sich über Link anmelden,
  - „Mit Freigabe" → Beitrittswünsche müssen freigegeben werden,
- ✅ Einladungslinks mit Limits (Ablauf, Höchstzahl Nutzungen),
- ✅ Benutzerdatengruppen über API verwalten (für Integrations-Power-User).

**Wann paßt das?**
- Größerer Raum (30+ Mitglieder),
- Mehrere externe Partner, die sich selbst anmelden können,
- Komplexe Freigabeprozesse (z. B. „Geschäftsführer muß genehmigen").

---

## 📌 Konkrete Aufgaben — Schritt für Schritt

### 🎫 „Ich möchte einen neuen Space gründen"

#### Wenn dein Admin das aktiviert hat:

1. Gehe zu **„Meine Arbeitsgruppen"** → **„Neue Arbeitsgruppe"**.
2. Gib einen Namen ein: z. B. „Projektteam Widget 2025".
3. Gib eine Beschreibung ein (optional): „Hier koordinieren wir die Widget-Entwicklung."
4. Wähle die Sichtbarkeit:
   - **Privat**: Nur Mitglieder sehen den Raum im Forum.
   - **Geschützt**: Raum ist sichtbar, aber nur Mitglieder können Themen/Beiträge sehen.
5. Klick **„Erstellen"**.

**Was passiert?**
- Asgaros erstellt automatisch ein neues Forum.
- Du wirst Owner (Vollzugriff).
- Du kannst sofort Mitglieder einladen.

**Wenn Freigabepflicht aktiv ist:**
- Der Raum ist erst **„Ausstehend"** (Gelbwerbung).
- Der Admin muß ihn freigeben, bevor Mitglieder beitreten können.
- Das könnte 1–3 Tage dauern.

---

### 👥 „Ich möchte jemanden hinzufügen"

#### Option 1: Direktes Hinzufügen (schnell)

1. Öffne deinen Space.
2. Gehe zu **„Mitglieder"**.
3. Klick **„+ Mitglied hinzufügen"**.
4. Suche nach dem Benutzernamen (z. B. „max.mueller").
5. Klick **„Bestätigen"**.

**Ergebnis:** Der Benutzer ist sofort Mitglied. Er bekommt keinen Hinweis — prüfe, daß du die richtige Person erwischst!

---

#### Option 2: Persönliche Einladung (höflich)

1. Öffne deinen Space.
2. Gehe zu **„Einladungen"**.
3. Klick **„+ Einladung versenden"**.
4. Suche den Benutzer.
5. Schreib optional eine Nachricht: „Willkommen im Projektteam, wir freuen uns auf dich!"
6. Setze ein Ablaufdatum (z. B. 14 Tage).
7. Klick **„Versenden"**.

**Ergebnis:**
- Die Person bekommt eine E-Mail.
- Im Frontend sieht sie unter „Meine Einladungen" den Raum.
- Sie kann **Annehmen** oder **Ablehnen**.
- Nur nach Annahme wird sie Mitglied.

---

#### Option 3: Einladungslink (für viele oder Externe)

1. Öffne deinen Space.
2. Gehe zu **„Einladungslinks"**.
3. Klick **„+ Neuer Link"**.
4. Stell die Bedingungen:
   - **Ablaufdatum**: z. B. 30 Tage (oder unbegrenzt, wenn Admin erlaubt).
   - **Max. Nutzungen**: z. B. 50 (oder unbegrenzt).
   - **Typ**:
     - „Direkte Aufnahme" → Link reicht, sofort Mitglied.
     - „Mit Freigabe" → Person muß beitreten, du mußt freigeben.
     - „Mit Registrierung" → Neue WordPress-Benutzer können sich selbst erstellen.
5. Klick **„Link erzeugen"**.
6. **Kopier den Link sofort** — danach ist er weg!
7. Teile ihn per E-Mail, Chat, QR-Code...

**Ergebnis:**
- Jeder, der den Link hat, kann beitreten (oder Beitritt anfragen).
- Nach Ablauf oder Nutzungslimit: Link funktioniert nicht mehr.

---

### ❌ „Ich möchte ein Mitglied entfernen"

1. Öffne deinen Space.
2. Gehe zu **„Mitglieder"**.
3. Suche das Mitglied.
4. Klick auf das Mitglied oder sein Zahnrad-Icon.
5. Klick **„Entfernen"**.
6. Bestätige: „Wirklich entfernen?"

**Ergebnis:**
- Das Mitglied ist sofort raus.
- Sein Zugriff auf den Raum endet sofort.
- Seine bestehenden Beiträge bleiben sichtbar (oder werden archiviert).

**Achtung:** Es gibt **kein Backup**. Klär vorher mit dem Mitglied, falls sein Zugang nötig ist.

---

### 🔄 „Ich möchte einen Beitrag in ein anderes Thema verschieben"

1. Öffne den Beitrag im Forum.
2. Klick auf **das kleine ⋮-Icon** neben dem Beitrag (oder unter „Aktionen").
3. Wähle **„Verschieben"**.
4. Wähle das Ziel-Thema aus der Dropdown-Liste.
5. Klick **„Bestätigen"**.

**Ergebnis:**
- Der Beitrag erscheint im neuen Thema.
- Die Antwort-Struktur bleibt erhalten (wenn möglich).

**Achtung:** Nur Raumverantwortliche sehen diese Option. Normale Mitglieder sehen sie nicht.

---

### 📝 „Ich möchte ein ganzes Thema löschen"

1. Öffne das Thema im Forum.
2. Klick auf das ⋮-Icon **beim Eröffnungsbeitrag** (erste Nachricht).
3. Wähle **„Thema löschen"**.
4. Bestätige: „Dieses Thema mit allen Beiträgen löschen?"

**Ergebnis:**
- Das Thema und **alle Antworten** werden gelöscht.
- Dies ist **nicht rückgängig machbar**!

**Sicherer Weg:** 
- Statt zu löschen: Thema als „Archiviert" kennzeichnen (falls euer System das unterstützt) oder in ein separates „Archiv"-Forum verschieben.

---

### 🗑️ „Ich möchte einen einzelnen Beitrag löschen"

1. Öffne den Beitrag.
2. Klick auf das ⋮-Icon neben dem Beitrag.
3. Wähle **„Beitrag löschen"**.
4. Bestätige.

**Ergebnis:**
- Der Beitrag ist weg.
- Das Thema bleibt (wenn mindestens noch ein Beitrag vorhanden).

**Hinweis:** Wenn das der **letzte Beitrag** eines Themas ist, wird das ganze Thema gelöscht.

---

### 🤝 „Ich möchte einen anderen Manager ernennen"

1. Öffne deinen Space.
2. Gehe zu **„Einstellungen"** oder **„Manager"**.
3. Suche ein Mitglied, das Manager werden soll.
4. Klick **„Zum Manager ernennen"**.
5. Bestätige.

**Ergebnis:**
- Diese Person kann jetzt auch Mitglieder verwalten, Beiträge moderieren etc.
- Sie kann aber nicht:
  - Dich als Owner entfernen,
  - den Raum löschen,
  - Sichtbarkeit ändern (wenn du das beschränkt hast).

**Rollback:** Du kannst den Manager jederzeit herabstufen: **„Manager entfernen"**.

---

### ⏸️ „Ich möchte den Raum archivieren"

(Dies funktioniert nur, wenn der Admin das aktiviert hat.)

1. Öffne deinen Space.
2. Gehe zu **„Einstellungen"**.
3. Klick **„Archivieren"**.
4. Bestätige: „Der Raum kann später reaktiviert werden."

**Ergebnis:**
- Der Raum ist sichtbar, aber **„Schreibzugriff gesperrt"**.
- Mitglieder können lesen, aber keine neuen Themen/Beiträge schreiben.
- Du kannst ihn später wieder aktivieren.

**Wann nutzen?**
- Projekt ist abgeschlossen, aber Dokumentation soll erhalten bleiben.
- Temporäre Teams (z. B. Tagungsort-Planung).

---

### 🗑️ „Ich möchte den Raum löschen"

1. Öffne deinen Space.
2. Gehe zu **„Einstellungen"**.
3. Klick **„Löschen"**.
4. Lies die Warnung sorgfältig.
5. Gib ggf. ein Confirmation-Passwort ein (je nach Konfiguration).
6. Klick **„Endgültig löschen"**.

**Ergebnis:**
- Der Raum ist **unwiederbringlich weg**.
- Alle Themen und Beiträge werden gelöscht.
- Der Zugriff aller Mitglieder endet sofort.

**Backup-Strategie:**
- Falls wichtig: Vorher eine Kopie der Beiträge machen (Export oder Screenshots).
- Nach Löschung: **Keine Wiederherstellung möglich**!

---

## 🛡️ Sicherheit und Datenschutz

### Wer sieht wen?

- **In deinem Space**: Nur Mitglieder sehen sich gegenseitig und die Mitgliederliste.
- **Außerhalb**: Nicht-Mitglieder sehen weder den Raum noch die Mitglieder noch die Beiträge.

### Einladungslinks

- Links sind lange Zufallsketten (z. B. `abc123xyz789`).
- Nur wer den Link hat, kann ihn nutzen.
- **Nach Erstellung kann der Link nicht erneut angezeigt werden** — bewahre ihn auf!
- Gültig nur für die Dauer, die du eingestellt hast.

### Berechtigungsprüfung

- Alle Berechtigungen werden **auf dem Server** geprüft.
- Selbst wenn du JavaScript manipulierst: Der Server lehnt ab, wenn du nicht berechtigt bist.
- Kein Risiko durch Manipulation des Browsers.

### Moderationslog (falls aktiv)

- Der Admin kann sehen, wer wann wen hinzugefügt, entfernt oder Beiträge gelöscht hat.
- Dies dient der Nachverfolgung, nicht der Überwachung.

---

## ❓ Häufige Fragen

### F: Kann ich einen Mitglied sein Passwort zurücksetzen?
**A:** Nein. Das macht nur der WordPress-Admin über das Backend. Du kannst das Mitglied entfernen und später erneut hinzufügen.

---

### F: Kann ein Mitglied ohne mein Wissen beitreten?
**A:** Das hängt vom Modus ab:
- **Direktes Hinzufügen**: Nein, du mußt es tun.
- **Persönliche Einladung**: Der Mitglied muß sie annehmen.
- **Einladungslink mit direkter Aufnahme**: Ja, wer den Link hat, kann sofort beitreten.
- **Mit Freigabe**: Wer den Link/Code hat, kann beitrittswünsch stellen, du gibst frei.

---

### F: Was passiert mit gelöschten Beiträgen?
**A:** Sie sind weg. Es gibt kein Papierkorb-System. Daher: Lieber archivieren als löschen!

---

### F: Kann ich einen Raum umbenennen?
**A:** Ja, das geht meist unter **Einstellungen** → **Name bearbeiten** (wenn du Owner/Manager bist).

---

### F: Kann ein Mitglied selbst einen Manager befördern?
**A:** Nein. Nur **der Owner** oder **bestehende Manager** (je nach Konfiguration) können Manager ernennen.

---

### F: Was passiert, wenn der letzte Owner den Raum verläßt?
**A:** Das System verhindert das! Der Owner kann sich nicht selbst entfernen, wenn kein anderer Owner mehr vorhanden ist. Du mußt vorher einen neuen Owner ernennen.

---

### F: Können Mitglieder ihre Einladung ändern?
**A:** Nein. Eine angenommene Einladung ist gekoppelt an die Mitgliedschaft im Asgaros-Forum. Nur Manager können die Mitgliedschaft beenden (d. h. den Mitglied entfernen).

---

### F: Wird der Admin benachrichtigt, wenn ich einen Raum erstelle?
**A:** Das hängt von der Konfiguration ab:
- **Ohne Freigabepflicht**: Nein, der Raum ist sofort aktiv.
- **Mit Freigabepflicht**: Ja, Admin prüft und gibt frei (kann Tage dauern).

---

### F: Kann ich einen Beitrag wieder herstellen, nachdem ich ihn gelöscht habe?
**A:** Nein. Gelöschte Beiträge sind unwiederbringlich weg. **Nutze lieber**:
- Die „Verschieben"-Funktion in ein Archiv-Forum,
- oder „Als gelesen kennzeichnen", um den Überblick zu bewahren.

---

### F: Kann ich mein Passwort im Forum ändern?
**A:** Nein. Du brauchst dazu das **WordPress-Dashboard** unter **Profil** → **Kontoeinstellungen** → **Neues Passwort**. Das Forum selbst verwaltet keine Passwörter.

---

## 🚀 Tipps und Best Practices

### 1. **Regeln von Anfang an festlegen**
Schreib die Raum-Beschreibung so, daß neue Mitglieder **auf den ersten Blick verstehen**, worum es geht:

> "Das Projektteam Widget 2025 koordiniert die Entwicklung und den Test des neuen Widgets. Arbeitsgruppe bis Dezember 2025. Nächstes Treffen: 15. August, 10 Uhr."

### 2. **Einladungslink vs. persönliche Einladung**
- **Link**: Schnell, für viele. Gut für: „Alle aus Abteilung X, bitte beitreten!"
- **Persönlich**: Höflicher, mit Nachricht. Gut für: „Ich möchte dich ganz speziell einladen."

### 3. **Manager wählen, bevor es wichtig wird**
Benenne Manager, solange der Raum noch ruhig läuft. Im Notfall (du fällst aus) kann jemand einspringen.

### 4. **Archivieren statt Löschen**
Faustregel: **Nie löschen, wenn die Infos noch wichtig sein könnten.** Archivieren lässt den Raum erhalten, sperrt aber Schreibzugriff.

### 5. **Ablaufdaten für Einladungslinks setzen**
- **Für offene Teams**: 30–60 Tage.
- **Für sensible Räume**: 7 Tage (und sag den Kandidaten vorher Bescheid).
- **Für Säulen-Projekte**: 180+ Tage oder unbegrenzt (mit Admin-Freigabe).

### 6. **Einmal pro Monat die Mitgliederliste prüfen**
Wer ist inaktiv? Wer ist nie gekommen? Manchmal ist es höflich zu fragen: „Brauchst du noch Zugang?"

---

## 📞 Support und Probleme

### „Mein Space ist weg!"
Wenn ein Space unerwartet verschwindet:
1. Überprüf deinen Zugriff (bist du noch Mitglied?).
2. Schreib den Admin an: Dieser kann im Backend nachschlagen, was passiert ist.

### „Ich kann nicht einladen!"
- Prüf: Hast du Manager-Rechte? (Nur Manager/Owner können einladen.)
- Prüf: Gibt es noch aktive Einladungen für diese Person? (Dupletten sind nicht möglich.)
- Prüf: Ist der Raum noch aktiv? (Archivierte Räume erlauben keine Einladungen.)

### „Der Einladungslink funktioniert nicht!"
- Link ist abgelaufen → Neuer Link nötig.
- Link ist aufgebraucht (zu oft genutzt) → Neuer Link nötig.
- Du wurdest entfernt → Frag den Manager, ob du noch Zugang brauchst.

---

## 📚 Weiterführende Ressourcen

- **Asgaros Forum Hauptseite**: [https://www.asgaros.de](https://www.asgaros.de) (je nach Installation)
- **WordPress-Dashboard-Hilfe**: Unter Admin → Hilfe.
- **Dein Admin-Kontakt**: Fragen zum System, Freigabeprozess, Richtlinien.

---

## 🎓 Zusammenfassung: Dein Weg

| Du brauchst | Schritt 1 | Schritt 2 | Schritt 3 |
|---|---|---|---|
| **Neuen Raum** | Gründungsrecht prüfen | Name + Beschreibung eingeben | Raum erstellen |
| **Mitglied hinzufügen** | Manager-Rolle haben | Mitglied suchen | Direkt hinzufügen ODER einladen |
| **Beitrag moderieren** | Im Raum sein | Beitrag öffnen | Verschieben/Löschen |
| **Raum abgeben** | Manager wählen | Ihn zum Manager machen | Dich selbst ggf. entfernen |

---

Viel Erfolg bei der Verwaltung deiner Arbeitsgruppen! 🚀
