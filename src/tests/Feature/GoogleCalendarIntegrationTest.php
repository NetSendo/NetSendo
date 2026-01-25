<?php

namespace Tests\Feature;

use App\Models\CrmTask;
use App\Models\GoogleIntegration;
use App\Models\User;
use App\Models\UserCalendarConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private GoogleIntegration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->integration = GoogleIntegration::create([
            'user_id' => $this->user->id,
            'name' => 'Test Integration',
            'client_id' => 'test_client_id_123',
            'client_secret' => 'test_client_secret',
            'status' => 'active',
        ]);
    }

    public function test_calendar_settings_page_available(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('settings.calendar.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Settings/Calendar/Index'));
    }

    public function test_calendar_connect_redirects_to_google(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('settings.calendar.connect', $this->integration));

        $response->assertStatus(302);
        $response->assertRedirectContains('accounts.google.com');
    }

    public function test_calendar_connection_model_creation(): void
    {
        $connection = UserCalendarConnection::create([
            'user_id' => $this->user->id,
            'google_integration_id' => $this->integration->id,
            'access_token' => 'test_access_token',
            'refresh_token' => 'test_refresh_token',
            'token_expires_at' => now()->addHour(),
            'connected_email' => 'test@gmail.com',
            'calendar_id' => 'primary',
            'is_active' => true,
            'auto_sync_tasks' => true,
        ]);

        $this->assertDatabaseHas('user_calendar_connections', [
            'user_id' => $this->user->id,
            'connected_email' => 'test@gmail.com',
            'is_active' => true,
        ]);

        $this->assertTrue($connection->isActive());
        $this->assertFalse($connection->hasPushNotifications());
    }

    public function test_task_sync_fields_exist(): void
    {
        $task = CrmTask::create([
            'user_id' => $this->user->id,
            'owner_id' => $this->user->id,
            'title' => 'Test Task',
            'type' => 'task',
            'priority' => 'medium',
            'status' => 'pending',
            'sync_to_calendar' => true,
        ]);

        $this->assertDatabaseHas('crm_tasks', [
            'id' => $task->id,
            'sync_to_calendar' => true,
        ]);

        $this->assertTrue($task->sync_to_calendar);
        $this->assertFalse($task->isSyncedToCalendar());
    }

    public function test_task_synced_status_after_event_created(): void
    {
        $task = CrmTask::create([
            'user_id' => $this->user->id,
            'owner_id' => $this->user->id,
            'title' => 'Test Task',
            'type' => 'meeting',
            'priority' => 'high',
            'status' => 'pending',
            'sync_to_calendar' => true,
            'google_calendar_event_id' => 'event_123',
            'google_calendar_id' => 'primary',
            'google_calendar_synced_at' => now(),
        ]);

        $this->assertTrue($task->isSyncedToCalendar());
    }

    public function test_connection_channel_expiration(): void
    {
        $connection = UserCalendarConnection::create([
            'user_id' => $this->user->id,
            'google_integration_id' => $this->integration->id,
            'access_token' => 'test_access_token',
            'token_expires_at' => now()->addHour(),
            'connected_email' => 'test@gmail.com',
            'calendar_id' => 'primary',
            'is_active' => true,
            'channel_id' => 'channel_123',
            'resource_id' => 'resource_456',
            'channel_expires_at' => now()->addDays(7),
        ]);

        $this->assertTrue($connection->hasPushNotifications());

        // Simulate channel near expiration
        $connection->update(['channel_expires_at' => now()->addHours(12)]);

        // Should be in needsChannelRefresh scope
        $needsRefresh = UserCalendarConnection::needsChannelRefresh()->count();
        $this->assertEquals(1, $needsRefresh);
    }

    public function test_disconnect_clears_connection(): void
    {
        $connection = UserCalendarConnection::create([
            'user_id' => $this->user->id,
            'google_integration_id' => $this->integration->id,
            'access_token' => 'test_access_token',
            'token_expires_at' => now()->addHour(),
            'connected_email' => 'test@gmail.com',
            'calendar_id' => 'primary',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('user_calendar_connections', 1);

        // Mock the OAuth service to avoid external calls
        $response = $this->actingAs($this->user)
            ->post(route('settings.calendar.disconnect', $connection));

        $this->assertDatabaseCount('user_calendar_connections', 0);
    }
}
