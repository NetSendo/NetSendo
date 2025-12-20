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
        // AJAX request
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $form->success_message ?? 'Dziękujemy za zapisanie się!',
                'redirect' => $form->redirect_url,
            ]);
        }

        // Regular form submission
        if ($form->redirect_url) {
            return redirect()->away($form->redirect_url);
        }

        return redirect()->route('subscribe.success', $form->slug);
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
