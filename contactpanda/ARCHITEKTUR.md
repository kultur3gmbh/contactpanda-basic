# ContactPanda – modulare Architektur 0.8.40

## Pakete

`ContactPanda` ist das kostenlose, eigenständig lauffähige Haupt-Plugin. Es enthält Personen, Firmen, Adressen, Tags, Einwilligungen, Aktivitäten, Groundhogg-Kompatibilität und künftig ein WooCommerce-unabhängiges Kunden- und Präferenzportal. Die bestehende WooCommerce-Kundenansicht wird dabei zu einem optionalen Adapter des gemeinsamen Portals. Kunden-/Businessregistrierung und vCard-Austausch werden über stabile Kern-Schnittstellen ausschließlich durch Plus aktiviert. Bestellungen bleiben in WooCommerce; ContactPanda liest sie über die öffentliche CRUD-API und legt keine Bestellkopien an.

Das reduzierte Newsletter-Modul gehört dauerhaft zum kostenlosen ContactPanda. ContactPanda Plus ergänzt den erweiterten Newsletter-Editor, vollständige Formulare, Automatisierungen, Migrationen und Spezialintegrationen. Das Ende eines gebuchten Update- und Supportzeitraums deaktiviert weder bereits freigeschaltete Funktionen noch gespeicherte Inhalte oder Versandfunktionen; lediglich weitere Updates und Support stehen dann nicht mehr zur Verfügung.

`ContactPanda Plus` benötigt ContactPanda und ergänzt abgegrenzte Marketingmodule wie Automatisierungen, den vollständigen visuellen Formular-Builder, erweiterte Newsletter-Funktionen, Events, Migrationen sowie Messaging- beziehungsweise E-Mail-Gateways. Pro enthält Plus Complete kommerziell und stellt Kundenverwaltung, Support, Verträge, wiederkehrende Abonnements und Zahlungen, Portalberechtigungen und das gemeinsame fein abgestufte Rechtemodell bereit. Business und Members setzen auf Pro auf: Business ergänzt Projekte und Aufgaben, Members ergänzt Mitgliedschaften und Vereinsverwaltung. Eine Deaktivierung einer Erweiterung entfernt weder Daten noch Einstellungen.

ContactPanda stellt den einzigen MCP-Endpunkt der Produktfamilie bereit. Der Transport, die Authentifizierung und das datensparsame Audit-Protokoll sind kostenlos. Plus, Pro und Business registrieren dort ihre Werkzeuge; deren Aktionen bleiben an die jeweilige Fachlizenz und WordPress-Berechtigung gebunden.

```text
ContactPanda
├── Personen und Firmen
├── Adressen, Tags und Einwilligungen
├── Registrierung sowie unabhängiges Kunden- und Präferenzportal
├── WooCommerce-Basisintegration
├── Groundhogg-Kompatibilität und Migration
├── reduziertes Newsletter-Modul
│   ├── einfache E-Mail-Erstellung und Newsletter-Versand
│   ├── Einwilligungsprüfung und Abmeldung
│   └── Versandstatus und Sperrlisten
└── Erweiterungspunkte
    ├── ContactPanda Plus
    │   ├── erweiterter Newsletter-Editor und Kampagnen
    │   ├── Automatisierungen
    │   ├── vollständiger visueller Formular-Builder
    │   ├── Messaging- und E-Mail-Gateways
    │   └── erweiterte Integrationen
    ├── ContactPanda Business
	│   ├── Kundenverwaltung, Support Desk und Kundenportal
	│   ├── Berechtigungs- und Audit-Unterbau
	│   └── Projekte, Aufgaben, Zeiten, Budgets und Freigaben
    └── ContactPanda Members
        └── Mitglieder, Gruppen, Beiträge und Vereinsverwaltung
```

## Daten- und Modulzuständigkeit

