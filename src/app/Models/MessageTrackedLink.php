<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageTrackedLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'url',
        'url_hash',
        'tracking_enabled',
        'share_data_enabled',
        'shared_fields',
        'subscribe_to_list_ids',
        'unsubscribe_from_list_ids',
    ];

    protected $casts = [
        'tracking_enabled' => 'boolean',
        'share_data_enabled' => 'boolean',
        'shared_fields' => 'array',
        'subscribe_to_list_ids' => 'array',
        'unsubscribe_from_list_ids' => 'array',
    ];

    /**
     * Available fields that can be shared with external pages.
     * Mirrors the fields available in External Pages feature.
     */
    public const SHAREABLE_FIELDS = [
        'fname' => 'First Name',
        'lname' => 'Last Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'sex' => 'Gender',
    ];

    /**
     * Boot the model and set up event listeners.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate URL hash before saving
        static::saving(function ($trackedLink) {
            if ($trackedLink->url && !$trackedLink->url_hash) {
                $trackedLink->url_hash = static::generateUrlHash($trackedLink->url);
            }
        });
    }

    /**
     * Generate a normalized URL hash for consistent lookups.
     */
    public static function generateUrlHash(string $url): string
    {
        // Normalize URL: lowercase, remove trailing slash, sort query params
        $normalized = static::normalizeUrl($url);
        return hash('sha256', $normalized);
    }

    /**
     * Normalize URL for consistent matching.
     * - Lowercase scheme and host
     * - Remove trailing slash
     * - Sort query parameters
     */
    public static function normalizeUrl(string $url): string
    {
        $parsed = parse_url($url);

        if (!$parsed || !isset($parsed['host'])) {
            // If not a valid URL, just lowercase and trim
            return strtolower(rtrim(trim($url), '/'));
        }

        // Build normalized URL
        $normalized = strtolower($parsed['scheme'] ?? 'https') . '://';
        $normalized .= strtolower($parsed['host']);

        if (isset($parsed['port'])) {
            $normalized .= ':' . $parsed['port'];
        }

        if (isset($parsed['path'])) {
            $normalized .= rtrim($parsed['path'], '/');
        }

        // Sort query parameters for consistent matching
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            ksort($queryParams);
            $normalized .= '?' . http_build_query($queryParams);
        }

        if (isset($parsed['fragment'])) {
            $normalized .= '#' . $parsed['fragment'];
        }

        return $normalized;
    }

    /**
     * Get the message that owns this tracked link.
     */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the lists to subscribe on click.
     */
    public function subscribeLists()
    {
        $ids = $this->subscribe_to_list_ids ?? [];
        return ContactList::whereIn('id', $ids)->get();
    }

    /**
     * Get the lists to unsubscribe on click.
     */
    public function unsubscribeLists()
    {
        $ids = $this->unsubscribe_from_list_ids ?? [];
        return ContactList::whereIn('id', $ids)->get();
    }

    /**
     * Check if this link has any actions configured.
     */
    public function hasActions(): bool
    {
        return !empty($this->subscribe_to_list_ids) || !empty($this->unsubscribe_from_list_ids);
    }

    /**
     * Build URL with shared subscriber data appended.
     */
    public function buildUrlWithSharedData(Subscriber $subscriber): string
    {
        if (!$this->share_data_enabled || empty($this->shared_fields)) {
            return $this->url;
        }

        $params = [];
        foreach ($this->shared_fields as $field) {
            switch ($field) {
                case 'fname':
                    $params['fname'] = $subscriber->first_name ?? '';
                    break;
                case 'lname':
                    $params['lname'] = $subscriber->last_name ?? '';
                    break;
                case 'email':
                    $params['email'] = $subscriber->email ?? '';
                    break;
                case 'phone':
                    $params['phone'] = $subscriber->phone ?? '';
                    break;
                case 'sex':
                    $params['sex'] = $subscriber->sex ?? '';
                    break;
                default:
                    // Check for custom fields
                    if (isset($subscriber->custom_fields[$field])) {
                        $params[$field] = $subscriber->custom_fields[$field];
                    }
                    break;
            }
        }

        if (empty($params)) {
            return $this->url;
        }

        // Append params to URL
        $separator = (parse_url($this->url, PHP_URL_QUERY) === null) ? '?' : '&';
        return $this->url . $separator . http_build_query($params);
    }

    /**
     * Find tracked link by message ID and URL.
     */
    public static function findByUrl(int $messageId, string $url): ?self
    {
        $hash = static::generateUrlHash($url);
        return static::where('message_id', $messageId)
            ->where('url_hash', $hash)
            ->first();
    }
}
