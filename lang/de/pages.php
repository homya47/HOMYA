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
            'title' => 'Unsere Produkte',
            'intro' => 'Klare Orientierung beim Erkunden von HOMYA.',
            'paragraphs' => ['Sehen Sie sich Fotos, technische Daten und Optionen jedes Produkts an, um Ihren Kauf vorzubereiten.', 'HOMYA ist ein unabhängiger Händler. Unsere Geräte sind neu und versiegelt, mit 2 Jahren gesetzlicher Gewährleistung und einer Rechnung auf Ihren Namen.'],
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
            'paragraphs' => ['Nennen Sie Ihr Anliegen und gegebenenfalls die Produkt- oder Bestellreferenz.', 'Kontaktieren Sie HOMYA per E-Mail, Telefon, WhatsApp oder SMS über die unten angegebenen Kontaktdaten.', 'Der Sitz nimmt auch zurückgesendete Waren einschließlich sperriger Güter an. Die Modalitäten finden Sie unter Rückgabe & Widerruf.'],
            'pending' => false,
        ],
        'livraison' => [
            'title' => 'Lieferung',
            'intro' => 'Kostenlose Lieferung in Frankreich, Belgien und Deutschland.',
            'paragraphs' => ['Ihre Geräte werden innerhalb von 3 bis 7 Tagen ab Zahlung durch unseren eigenen Transporteur geliefert.', 'Die Lieferung ist in Frankreich, Belgien und Deutschland kostenlos.', 'Für Möbel und sperrige Geräte teilen Sie bitte die Zugangsbedingungen mit, um die Lieferung mit HOMYA abzustimmen.', 'Rücksendungen sind bei einem innerhalb von 14 Tagen nach Erhalt erklärten Widerruf kostenlos. Adresse und Ablauf finden Sie unter Rückgabe & Widerruf.'],
            'pending' => false,
        ],
        'retractation' => $legalPages['entries']['retractation'],
        'paiement' => [
            'title' => 'Zahlung',
            'intro' => 'Hier finden Sie die Informationen zur Bezahlung Ihrer Bestellung.',
            'paragraphs' => ['Bestätigen Sie Warenkorb und Kontaktdaten, um Ihre Bestellung zu erfassen. Die Zahlungsanweisungen sind Ihrer Bestellreferenz zugeordnet.', 'Die Übersicht zeigt Artikel, Mengen und Gesamtbetrag in Euro einschließlich Steuern. Die Lieferung ist in Frankreich, Belgien und Deutschland kostenlos.', 'Ihre Artikel sind nach Bestätigung 72 Stunden reserviert. Die Bearbeitung geht nach Bestätigung der Zahlung weiter.', 'Bei Fragen zur Zahlung kontaktieren Sie HOMYA mit Ihrer Bestellnummer.'],
            'pending' => false,
        ],
        'suivi' => [
            'title' => 'Bestellverfolgung',
            'intro' => 'Geben Sie Ihre Bestellnummer ein, um den Bearbeitungsstand zu sehen.',
            'paragraphs' => ['Prüfen Sie Erfassung, Zahlung, Vorbereitung, Versand und Zustellung.'],
            'pending' => false,
        ],
        'panier' => [
            'title' => 'Warenkorb',
            'intro' => 'Prüfen Sie Ihre Artikel und bestätigen Sie Ihre Bestellung.',
            'paragraphs' => ['Prüfen Sie Produkte, Optionen und Mengen, bevor Sie Ihre Kontaktdaten eingeben.'],
            'pending' => false,
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
        'answer' => 'Ja. Legen Sie Ihre Artikel in den Warenkorb, prüfen Sie die Mengen und bestätigen Sie Ihre Bestellung. Bewahren Sie die Bestellnummer auf, um den Bearbeitungsstand auf der Seite Bestellverfolgung zu sehen.',
    ]],
];