Basic ist alleiniger Eigentümer des CRM- und Newsletter-Schemas. Plus verwendet diese bestehenden Tabellen über die öffentlichen Basic-Schnittstellen und legt keine zweite Kopie von Vorlagen, Kampagnen, Empfängern, Sperren oder Versandstatistiken an. Plus besitzt ausschließlich seine Automatisierungs-, Formular-, Zustell- und Nachrichtenstrukturen. Business besitzt Kunden-, Support-, Portal-, Berechtigungs- und Audit-Daten sowie die Projektverwaltung. Members ergänzt die Mitgliederverwaltung, ohne parallele Personendaten anzulegen.

Basic ist außerdem Eigentümer des allgemeinen Kunden- und Präferenzportals. Jeder angemeldete WordPress-Benutzer kann darauf zugreifen, unabhängig davon, wie das Konto erstellt wurde und ob WooCommerce oder ein anderes ContactPanda-Paket aktiv ist. Newsletter-, Newsletter-Voranmeldungs- und Seasons-Anmeldungen erzeugen dagegen ausschließlich einen ContactPanda-Kontakt beziehungsweise dessen Abonnements und niemals automatisch ein WordPress-Konto. Ein WordPress-Konto entsteht erst durch eine vom Benutzer ausdrücklich gewählte Kunden- oder sonstige Kontoregistrierung, beispielsweise über WooCommerce. Die öffentliche Erstellung neuer WordPress-Konten und deren Registrierungsregeln bleiben eine vom Portal getrennte Funktion. Vollständige Profildaten werden nur für den angemeldeten Benutzer angezeigt. Die Zuordnung zum ContactPanda-Kontakt erfolgt vorrangig über eine gespeicherte Benutzerverknüpfung und nur ersatzweise über eine eindeutige, normalisierte E-Mail-Adresse; mehrdeutige Treffer werden niemals automatisch zusammengeführt. Reine Newsletter- und Seasons-Empfänger erhalten über einen zweckgebundenen, widerrufbaren und zeitlich begrenzten persönlichen Link ausschließlich Zugriff auf ein reduziertes Präferenzzentrum und müssen dafür kein WordPress-Konto anlegen. Shortcode, serverseitig gerenderter WordPress-Block, WooCommerce-Einbindung und optionale Avada-, Elementor- und Divi-Elemente verwenden denselben Renderer und dieselben serverseitigen Befehle. Die Einbindungen dürfen nur Darstellung und Kontext bestimmen, aber keine eigene Validierungs-, Double-Opt-in-, Tag-, Zustimmungs- oder Speicherlogik besitzen. Die Detailplanung steht in `docs/customer-portal-plan.md`.

Wählt eine Person ausdrücklich eine Kundenregistrierung, sucht ContactPanda vor der Neuanlage nach einer bereits vorhandenen Person. Nach Bestätigung der Kontoinhaberschaft wird ein eindeutiger Treffer über die verifizierte E-Mail-Adresse mit dem neuen WordPress-/WooCommerce-Konto verknüpft und als Kunde klassifiziert, ohne Kontakt-ID, Herkunft, Tags, Einwilligungen, Abmeldungen, Seasons-Anmeldungen oder Aktivitäten zu ersetzen. Mehrere Treffer oder eine bestehende Verknüpfung zu einem anderen Benutzer werden niemals automatisch zusammengeführt. Die Kundenregistrierung erteilt keine Marketingzustimmung und hebt keine Sperre auf.

Diese Zuständigkeitsgrenze ist absichtlich nicht mit einer Datenverschiebung verbunden. Bestehende Tabellennamen, Datensatz-IDs, Groundhogg-Herkunftsverknüpfungen, Newsletter-Empfänger und Versandhistorien bleiben unverändert. Strukturänderungen werden nur als additive, wiederholbare Migrationen umgesetzt. Das Entfernen oder Umbenennen produktiv verwendeter Spalten oder Tabellen benötigt einen eigenen, zuvor geprüften Migrationsschritt mit Sicherung und Rückfallmöglichkeit.

