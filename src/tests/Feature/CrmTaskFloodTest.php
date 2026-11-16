<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\CrmFollowUpEnrollment;
use App\Models\CrmFollowUpSequence;
use App\Models\CrmFollowUpStep;
use App\Models\CrmTask;
use App\Models\Subscriber;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seen on a live account (2.11.4): 5,539 overdue CRM tasks, 84 % of them made
 * by follow-up sequences for ~1,100 contacts since February and never done.
 * - "After the meeting" (on_task_completed) enrolled a contact after ANY
 *   completed task, its own included, so it fed itself.
 * - Contacts of a CRM file import were all enrolled in "on contact created"
 *   sequences, three tasks each.
 * - Converting a subscriber in an automation raised "contact created" twice.
 */
class CrmTaskFloodTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'UTC'));
        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function contact(string $email): CrmContact
    {
        $subscriber = Subscriber::create(['user_id' => $this->user->id, 'email' => $email, 'status' => 'active', 'is_active_global' => true]);

        return CrmContact::create(['subscriber_id' => $subscriber->id, 'user_id' => $this->user->id, 'status' => 'lead']);
    }

    protected function sequence(string $trigger, ?array $conditions = null): CrmFollowUpSequence
    {
        $sequence = CrmFollowUpSequence::create([
            'user_id' => $this->user->id,
            'name' => $trigger,
            'trigger_type' => $trigger,
            'trigger_conditions' => $conditions,
            'is_active' => true,
        ]);
        CrmFollowUpStep::create(['sequence_id' => $sequence->id, 'position' => 0, 'delay_days' => 1, 'delay_hours' => 0, 'action_type' => 'task', 'task_title' => 'Krok']);

        return $sequence;
    }

    protected function enrolled(CrmFollowUpSequence $sequence, CrmContact $contact): bool
    {
        return CrmFollowUpEnrollment::where('sequence_id', $sequence->id)->where('crm_contact_id', $contact->id)->exists();
    }

    public function test_after_meeting_runs_after_meetings_only_and_not_after_its_own_tasks(): void
    {
        $afterMeeting = $this->sequence('on_task_completed', ['task_type' => 'meeting']);
        $contact = $this->contact('a@example.com');

        $call = CrmTask::create(['user_id' => $this->user->id, 'crm_contact_id' => $contact->id, 'title' => 'Telefon', 'type' => 'call', 'status' => 'pending', 'priority' => 'medium']);
        $call->complete();
        $this->assertFalse($this->enrolled($afterMeeting, $contact), 'a call is not a meeting');

        $meeting = CrmTask::create(['user_id' => $this->user->id, 'crm_contact_id' => $contact->id, 'title' => 'Spotkanie', 'type' => 'meeting', 'status' => 'pending', 'priority' => 'medium']);
        $meeting->complete();
        $this->assertTrue($this->enrolled($afterMeeting, $contact));

        // a meeting task that a sequence created does not start another sequence
        $other = $this->contact('b@example.com');
        $enrollment = CrmFollowUpEnrollment::where('sequence_id', $afterMeeting->id)->first();
        $sequenceTask = CrmTask::create([
            'user_id' => $this->user->id, 'crm_contact_id' => $other->id, 'title' => 'Spotkanie z sekwencji',
            'type' => 'meeting', 'status' => 'pending', 'priority' => 'medium', 'follow_up_enrollment_id' => $enrollment->id,
        ]);
        $sequenceTask->complete();
        $this->assertFalse($this->enrolled($afterMeeting, $other));
    }

    public function test_imported_contacts_are_not_enrolled_in_contact_created_sequences(): void
    {
        $nurture = $this->sequence('on_contact_created');

        CrmContact::$creatingInBulk = true;
        try {
            $imported = $this->contact('import@example.com');
        } finally {
            CrmContact::$creatingInBulk = false;
        }
        $manual = $this->contact('reczny@example.com');

        $this->assertFalse($this->enrolled($nurture, $imported));
        $this->assertTrue($this->enrolled($nurture, $manual));
    }

    public function test_stale_sequence_tasks_close_in_one_go_and_their_sequences_stop(): void
    {
        $sequence = $this->sequence('manual');
        $contact = $this->contact('c@example.com');
        $enrollment = CrmFollowUpEnrollment::create([
            'sequence_id' => $sequence->id, 'crm_contact_id' => $contact->id, 'status' => 'active',
            'started_at' => now()->subMonths(3), 'next_action_at' => now()->addDay(), 'steps_completed' => 1,
        ]);

        $base = ['user_id' => $this->user->id, 'crm_contact_id' => $contact->id, 'type' => 'email', 'status' => 'pending', 'priority' => 'medium'];
        $stale = CrmTask::create($base + ['title' => 'Stare z sekwencji', 'due_date' => now()->subDays(60), 'follow_up_enrollment_id' => $enrollment->id]);
        $recent = CrmTask::create($base + ['title' => 'Świeże z sekwencji', 'due_date' => now()->subDays(3), 'follow_up_enrollment_id' => $enrollment->id]);
        $manual = CrmTask::create($base + ['title' => 'Ręczne', 'due_date' => now()->subDays(60)]);

        $this->get(route('crm.tasks.index', ['view' => 'overdue']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('counts.stale_automatic', 1));

        $this->post(route('crm.tasks.close-stale'))->assertRedirect();

        $this->assertSame('cancelled', $stale->fresh()->status);
        $this->assertSame('pending', $recent->fresh()->status);
        $this->assertSame('pending', $manual->fresh()->status);
        $this->assertSame('cancelled', $enrollment->fresh()->status);
    }
}
