<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\Subscriber;
use Illuminate\Support\Facades\URL;

class PlaceholderService
{
    /**
     * Standard placeholder definitions
     */
    protected array $standardFields = [
        'email' => ['label' => 'Email', 'description' => 'Adres email subskrybenta'],
        'first_name' => ['label' => 'Imię', 'description' => 'Imię subskrybenta'],
        'last_name' => ['label' => 'Nazwisko', 'description' => 'Nazwisko subskrybenta'],
        'phone' => ['label' => 'Telefon', 'description' => 'Numer telefonu'],
        'device' => ['label' => 'Urządzenie', 'description' => 'Wykryte urządzenie'],
        'ip_address' => ['label' => 'Adres IP', 'description' => 'Adres IP przy subskrypcji'],
        'subscribed_at' => ['label' => 'Data subskrypcji', 'description' => 'Data i godzina subskrypcji'],
        'confirmed_at' => ['label' => 'Data potwierdzenia', 'description' => 'Data potwierdzenia (double opt-in)'],
        'source' => ['label' => 'Źródło', 'description' => 'Źródło subskrypcji (form, import, api)'],
    ];

    /**
     * System placeholders (links, special values)
     */
    protected array $systemPlaceholders = [
        'unsubscribe_link' => ['label' => 'Link wypisania', 'description' => 'Link do wypisania z listy'],
        'unsubscribe_url' => ['label' => 'URL wypisania', 'description' => 'URL do wypisania (alias)'],
        'manage' => ['label' => 'Link zarządzania preferencjami', 'description' => 'Link do strony zarządzania subskrypcjami'],
        'manage_url' => ['label' => 'URL zarządzania', 'description' => 'URL do zarządzania (alias)'],
        'webinar_register_link' => ['label' => 'Link rejestracji na webinar', 'description' => 'Link do strony rejestracji na webinar'],
        'webinar_watch_link' => ['label' => 'Link do webinaru', 'description' => 'Personalny link do oglądania webinaru'],
    ];

    /**
     * Special placeholders with different syntax (e.g., {{male|female}})
     */
    protected array $specialPlaceholders = [
        'gender_form' => [
            'label' => 'Odmiana przez płeć',
            'description' => 'Odmiana przez płeć, np. {{Byłeś|Byłaś}}, {{Drogi|Droga}}',
            'placeholder' => '{{męska|żeńska}}',
        ],
    ];

    /**
     * Get list of all standard fields
     */
    public function getStandardFields(): array
    {
        return $this->standardFields;
    }

    /**
     * Get list of system placeholders
     */
    public function getSystemPlaceholders(): array
    {
        return $this->systemPlaceholders;
    }

    /**
     * Get all available placeholders for a specific list context
     *
     * @param int|null $listId Contact list ID (null for global only)
     * @param int|null $userId User ID to filter custom fields
     * @return array Grouped placeholders
     */
    public function getAvailablePlaceholders(?int $listId = null, ?int $userId = null): array
    {
        $result = [
            'standard' => [],
            'system' => [],
            'special' => [],
            'custom' => [],
        ];

        // Standard fields
        foreach ($this->standardFields as $name => $info) {
            $result['standard'][] = [
                'name' => $name,
                'placeholder' => '[[' . $name . ']]',
                'label' => $info['label'],
                'description' => $info['description'],
            ];
        }

        // System placeholders
        foreach ($this->systemPlaceholders as $name => $info) {
            $result['system'][] = [
                'name' => $name,
                'placeholder' => '[[' . $name . ']]',
                'label' => $info['label'],
                'description' => $info['description'],
            ];
        }

        // Special placeholders (different syntax like {{male|female}})
        foreach ($this->specialPlaceholders as $name => $info) {
            $result['special'][] = [
                'name' => $name,
                'placeholder' => $info['placeholder'],
                'label' => $info['label'],
                'description' => $info['description'],
            ];
        }

        // Custom fields
        $query = CustomField::query()->orderBy('sort_order');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($listId) {
            $query->forList($listId);
        } else {
            $query->global();
        }

        foreach ($query->get() as $field) {
            $result['custom'][] = [
                'id' => $field->id,
                'name' => $field->name,
                'placeholder' => $field->placeholder,
                'label' => $field->label,
                'description' => $field->description,
                'type' => $field->type,
                'scope' => $field->scope,
                'is_required' => $field->is_required,
            ];
        }

        return $result;
    }

    /**
     * Replace all placeholders in content with subscriber values
     *
     * @param string $content Content with placeholders
     * @param Subscriber $subscriber Subscriber to get values from
     * @param array $additionalData Additional replacements (e.g., unsubscribe_link)
     * @return string Content with replaced placeholders
     */
    public function replacePlaceholders(string $content, Subscriber $subscriber, array $additionalData = []): string
    {
        // Process gender forms first {{male|female}}
        $content = app(\App\Services\GenderService::class)->processGenderForms($content, $subscriber);

        // Handle special [[!fname]] vocative placeholder
        if (str_contains($content, '[[!fname]]')) {
            $genderService = app(\App\Services\GenderService::class);
            $vocative = $genderService->getVocative(
                $subscriber->first_name ?? '',
                'PL', // Default to Polish
                $subscriber->user_id
            );
            $content = str_replace('[[!fname]]', $vocative, $content);
        }

        // Get all subscriber placeholder values
        $values = $subscriber->getAllPlaceholderValues();

        // Merge with additional data (override if needed)
        $values = array_merge($values, $additionalData);

        // Replace all [[placeholder]] patterns
        $content = preg_replace_callback(
            '/\[\[([a-zA-Z_][a-zA-Z0-9_]*)\]\]/',
            function ($matches) use ($values) {
                $key = $matches[1];
                return $values[$key] ?? '';
            },
            $content
        );

        return $content;
    }