Basic stellt über einen isolierten, rein lesenden Adapter Verfügbarkeit, Teilnehmerzahlen und Veranstaltungslisten bereit. Unterstützt werden The Events Calendar/Event Tickets und Amelia. Sind beide Systeme aktiv, muss eine globale Quelle ausgewählt werden; Ergebnisse werden niemals gemischt. Plus kann dieselben normalisierten Erweiterungspunkte für Segmente verwenden. Zusätzliche Einstellungsbereiche werden über `contactpanda_settings_tabs` und `contactpanda_render_settings_tab_{name}` registriert.

Die Personen-Detailansicht ist ebenfalls erweiterbar. Erweiterungen registrieren sichtbare Reiter über `contactpanda_person_detail_tabs` und rendern deren Inhalt über `contactpanda_render_person_detail_tab_{name}`. Basic bleibt dabei ohne Erweiterung unverändert; Pro ergänzt den Reiter „Kunde“ nur für tatsächlich erkannte Kunden, Business kann dort verknüpfte Projekte und Members Mitgliedschaften anzeigen.

## Lizenz und Newsletter

Das reduzierte Newsletter-Modul ist ohne Lizenz nutzbar. Erweiterte Newsletter-, Formular-, Automatisierungs-, Integrations- und Migrationsfunktionen werden über kanonische Plus-Berechtigungen gesteuert. Frühere Modulnamen bleiben ausschließlich als Kompatibilitäts-Aliase erhalten. Ein späterer Lizenzablauf oder das Trennen des gespeicherten Lizenzschlüssels darf eine bereits dauerhaft erworbene Funktionsberechtigung nicht zurücknehmen. Fehlgeschlagene oder nicht erreichbare Lizenzprüfungen verändern den vorhandenen Zustand während der definierten Offline-Toleranz nicht.

## E-Mail-Vorlagen

E-Mail-Vorlagen werden im ContactPanda-Datenbank-Modus in `cp_newsletter_templates` gespeichert. Im Groundhogg-Frontend-Modus bleiben dieser Bereich und der ContactPanda-Versand ausgeblendet. Inhalt und fertiges responsives HTML werden getrennt gespeichert. Der visuelle Editor besitzt eine Bausteinpalette, Drag-and-drop-Arbeitsfläche, direkte Text- und Bildwerkzeuge sowie WordPress-WYSIWYG und Mediathek. Kampagnen, Empfängerstatus und globale Sperren liegen getrennt in `cp_newsletter_campaigns`, `cp_newsletter_recipients` und `cp_newsletter_suppressions`. Der Versand erfolgt über eine gebündelte WP-Cron-Warteschlange und `wp_mail`; fehlende Zustimmung und globale Sperren werden vor Vorbereitung und erneut vor dem Versand geprüft.

Die Vorlagen enthalten automatisch einen Abmelde-Platzhalter. Personalisierungen werden erst bei Vorschau beziehungsweise später beim Versand aufgelöst. Ein Testversand verwendet den angemeldeten Administrator als Beispieldatensatz und wird ausschließlich an die ausdrücklich eingegebene Testadresse gesendet.

## Nächster Ausbau: Notizen und Aktivitäten

Die Tabelle `cp_activities` bleibt die zentrale, eigenständige Historie. Der nächste Ausbau soll Notizen, Telefonate, E-Mails und Aufgaben als unterscheidbare Aktivitätstypen ablegen. Jeder Eintrag erhält die Zuordnung zu Person und optional Firma, Bestellung oder Veranstaltung sowie Autor, Zeitstempel, Inhalt und Änderungsinformationen. Die Oberfläche wird in den bestehenden Tab „Aktivitäten“ integriert; Berechtigungen regeln Erstellen, Bearbeiten und Löschen. Für den Groundhogg-Übergang ist eine einmalige, wiederholbar protokollierte Migration vorhandener Notizen vorgesehen.

## Datenquelle und Betriebsarten

Im **Groundhogg-Frontend-Modus** bleibt Groundhogg alleinige Datenquelle für Kontakte, Firmen, Tags, Zustimmungen, Broadcasts, Funnels und Automatisierungen. ContactPanda stellt die Oberfläche bereit; der eigene Newsletter-Editor und -Versand bleiben in diesem Modus deaktiviert.

