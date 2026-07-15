# GOAL — Asgaros Forum Spaces

## Produktziel

Asgaros Forum Spaces erweitert Asgaros Forum um eine verständliche, sichere und vollständig im WordPress-Frontend bedienbare Verwaltung privater oder geschützter Forenräume.

Menschen mit einer fachlichen Verantwortung für ein Forum sollen Mitglieder verwalten und einladen können, ohne die WordPress-Administration, Asgaros-interne Benutzergruppen oder technische Berechtigungsmodelle verstehen zu müssen.

Langfristig sollen berechtigte WordPress-Benutzer kleine private Forenräume selbst anlegen, andere Personen einladen und den Raum innerhalb klarer administrativer Grenzen eigenständig verwalten können.

## Kernversprechen

> Ein Forumraum erklärt sich selbst: Berechtigte Personen sehen, wer dazugehört, können Menschen sicher einladen oder entfernen und verstehen jederzeit, welche Wirkung eine Aktion hat.

## Zielgruppen

1. WordPress-Administratoren, die die Funktion zentral konfigurieren.
2. Forum-Moderatoren, die Beiträge moderieren.
3. Raumverantwortliche, die Mitglieder und Einladungen verwalten.
4. Bestehende WordPress-Benutzer, die zu einem Raum eingeladen werden.
5. Optional: berechtigte Benutzer, die eigene private Räume gründen.

## Leitprinzipien

### Frontend first

Alle alltäglichen Aufgaben müssen im Frontend möglich sein. Das WordPress-Backend bleibt der globalen Administration, Fehlerdiagnose und Konfiguration vorbehalten.

### Selbsterklärende Bedienung

Die Oberfläche verwendet Begriffe wie „Mitglieder“, „Einladungen“ und „Raumverantwortliche“ statt technischer Begriffe wie „Taxonomie“, „Capability“ oder „Asgaros User Group“.

Jede Aktion zeigt vor dem Ausführen:

- was geändert wird,
- für welchen Raum die Änderung gilt,
- ob die betroffene Person benachrichtigt wird,
- wie die Änderung rückgängig gemacht werden kann.

### Barrierefreiheit

Die Kernfunktionen müssen mindestens WCAG 2.2 AA anstreben.

Insbesondere:

- vollständige Tastaturbedienung,
- sichtbarer Tastaturfokus,
- semantisches HTML,
- verständliche Beschriftungen und Fehlermeldungen,
- ausreichende Kontraste,
- Statusänderungen für Screenreader,
- keine ausschließlich farbliche Bedeutungsvermittlung,
- keine Abhängigkeit von Drag-and-drop,
- vergrößerbare Darstellung bis 200 Prozent ohne Funktionsverlust.

Drag-and-drop darf nur eine zusätzliche Komfortfunktion sein. Jede Drag-and-drop-Aktion benötigt eine gleichwertige Bedienung über Buttons, Auswahlfelder oder Menüs.

### Sicherheit vor Komfort

Berechtigungen werden immer serverseitig geprüft. Verborgene Buttons oder Frontend-Zustände gelten niemals als Zugriffsschutz.

### Asgaros bleibt Quelle der Zugriffsrechte

Das Plugin führt keine konkurrierende Foren-Zugriffsverwaltung ein. Soweit technisch möglich, bleiben Asgaros-Benutzergruppen und Asgaros-Forenzuordnungen die maßgebliche Quelle für den Zugriff.

Eigene Daten werden nur für zusätzliche Konzepte gespeichert, beispielsweise:

- Raumverantwortliche,
- Einladungen,
- Einladungstokens,
- Raum-Metadaten,
- Änderungsprotokolle.

### Kontrollierte Selbstverwaltung

Selbst erstellte Räume unterliegen zentralen Regeln:

- aktivierbare Funktion,
- Höchstzahl pro Benutzer,
- vorgegebene Kategorie,
- erlaubte Sichtbarkeit,
- optionale Freigabe,
- Archivierung und Löschung,
- Missbrauchsschutz.

## Nicht-Ziele

Das Plugin soll nicht:

- Asgaros Forum forken,
- die Beitrags- und Themenverwaltung neu implementieren,
- WordPress-Benutzerrollen durch ein zweites globales Rollensystem ersetzen,
- Drag-and-drop als einzige Bedienform verwenden,
- normalen Raumverantwortlichen Zugriff auf die WordPress-Administration geben,
- unkontrolliert beliebig viele Foren und Benutzergruppen erzeugen.

## Erfolgskriterien

Das Produkt gilt als erfolgreich, wenn eine erstmals nutzende Raumverantwortliche ohne Dokumentation:

1. die Mitglieder ihres Forums findet,
2. einen vorhandenen WordPress-Benutzer einlädt,
3. eine offene Einladung erkennt und widerruft,
4. ein Mitglied entfernt,
5. die Folgen jeder Aktion versteht,
6. alle Schritte ausschließlich mit Tastatur oder Screenreader ausführen kann.

## Produktstufen

1. **MVP 1:** Frontend-Mitgliederverwaltung für bestehende Foren.
2. **MVP 2:** Persönliche Einladungen für bestehende WordPress-Benutzer.
3. **MVP 3:** Sichere Einladungslinks.
4. **MVP 4:** Kontrollierte Gründung eigener privater Forenräume.
