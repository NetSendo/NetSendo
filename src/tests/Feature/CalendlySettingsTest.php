<?php

namespace Tests\Feature;

use App\Models\CalendlyIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Calendly settings: lists and tags chosen for one event type are stored under the event
 * type's address and are the ones a booking of that type gets.
 */
class CalendlySettingsTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT_TYPE = 'https://api.calendly.com/event_types/AAAA-1111';

    private User $user;

    private CalendlyIntegration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->integration = CalendlyIntegration::create([
            'user_id' => $this->user->id,
            'client_id' => 'client-id-for-tests',
            'client_secret' => 'client-secret-for-tests',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'is_active' => true,
            'calendly_user_email' => 'anna@example.test',
            'settings' => CalendlyIntegration::getDefaultSettings(),
            'event_types' => [['uri' => self::EVENT_TYPE, 'name' => 'Demo call']],
        ]);
    }

    private function settings(array $mappings): array
    {
        $settings = CalendlyIntegration::getDefaultSettings();
        $settings['mailing_lists']['default_list_ids'] = [1];
        $settings['mailing_lists']['default_tag_ids'] = [5];
        $settings['event_type_mappings'] = $mappings;

        return $settings;
    }

    public function test_an_event_type_with_its_own_lists_uses_them(): void
    {
        $this->actingAs($this->user)
            ->put(route('settings.calendly.settings', $this->integration), [
                'settings' => $this->settings([
                    self::EVENT_TYPE => ['use_custom' => true, 'list_ids' => [7, 8], 'tag_ids' => [9]],
                ]),
            ])
            ->assertSessionHasNoErrors();

        $integration = $this->integration->fresh();

        $this->assertSame([7, 8], $integration->getListIdsForEventType(self::EVENT_TYPE));
        $this->assertSame([9], $integration->getTagIdsForEventType(self::EVENT_TYPE));
    }

    public function test_an_event_type_switched_back_to_defaults_uses_the_defaults(): void
    {
        $this->actingAs($this->user)
            ->put(route('settings.calendly.settings', $this->integration), [
                'settings' => $this->settings([
                    self::EVENT_TYPE => ['use_custom' => false, 'list_ids' => [7, 8], 'tag_ids' => [9]],
                ]),
            ])
            ->assertSessionHasNoErrors();

        $integration = $this->integration->fresh();

        $this->assertSame([1], $integration->getListIdsForEventType(self::EVENT_TYPE));
        $this->assertSame([5], $integration->getTagIdsForEventType(self::EVENT_TYPE));
    }

    public function test_an_event_type_without_a_mapping_uses_the_defaults(): void
    {
        $this->integration->update(['settings' => $this->settings([])]);

        $this->assertSame([1], $this->integration->getListIdsForEventType('https://api.calendly.com/event_types/OTHER'));
    }

    public function test_the_page_marks_an_account_whose_authorisation_was_not_finished(): void
    {
        CalendlyIntegration::create([
            'user_id' => $this->user->id,
            'client_id' => 'client-id-for-tests',
            'client_secret' => 'client-secret-for-tests',
            'is_active' => false,
            'settings' => CalendlyIntegration::getDefaultSettings(),
        ]);

        $this->actingAs($this->user)
            ->get(route('settings.calendly.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Calendly/Index')
                ->where('integrations.0.is_pending', false)
                ->where('integrations.1.is_pending', true));
    }

    public function test_settings_of_another_users_account_cannot_be_changed(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('settings.calendly.settings', $this->integration), ['settings' => $this->settings([])])
            ->assertForbidden();
    }
}
