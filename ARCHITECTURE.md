# ARCHITECTURE.md

## Schichten

1. **Domain:** Space, Manager, Invitation, Invite Link, Policies und Statusübergänge.
2. **Application:** Use Cases wie Mitglied hinzufügen, Einladung annehmen oder Raum erstellen.
3. **Adapters:** Asgaros, WordPress-Benutzer, E-Mail, Datenbank und Audit.
4. **Interface:** REST, serverseitige Formulare, Shortcodes, Templates und optionale JavaScript-Komponenten.

## Abhängigkeitsregel

Domain und Application dürfen nicht direkt von Asgaros-internen Klassen abhängen. Nur der Asgaros-Adapter kennt diese Implementierungsdetails.

## Datenhaltung

Asgaros bleibt maßgeblich für Forum, Benutzergruppe und Gruppenzuordnung. Eigene Tabellen speichern nur Space-Metadaten, Manager, Einladungen, Invite Links und Audit-Ereignisse.

## Transaktionsähnliche Operationen

WordPress und Asgaros bieten möglicherweise keine durchgehende Transaktion über alle Operationen. Mehrschrittige Use Cases benötigen daher:

- definierte Reihenfolge,
- idempotente Schritte,
- Kompensationsaktionen,
- Erkennung unvollständiger Zustände,
- Reparaturwerkzeug für Administratoren.

## Frontend

Serverseitig gerenderte Formulare sind die Basis. REST und JavaScript verbessern die Interaktion. Kein Kernprozess darf ausschließlich über JavaScript erreichbar sein.
