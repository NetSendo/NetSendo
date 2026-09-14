<?php

namespace Tests\Feature;

use App\Events\SubscriberSignedUp;
use App\Events\TagAdded;
use App\Models\ContactList;
use App\Models\FormSubmission;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelSubscriber;
use App\Models\Subscriber;
use App\Models\SubscriptionForm;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\BuildsFunnels;
use Tests\TestCase;

/**
 * Nothing read a funnel's trigger: a funnel set to start on a list signup, a
 * form or a tag never enrolled anyone. EnrollInTriggeredFunnels does, on the
 * real events (the queue runs synchronously in tests).
 */
class FunnelTriggerEnrollmentTest extends TestCase
{
    use RefreshDatabase;
    use BuildsFunnels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFunnelOwner();
    }

    private function funnelWithEnd(array $attributes): Funnel
    {
        $funnel = $this->makeFunnel($attributes);
        $this->makeChain($funnel, $this->makeStep($funnel, FunnelStep::TYPE_END));

        return $funnel;
    }

    private function signUp(Subscriber $subscriber, ContactList $list, string $status = 'active', ?SubscriptionForm $form = null, string $source = 'manual'): void
    {
        $subscriber->contactLists()->syncWithoutDetaching([$list->id => ['status' => $status, 'subscribed_at' => now()]]);

        event(new SubscriberSignedUp($subscriber, $list, $form, $source));
    }

    private function enrollment(Funnel $funnel, Subscriber $subscriber): ?FunnelSubscriber
    {
        return FunnelSubscriber::where('funnel_id', $funnel->id)->where('subscriber_id', $subscriber->id)->first();
    }

    private function makeForm(ContactList $list): SubscriptionForm
    {
        return SubscriptionForm::create([
            'user_id' => $this->user->id,
            'contact_list_id' => $list->id,
            'name' => 'Signup form',
            'slug' => Str::random(12),
            'double_optin' => true,
        ]);
    }

    public function test_a_list_signup_enrolls_in_the_funnel_of_that_list(): void
    {
        $list = $this->makeList();
        $other = $this->makeList('Other');
        $funnel = $this->funnelWithEnd(['trigger_type' => Funnel::TRIGGER_LIST_SIGNUP, 'trigger_list_id' => $list->id]);
        $otherFunnel = $this->funnelWithEnd(['trigger_type' => Funnel::TRIGGER_LIST_SIGNUP, 'trigger_list_id' => $other->id]);

        $subscriber = $this->makeSubscriber();
        $this->signUp($subscriber, $list);

        $this->assertSame(FunnelSubscriber::STATUS_COMPLETED, $this->enrollment($funnel, $subscriber)?->status);
        $this->assertNull($this->enrollment($otherFunnel, $subscriber));
    }

    public function test_inactive_funnels_and_unconfirmed_memberships_enroll_nobody(): void
    {
        $list = $this->makeList();
        $paused = $this->funnelWithEnd(['trigger_type' => Funnel::TRIGGER_LIST_SIGNUP, 'trigger_list_id' => $list->id, 'status' => Funnel::STATUS_PAUSED]);
        $active = $this->funnelWithEnd(['trigger_type' => Funnel::TRIGGER_LIST_SIGNUP, 'trigger_list_id' => $list->id]);

        $pending = $this->makeSubscriber('pending@example.com');
        $this->signUp($pending, $list, 'pending');

        $member = $this->makeSubscriber();
        $this->signUp($member, $list);

        $this->assertNull($this->enrollment($active, $pending));
        $this->assertNull($this->enrollment($paused, $member));
        $this->assertNotNull($this->enrollment($active, $member));
    }

    public function test_a_subscriber_is_enrolled_in_a_funnel_once(): void
    {
        $list = $this->makeList();
        $funnel = $this->funnelWithEnd(['trigger_type' => Funnel::TRIGGER_LIST_SIGNUP, 'trigger_list_id' => $list->id]);

        $subscriber = $this->makeSubscriber();
        $this->signUp($subscriber, $list);
        $this->signUp($subscriber, $list, 'active', null, 'resubscribe');

        $this->assertSame(1, FunnelSubscriber::where('funnel_id', $funnel->id)->count());
        $this->assertSame(1, $funnel->fresh()->subscribers_count);
    }

    public function test_a_form_funnel_starts_on_its_form_or_on_confirming_a_signup_made_through_it(): void
    {
        $list = $this->makeList();
        $form = $this->makeForm($list);
        $otherForm = $this->makeForm($list);
        $funnel = $this->funnelWithEnd(['trigger_type' => Funnel::TRIGGER_FORM_SUBMIT, 'trigger_form_id' => $form->id]);

        $viaForm = $this->makeSubscriber('form@example.com');
        $this->signUp($viaForm, $list, 'active', $form, 'form');

        $viaOtherForm = $this->makeSubscriber('other-form@example.com');
        $this->signUp($viaOtherForm, $list, 'active', $otherForm, 'form');

        $manual = $this->makeSubscriber('manual@example.com');
        $this->signUp($manual, $list);

        // Double opt-in: the submission is stored, the signup event comes with the confirmation
        $confirmed = $this->makeSubscriber('confirmed@example.com');
        FormSubmission::create([
            'subscription_form_id' => $form->id,
            'subscriber_id' => $confirmed->id,
            'status' => 'pending',
            'submission_data' => ['email' => $confirmed->email],
        ]);
        $this->signUp($confirmed, $list, 'active', null, 'activation');

        $this->assertNotNull($this->enrollment($funnel, $viaForm));
        $this->assertNotNull($this->enrollment($funnel, $confirmed));
        $this->assertNull($this->enrollment($funnel, $viaOtherForm));
        $this->assertNull($this->enrollment($funnel, $manual));
    }

    public function test_adding_the_trigger_tag_enrolls_regardless_of_case(): void
    {
        $funnel = $this->funnelWithEnd(['trigger_type' => Funnel::TRIGGER_TAG_ADDED, 'trigger_tag' => 'Customer']);
        $subscriber = $this->makeSubscriber();

        $subscriber->addTag(Tag::create(['user_id' => $this->user->id, 'name' => 'lead']));
        $this->assertNull($this->enrollment($funnel, $subscriber));

        $subscriber->addTag(Tag::create(['user_id' => $this->user->id, 'name' => 'customer']));
        $this->assertNotNull($this->enrollment($funnel, $subscriber));
    }

    public function test_another_accounts_tag_funnel_is_not_started(): void
    {
        $funnel = $this->funnelWithEnd(['trigger_type' => Funnel::TRIGGER_TAG_ADDED, 'trigger_tag' => 'customer']);

        $stranger = \App\Models\User::factory()->create();
        $subscriber = Subscriber::create(['user_id' => $stranger->id, 'email' => 'stranger@example.com', 'status' => 'active', 'is_active_global' => true]);

        event(new TagAdded($subscriber, Tag::create(['user_id' => $stranger->id, 'name' => 'customer'])));

        $this->assertNull($this->enrollment($funnel, $subscriber));
    }
}
