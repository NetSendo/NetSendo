<?php

return [
    // Quick Win recommendations
    'missing_preheader' => [
        'title' => 'Preheader zu E-Mails hinzufügen',
        'description' => 'E-Mails ohne Preheader verpassen wertvolle Vorschaufläche im Posteingang. Ansprechende Preheader können die Öffnungsrate um 5-10% steigern.',
        'action_steps' => [
            'Öffnen Sie den E-Mail-Editor für jeden Entwurf/geplante E-Mail',
            'Fügen Sie einen Preheader hinzu, der Ihre Betreffzeile ergänzt',
            'Halten Sie ihn unter 100 Zeichen für beste Darstellung',
            'Verwenden Sie Personalisierungs-Token für höheres Engagement',
        ],
    ],
    'long_subject' => [
        'title' => 'Betreffzeilen kürzen',
        'description' => 'Lange Betreffzeilen werden auf Mobilgeräten abgeschnitten. Unter 50 Zeichen sichert vollständige Sichtbarkeit.',
        'action_steps' => [
            'Überprüfen Sie Betreffzeilen über 50 Zeichen',
            'Konzentrieren Sie sich auf den überzeugendsten Teil Ihrer Nachricht',
            'Verwenden Sie Power-Wörter, die Emotionen auslösen',
            'Testen Sie mit Emoji (sparsam) für visuellen Reiz',
        ],
    ],
    'no_personalization' => [
        'title' => 'E-Mail-Inhalte personalisieren',
        'description' => 'Personalisierte E-Mails erreichen 26% höhere Öffnungsraten. Verwendung von Abonnentennamen schafft stärkere Verbindungen.',
        'action_steps' => [
            '[[first_name]] zu Betreffzeilen und Begrüßungen hinzufügen',
            '[[company]] oder [[city]] für B2B-Kommunikation verwenden',
            'Dynamische Inhaltsblöcke basierend auf Abonnenten-Tags erstellen',
            'Fallback-Werte für fehlende Daten einrichten',
        ],
    ],
    'spam_content' => [
        'title' => 'Spam-Auslösewörter reduzieren',
        'description' => 'Ihr Inhalt enthält Wörter, die Spam-Filter auslösen können. Bereinigung verbessert die Zustellbarkeit.',
        'action_steps' => [
            'GROSSBUCHSTABEN und übermäßige Ausrufezeichen vermeiden',
            'Wörter wie "KOSTENLOS", "DRINGEND", "JETZT HANDELN" durch sanftere Alternativen ersetzen',
            'Werbe- und wertorientierte Inhalte ausbalancieren',
            'HTML-E-Mail-Checker vor dem Senden verwenden',
        ],
    ],
    'stale_list' => [
        'title' => 'Abonnentenlisten bereinigen',
        'description' => 'Listen mit inaktiven Abonnenten schaden der Zustellbarkeit. Regelmäßige Bereinigung verbessert Öffnungsraten und Absenderreputation.',
        'action_steps' => [
            'Abonnenten ohne Öffnungen seit 90 Tagen identifizieren',
            'Reaktivierungskampagne vor Entfernung durchführen',
            'Hard Bounces sofort entfernen',
            'Sunset-Policy für langfristig inaktive Nutzer erwägen',
        ],
    ],
    'poor_timing' => [
        'title' => 'Versandzeiten optimieren',
        'description' => 'Optimale Versandzeiten beeinflussen die Öffnungsrate erheblich. Bestes Zeitfenster: 9-11 Uhr oder 14-16 Uhr Ortszeit.',
        'action_steps' => [
            'E-Mails zwischen 9-11 Uhr für Geschäftskunden planen',
            'Versuchen Sie 14-16 Uhr für Verbraucher',
            'Dienstag bis Donnerstag funktioniert meist am besten',
            'Wochenenden vermeiden, außer Ihre Daten zeigen anderes',
        ],
    ],
    'over_mailing' => [
        'title' => 'Versandhäufigkeit reduzieren',
        'description' => 'Sie versenden zu häufig an einige Listen. Dies erhöht Abmeldungen und Spam-Beschwerden.',
        'action_steps' => [
            'Auf 2-3 E-Mails pro Woche pro Liste begrenzen',
            'Präferenzzentrum für Frequenzoptionen erstellen',
            'Hoch engagierte Nutzer für mehr Inhalte segmentieren',
            'Automatisierungen statt manueller Broadcasts wo möglich nutzen',
        ],
    ],
    'no_automation' => [
        'title' => 'Willkommens-Automatisierungen einrichten',
        'description' => 'Automatisierte E-Mails generieren 320% mehr Umsatz als nicht automatisierte. Beginnen Sie mit einer Willkommenssequenz.',
        'action_steps' => [
            'Eine 3-5 E-Mail Willkommenssequenz erstellen',
            'Automatisierung bei neuem Abonnenten auslösen',
            'Wertvolle Inhalte vor Werbeangeboten einbauen',
            'Engagement verfolgen um heiße Leads zu identifizieren',
        ],
    ],
    'sms_missing' => [
        'title' => 'SMS-Kampagnen starten',
        'description' => 'Sie haben Telefonnummern aber nutzen kein SMS. Multi-Channel-Kampagnen verbessern die Conversion um 12-15%.',
        'action_steps' => [
            'SMS-Follow-up für wichtige E-Mail-Kampagnen erstellen',
            'SMS für zeitkritische Angebote nutzen',
            'Nachrichten unter 160 Zeichen halten',
            'Klaren Call-to-Action mit Link einfügen',
        ],
    ],

    // Strategic recommendations
    'declining_open_rate' => [
        'title' => 'Sinkende Öffnungsraten umkehren',
        'description' => 'Ihre Öffnungsraten sind in den letzten 30 Tagen um :change% gesunken. Fokussieren Sie sich auf Betreffzeilen-Optimierung und Listen-Hygiene.',
        'action_steps' => [
            'A/B-Tests für Betreffzeilen in den nächsten 5 Kampagnen',
            'Abonnenten entfernen, die 90+ Tage inaktiv sind',
            'Absenderreputation auf mail-tester.com prüfen',
            'SPF/DKIM/DMARC-Einträge verifizieren',
        ],
    ],
    'low_click_rate' => [
        'title' => 'E-Mail-Klickraten verbessern',
        'description' => 'Ihre Klickrate liegt unter 2%, was unter dem Branchendurchschnitt liegt. Bessere CTAs und Inhaltsstruktur können helfen.',
        'action_steps' => [
            'Button-CTAs statt Textlinks verwenden',
            'Haupt-CTA im sichtbaren Bereich platzieren',
            'Handlungsorientierte Sprache verwenden ("Jetzt starten" vs "Hier klicken")',
            'Auf 1-2 Haupt-CTAs pro E-Mail beschränken',
        ],
    ],
    'low_segmentation' => [
        'title' => 'Abonnenten-Segmentierung implementieren',
        'description' => 'Nur :percent% Ihrer Abonnenten sind getaggt. Bessere Segmentierung führt zu 14% höheren Klickraten.',
        'action_steps' => [
            'Interessenbasierte Tags aus Klickverhalten erstellen',
            'Tag-Automatisierungen für wichtige Aktionen einrichten',
            'Nach Engagement-Level segmentieren (aktiv/passiv/kalt)',
            'Dynamische Inhaltsblöcke für verschiedene Segmente nutzen',
        ],
    ],
];