Im **ContactPanda-Datenbank-Modus** werden neue und bearbeitete Personen, Firmen, Tags, Zustimmungen und Aktivitäten ausschließlich in ContactPanda gespeichert; Groundhogg wird nicht parallel beschrieben. Mit Newsletter-Lizenz oder ContactPanda Plus übernimmt ContactPanda in diesem Modus auch E-Mail-Vorlagen, Kampagnen und Versand. WooCommerce-, WordPress-, Rechnungs- und Veranstaltungsdaten bleiben über ihre jeweiligen Systeme verknüpft.

## Gemeinsames Kundenportal und Identitäten

Basic besitzt die einzige providerneutrale Zuordnung zwischen WordPress-Benutzerkonto und ContactPanda-Person sowie den gemeinsamen Portal-Renderer. Das Portal wird als Block oder Shortcode eingebunden und funktioniert ohne WooCommerce. Eine vorhandene WooCommerce-Kontoseite ist nur ein Adapter auf denselben Profil-Renderer. Plus, Pro und Business ergänzen eigene Bereiche ausschließlich über `contactpanda_customer_portal_tabs` und reagieren über dokumentierte Portalereignisse; sie kopieren weder Profil- noch Zustimmungsdaten.

Ein WordPress-Konto entsteht nur durch eine ausdrückliche Konto- oder Kundenregistrierung. Newsletter-, Formular- oder Seasons-Anmeldungen legen allein deshalb kein Konto an. Eine bestehende Person darf einem Konto nur über eine bereits vorhandene Verknüpfung oder nach exakter, geprüfter E-Mail-Übereinstimmung zugeordnet werden; Kontakte werden dabei nicht automatisch zusammengeführt. Profiländerungen verändern niemals stillschweigend Einwilligungen oder Sperrstatus. E-Mail-Änderungen werden erst nach Bestätigung über die neue Adresse wirksam.

Das CRM-Schema verwendet die Tabellen `cp_contacts`, `cp_companies`, `cp_company_contacts`, `cp_tags`, `cp_contact_tags`, `cp_consents`, `cp_activities`, `cp_contactmeta` und `cp_companymeta`. Vor jedem Anlegen oder Aktualisieren wird der tatsächliche Tabellen- und Versionsstand geprüft. Ohne Groundhogg werden diese Tabellen bei einer frischen Aktivierung automatisch angelegt und ausgewählt. Ist Groundhogg aktiv, entstehen sie erst nach einer ausdrücklichen Einrichtung oder Migration.

Die Groundhogg-Migration liest Kontakte, Firmen, Firmenbeziehungen und Tags und protokolliert übernommene, übersprungene und fehlerhafte Datensätze. Groundhogg-IDs werden nur als Herkunftsverknüpfung in ContactPanda gespeichert. ContactPanda Plus kann zusätzlich E-Mail-Vorlagen und abgeschlossene Broadcasts samt aggregierten Versandwerten als schreibgeschützte, deduplizierte Archive übernehmen. Groundhogg wird dabei nie verändert. Importierte Originalvorlagen können nur angesehen oder in eine bearbeitbare ContactPanda-Vorlage dupliziert werden; importierte Broadcasts lassen sich weder starten noch erneut versenden.

## Quellen

