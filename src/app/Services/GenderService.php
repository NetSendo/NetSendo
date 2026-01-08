<?php

namespace App\Services;

use App\Models\Name;
use App\Models\Subscriber;

class GenderService
{
    /**
     * Detect gender from name database
     *
     * @param string $firstName First name to check
     * @param string|null $country Country code (default: PL)
     * @param int|null $userId User ID for custom names lookup
     * @return string|null 'male', 'female', 'neutral', or null if not found
     */
    public function detectGender(string $firstName, ?string $country = 'PL', ?int $userId = null): ?string
    {
        // Try name database first
        $gender = Name::findGender($firstName, $country, $userId);

        if ($gender) {
            return $gender;
        }

        // Fallback to pattern-based detection for Polish names
        if ($country === 'PL') {
            return $this->detectGenderByPattern($firstName);
        }

        return null;
    }

    /**
     * Pattern-based gender detection (fallback for Polish names)
     */
    protected function detectGenderByPattern(string $firstName): ?string
    {
        $name = mb_strtolower(trim($firstName));

        // Common Polish female endings
        if (preg_match('/(a)$/u', $name) && !preg_match('/^(kuba|kosma|barnaba|bonawentura)$/u', $name)) {
            return 'female';
        }

        // Common Polish male endings
        if (preg_match('/(ek|aw|an|sz|rz|cz|ej|aj|eł|ał|oł|ił)$/u', $name)) {
            return 'male';
        }

        return null;
    }

    /**
     * Resolve gender form based on subscriber's gender
     *
     * @param string $maleForm Form to use for males
     * @param string $femaleForm Form to use for females
     * @param Subscriber $subscriber Subscriber to check
     * @param string $defaultGender Default gender if unknown ('male' or 'female')
     * @return string Resolved form
     */
    public function resolveGenderForm(
        string $maleForm,
        string $femaleForm,
        Subscriber $subscriber,
        string $defaultGender = 'male'
    ): string {
        $gender = $subscriber->gender;

        // If subscriber has no gender, try to detect from name
        if (!$gender && $subscriber->first_name) {
            $gender = $this->detectGender(
                $subscriber->first_name,
                'PL', // TODO: Get country from subscriber
                $subscriber->user_id
            );
        }

        // If still no gender, use default
        if (!$gender) {
            $gender = $defaultGender;
        }

        return $gender === 'female' ? $femaleForm : $maleForm;
    }

    /**
     * Process all gender forms in content
     * Replaces {{male|female}} patterns
     *
     * @param string $content Content with gender forms
     * @param Subscriber $subscriber Subscriber for context
     * @return string Processed content
     */
    public function processGenderForms(string $content, Subscriber $subscriber): string
    {
        // Match {{male_form|female_form}} pattern
        return preg_replace_callback(
            '/\{\{([^|{}]+)\|([^|{}]+)\}\}/',
            function ($matches) use ($subscriber) {
                $maleForm = $matches[1];
                $femaleForm = $matches[2];
                return $this->resolveGenderForm($maleForm, $femaleForm, $subscriber);
            },
            $content
        );
    }

    /**
     * Get statistics about gender coverage for a user's subscribers
     */
    public function getGenderStats(int $userId): array
    {
        $subscribers = Subscriber::where('user_id', $userId)->get();

        $stats = [
            'total' => $subscribers->count(),
            'with_gender' => 0,
            'male' => 0,
            'female' => 0,
            'neutral' => 0,
            'unknown' => 0,
        ];

        foreach ($subscribers as $subscriber) {
            if ($subscriber->gender) {
                $stats['with_gender']++;
                $stats[$subscriber->gender] = ($stats[$subscriber->gender] ?? 0) + 1;
            } else {
                $stats['unknown']++;
            }
        }

        return $stats;
    }
}
