<?php

$legalPages = require __DIR__.'/legal.php';

return [
    'help' => 'Hilfe & Beratung',
    'information' => 'Informationen',
    'pending' => 'Service in Vorbereitung',
    'pending_detail' => 'Der Katalog kann durchsucht werden. Der Verkauf ist noch nicht geöffnet.',
    'questions' => 'Häufige Fragen',
    'entries' => [
        'cookies' => $legalPages['entries']['cookies'],
        'a-propos' => [
            'title' => 'Über HOMYA',
            'intro' => 'Alles für Ihr Zuhause.',
            'paragraphs' => ['HOMYA ist ein unabhängiger Händler für Öfen und Holzheizungen, Kühlschränke, Waschmaschinen, Küchenmaschinen und Möbel.', 'Neue Geräte in versiegelter Verpackung, Lieferung in 3 bis 7 Tagen durch unseren eigenen Transportdienst, mit 2 Jahren gesetzlicher Gewährleistung und einer Rechnung auf Ihren Namen.'],
            'pending' => false,
        ],
        'provenance' => [
            'title' => 'Katalog & häufige Fragen',
            'intro' => 'Klare Orientierung beim Erkunden von HOMYA.',
            'paragraphs' => ['Die Produktseiten zeigen die verfügbaren Fotos und technischen Informationen zum jeweiligen Modell. Einige gekennzeichnete Daten müssen noch geprüft werden. Ein angezeigter Preis bestätigt keine aktuelle Verfügbarkeit.', 'HOMYA ist ein unabhängiger Händler. Unsere Geräte sind neu und versiegelt verpackt, mit 2 Jahren gesetzlicher Gewährleistung und einer Rechnung auf Ihren Namen.'],
            'pending' => false,
        ],
        'conseiller' => [
            'title' => 'Das passende Gerät finden',
            'intro' => 'Finden Sie ein Modell für Ihr Vorhaben.',
            'paragraphs' => ['Beginnen Sie mit einem Bereich und wählen Sie Kategorie und Budget. Vergleichen Sie die auf den Produktseiten angegebenen Maße, Kapazitäten, Leistungen und Ausstattungen.', 'Prüfen Sie bei Heizgeräten auch die Installationsanforderungen mit einer qualifizierten Fachkraft.'],
            'pending' => false,
        ],
        'contact' => [
            'title' => 'Kontakt',
            'intro' => 'Fragen zu Produkt, Lieferung, Bestellung oder Rücksendung? Hier finden Sie die HOMYA-Kontaktdaten.',
            'paragraphs' => ['Nennen Sie das Anliegen und gegebenenfalls Produkt- oder Bestellreferenz. Übermitteln Sie keine Passwörter oder vollständigen Kartennummern.', 'Sie können HOMYA unter der folgenden Korrespondenzadresse schreiben. E-Mail, Geschäftstelefon und Öffnungszeiten werden nach Bestätigung ergänzt. Kein Kontaktformular oder automatischer Nachrichtenversand ist derzeit aktiv.', 'Der Sitz nimmt auch zurückgesendete Waren einschließlich sperriger Güter an. Unter Rückgabe & Widerruf stehen Erklärung und kostenloser Transport. Ein Ladenabholpunkt oder öffentliches Lager wird nicht angekündigt.'],
            'pending' => true,
        ],
        'livraison' => [
            'title' => 'Lieferung',
            'intro' => 'Kostenlose Lieferung in Frankreich, Belgien und Deutschland.',
            'paragraphs' => ['Ihre Geräte werden nach der von HOMYA erklärten Zusage innerhalb von 3–7 Tagen ab Zahlung durch unseren eigenen Transporteur geliefert.', 'Die Lieferung ist in Frankreich, Belgien und Deutschland kostenlos: angekündigte Lieferkosten 0 €.', 'Vor Verkaufsbeginn werden die Berechnung in Werk- oder Kalendertagen, Bearbeitung, Transport und Liefergebiete präzisiert. Der Versandort ist noch anzugeben.', 'Zugang, Entladung und Übergabe bei Paletten, Möbeln und sperrigen Geräten sind noch zu bestätigen. Installation, Inbetriebnahme und Altgeräterücknahme sind nicht automatisch enthalten.', 'Rücksendungen sind bei Widerruf innerhalb von 14 Tagen nach Empfang kostenlos. Die Empfangsadresse steht unter Rückgabe & Widerruf; geeigneter Transport ist mit HOMYA zu organisieren.'],
            'pending' => true,
        ],
        'retractation' => $legalPages['entries']['retractation'],
        'paiement' => [
            'title' => 'Zahlung',
            'intro' => 'Zahlungsmethoden müssen vor Verkaufsbeginn angeschlossen werden.',
            'paragraphs' => ['Das Kartenformular bleibt bis zur Einrichtung eines Zahlungsanbieters erhalten. Die Überweisungsseite nach der Bestellung ist vorbereitet; die Geschäftskontodaten fehlen noch. Überweisungsfrist und Artikelreservierung werden 72 Stunden betragen.', 'Visa, Mastercard und CB sind vorgesehene Zahlungsmittel. Die Logos bestätigen weder Akzeptanz noch einen aktiven Zahlungsdienst.', 'Vor Bestellbestätigung müssen Europreis, anwendbare Umsatzsteuer, Pflichtkosten und Gesamtbetrag sichtbar sein. Die angekündigten Lieferkosten betragen 0 € in Frankreich, Belgien und Deutschland.', 'Einzug, Bestätigung und Gutschriftfristen für Erstattungen sind mit dem Anbieter festzulegen. In dieser lokalen Version sind keine Zahlungen, Überweisungen oder Abbuchungen möglich.'],
            'pending' => true,
        ],
        'suivi' => [
            'title' => 'Bestellverfolgung',
            'intro' => 'Die Verfolgung wird nach Öffnung der Bestellungen verfügbar sein.',
            'paragraphs' => ['Es sind keine Bestellungen oder Verbindungen zu Transportdienstleistern aktiv. Diese Seite fragt keine Bestellnummer ab und simuliert keinen Lieferstatus.'],
            'pending' => true,
        ],
        'panier' => [
            'title' => 'Warenkorb',
            'intro' => 'Einkäufe sind noch nicht möglich.',
            'paragraphs' => ['Sie können Produkte und technische Daten erkunden. Bestands-, Mengen-, Steuer- und Lieferregeln müssen vor Aktivierung von Warenkorb und Bestellungen bestätigt werden.'],
            'pending' => true,
        ],
        'avis' => [
            'title' => 'Kundenbewertungen',
            'intro' => 'HOMYA-Bewertungen erscheinen, sobald sie verfügbar sind.',
            'paragraphs' => ['Bewertungen oder Verkaufshistorien anderer Geschäfte werden nicht als HOMYA-Bewertungen dargestellt. Diese Version erfasst keine Erfahrungsberichte.'],
            'pending' => true,
        ],
        'mentions-legales' => $legalPages['entries']['mentions-legales'],
        'cgv' => $legalPages['entries']['cgv'],
        'confidentialite' => $legalPages['entries']['confidentialite'],
    ],
    'faq' => [[
        'question' => 'Wie finde ich ein Produkt?',
        'answer' => 'Nutzen Sie die Suche oben auf der Seite oder die Katalogfilter: Kategorie, Marke, Zustand und Preisbereich entsprechend den verfügbaren Daten.',
    ], [
        'question' => 'Kann ich die Sprache wechseln?',
        'answer' => 'Die Auswahl FR, EN und DE behält die Produktseite oder den Katalogkontext bei. Fehlende wesentliche Übersetzungen werden angezeigt.',
    ], [
        'question' => 'Wo finde ich technische Daten?',
        'answer' => 'Öffnen Sie eine Produktseite und nutzen Sie den Link Technische Daten. Weitere Fotos sind über die Vorschaubilder zugänglich, sofern vorhanden.',
    ], [
        'question' => 'Kann ich jetzt bestellen?',
        'answer' => 'Sie können Ihren Warenkorb vorbereiten. Zahlungen sind vorübergehend nicht verfügbar.',
    ]],
];