- Firmen, Ansprechpartner und Firmennotizen: offizielle WordPress-Abilities von Groundhogg Companies 3.5.0
- Personen, Tags, Kontaktnotizen und Marketingstatus: offizielle Groundhogg-Contact-Abilities
- Bestellungen und Rechnungsadresse: WooCommerce CRUD-API
- Rechnungs-PDF: vorhandene German-Market-Adminaktion aus den WooCommerce-Bestellaktionen; keine eigene PDF-Erzeugung
- Rechnungsnummer: vorhandene German-Market-Bestellmetadaten mit filterbarem Kompatibilitäts-Fallback; ohne separates Rechnungsnummern-Add-on wird wie bei German Market die WooCommerce-Bestellnummer verwendet; keine Kopie
- UID: `billing_vat` (German Market) und `b2b_uid` (B2B Market) des zugeordneten WordPress-Benutzers
- Installationshinweis für die spätere Benutzerdokumentation: Die eigene Option zur Anzeige des UID-Feldes in German Market beziehungsweise B2B Market muss deaktiviert werden, damit ContactPanda das zentrale UID-Feld ohne doppelte Ausgabe darstellen kann.
- Aktivitäten: Groundhogg-Firmen- und Kontaktnotizen; keine zweite Ereignisdatenbank
- Marketingzustimmung: Groundhogg `optin_status`; Zusatzinformationen als Kontakt-Metadaten und nachvollziehbare Groundhogg-Notiz
- Telefonnummern: bestehende Groundhogg- und WooCommerce-Felder; die Trennung von Ländervorwahl und nationaler Nummer ist ausschließlich eine Darstellung der Oberfläche
- Rechnungs- und Lieferadressen: WooCommerce `WC_Customer` und dessen öffentliche CRUD-Methoden
- Bestellanzahl und Bestellsumme in der Personenliste: gemeinsame, seitenweise WooCommerce-Bestellabfrage; Zuordnung über die verknüpfte Kundennummer sowie Groundhogg-, Benutzer- und Rechnungs-E-Mail
- Doppelte Treffer werden anhand der WooCommerce-Bestell-ID zusammengeführt; stornierte, fehlgeschlagene und Checkout-Entwürfe sind ausgeschlossen, Rückerstattungen werden von der Summe abgezogen
- Veranstaltungsbuchungen und Check-ins: offizielle Attendee-Repository-Schnittstelle von Event Tickets Plus; Zuordnung zu Personen ausschließlich über normalisierte Teilnehmer-E-Mail-Adressen
- Veranstaltungs-Pop-up: strukturierte Termin- und Ortsfunktionen von The Events Calendar sowie Ticketbestand und Verkäufe aus den vorhandenen Event-Tickets-Objekten; keine Kopie
- Broadcasts: Groundhogg-Ereignisverlauf (`events`) und vorhandene Öffnungs-/Klickaktivitäten (`activity`); E-Mail-Vorschau über das zugeordnete Groundhogg-E-Mail-Objekt
- Tags: Groundhogg-Abilities zum Auflisten, Erstellen und Ändern der Kontaktzuordnung; dauerhaftes Löschen über Groundhoggs eigene Tags-Datenbankschnittstelle und deren Aufräum-Hooks
- Benutzerkonten und Rollen: WordPress-Benutzer-API; nur für Administratoren sichtbar und zusätzlich über Nonce, Rollen- und Benutzerberechtigungen geschützt

## Optionale Firmenverwaltung

Im Groundhogg-Frontend-Modus ist Groundhogg Companies ein optionales Modul. Ist die Erweiterung dort nicht aktiv, startet ContactPanda direkt mit der Personenübersicht. Firmenmenü, Firmenspalte, Firmensortierung und Firmenblock werden nicht angezeigt; entsprechende Schreibzugriffe werden serverseitig abgewiesen. Im ContactPanda-Datenbank-Modus steht die eigene Firmenverwaltung ohne Groundhogg bereit. Die Personenverwaltung mit Marketingstatus, WooCommerce-Adressen, Bestellungen, Rechnungslinks und Aktivitäten bleibt in beiden Betriebsarten aktiv.

## Optionale Veranstaltungsintegration

Ist eine unterstützte Eventquelle aktiv und ausgewählt, erscheint in der Personenliste die Zahl der eindeutig zugeordneten Buchungen. Die Zuordnung erfolgt über normalisierte E-Mail-Adressen. The Events Calendar verwendet die Event-Tickets-Teilnehmerliste und Check-ins; Amelia verwendet seine lokalen Kunden-, Buchungs-, Termin- und Eventtabellen mit dem tatsächlichen WordPress-Tabellenpräfix. Die Detailansicht unterscheidet Buchungen, Teilnahmen und weitere verfügbare Buchungsstatus. Fehlt eine Quelle, bleiben Spalte und Tab ausgeblendet. Es werden keine Buchungen, Tickets, Termine oder Check-ins verändert und keine Veranstaltungsdaten dupliziert.

