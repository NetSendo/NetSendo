<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\ContactList;
use App\Models\Funnel;
use App\Models\SubscriptionForm;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The lists of forms, funnels, automations and templates: the tabs carry counts for the whole
 * account, the reader chooses the page size, and the page links keep the filters.
 */
class ToolListsIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    private function form(string $name, string $status, ?User $owner = null): SubscriptionForm
    {
        $owner ??= $this->user;
        $list = ContactList::factory()->create(['user_id' => $owner->id, 'type' => 'email']);

        return SubscriptionForm::create([
            'user_id' => $owner->id,
            'contact_list_id' => $list->id,
            'name' => $name,
            'status' => $status,
            'type' => 'inline',
            'fields' => [],
        ]);
    }

    private function rule(string $name, bool $active, array $extra = [], ?User $owner = null): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'user_id' => ($owner ?? $this->user)->id,
            'name' => $name,
            'trigger_event' => 'subscriber_signup',
            'trigger_config' => [],
            'conditions' => [],
            'actions' => [['type' => 'add_tag', 'config' => []]],
            'is_active' => $active,
        ], $extra));
    }

    public function test_forms_tabs_count_the_whole_account_whatever_the_filter(): void
    {
        $this->form('Newsletter', 'active');
        $this->form('Webinar', 'active');
        $this->form('Old popup', 'disabled');
        $this->form('Idea', 'draft');
        $this->form('Someone else', 'active', User::factory()->create());

        $this->actingAs($this->user)
            ->get(route('forms.index', ['status' => 'disabled']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Forms/Index')
                ->has('forms.data', 1)
                ->where('forms.data.0.name', 'Old popup')
                ->where('counts', ['all' => 4, 'active' => 2, 'draft' => 1, 'disabled' => 1])
                ->where('filters.status', 'disabled'));
    }

    public function test_forms_page_size_is_the_readers_choice_within_limits(): void
    {
        foreach (range(1, 12) as $i) {
            $this->form("Form {$i}", 'active');
        }

        $this->actingAs($this->user)
            ->get(route('forms.index', ['per_page' => 10]))
            ->assertInertia(fn (Assert $page) => $page->has('forms.data', 10)->where('forms.total', 12));

        $this->actingAs($this->user)
            ->get(route('forms.index', ['per_page' => 100000]))
            ->assertInertia(fn (Assert $page) => $page->where('forms.per_page', 100));
    }

    public function test_funnels_tabs_count_the_whole_account_whatever_the_filter(): void
    {
        Funnel::factory()->create(['user_id' => $this->user->id, 'name' => 'Welcome', 'status' => Funnel::STATUS_ACTIVE]);
        Funnel::factory()->create(['user_id' => $this->user->id, 'name' => 'Winback', 'status' => Funnel::STATUS_PAUSED]);
        Funnel::factory()->create(['user_id' => $this->user->id, 'name' => 'Sketch', 'status' => Funnel::STATUS_DRAFT]);
        Funnel::factory()->create(['user_id' => User::factory()->create()->id, 'status' => Funnel::STATUS_ACTIVE]);

        $this->actingAs($this->user)
            ->get(route('funnels.index', ['status' => Funnel::STATUS_PAUSED]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Funnels/Index')
                ->has('funnels.data', 1)
                ->where('funnels.data.0.name', 'Winback')
                ->where('counts', ['all' => 3, 'active' => 1, 'paused' => 1, 'draft' => 1]));
    }

    public function test_automations_list_marks_defaults_and_counts_the_tabs(): void
    {
        $this->rule('Tag buyers', true, ['is_system' => true, 'system_key' => 'tag_buyers']);
        $this->rule('Say hello', true);
        $this->rule('Parked', false);
        $this->rule('Someone else', true, [], User::factory()->create());

        $this->actingAs($this->user)
            ->get(route('automations.index', ['status' => 'active']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Automations/Index')
                ->has('rules.data', 2)
                ->where('counts', ['all' => 3, 'active' => 2, 'inactive' => 1])
                ->where('rules.data', fn ($rows) => collect($rows)->firstWhere('name', 'Tag buyers')['is_system'] === true
                    && collect($rows)->firstWhere('name', 'Say hello')['is_system'] === false));
    }

    public function test_automations_page_links_keep_the_filters(): void
    {
        foreach (range(1, 12) as $i) {
            $this->rule("Rule {$i}", true);
        }
        $this->rule('Parked', false);

        $this->actingAs($this->user)
            ->get(route('automations.index', ['status' => 'active', 'per_page' => 10]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rules.data', 10)
                ->where('rules.total', 12)
                ->where('rules.next_page_url', fn ($url) => str_contains($url, 'status=active')
                    && str_contains($url, 'per_page=10')
                    && str_contains($url, 'page=2')));
    }

    public function test_templates_page_size_is_the_readers_choice_and_links_keep_the_search(): void
    {
        foreach (range(1, 14) as $i) {
            Template::create(['user_id' => $this->user->id, 'name' => "Promo {$i}"]);
        }
        Template::create(['user_id' => $this->user->id, 'name' => 'Welcome']);

        $this->actingAs($this->user)
            ->get(route('templates.index', ['search' => 'Promo']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Template/Index')
                ->has('templates.data', 12)
                ->where('templates.total', 14)
                ->where('templates.next_page_url', fn ($url) => str_contains($url, 'search=Promo')));

        $this->actingAs($this->user)
            ->get(route('templates.index', ['per_page' => 24]))
            ->assertInertia(fn (Assert $page) => $page->has('templates.data', 15));
    }

    public function test_the_no_op_outcome_of_restoring_defaults_reaches_the_page(): void
    {
        $this->actingAs($this->user)
            ->from(route('automations.index'))
            ->post(route('automations.restore-defaults'))
            ->assertRedirect(route('automations.index'));

        // Everything is in place now: the second run has nothing to add and says so
        $this->actingAs($this->user)
            ->from(route('automations.index'))
            ->post(route('automations.restore-defaults'))
            ->assertSessionHas('info');

        $this->actingAs($this->user)
            ->withSession(['info' => 'Nothing to restore'])
            ->get(route('automations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('flash.info', 'Nothing to restore'));
    }
}
