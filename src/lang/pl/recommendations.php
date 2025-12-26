<?php

return [
    // Quick Win recommendations
    'missing_preheader' => [
        'title' => 'Dodaj preheadery do maili',
        'description' => 'Maile bez preheaderów tracą cenne miejsce w podglądzie skrzynki. Dodanie atrakcyjnych preheaderów może zwiększyć open rate o 5-10%.',
        'action_steps' => [
            'Otwórz edytor maili dla każdego szkicu/zaplanowanego maila',
            'Dodaj preheader uzupełniający temat',
            'Ogranicz do 100 znaków dla najlepszego wyświetlania',
            'Użyj tokenów personalizacji dla większego zaangażowania',
        ],
    ],
    'long_subject' => [
        'title' => 'Skróć tematy wiadomości',
        'description' => 'Długie tematy są obcinane na urządzeniach mobilnych. Utrzymanie ich poniżej 50 znaków gwarantuje pełną widoczność.',
        'action_steps' => [
            'Przejrzyj tematy powyżej 50 znaków',
            'Skup się na najbardziej przekonującej części wiadomości',
            'Użyj słów mocy wywołujących emocje',
            'Przetestuj z emoji (oszczędnie) dla atrakcyjności wizualnej',
        ],
    ],
    'no_personalization' => [
        'title' => 'Personalizuj treść maili',
        'description' => 'Spersonalizowane maile osiągają 26% wyższy open rate. Używanie imion i odpowiednich danych tworzy silniejsze połączenie.',
        'action_steps' => [
            'Dodaj [[first_name]] do tematów i powitań',
            'Użyj [[company]] lub [[city]] dla komunikacji B2B',
            'Twórz dynamiczne bloki treści na podstawie tagów subskrybentów',
            'Ustaw wartości zastępcze dla brakujących danych',
        ],
    ],
    'spam_content' => [
        'title' => 'Zmniejsz słowa wyzwalające spam',
        'description' => 'Twoja treść zawiera słowa mogące wywołać filtry spamu. Oczyszczenie języka poprawia dostarczalność.',
        'action_steps' => [
            'Unikaj WIELKICH LITER i nadmiernych wykrzykników',
            'Zamień słowa jak "ZA DARMO", "PILNE", "DZIAŁAJ TERAZ" na łagodniejsze alternatywy',
            'Balansuj treści promocyjne z wartościowymi',
            'Użyj sprawdzarek HTML maili przed wysyłką',
        ],
    ],
    'stale_list' => [
        'title' => 'Oczyść listy subskrybentów',
        'description' => 'Listy z nieaktywnymi subskrybentami szkodzą dostarczalności. Regularne czyszczenie poprawia open rate i reputację nadawcy.',
        'action_steps' => [
            'Zidentyfikuj subskrybentów bez otwarć przez 90 dni',
            'Przeprowadź kampanię reaktywacyjną przed usunięciem',
            'Usuń twarde odbicia natychmiast',
            'Rozważ politykę wygasania dla długo nieaktywnych użytkowników',
        ],
    ],
    'poor_timing' => [
        'title' => 'Zoptymalizuj godziny wysyłki',
        'description' => 'Wysyłanie o optymalnych porach znacząco wpływa na open rate. Najlepsze okno to zazwyczaj 9-11 rano lub 14-16.',
        'action_steps' => [
            'Planuj maile między 9-11 dla odbiorców biznesowych',
            'Wypróbuj 14-16 dla odbiorców konsumenckich',
            'Wtorek-czwartek zazwyczaj dają najlepsze wyniki',
            'Unikaj weekendów, chyba że dane wskazują inaczej',
        ],
    ],
    'over_mailing' => [
        'title' => 'Zmniejsz częstotliwość wysyłki',
        'description' => 'Wysyłasz zbyt często do niektórych list. To zwiększa wypisy i skargi na spam.',
        'action_steps' => [
            'Ogranicz do 2-3 maili tygodniowo na listę',
            'Stwórz centrum preferencji dla opcji częstotliwości',
            'Segmentuj wysoko zaangażowanych użytkowników dla większej ilości treści',
            'Używaj automatyzacji zamiast ręcznych broadcastów gdzie to możliwe',
        ],
    ],
    'no_automation' => [
        'title' => 'Skonfiguruj automatyzacje powitalne',
        'description' => 'Zautomatyzowane maile generują 320% więcej przychodów niż niezautomatyzowane. Zacznij od sekwencji powitalnej.',
        'action_steps' => [
            'Stwórz sekwencję powitalną 3-5 maili',
            'Skonfiguruj automatyzację wyzwalaną nowym subskrybentem',
            'Zawrzyj treść wartościową przed ofertami promocyjnymi',
            'Śledź zaangażowanie aby identyfikować gorących leadów',
        ],
    ],
    'sms_missing' => [
        'title' => 'Uruchom kampanie SMS',
        'description' => 'Masz numery telefonów ale nie używasz SMS. Kampanie wielokanałowe poprawiają konwersję o 12-15%.',
        'action_steps' => [
            'Stwórz SMS follow-up dla kluczowych kampanii mailowych',
            'Używaj SMS dla ofert czasowych',
            'Ogranicz wiadomości do 160 znaków',
            'Zawrzyj jasne wezwanie do działania z linkiem',
        ],
    ],

    // Strategic recommendations
    'declining_open_rate' => [
        'title' => 'Odwróć spadający open rate',
        'description' => 'Twój open rate spadł o :change% w ciągu ostatnich 30 dni. Skup się na optymalizacji tematów i higienie list.',
        'action_steps' => [
            'Testuj A/B tematy w kolejnych 5 kampaniach',
            'Usuń subskrybentów nieaktywnych przez 90+ dni',
            'Sprawdź reputację nadawcy na mail-tester.com',
            'Zweryfikuj rekordy SPF/DKIM/DMARC',
        ],
    ],
    'low_click_rate' => [
        'title' => 'Popraw click-through rate',
        'description' => 'Twój click rate jest poniżej 2%, co jest poniżej średniej branżowej. Lepsze CTA i struktura treści mogą pomóc.',
        'action_steps' => [
            'Używaj CTA w formie przycisków zamiast linków tekstowych',
            'Umieść główne CTA powyżej linii zgięcia',
            'Używaj języka nastawionego na działanie ("Rozpocznij" vs "Kliknij tutaj")',
            'Ogranicz do 1-2 głównych CTA na mail',
        ],
    ],
    'low_segmentation' => [
        'title' => 'Wdróż segmentację subskrybentów',
        'description' => 'Tylko :percent% twoich subskrybentów ma tagi. Lepsza segmentacja prowadzi do 14% wyższego click rate.',
        'action_steps' => [
            'Twórz tagi oparte na zainteresowaniach z zachowań kliknięć',
            'Skonfiguruj automatyzacje tagów dla kluczowych akcji',
            'Segmentuj według poziomu zaangażowania (aktywny/pasywny/zimny)',
            'Używaj dynamicznych bloków treści dla różnych segmentów',
        ],
    ],
];
