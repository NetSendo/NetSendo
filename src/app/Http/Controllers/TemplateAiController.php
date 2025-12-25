<?php

namespace App\Http\Controllers;

use App\Services\TemplateAiService;
use Illuminate\Http\Request;

class TemplateAiController extends Controller
{
    protected TemplateAiService $aiService;

    public function __construct(TemplateAiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Generate content for a block
     */
    public function generateContent(Request $request)
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:1000',
            'block_type' => 'required|string',
            'tone' => 'nullable|string|in:formal,casual,persuasive',
            'integration_id' => 'nullable|integer|exists:ai_integrations,id',
            'model_id' => 'nullable|string|max:255',
        ]);

        try {
            $content = $this->aiService->generateContent(
                $validated['prompt'],
                $validated['block_type'],
                $validated['tone'] ?? 'casual',
                $validated['integration_id'] ?? null,
                $validated['model_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'content' => $content,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate entire email section
     */
    public function generateSection(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:1000',
            'section_type' => 'nullable|string|in:promotional,welcome,product,newsletter',
            'integration_id' => 'nullable|integer|exists:ai_integrations,id',
            'model_id' => 'nullable|string|max:255',
        ]);

        try {
            $section = $this->aiService->generateSection(
                $validated['description'],
                $validated['section_type'] ?? 'promotional',
                $validated['integration_id'] ?? null,
                $validated['model_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'section' => $section,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Improve existing text
     */
    public function improveText(Request $request)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:5000',
            'tone' => 'nullable|string|in:formal,casual,persuasive',
            'action' => 'nullable|string|in:improve,shorten,expand,fix_grammar',
        ]);

        try {
            $improved = $this->aiService->improveText(
                $validated['text'],
                $validated['tone'] ?? 'casual',
                $validated['action'] ?? 'improve'
            );

            return response()->json([
                'success' => true,
                'content' => $improved,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate message content (text fragment or full template)
     * Supports two modes: 'text' for inserting fragments, 'template' for full email generation/modification
     */
    public function generateMessageContent(Request $request)
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'mode' => 'required|string|in:text,template',
            'current_content' => 'nullable|string|max:50000',
            'tone' => 'nullable|string|in:formal,casual,persuasive',
            'with_formatting' => 'nullable|boolean',
            'integration_id' => 'nullable|integer|exists:ai_integrations,id',
            'model_id' => 'nullable|string|max:255',
            'placeholders' => 'nullable|array',
            'placeholders.*.name' => 'required_with:placeholders|string|max:100',
            'placeholders.*.label' => 'required_with:placeholders|string|max:255',
            'placeholders.*.description' => 'nullable|string|max:500',
        ]);

        try {
            $content = $this->aiService->generateMessageContent(
                $validated['prompt'],
                $validated['mode'],
                $validated['current_content'] ?? null,
                $validated['tone'] ?? 'casual',
                $validated['with_formatting'] ?? true,
                $validated['integration_id'] ?? null,
                $validated['model_id'] ?? null,
                $validated['placeholders'] ?? []
            );

            return response()->json([
                'success' => true,
                'content' => $content,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate SMS content suggestions
     * Generates 1 or 3 SMS content proposals based on user prompt
     */
    public function generateSmsContent(Request $request)
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'count' => 'nullable|integer|in:1,3',
            'tone' => 'nullable|string|in:formal,casual,persuasive',
            'integration_id' => 'nullable|integer|exists:ai_integrations,id',
            'model_id' => 'nullable|string|max:255',
            'placeholders' => 'nullable|array',
            'placeholders.*.name' => 'required_with:placeholders|string|max:100',
            'placeholders.*.label' => 'nullable|string|max:255',
            'placeholders.*.description' => 'nullable|string|max:500',
        ]);

        try {
            $suggestions = $this->aiService->generateSmsContent(
                $validated['prompt'],
                $validated['count'] ?? 1,
                $validated['tone'] ?? 'casual',
                $validated['integration_id'] ?? null,
                $validated['model_id'] ?? null,
                $validated['placeholders'] ?? []
            );

            return response()->json([
                'success' => true,
                'suggestions' => $suggestions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate email subject lines
     */
    public function generateSubject(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:200000',
            'count' => 'nullable|integer|min:1|max:5',
            'hint' => 'nullable|string|max:500',
            'integration_id' => 'nullable|integer|exists:ai_integrations,id',
            'model_id' => 'nullable|string|max:255',
        ]);

        try {
            \Log::info('AI Subject generation request', [
                'content_length' => strlen($validated['content']),
                'integration_id' => $validated['integration_id'] ?? null,
                'model_id' => $validated['model_id'] ?? null,
            ]);

            $subjects = $this->aiService->generateSubjectLine(
                $validated['content'],
                $validated['count'] ?? 3,
                $validated['hint'] ?? null,
                $validated['integration_id'] ?? null,
                $validated['model_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'subjects' => $subjects,
            ]);
        } catch (\Exception $e) {
            \Log::error('AI Subject generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate product description
     */
    public function generateProductDescription(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'features' => 'nullable|string|max:500',
        ]);

        try {
            $description = $this->aiService->generateProductDescription($validated);

            return response()->json([
                'success' => true,
                'product' => $description,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get design suggestions for template
     */
    public function suggestImprovements(Request $request)
    {
        $validated = $request->validate([
            'structure' => 'required|array',
        ]);

        try {
            $suggestions = $this->aiService->suggestDesignImprovements($validated['structure']);

            return response()->json([
                'success' => true,
                'suggestions' => $suggestions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'suggestions' => [],
            ]);
        }
    }

    /**
     * Check if AI is available
     */
    public function checkAvailability()
    {
        return response()->json([
            'available' => $this->aiService->isAvailable(),
        ]);
    }
}
