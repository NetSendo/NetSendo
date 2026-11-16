<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\CustomField;
use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Models\Message;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use App\Services\Segmentation\SegmentQueryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saved dynamic segments. On a live account segments were kept as static lists
 * ("opened, did not click", "hot clickers") filled by hand; a segment now
 * describes them once and stays current.
 */
class SegmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ContactList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'UTC'));
        $this->withoutVite();

        $this->user = User::factory()->create();
        $this->list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Newsletter', 'type' => 'email']);
    }

    protected function subscriber(string $email, array $attributes = [], ?ContactList $list = null, string $status = 'active'): Subscriber
    {
        $subscriber = Subscriber::create([
            'user_id' => $this->user->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);
        if ($attributes) {
            DB::table('subscribers')->where('id', $subscriber->id)->update($attributes);
        }
        $subscriber->contactLists()->attach(($list ?? $this->list)->id, ['status' => $status, 'subscribed_at' => now()]);

        return $subscriber->fresh();
    }

    protected function message(string $subject = 'Hello', ?ContactList $list = null): Message
    {
        $message = Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => $subject,
            'content' => '<p>Body</p>',
            'status' => 'sent',
        ]);
        $message->contactLists()->attach(($list ?? $this->list)->id);

        return $message;
    }

    protected function segment(array $conditions, string $match = 'all'): Segment
    {
        return Segment::create([
            'user_id' => $this->user->id,
            'name' => 'Test',
            'match' => $match,
            'conditions' => $conditions,
        ]);
    }

    protected function emails(Segment $segment): array
    {
        return app(SegmentQueryService::class)->query($segment)->orderBy('email')->pluck('email')->all();
    }

    public function test_opened_but_did_not_click_a_message(): void
    {
        $message = $this->message();
        $opener = $this->subscriber('otworzyl@example.com');
        $clicker = $this->subscriber('kliknal@example.com');
        $this->subscriber('nic@example.com');

        foreach ([$opener, $clicker] as $s) {
            EmailOpen::create(['message_id' => $message->id, 'subscriber_id' => $s->id, 'opened_at' => now()]);
        }
        EmailClick::create(['message_id' => $message->id, 'subscriber_id' => $clicker->id, 'url' => 'https://x.test', 'clicked_at' => now()]);

        $segment = $this->segment([
            ['type' => 'opened_message', 'message_id' => $message->id],
            ['type' => 'not_clicked_message', 'message_id' => $message->id],
        ]);

        $this->assertSame(['otworzyl@example.com'], $this->emails($segment));
    }

    public function test_engagement_windows_tags_lists_fields_and_any_match(): void
    {
        $vip = Tag::create(['user_id' => $this->user->id, 'name' => 'vip', 'color' => '#000000']);
        $course = ContactList::create(['user_id' => $this->user->id, 'name' => 'Kurs', 'type' => 'email']);
        $city = CustomField::create(['user_id' => $this->user->id, 'name' => 'miasto', 'label' => 'Miasto', 'type' => 'text', 'scope' => 'global']);

        $recent = $this->subscriber('swiezy@example.com', ['last_opened_at' => now()->subDays(3)]);
        $old = $this->subscriber('dawny@example.com', ['last_opened_at' => now()->subDays(90)]);
        $tagged = $this->subscriber('vip@example.com');
        $tagged->tags()->attach($vip->id);
        $student = $this->subscriber('kursant@example.com', [], $course);
        $warsaw = $this->subscriber('warszawa@example.com');
        $warsaw->fieldValues()->create(['custom_field_id' => $city->id, 'value' => 'Warszawa']);
        $this->subscriber('wypisany@example.com', ['last_opened_at' => now()->subDay()], null, 'unsubscribed');

        $this->assertSame(['swiezy@example.com'], $this->emails($this->segment([['type' => 'opened_within', 'days' => 30]])));
        $this->assertContains('dawny@example.com', $this->emails($this->segment([['type' => 'not_opened_within', 'days' => 30]])));
        $this->assertNotContains('swiezy@example.com', $this->emails($this->segment([['type' => 'not_opened_within', 'days' => 30]])));
        $this->assertSame(['vip@example.com'], $this->emails($this->segment([['type' => 'has_tag', 'tag_id' => $vip->id]])));
        $this->assertSame(['kursant@example.com'], $this->emails($this->segment([['type' => 'on_list', 'list_id' => $course->id]])));
        $this->assertSame(['warszawa@example.com'], $this->emails($this->segment([
            ['type' => 'field', 'custom_field_id' => $city->id, 'operator' => 'any_of', 'values' => ['Warszawa']],
        ])));

        $any = $this->segment([
            ['type' => 'has_tag', 'tag_id' => $vip->id],
            ['type' => 'on_list', 'list_id' => $course->id],
        ], 'any');
        $this->assertSame(['kursant@example.com', 'vip@example.com'], $this->emails($any));
    }

    public function test_a_message_with_a_segment_goes_only_to_list_members_in_it(): void
    {
        $vip = Tag::create(['user_id' => $this->user->id, 'name' => 'vip', 'color' => '#000000']);
        $in = $this->subscriber('w-segmencie@example.com');
        $in->tags()->attach($vip->id);
        $this->subscriber('poza@example.com');

        $otherList = ContactList::create(['user_id' => $this->user->id, 'name' => 'Inna', 'type' => 'email']);
        $elsewhere = $this->subscriber('inna-lista@example.com', [], $otherList);
        $elsewhere->tags()->attach($vip->id);

        $message = $this->message('Do VIP');
        $message->update(['segment_id' => $this->segment([['type' => 'has_tag', 'tag_id' => $vip->id]])->id]);

        $recipients = $message->fresh()->getUniqueRecipients()->pluck('email')->all();

        $this->assertSame(['w-segmencie@example.com'], $recipients);
    }

    public function test_segments_are_saved_previewed_and_kept_to_the_account(): void
    {
        $this->subscriber('a@example.com', ['last_clicked_at' => now()->subDay()]);
        $this->subscriber('b@example.com');

        $this->actingAs($this->user)
            ->postJson(route('segments.preview'), ['match' => 'all', 'conditions' => [['type' => 'clicked_within', 'days' => 7]]])
            ->assertOk()
            ->assertJson(['count' => 1, 'total' => 2, 'sample' => ['a@example.com']]);

        $this->actingAs($this->user)
            ->post(route('segments.store'), [
                'name' => 'Klikacze',
                'match' => 'all',
                'conditions' => [['type' => 'clicked_within', 'days' => 7, 'junk' => 'x']],
            ])
            ->assertRedirect(route('segments.index'));

        $segment = Segment::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame([['type' => 'clicked_within', 'days' => 7]], $segment->conditions);
        $this->assertSame(1, $segment->subscribers_count);

        // another account's list or a foreign segment are refused
        $stranger = User::factory()->create();
        $foreignList = ContactList::create(['user_id' => $stranger->id, 'name' => 'Obca', 'type' => 'email']);

        $this->actingAs($this->user)
            ->postJson(route('segments.preview'), ['conditions' => [['type' => 'on_list', 'list_id' => $foreignList->id]]])
            ->assertStatus(422);

        $this->actingAs($stranger)->get(route('segments.edit', $segment))->assertNotFound();
        $this->actingAs($stranger)->delete(route('segments.destroy', $segment))->assertNotFound();

        $this->actingAs($this->user)->get(route('segments.index'))->assertOk();
    }

    public function test_the_api_lists_segments_with_a_count(): void
    {
        $this->subscriber('a@example.com');
        $segment = $this->segment([['type' => 'on_list', 'list_id' => $this->list->id]]);

        $key = \App\Models\ApiKey::generate($this->user->id, 'Test Key', ['lists:read'])['key'];
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $key])->getJson('/api/v1/segments/' . $segment->id);

        $response->assertOk()->assertJsonPath('data.subscribers_count', 1);
    }
}