## Schutzregeln

Der Prototyp besitzt eng begrenzte AJAX-Schreibendpunkte für freigegebene Groundhogg-Firmen- und Kontaktfelder, WooCommerce-Kundenadressen sowie die beiden bestehenden UID-Benutzerfelder. Sie sind durch WooCommerce-Verwaltungsrecht und Nonce geschützt. Firmen- und Kontaktwerte werden ausschließlich über die offiziellen Abilities gespeichert; die UID wird synchron in `billing_vat` und `b2b_uid` geschrieben. Die Massenbearbeitung ist auf höchstens 100 ausdrücklich ausgewählte Kontakte begrenzt, verlangt einen Zustimmungsnachweis und schützt Abmeldungen, unzustellbare Adressen, Spam-, Beschwerde- und Sperrstatus vor einer Reaktivierung. Ohne Groundhogg legt ContactPanda seine CRM-Tabellen bei der Aktivierung automatisch an; mit Groundhogg geschieht dies nur nach ausdrücklicher Einrichtung oder Migration. ContactPanda verändert keine Automatisierungen und generiert keine Rechnungen neu. Historische Rechnungs- und Bestelldaten bleiben schreibgeschützt.

Bei einer Deinstallation bleiben Daten standardmäßig vollständig erhalten. Nur wenn ein Administrator die Löschoption zuvor ausdrücklich aktiviert und bestätigt hat, werden ContactPanda-eigene `cp_`-Tabellen, ContactPanda-Optionen und ContactPanda-Benutzermetadaten entfernt. Groundhogg-, WooCommerce-, German-Market-, B2B-Market-, Event- und Rechnungsdaten sind davon ausgeschlossen.

Die Deaktivierung oder Deinstallation von ContactPanda darf außerdem keine native WordPress-, WooCommerce- oder Kalenderfunktion beeinträchtigen. WordPress-Benutzer, Passwörter, Rollen und auch über eine ContactPanda-unterstützte Registrierung entstandene Kundenkonten bleiben normale WordPress-Konten. WooCommerce-Bestellungen, Adressen, Zahlungsdaten, Downloads, Produkte und Rechnungen sowie Veranstaltungen, Buchungen und Tickets verbleiben vollständig in ihren Ursprungssystemen. ContactPanda verändert deren Darstellung ausschließlich über zur Laufzeit aktive Hooks und Adapter; nach einer Deaktivierung übernehmen automatisch wieder die Standardoberflächen der jeweiligen Systeme. Optionale ContactPanda-Pakete müssen das Fehlen von Basic erkennen und ihre eigenen Funktionen ohne schwerwiegenden Fehler oder Blockade des Ursprungssystems einstellen.

Eine VIES-Prüfung findet nur nach einem ausdrücklichen Klick statt. Für das grüne Häkchen werden ausschließlich ein SHA-256-Prüfwert und der Prüfzeitpunkt gespeichert, keine dritte UID-Kopie. Bei einer Änderung der UID verfällt die Kennzeichnung automatisch.

Das manuelle Erteilen oder Widerrufen der E-Mail-Marketingzustimmung erfordert eine ausdrückliche Bestätigung, Herkunft und Datum. Der echte Groundhogg-Opt-in-Status wird geändert und der Vorgang zusätzlich als Groundhogg-Kontaktnotiz mit Bearbeiter protokolliert.

Neue Personen werden ausschließlich in der ausgewählten Datenquelle mit dem Status „Unbestätigt“ angelegt. Existiert die E-Mail-Adresse bereits, wird kein bestehender Kontakt überschrieben. Das Plugin legt dabei nicht automatisch ein WordPress- oder WooCommerce-Benutzerkonto an.

