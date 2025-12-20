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
     * Generate unsubscribe link for a subscriber
     */
    public function generateUnsubscribeLink(Subscriber $subscriber): string
    {
        return URL::signedRoute('unsubscribe', ['subscriber' => $subscriber->id]);
    }

    /**
     * Replace placeholders in both content and subject
     * Convenience method for email sending
     */
    public function processEmailContent(string $content, string $subject, Subscriber $subscriber): array
    {
        // Generate system links
        $additionalData = [
            'unsubscribe_link' => $this->generateUnsubscribeLink($subscriber),
            'unsubscribe_url' => $this->generateUnsubscribeLink($subscriber),
        ];

        return [
            'content' => $this->replacePlaceholders($content, $subscriber, $additionalData),
            'subject' => $this->replacePlaceholders($subject, $subscriber, $additionalData),
        ];
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
