<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\CustomField;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InsertController extends Controller
{
    /**
     * Display inserts and signatures list with system variables.
     */
    public function index()
    {
        $user = auth()->user();

        // Get user's inserts
        $inserts = Template::where('user_id', $user->id)
            ->inserts()
            ->orderBy('name')
            ->get();

        // Get user's signatures
        $signatures = Template::where('user_id', $user->id)
            ->signatures()
            ->orderBy('name')
            ->get();

        // Get system variables
        $systemVariables = $this->getSystemVariables();

        // Get custom fields for dynamic variables
        $customFields = CustomField::orderBy('label')->get();

        return Inertia::render('Template/Inserts', [
            'inserts' => $inserts,
            'signatures' => $signatures,
            'systemVariables' => $systemVariables,
            'customFields' => $customFields,
        ]);
    }

    /**
     * Store a new insert or signature.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'type' => 'required|in:insert,signature',
            'content' => 'nullable|string',
            'content_plain' => 'nullable|string',
        ]);

        $validated['user_id'] = auth()->id();

        Template::create($validated);

        return redirect()->route('inserts.index')
            ->with('success', __('inserts.created'));
    }

    /**
     * Update an insert or signature.
     */
    public function update(Request $request, Template $template)
    {
        // Check ownership
        if ($template->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'type' => 'required|in:insert,signature',
            'content' => 'nullable|string',
            'content_plain' => 'nullable|string',
        ]);

        $template->update($validated);

        return redirect()->route('inserts.index')
            ->with('success', __('inserts.updated'));
    }

    /**
     * Delete an insert or signature.
     */
    public function destroy(Template $template)
    {
        // Check ownership
        if ($template->user_id !== auth()->id()) {
            abort(403);
        }

        $template->delete();

        return redirect()->route('inserts.index')
            ->with('success', __('inserts.deleted'));
    }

    /**
     * Get available system variables for message personalization.
     */
    public function variables()
    {
        return response()->json([
            'system' => $this->getSystemVariables(),
            'custom' => CustomField::orderBy('label')->get()->map(fn($f) => [
                'code' => '[[' . $f->name . ']]',
                'label' => $f->label,
                'description' => $f->description,
            ]),
        ]);
    }

    /**
     * Get system variables definition.
     */
    private function getSystemVariables(): array
    {
        return [
            // Subscriber data
            [
                'category' => 'subscriber',
                'label' => __('inserts.variables.subscriber_data'),
                'variables' => [
                    ['code' => '[[fname]]', 'label' => __('inserts.variables.first_name'), 'description' => __('inserts.variables.first_name_desc')],
                    ['code' => '[[!fname]]', 'label' => __('inserts.variables.first_name_vocative'), 'description' => __('inserts.variables.first_name_vocative_desc')],
                    ['code' => '[[lname]]', 'label' => __('inserts.variables.last_name'), 'description' => __('inserts.variables.last_name_desc')],
                    ['code' => '[[email]]', 'label' => __('inserts.variables.email'), 'description' => __('inserts.variables.email_desc')],
                    ['code' => '[[phone]]', 'label' => __('inserts.variables.phone'), 'description' => __('inserts.variables.phone_desc')],
                    ['code' => '[[sex]]', 'label' => __('inserts.variables.sex'), 'description' => __('inserts.variables.sex_desc')],
                ],
            ],
            // Links
            [
                'category' => 'links',
                'label' => __('inserts.variables.links'),
                'variables' => [
                    ['code' => '[[unsubscribe]]', 'label' => __('inserts.variables.unsubscribe_link'), 'description' => __('inserts.variables.unsubscribe_link_desc')],
                    ['code' => '[[manage]]', 'label' => __('inserts.variables.manage_link'), 'description' => __('inserts.variables.manage_link_desc')],
                ],
            ],
            // Dates
            [
                'category' => 'dates',
                'label' => __('inserts.variables.dates'),
                'variables' => [
                    ['code' => '[[system-created]]', 'label' => __('inserts.variables.account_created'), 'description' => __('inserts.variables.account_created_desc')],
                    ['code' => '[[last-message]]', 'label' => __('inserts.variables.last_message'), 'description' => __('inserts.variables.last_message_desc')],
                    ['code' => '[[list-created]]', 'label' => __('inserts.variables.list_signup'), 'description' => __('inserts.variables.list_signup_desc')],
                    ['code' => '[[list-activated]]', 'label' => __('inserts.variables.list_activated'), 'description' => __('inserts.variables.list_activated_desc')],
                    ['code' => '[[list-source]]', 'label' => __('inserts.variables.signup_source'), 'description' => __('inserts.variables.signup_source_desc')],
                ],
            ],
            // System
            [
                'category' => 'system',
                'label' => __('inserts.variables.system'),
                'variables' => [
                    ['code' => '[[system-status]]', 'label' => __('inserts.variables.system_status'), 'description' => __('inserts.variables.system_status_desc')],
                    ['code' => '[[list-status]]', 'label' => __('inserts.variables.list_status'), 'description' => __('inserts.variables.list_status_desc')],
                    ['code' => '[[ip]]', 'label' => __('inserts.variables.ip_address'), 'description' => __('inserts.variables.ip_address_desc')],
                ],
            ],
            // Special syntax
            [
                'category' => 'special',
                'label' => __('inserts.variables.special'),
                'variables' => [
                    ['code' => '{{męska|żeńska}}', 'label' => __('inserts.variables.gender_form'), 'description' => __('inserts.variables.gender_form_desc')],
                ],
            ],
        ];
    }
}