    /**
     * Generate unsubscribe link for a subscriber.
     * If a list is provided, generates a link to unsubscribe from that specific list.
     * If no list is provided, generates a link to the preferences/manage page.
     *
     * @param Subscriber $subscriber
     * @param \App\Models\ContactList|null $list Specific list to unsubscribe from
     * @return string
     */
    public function generateUnsubscribeLink(Subscriber $subscriber, ?\App\Models\ContactList $list = null): string
    {
        if ($list) {
            // Unsubscribe from specific list
            return URL::signedRoute('subscriber.unsubscribe.confirm', [
                'subscriber' => $subscriber->id,
                'list' => $list->id,
            ]);
        }

        // No specific list - redirect to preferences page
        return $this->generateManageLink($subscriber);
    }

    /**
     * Generate manage/preferences link for a subscriber.
     */
    public function generateManageLink(Subscriber $subscriber): string
    {
        return URL::signedRoute('subscriber.preferences', ['subscriber' => $subscriber->id]);
    }

    /**
     * Replace placeholders in both content and subject
     * Convenience method for email sending
     */
    public function processEmailContent(string $content, string $subject, Subscriber $subscriber, ?\App\Models\ContactList $list = null): array
    {
        // Generate system links
        $unsubscribeLink = $this->generateUnsubscribeLink($subscriber, $list);
        $manageLink = $this->generateManageLink($subscriber);

        $additionalData = [
            'unsubscribe_link' => $unsubscribeLink,
            'unsubscribe_url' => $unsubscribeLink,
            'unsubscribe' => $unsubscribeLink,
            'manage' => $manageLink,
            'manage_url' => $manageLink,
        ];

        return [
            'content' => $this->replacePlaceholders($content, $subscriber, $additionalData),
            'subject' => $this->replacePlaceholders($subject, $subscriber, $additionalData),
        ];
    }

    /**
     * Replace placeholders in content with webinar context.
     * Generates webinar_register_link and webinar_watch_link.
     */
    public function processEmailContentWithWebinar(
        string $content,
        string $subject,
        Subscriber $subscriber,
        ?\App\Models\Webinar $webinar = null,
        bool $autoRegister = true,
        ?\App\Models\ContactList $list = null
    ): array {
        // Generate system links
        $unsubscribeLink = $this->generateUnsubscribeLink($subscriber, $list);
        $manageLink = $this->generateManageLink($subscriber);

        $additionalData = [
            'unsubscribe_link' => $unsubscribeLink,
            'unsubscribe_url' => $unsubscribeLink,
            'unsubscribe' => $unsubscribeLink,
            'manage' => $manageLink,
            'manage_url' => $manageLink,
        ];

        // Add webinar links if webinar is provided
        if ($webinar) {
            $additionalData['webinar_register_link'] = $this->generateWebinarRegisterLink($webinar);

            if ($autoRegister) {
                $additionalData['webinar_watch_link'] = $this->generateWebinarAutoRegisterLink($webinar, $subscriber);
            } else {
                $additionalData['webinar_watch_link'] = $this->generateWebinarRegisterLink($webinar);
            }
        }

        return [
            'content' => $this->replacePlaceholders($content, $subscriber, $additionalData),
            'subject' => $this->replacePlaceholders($subject, $subscriber, $additionalData),
        ];
    }

    /**
     * Generate webinar registration link.
     */
    public function generateWebinarRegisterLink(\App\Models\Webinar $webinar): string
    {
        return route('webinar.register', $webinar->slug);
    }

    /**
     * Generate webinar auto-register link with signed URL.
     */
    public function generateWebinarAutoRegisterLink(\App\Models\Webinar $webinar, Subscriber $subscriber): string
    {
        return URL::signedRoute('webinar.auto-register', [
            'slug' => $webinar->slug,
            'subscriberToken' => $subscriber->id,
        ]);
    }

    /**
     * Check if content contains any placeholders
     */
    public function hasPlaceholders(string $content): bool
    {
        return preg_match('/\[\[[a-zA-Z_][a-zA-Z0-9_]*\]\]/', $content) === 1;
    }

    /**
     * Extract all placeholder names from content
     */
    public function extractPlaceholders(string $content): array
    {
        preg_match_all('/\[\[([a-zA-Z_][a-zA-Z0-9_]*)\]\]/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Validate that all placeholders in content are available for a given context
     */
    public function validatePlaceholders(string $content, ?int $listId = null, ?int $userId = null): array
    {
        $usedPlaceholders = $this->extractPlaceholders($content);
        $available = $this->getAvailablePlaceholders($listId, $userId);

        $allAvailable = [];
        foreach ($available as $group) {
            foreach ($group as $placeholder) {
                $allAvailable[] = $placeholder['name'];
            }
        }

        $invalid = [];
        foreach ($usedPlaceholders as $placeholder) {
            if (!in_array($placeholder, $allAvailable)) {
                $invalid[] = $placeholder;
            }
        }

        return [
            'valid' => empty($invalid),
            'invalid_placeholders' => $invalid,
        ];
    }
}
