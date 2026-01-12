<?php
/**
 * NetSendo API Communication Class
 *
 * @package NetSendo_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NetSendo_WC_API
 * Handles all communication with NetSendo API
 */
class NetSendo_WC_API {

    /**
     * @var string API base URL
     */
    private $api_url;

    /**
     * @var string API Key
     */
    private $api_key;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = NetSendo_WC_Admin_Settings::get_settings();
        $this->api_url = rtrim($settings['api_url'] ?? '', '/');
        $this->api_key = $settings['api_key'] ?? '';
    }

    /**
     * Check if API is configured
     *
     * @return bool
     */
    public function is_configured() {
        return !empty($this->api_url) && !empty($this->api_key);
    }

    /**
     * Add subscriber to a list
     *
     * @param string $list_id List ID
     * @param string $email Subscriber email
     * @param string $name Subscriber name
     * @param array $custom_fields Additional custom fields
     * @return bool|array Success or error
     */
    public function add_subscriber($list_id, $email, $name = '', $custom_fields = []) {
        if (!$this->is_configured()) {
            $this->log('API not configured');
            return false;
        }

        $endpoint = $this->api_url . '/api/v1/subscribers';

        // Parse name into first and last name
        $name_parts = explode(' ', $name, 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $name_parts[1] ?? '';

        $body = [
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'list_id' => $list_id,
            'status' => 'active',
            'custom_fields' => $custom_fields,
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->log('API Error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            $this->log('Subscriber added successfully: ' . $email . ' to list ' . $list_id);
            return $body;
        }

        $this->log('API Error: ' . ($body['message'] ?? 'Unknown error') . ' (Code: ' . $code . ')');
        return false;
    }

    /**
     * Get available lists from NetSendo
     *
     * @return array|false
     */
    public function get_lists() {
        if (!$this->is_configured()) {
            return false;
        }

        $endpoint = $this->api_url . '/api/v1/lists';

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->log('API Error getting lists: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            return $body['data'] ?? $body;
        }

        $this->log('API Error getting lists: ' . ($body['message'] ?? 'Unknown error'));
        return false;
    }

    /**
     * Test API connection
     *
     * @return array
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => __('API is not configured. Please enter API URL and API Key.', 'netsendo-woocommerce'),
            ];
        }

        // Use account info endpoint to get user_id
        $endpoint = $this->api_url . '/api/v1/account';

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200) {
            // Extract and save user_id for Pixel tracking
            $user_id = $body['data']['id'] ?? $body['id'] ?? null;
            if ($user_id) {
                $this->save_user_id($user_id);
            }

            return [
                'success' => true,
                'message' => __('Connection successful! NetSendo API is working.', 'netsendo-woocommerce'),
                'user_id' => $user_id,
            ];
        } elseif ($code === 401) {
            return [
                'success' => false,
                'message' => __('Authentication failed. Please check your API Key.', 'netsendo-woocommerce'),
            ];
        } else {
            return [
                'success' => false,
                'message' => sprintf(__('API returned error code: %d', 'netsendo-woocommerce'), $code),
            ];
        }
    }

    /**
     * Save user_id to settings for Pixel tracking
     *
     * @param int $user_id
     */
    private function save_user_id($user_id) {
        $settings = get_option(NetSendo_WC_Admin_Settings::OPTION_NAME, []);
        $settings['user_id'] = (int) $user_id;
        update_option(NetSendo_WC_Admin_Settings::OPTION_NAME, $settings);
    }

    /**
     * Get external pages from NetSendo
     *
     * @return array|false
     */
    public function get_external_pages() {
        if (!$this->is_configured()) {
            return false;
        }

        $endpoint = $this->api_url . '/api/v1/external-pages';

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->log('API Error getting external pages: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            return $body['data'] ?? $body;
        }

        $this->log('API Error getting external pages: ' . ($body['message'] ?? 'Unknown error'));
        return false;
    }

    /**
     * Log message for debugging
     *
     * @param string $message
     */
    private function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[NetSendo WooCommerce] ' . $message);
        }
    }
}