Beim dauerhaften Löschen eines Tags werden die bestehenden Groundhogg-Berechtigungen und Lösch-Hooks verwendet. Tags, die Groundhogg zur Abbildung von Opt-in- und Marketable-Status verwendet, sind in dieser Oberfläche vor dem Löschen geschützt.

WooCommerce-Adressen werden nur am verknüpften Kundenkonto gespeichert. Der Schreibendpunkt akzeptiert ausschließlich freigegebene Rechnungs- und Lieferadressfelder und verändert keine `WC_Order`-Objekte. Ohne verknüpftes WordPress-Benutzerkonto bleibt die Funktion deaktiviert.

Die Bestellvorschau liest ausschließlich das vorhandene WooCommerce-Bestellobjekt. Für das Rechnungs-PDF wird genau die Adminaktion verwendet, die German Market in der WooCommerce-Bestellliste bereitstellt. Das Plugin erzeugt weder eine neue Rechnung noch eine zweite Downloadberechtigung.

WordPress-Rollen und Kontoverknüpfungen sind nur für Administratoren sichtbar. Mehrfachrollen werden gegen die in WordPress tatsächlich bearbeitbaren Rollen geprüft; die eigene Administratorrolle und die Rolle des letzten Administrators sind vor versehentlichem Entfernen geschützt. Neue Konten erhalten ein starkes Zufallspasswort und optional die WordPress-Einladung. Ein bestehendes Konto wird nur bei identischer E-Mail-Adresse und nach ausdrücklicher Bestätigung verknüpft. Diese Vorgänge ändern keinen Groundhogg-Marketingstatus.

## Globale UI-Konventionen der Produktfamilie

Basic stellt die gemeinsame Admin-Komponente für Basic, Plus, Pro und Business bereit. Farbwähler zeigen links den aktuellen Wert und rechts die erkannten Theme- und Vorlagenfarben. Bildauswahlen öffnen per Klick die WordPress-Mediathek, akzeptieren Bilder per Drag-and-drop und zeigen die aktuelle Auswahl direkt in der Auswahlfläche.

Aktionen in Tabellen, Listen und kompakten Karten werden, sofern ihre Bedeutung durch ein bekanntes Symbol eindeutig ist, als graue quadratische Symbolschaltflächen mit Tooltip und zugänglicher Beschriftung dargestellt. Primäre Arbeitsabläufe wie Speichern, Veröffentlichen, Versenden, Importieren, Suchen, Filtern, Mehrfachaktionen und das Erstellen neuer Datensätze bleiben beschriftet. Diese Texte sind für Tragweite oder Ergebnis der Aktion notwendig und werden nicht allein durch mehrdeutige Symbole ersetzt.

Datenmigrationen besitzen einen eigenen Hauptmenüpunkt „Migration“, getrennt vom gewöhnlichen Datei-Import und -Export. Dort erscheinen nur Tabs für tatsächlich installierte Quellsysteme wie Groundhogg, FluentCRM, WooCommerce oder The Newsletter Plugin. Alle Adapter verwenden dieselbe geschützte, nicht destruktive Vorschau-, Zuordnungs- und Protokollbasis. Weitere Erweiterungen ergänzen diesen Bereich über `contactpanda_migration_sources`, `contactpanda_render_migration_source_{source}` und den kompatibel erhaltenen Hook `contactpanda_render_migration_tools`.

## Noch zu verifizieren

Der verfügbare Website-Zugang liefert Plugin-Versionen und die Companies-Abilities, aber keinen Datei- oder Datenbankzugriff. Deshalb muss auf Staging geprüft werden:

1. Im Shop aktivierte German-Market-Rechnungsstatus, Rechnungsnummern-Metadaten und die von German Market gelieferte Adminaktion.
2. Zuordnung mehrerer Firmen zu einem Kontakt und Bestellungen ohne Groundhogg-Kontakt.
3. Tatsächliche Versionen und Buchungsdaten von The Events Calendar und Event Tickets Plus auf Wunderweiber Business.

