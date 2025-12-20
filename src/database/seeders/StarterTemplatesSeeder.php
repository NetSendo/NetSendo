<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class StarterTemplatesSeeder extends Seeder
{
    /**
     * Seed 6 premium starter templates.
     */
    public function run(): void
    {
        $templates = [
            $this->welcomeTemplate(),
            $this->newsletterTemplate(),
            $this->promotionalTemplate(),
            $this->cartAbandonmentTemplate(),
            $this->orderConfirmationTemplate(),
            $this->passwordResetTemplate(),
        ];

        foreach ($templates as $template) {
            Template::updateOrCreate(
                [
                    'name' => $template['name'],
                    'user_id' => null,
                    'is_public' => true,
                ],
                $template
            );
        }
    }

    private function welcomeTemplate(): array
    {
        return [
            'name' => 'Welcome Email',
            'user_id' => null,
            'category' => 'welcome',
            'is_public' => true,
            'preheader' => 'Witaj w naszej społeczności!',
            'settings' => [
                'width' => 600,
                'background_color' => '#f0f4f8',
                'content_background' => '#ffffff',
                'font_family' => 'Arial, Helvetica, sans-serif',
                'primary_color' => '#6366f1',
                'secondary_color' => '#4f46e5',
            ],
            'json_structure' => [
                'blocks' => [
                    [
                        'id' => 'header-1',
                        'type' => 'header',
                        'props' => [
                            'logoUrl' => '',
                            'logoWidth' => 150,
                            'backgroundColor' => '#6366f1',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'text-1',
                        'type' => 'text',
                        'props' => [
                            'content' => '<h1 style="color:#1e293b;text-align:center;">Witaj, [name]! 👋</h1><p style="text-align:center;color:#64748b;font-size:18px;">Dziękujemy za dołączenie do naszej społeczności. Cieszymy się, że jesteś z nami!</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'button-1',
                        'type' => 'button',
                        'props' => [
                            'text' => 'Rozpocznij teraz',
                            'url' => '#',
                            'backgroundColor' => '#6366f1',
                            'textColor' => '#ffffff',
                            'borderRadius' => 'medium',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'divider-1',
                        'type' => 'divider',
                        'props' => [
                            'lineColor' => '#e2e8f0',
                            'height' => 1,
                        ],
                    ],
                    [
                        'id' => 'text-2',
                        'type' => 'text',
                        'props' => [
                            'content' => '<p style="text-align:center;color:#94a3b8;font-size:14px;">Jeśli masz pytania, odpowiedz na tego maila - chętnie pomożemy!</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'footer-1',
                        'type' => 'footer',
                        'props' => [
                            'companyName' => 'Twoja Firma',
                            'address' => 'ul. Przykładowa 123, 00-001 Warszawa',
                            'unsubscribeText' => 'Wypisz się',
                            'backgroundColor' => '#f8fafc',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function newsletterTemplate(): array
    {
        return [
            'name' => 'Classic Newsletter',
            'user_id' => null,
            'category' => 'newsletter',
            'is_public' => true,
            'preheader' => 'Najnowsze wiadomości i artykuły',
            'settings' => [
                'width' => 600,
                'background_color' => '#f8fafc',
                'content_background' => '#ffffff',
                'font_family' => 'Arial, Helvetica, sans-serif',
                'primary_color' => '#0ea5e9',
                'secondary_color' => '#0284c7',
            ],
            'json_structure' => [
                'blocks' => [
                    [
                        'id' => 'header-1',
                        'type' => 'header',
                        'props' => [
                            'logoUrl' => '',
                            'logoWidth' => 180,
                            'backgroundColor' => '#0ea5e9',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'text-1',
                        'type' => 'text',
                        'props' => [
                            'content' => '<h1 style="color:#0f172a;margin-bottom:8px;">Newsletter - Grudzień 2025</h1><p style="color:#64748b;">Witaj [name]! Oto najnowsze wiadomości specjalnie dla Ciebie.</p>',
                        ],
                    ],
                    [
                        'id' => 'image-1',
                        'type' => 'image',
                        'props' => [
                            'src' => 'https://placehold.co/600x300/0ea5e9/ffffff?text=Featured+Article',
                            'alt' => 'Wyróżniony artykuł',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'text-2',
                        'type' => 'text',
                        'props' => [
                            'content' => '<h2 style="color:#0f172a;">Wyróżniony artykuł</h2><p style="color:#475569;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>',
                        ],
                    ],
                    [
                        'id' => 'button-1',
                        'type' => 'button',
                        'props' => [
                            'text' => 'Czytaj więcej',
                            'url' => '#',
                            'backgroundColor' => '#0ea5e9',
                            'textColor' => '#ffffff',
                            'borderRadius' => 'small',
                            'alignment' => 'left',
                        ],
                    ],
                    [
                        'id' => 'divider-1',
                        'type' => 'divider',
                        'props' => [
                            'lineColor' => '#e2e8f0',
                            'height' => 1,
                        ],
                    ],
                    [
                        'id' => 'columns-1',
                        'type' => 'columns',
                        'props' => [
                            'columnsCount' => 2,
                            'gap' => 20,
                            'columnBlocks' => [
                                [
                                    [
                                        'id' => 'col1-text',
                                        'type' => 'text',
                                        'props' => ['content' => '<h3>Artykuł 1</h3><p style="color:#64748b;font-size:14px;">Krótki opis artykułu...</p>'],
                                    ],
                                ],
                                [
                                    [
                                        'id' => 'col2-text',
                                        'type' => 'text',
                                        'props' => ['content' => '<h3>Artykuł 2</h3><p style="color:#64748b;font-size:14px;">Krótki opis artykułu...</p>'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'footer-1',
                        'type' => 'footer',
                        'props' => [
                            'companyName' => 'Twoja Firma',
                            'address' => 'ul. Przykładowa 123, 00-001 Warszawa',
                            'unsubscribeText' => 'Wypisz się z newslettera',
                            'backgroundColor' => '#f1f5f9',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function promotionalTemplate(): array
    {
        return [
            'name' => 'Promo Campaign',
            'user_id' => null,
            'category' => 'promotional',
            'is_public' => true,
            'preheader' => '🔥 Nie przegap tej oferty!',
            'settings' => [
                'width' => 600,
                'background_color' => '#fef2f2',
                'content_background' => '#ffffff',
                'font_family' => 'Arial, Helvetica, sans-serif',
                'primary_color' => '#ef4444',
                'secondary_color' => '#dc2626',
            ],
            'json_structure' => [
                'blocks' => [
                    [
                        'id' => 'header-1',
                        'type' => 'header',
                        'props' => [
                            'logoUrl' => '',
                            'logoWidth' => 150,
                            'backgroundColor' => '#ef4444',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'text-1',
                        'type' => 'text',
                        'props' => [
                            'content' => '<h1 style="color:#ef4444;text-align:center;font-size:36px;">🎉 WIELKA WYPRZEDAŻ!</h1><p style="text-align:center;color:#1e293b;font-size:20px;">Do <strong>-50%</strong> na wszystkie produkty</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'image-1',
                        'type' => 'image',
                        'props' => [
                            'src' => 'https://placehold.co/600x300/ef4444/ffffff?text=SALE+50%25+OFF',
                            'alt' => 'Promocja',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'text-2',
                        'type' => 'text',
                        'props' => [
                            'content' => '<p style="text-align:center;color:#475569;font-size:16px;">Oferta ważna tylko do końca tygodnia! Nie przegap okazji na zakupy w super cenach.</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'button-1',
                        'type' => 'button',
                        'props' => [
                            'text' => 'KUPUJĘ TERAZ →',
                            'url' => '#',
                            'backgroundColor' => '#ef4444',
                            'textColor' => '#ffffff',
                            'borderRadius' => 'pill',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'spacer-1',
                        'type' => 'spacer',
                        'props' => ['height' => 20],
                    ],
                    [
                        'id' => 'text-3',
                        'type' => 'text',
                        'props' => [
                            'content' => '<p style="text-align:center;color:#94a3b8;font-size:12px;">Użyj kodu: <strong>PROMO50</strong> przy kasie</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'footer-1',
                        'type' => 'footer',
                        'props' => [
                            'companyName' => 'Twoja Firma',
                            'address' => 'ul. Przykładowa 123, 00-001 Warszawa',
                            'unsubscribeText' => 'Wypisz się',
                            'backgroundColor' => '#fef2f2',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function cartAbandonmentTemplate(): array
    {
        return [
            'name' => 'Cart Abandonment',
            'user_id' => null,
            'category' => 'ecommerce',
            'is_public' => true,
            'preheader' => 'Zapomniałeś o czymś w koszyku?',
            'settings' => [
                'width' => 600,
                'background_color' => '#fafafa',
                'content_background' => '#ffffff',
                'font_family' => 'Arial, Helvetica, sans-serif',
                'primary_color' => '#f59e0b',
                'secondary_color' => '#d97706',
            ],
            'json_structure' => [
                'blocks' => [
                    [
                        'id' => 'header-1',
                        'type' => 'header',
                        'props' => [
                            'logoUrl' => '',
                            'logoWidth' => 150,
                            'backgroundColor' => '#ffffff',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'text-1',
                        'type' => 'text',
                        'props' => [
                            'content' => '<h1 style="color:#1e293b;text-align:center;">Hej [name], Twój koszyk czeka! 🛒</h1><p style="text-align:center;color:#64748b;">Zostawiłeś produkty w koszyku. Dokończ zakupy, zanim znikną!</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'product-1',
                        'type' => 'product',
                        'props' => [
                            'imageUrl' => 'https://placehold.co/200x200/f59e0b/ffffff?text=Product',
                            'title' => 'Nazwa produktu',
                            'description' => 'Krótki opis produktu...',
                            'price' => '199,00 zł',
                            'oldPrice' => '249,00 zł',
                            'buttonText' => 'Kup teraz',
                            'buttonUrl' => '#',
                        ],
                    ],
                    [
                        'id' => 'button-1',
                        'type' => 'button',
                        'props' => [
                            'text' => 'Wróć do koszyka',
                            'url' => '#',
                            'backgroundColor' => '#f59e0b',
                            'textColor' => '#ffffff',
                            'borderRadius' => 'medium',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'text-2',
                        'type' => 'text',
                        'props' => [
                            'content' => '<p style="text-align:center;color:#94a3b8;font-size:14px;">💡 Darmowa dostawa przy zamówieniach powyżej 200 zł!</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'footer-1',
                        'type' => 'footer',
                        'props' => [
                            'companyName' => 'Twoja Firma',
                            'address' => 'ul. Przykładowa 123, 00-001 Warszawa',
                            'unsubscribeText' => 'Wypisz się',
                            'backgroundColor' => '#f8fafc',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function orderConfirmationTemplate(): array
    {
        return [
            'name' => 'Order Confirmation',
            'user_id' => null,
            'category' => 'transactional',
            'is_public' => true,
            'preheader' => 'Dziękujemy za zamówienie!',
            'settings' => [
                'width' => 600,
                'background_color' => '#f0fdf4',
                'content_background' => '#ffffff',
                'font_family' => 'Arial, Helvetica, sans-serif',
                'primary_color' => '#22c55e',
                'secondary_color' => '#16a34a',
            ],
            'json_structure' => [
                'blocks' => [
                    [
                        'id' => 'header-1',
                        'type' => 'header',
                        'props' => [
                            'logoUrl' => '',
                            'logoWidth' => 150,
                            'backgroundColor' => '#22c55e',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'text-1',
                        'type' => 'text',
                        'props' => [
                            'content' => '<h1 style="color:#22c55e;text-align:center;">✅ Zamówienie potwierdzone!</h1><p style="text-align:center;color:#1e293b;">Dziękujemy za zakupy, [name]!</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'text-2',
                        'type' => 'text',
                        'props' => [
                            'content' => '<div style="background:#f0fdf4;padding:20px;border-radius:8px;"><p style="margin:0;"><strong>Numer zamówienia:</strong> #123456</p><p style="margin:8px 0 0;"><strong>Data:</strong> 18.12.2025</p><p style="margin:8px 0 0;"><strong>Suma:</strong> 299,00 zł</p></div>',
                        ],
                    ],
                    [
                        'id' => 'divider-1',
                        'type' => 'divider',
                        'props' => [
                            'lineColor' => '#e2e8f0',
                            'height' => 1,
                        ],
                    ],
                    [
                        'id' => 'text-3',
                        'type' => 'text',
                        'props' => [
                            'content' => '<h3>Zamówione produkty:</h3><p>• Produkt 1 - 149,00 zł</p><p>• Produkt 2 - 150,00 zł</p>',
                        ],
                    ],
                    [
                        'id' => 'button-1',
                        'type' => 'button',
                        'props' => [
                            'text' => 'Śledź przesyłkę',
                            'url' => '#',
                            'backgroundColor' => '#22c55e',
                            'textColor' => '#ffffff',
                            'borderRadius' => 'medium',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'footer-1',
                        'type' => 'footer',
                        'props' => [
                            'companyName' => 'Twoja Firma',
                            'address' => 'ul. Przykładowa 123, 00-001 Warszawa',
                            'unsubscribeText' => 'Zarządzaj powiadomieniami',
                            'backgroundColor' => '#f0fdf4',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function passwordResetTemplate(): array
    {
        return [
            'name' => 'Password Reset',
            'user_id' => null,
            'category' => 'notification',
            'is_public' => true,
            'preheader' => 'Resetowanie hasła do konta',
            'settings' => [
                'width' => 600,
                'background_color' => '#f8fafc',
                'content_background' => '#ffffff',
                'font_family' => 'Arial, Helvetica, sans-serif',
                'primary_color' => '#6366f1',
                'secondary_color' => '#4f46e5',
            ],
            'json_structure' => [
                'blocks' => [
                    [
                        'id' => 'header-1',
                        'type' => 'header',
                        'props' => [
                            'logoUrl' => '',
                            'logoWidth' => 150,
                            'backgroundColor' => '#ffffff',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'text-1',
                        'type' => 'text',
                        'props' => [
                            'content' => '<h1 style="color:#1e293b;text-align:center;">Resetowanie hasła 🔐</h1><p style="text-align:center;color:#64748b;">Otrzymaliśmy prośbę o zresetowanie hasła do Twojego konta.</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'button-1',
                        'type' => 'button',
                        'props' => [
                            'text' => 'Zresetuj hasło',
                            'url' => '#',
                            'backgroundColor' => '#6366f1',
                            'textColor' => '#ffffff',
                            'borderRadius' => 'medium',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'text-2',
                        'type' => 'text',
                        'props' => [
                            'content' => '<p style="text-align:center;color:#94a3b8;font-size:14px;">Link wygaśnie za 60 minut.</p><p style="text-align:center;color:#94a3b8;font-size:14px;">Jeśli nie prosiłeś o reset hasła, zignoruj tę wiadomość.</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'divider-1',
                        'type' => 'divider',
                        'props' => [
                            'lineColor' => '#e2e8f0',
                            'height' => 1,
                        ],
                    ],
                    [
                        'id' => 'text-3',
                        'type' => 'text',
                        'props' => [
                            'content' => '<p style="text-align:center;color:#94a3b8;font-size:12px;">Ze względów bezpieczeństwa, ten email został wysłany na adres powiązany z Twoim kontem.</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'id' => 'footer-1',
                        'type' => 'footer',
                        'props' => [
                            'companyName' => 'Twoja Firma',
                            'address' => 'ul. Przykładowa 123, 00-001 Warszawa',
                            'unsubscribeText' => '',
                            'backgroundColor' => '#f8fafc',
                        ],
                    ],
                ],
            ],
        ];
    }
}
