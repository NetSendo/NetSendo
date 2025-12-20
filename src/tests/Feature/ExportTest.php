<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\User;
use App\Jobs\ExportSubscribersCsv;
use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    public function test_can_dispatch_export_job()
    {
        $user = User::factory()->create();
        $list = ContactList::create([
            'user_id' => $user->id,
            'name' => 'Test List',
        ]);
        
        // Manually creating a valid key for test
        $plainKey = 'ns_live_testkey1234567890';
        $prefix = 'ns_live_test';
        $hash = hash('sha256', $plainKey);
        
        $apiKey = ApiKey::create([
            'user_id' => $user->id,
            'name' => 'Test Key',
            'key_prefix' => $prefix,
            'key_hash' => $hash,
            'permissions' => ['subscribers:read'],
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $plainKey])
            ->postJson(route('api.v1.lists.export', $list));

        $response->assertStatus(202);
        
        Queue::assertPushed(ExportSubscribersCsv::class, function ($job) use ($list) {
            return $job->contactList->id === $list->id;
        });
    }

    public function test_cannot_export_others_list()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $list = ContactList::create([
            'user_id' => $otherUser->id,
            'name' => 'Other List',
        ]);
        
        $plainKey = 'ns_live_testkey1234567890';
        ApiKey::create([
            'user_id' => $user->id,
            'name' => 'Test Key',
            'key_prefix' => 'ns_live_test',
            'key_hash' => hash('sha256', $plainKey),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $plainKey])
            ->postJson(route('api.v1.lists.export', $list));

        $response->assertStatus(404);
        Queue::assertNothingPushed();
    }

    public function test_can_download_file_with_valid_signature()
    {
        $path = 'exports/test.csv';
        Storage::put($path, 'content');

        $url = URL::signedRoute('api.v1.exports.download', ['path' => $path]);

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename=test.csv');
    }

    public function test_cannot_download_with_invalid_signature()
    {
        $path = 'exports/test.csv';
        Storage::put($path, 'content');

        $url = route('api.v1.exports.download', ['path' => $path]); // Not signed

        $response = $this->get($url);

        $response->assertStatus(403);
    }
}
