<?php

namespace Tests\Unit\Services\Funnels;

use Tests\TestCase;
use App\Models\User;
use App\Models\Funnel;
use App\Models\FunnelSubscriber;
use App\Models\Subscriber;
use App\Models\ContactList;
use App\Services\Funnels\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class WebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WebhookService $service;
    protected User $user;
    protected Funnel $funnel;
    protected Subscriber $subscriber;
    protected FunnelSubscriber $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WebhookService();

        $this->user = User::factory()->create();

        $list = ContactList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'email',
        ]);

        $this->funnel = Funnel::factory()->create([
            'user_id' => $this->user->id,
            'trigger_list_id' => $list->id,
            'name' => 'Test Funnel',
        ]);

        $this->subscriber = Subscriber::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->enrollment = FunnelSubscriber::create([
            'funnel_id' => $this->funnel->id,
            'subscriber_id' => $this->subscriber->id,
            'status' => FunnelSubscriber::STATUS_ACTIVE,
            'entered_at' => now(),
            'steps_completed' => 2,
        ]);
    }

    /** @test */
    public function test_send_returns_success_on_200_response()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['success' => true], 200),
        ]);

        $result = $this->service->send(
            'https://example.com/webhook',
            ['test' => 'data']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status_code']);
        $this->assertEquals(1, $result['attempts']);
    }

    /** @test */
    public function test_send_retries_on_server_error()
    {
        $callCount = 0;

        Http::fake(function ($request) use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                return Http::response('Server Error', 500);
            }
            return Http::response(['success' => true], 200);
        });

        $result = $this->service->send(
            'https://example.com/webhook',
            ['test' => 'data']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['attempts']);
    }

    /** @test */
    public function test_send_does_not_retry_on_client_error()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response('Bad Request', 400),
        ]);

        $result = $this->service->send(
            'https://example.com/webhook',
            ['test' => 'data']
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(400, $result['status_code']);
        $this->assertEquals(1, $result['attempts']);
    }

    /** @test */
    public function test_send_includes_custom_headers()
    {
        Http::fake(function ($request) {
            $this->assertEquals('Bearer test-token', $request->header('Authorization')[0]);
            $this->assertEquals('custom-value', $request->header('X-Custom')[0]);
            return Http::response(['success' => true], 200);
        });

        $this->service->send(
            'https://example.com/webhook',
            ['test' => 'data'],
            [
                'Authorization' => 'Bearer test-token',
                'X-Custom' => 'custom-value',
            ]
        );
    }

    /** @test */
    public function test_build_payload_includes_subscriber_data()
    {
        $payload = $this->service->buildPayload($this->enrollment);

        $this->assertEquals('funnel_webhook', $payload['event']);
        $this->assertEquals($this->subscriber->email, $payload['subscriber']['email']);
        $this->assertEquals($this->subscriber->first_name, $payload['subscriber']['first_name']);
        $this->assertEquals($this->funnel->id, $payload['funnel']['id']);
        $this->assertEquals($this->funnel->name, $payload['funnel']['name']);
    }

    /** @test */
    public function test_build_payload_substitutes_variables()
    {
        $templateData = [
            'custom_message' => 'Hello {{subscriber.first_name}}!',
            'subscriber_email' => '{{subscriber.email}}',
            'funnel_name' => '{{funnel.name}}',
        ];

        $payload = $this->service->buildPayload($this->enrollment, $templateData);

        $this->assertEquals('Hello John!', $payload['custom_message']);
        $this->assertEquals('test@example.com', $payload['subscriber_email']);
        $this->assertEquals('Test Funnel', $payload['funnel_name']);
    }

    /** @test */
    public function test_parse_headers_handles_api_key()
    {
        $headers = $this->service->parseHeaders([
            'api_key' => 'my-secret-key',
        ]);

        $this->assertEquals('Bearer my-secret-key', $headers['Authorization']);
    }

    /** @test */
    public function test_parse_headers_handles_basic_auth()
    {
        $headers = $this->service->parseHeaders([
            'basic_auth' => 'user:password',
        ]);

        $this->assertEquals('Basic ' . base64_encode('user:password'), $headers['Authorization']);
    }

    /** @test */
    public function test_parse_headers_handles_custom_headers()
    {
        $headers = $this->service->parseHeaders([
            'headers' => [
                'X-Custom-1' => 'value1',
                'X-Custom-2' => 'value2',
            ],
        ]);

        $this->assertEquals('value1', $headers['X-Custom-1']);
        $this->assertEquals('value2', $headers['X-Custom-2']);
    }

    /** @test */
    public function test_send_supports_different_http_methods()
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $methods = ['POST', 'GET', 'PUT', 'PATCH', 'DELETE'];

        foreach ($methods as $method) {
            $result = $this->service->send(
                'https://example.com/webhook',
                ['test' => 'data'],
                [],
                $method
            );

            $this->assertTrue($result['success'], "Method {$method} should work");
        }
    }

    /** @test */
    public function test_send_fails_after_max_retries()
    {
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $result = $this->service->send(
            'https://example.com/webhook',
            ['test' => 'data']
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(3, $result['attempts']);
        $this->assertStringContainsString('Server error', $result['error']);
    }
}
