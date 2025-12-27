<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionForm;
use App\Services\Forms\FormBuilderService;
use App\Services\Forms\FormSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PublicFormController extends Controller
{
    protected FormBuilderService $formBuilder;
    protected FormSubmissionService $submissionService;

    public function __construct(FormBuilderService $formBuilder, FormSubmissionService $submissionService)
    {
        $this->formBuilder = $formBuilder;
        $this->submissionService = $submissionService;
    }

    /**
     * Display the form in iframe mode.
     */
    public function show(string $slug)
    {
        $form = $this->findActiveForm($slug);

        if (!$form) {
            return response()->view('forms.not-found', [], 404);
        }

        $html = $this->formBuilder->generateHtmlCode($form);

        return view('forms.embed', [
            'form' => $form,
            'html' => $html,
        ]);
    }

    /**
     * Process form submission.
     */
    public function submit(Request $request, string $slug)
    {
        $form = $this->findActiveForm($slug);

        if (!$form) {
            return $this->errorResponse($request, 'Formularz nie istnieje lub jest nieaktywny', 404);
        }

        try {
            $submission = $this->submissionService->processSubmission(
                $form,
                $request->all(),
                $request
            );

            if ($submission->isSuccessful()) {
                return $this->successResponse($request, $form, $submission);
            } else {
                return $this->errorResponse($request, $submission->error_message ?? 'Wystąpił błąd podczas zapisu');
            }

        } catch (\Exception $e) {
            Log::error('Form submission exception', [
                'form_id' => $form->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse($request, 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie.');
        }
    }

    /**
     * Return JavaScript embed script.
     */
    public function javascript(string $slug)
    {
        $form = SubscriptionForm::where('slug', $slug)->first();

        if (!$form) {
            return response('console.error("NetSendo: Form not found");', 404)
                ->header('Content-Type', 'application/javascript');
        }

        $js = $this->formBuilder->generateDynamicJs($form);

        return response($js)
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'public, max-age=300'); // 5 min cache
    }

    /**
     * Success page.
     */
    public function success(string $slug)
    {
        $form = SubscriptionForm::where('slug', $slug)->first();

        $message = $form->success_message ?? 'Dziękujemy za zapisanie się na naszą listę!';

        return view('forms.success', [
            'form' => $form,
            'message' => $message,
        ]);
    }

    /**
     * Error page.
     */
    public function error(Request $request, string $slug)
    {
        $form = SubscriptionForm::where('slug', $slug)->first();

        $message = $request->get('message', $form->error_message ?? 'Wystąpił błąd podczas zapisu.');

        return view('forms.error', [
            'form' => $form,
            'message' => $message,
        ]);
    }

    /**
     * Find active form by slug.
     */
    protected function findActiveForm(string $slug): ?SubscriptionForm
    {
        return SubscriptionForm::where('slug', $slug)
            ->where('status', 'active')
            ->with('contactList')
            ->first();
    }

    /**
     * Return success response (AJAX or redirect).
     */
    protected function successResponse(Request $request, SubscriptionForm $form, $submission)
    {
        // Get redirect settings with priority (form -> list -> global)
        $redirectSettings = $this->determineRedirectSettings($form);
        $redirectUrl = $redirectSettings['url'];
        $successMessage = $redirectSettings['message'];

        // AJAX request
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'redirect' => $redirectUrl,
            ]);
        }

        // Regular form submission
        if ($redirectUrl) {
            return redirect()->away($redirectUrl);
        }

        return redirect()->route('subscribe.success', $form->slug);
    }

    /**
     * Determine redirect URL with priority: form -> list -> global
     */
    protected function determineRedirectSettings(SubscriptionForm $form): array
    {
        // Priority 1: Form has custom redirect (and not using list redirect)
        if (!$form->use_list_redirect && $form->redirect_url) {
            return [
                'url' => $form->redirect_url,
                'message' => $form->success_message ?? 'Dziękujemy za zapisanie się!',
            ];
        }

        // Priority 2: List settings
        $listSettings = $form->contactList->settings ?? [];
        $useDoubleOptin = $form->shouldUseDoubleOptin();

        if ($useDoubleOptin) {
            $pageConfig = $listSettings['pages']['confirmation'] ?? null;
            $message = $listSettings['confirmation_message'] ?? 'Sprawdź swoją skrzynkę email, aby potwierdzić subskrypcję.';
        } else {
            $pageConfig = $listSettings['pages']['success'] ?? null;
            $message = $listSettings['thank_you_message'] ?? $form->success_message ?? 'Dziękujemy za zapisanie się!';
        }

        if ($pageConfig) {
            $url = $this->resolvePageUrl($pageConfig);
            if ($url) {
                return [
                    'url' => $url,
                    'message' => $message,
                ];
            }
        }

        // Priority 3: Global user settings
        $userDefaults = $form->user->settings['form_defaults'] ?? [];

        if (!empty($userDefaults['redirect_url'])) {
            return [
                'url' => $userDefaults['redirect_url'],
                'message' => $userDefaults['success_message'] ?? 'Dziękujemy za zapisanie się!',
            ];
        }

        // Fallback: no redirect, use internal success page
        return [
            'url' => null,
            'message' => $form->success_message ?? 'Dziękujemy za zapisanie się!',
        ];
    }

    /**
     * Resolve page URL from config (external page or custom URL)
     */
    protected function resolvePageUrl(?array $pageConfig): ?string
    {
        if (!$pageConfig) {
            return null;
        }

        $type = $pageConfig['type'] ?? 'system';

        if ($type === 'external' && !empty($pageConfig['external_page_id'])) {
            return route('page.show', ['externalPage' => $pageConfig['external_page_id']]);
        }

        if ($type === 'custom' && !empty($pageConfig['url'])) {
            return $pageConfig['url'];
        }

        return null;
    }

    /**
     * Return error response (AJAX or redirect).
     */
    protected function errorResponse(Request $request, string $message, int $status = 422)
    {
        // AJAX request
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        // Regular form submission - redirect to error page
        return redirect()
            ->back()
            ->withErrors(['form' => $message])
            ->withInput();
    }
}

