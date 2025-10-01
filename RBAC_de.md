# 🇩🇪 Rollen-Verwaltung für Administratoren (PHP-Framework)

Dieses Framework nutzt ein logisches Top-Down-Vererbungssystem. Sie definieren Rollen von der schwächsten bis zur stärksten Berechtigungsstufe. Jede neue Rolle kumuliert (sammelt) die Rechte der vorherigen Stufe an.

## 1. Die Logik: Kumulierte Rechte (Bottom-Up Creation)

Die Rolle, die von einer anderen Rolle erbt, wird automatisch mächtiger, da sie die geerbten Rechte beibehält und zusätzliche, exklusive Rechte erhält. Sie erstellen die Rollen von der Basis zur Spitze.

| Logischer Status       | Rolle         | Aktion im UI (Elternrolle wählen)          | Kumulierte Rechte |
|------------------------|---------------|--------------------------------------------|-------------------|
| Basis (0% Rechte)      | Gast          | -- Als Hauptrolle (Root) anlegen --        | Lese-Zugriff (Öffentlich) |
| Standard (20% Rechte)  | Mitglied      | Wählt: Gast                                | Lese-Zugriff (Öffentlich) + Lese-Zugriff (Privat) |
| Mittel (70% Rechte)    | Redakteur     | Wählt: Mitglied                            | Alle Rechte von Mitglied + Schreib- & Editierrechte |
| Top-Level (100% Rechte)| Administrator | Wählt: Redakteur                           | ALLE Rechte von Redakteur/Mitglied/Gast + Systemverwaltung |

## 2. Anleitung zur Rollenerstellung mit SVGs

| Schritt | Beschreibung der Aktion | Visueller Hinweis |
|---------|--------------------------|-------------------|
| 1. Basis-Rolle (Gast) | Erstellen Sie die unterste Rolle mit den wenigsten Rechten. Wählen Sie im Feld 'Elternrolle' die Option -- ROOT -- aus, da diese Rolle nichts erben soll. | <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="8" y1="14" x2="16" y2="14"/></svg> |
| 2. Nächste Rolle (Mitglied) | Erstellen Sie die Rolle Mitglied. Wählen Sie im Feld 'Elternrolle' die Rolle Gast. Zuweisung: Geben Sie der Rolle Mitglied die zusätzlichen Rechte (z.B. Kommentieren). | <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="12" x2="16" y2="12"/><polyline points="12 8 8 12 12 16 16 12 12 8"/><polyline points="8 12 12 8 16 12"/></svg> |
| 3. Die mächtigste Rolle (Administrator) | Erstellen Sie die Rolle Administrator. Wählen Sie im Feld 'Elternrolle' die Rolle Redakteur. Zuweisung: Geben Sie der Rolle Administrator nur die finalen, exklusiven Rechte (z.B. Benutzer anlegen/löschen). | <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48"><path d="M16.48 10c.36-.6.52-1.3.52-2 0-1.85-1.1-3.41-2.6-4.09l-2.4 1.14"/><path d="M7.52 10c-.36-.6-.52-1.3-.52-2 0-1.85 1.1-3.41 2.6-4.09l2.4 1.14"/><path d="M12 5L12 19"/><path d="M5 20h14"/><path d="M5 13l4.5 4.5 5-5 4.5 4.5"/><line x1="12" y1="9" x2="12" y2="15"/><line x1="9" y1="12" x2="15" y2="12"/></svg> |
| Prüfung | Weisen Sie einem Test-Benutzer die Rolle Administrator zu. Das System sollte automatisch alle Rechte (Administrator + Redakteur + Mitglied + Gast) für diesen Benutzer auflösen. | <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> |

