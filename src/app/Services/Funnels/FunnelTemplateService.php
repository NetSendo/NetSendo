<?php

namespace App\Services\Funnels;

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class FunnelTemplateService
{
    /**
     * Export a funnel to a template.
     */
    public function exportToTemplate(Funnel $funnel, array $data = []): FunnelTemplate
    {
        $structure = $this->extractStructure($funnel);

        return FunnelTemplate::create([
            'user_id' => $data['user_id'] ?? Auth::id(),
            'name' => $data['name'] ?? $funnel->name . ' (Szablon)',
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? FunnelTemplate::CATEGORY_CUSTOM,
            'structure' => $structure,
            'is_public' => $data['is_public'] ?? false,
            'tags' => $data['tags'] ?? [],
        ]);
    }

    /**
     * Extract funnel structure for template storage.
     */
    protected function extractStructure(Funnel $funnel): array
    {
        $nodes = [];
        $edges = [];

        foreach ($funnel->steps as $step) {
            $nodes[] = [
                'id' => 'step-' . $step->id,
                'type' => $step->type,
                'position' => [
                    'x' => $step->position_x,
                    'y' => $step->position_y,
                ],
                'data' => [
                    'name' => $step->name,
                    'delay_value' => $step->delay_value,
                    'delay_unit' => $step->delay_unit,
                    'condition_type' => $step->condition_type,
                    'condition_config' => $step->condition_config,
                    'action_type' => $step->action_type,
                    'action_config' => $step->action_config,
                    'sms_content' => $step->sms_content,
                    'wait_until_type' => $step->wait_until_type,
                    'goal_type' => $step->goal_type,
                    'goal_name' => $step->goal_name,
                    'split_variants' => $step->split_variants,
                    // Note: message_id is NOT included - user must select their own emails
                ],
            ];

            // Build edges
            if ($step->next_step_id) {
                $edges[] = [
                    'id' => "e-{$step->id}-{$step->next_step_id}",
                    'source' => 'step-' . $step->id,
                    'target' => 'step-' . $step->next_step_id,
                    'sourceHandle' => 'default',
                ];
            }
            if ($step->next_step_yes_id) {
                $edges[] = [
                    'id' => "e-{$step->id}-{$step->next_step_yes_id}-yes",
                    'source' => 'step-' . $step->id,
                    'target' => 'step-' . $step->next_step_yes_id,
                    'sourceHandle' => 'yes',
                ];
            }
            if ($step->next_step_no_id) {
                $edges[] = [
                    'id' => "e-{$step->id}-{$step->next_step_no_id}-no",
                    'source' => 'step-' . $step->id,
                    'target' => 'step-' . $step->next_step_no_id,
                    'sourceHandle' => 'no',
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'settings' => $funnel->settings ?? [],
            'trigger_type' => $funnel->trigger_type,
        ];
    }

    /**
     * Create a funnel from a template.
     */
    public function createFromTemplate(FunnelTemplate $template, array $data): Funnel
    {
        return DB::transaction(function () use ($template, $data) {
            // Record usage
            $template->recordUsage();

            // Create the funnel
            $funnel = Funnel::create([
                'user_id' => $data['user_id'] ?? Auth::id(),
                'name' => $data['name'] ?? $template->name,
                'status' => Funnel::STATUS_DRAFT,
                'trigger_type' => $template->structure['trigger_type'] ?? Funnel::TRIGGER_LIST_SIGNUP,
                'trigger_list_id' => $data['trigger_list_id'] ?? null,
                'settings' => $template->structure['settings'] ?? [],
            ]);

            // Create steps from template structure
            $this->createStepsFromStructure($funnel, $template->structure);

            return $funnel->fresh('steps');
        });
    }

    /**
     * Create steps from template structure.
     */
    protected function createStepsFromStructure(Funnel $funnel, array $structure): void
    {
        $nodes = $structure['nodes'] ?? [];
        $edges = $structure['edges'] ?? [];

        // Create ID mapping (template ID -> new step ID)
        $idMapping = [];

        // First pass: create all steps
        foreach ($nodes as $index => $node) {
            $step = $funnel->steps()->create([
                'type' => $node['type'],
                'name' => $node['data']['name'] ?? null,
                'position_x' => $node['position']['x'] ?? 250,
                'position_y' => $node['position']['y'] ?? ($index * 120),
                'order' => $index,
                'delay_value' => $node['data']['delay_value'] ?? null,
                'delay_unit' => $node['data']['delay_unit'] ?? null,
                'condition_type' => $node['data']['condition_type'] ?? null,
                'condition_config' => $node['data']['condition_config'] ?? null,
                'action_type' => $node['data']['action_type'] ?? null,
                'action_config' => $node['data']['action_config'] ?? null,
                'sms_content' => $node['data']['sms_content'] ?? null,
                'wait_until_type' => $node['data']['wait_until_type'] ?? null,
                'goal_type' => $node['data']['goal_type'] ?? null,
                'goal_name' => $node['data']['goal_name'] ?? null,
                'split_variants' => $node['data']['split_variants'] ?? null,
            ]);

            $idMapping[$node['id']] = $step->id;
        }

        // Second pass: create connections
        foreach ($edges as $edge) {
            $sourceStepId = $idMapping[$edge['source']] ?? null;
            $targetStepId = $idMapping[$edge['target']] ?? null;

            if (!$sourceStepId || !$targetStepId) {
                continue;
            }

            $sourceStep = FunnelStep::find($sourceStepId);
            $handle = $edge['sourceHandle'] ?? 'default';

            if ($handle === 'yes') {
                $sourceStep->next_step_yes_id = $targetStepId;
            } elseif ($handle === 'no') {
                $sourceStep->next_step_no_id = $targetStepId;
            } else {
                $sourceStep->next_step_id = $targetStepId;
            }

            $sourceStep->save();
        }
    }

    /**
     * Get templates available for a user.
     */
    public function getAvailableTemplates(int $userId, ?string $category = null): Collection
    {
        $query = FunnelTemplate::forUser($userId)
            ->orderByDesc('is_featured')
            ->orderByDesc('uses_count');

        if ($category) {
            $query->byCategory($category);
        }

        return $query->get();
    }

    /**
     * Get featured templates.
     */
    public function getFeaturedTemplates(): Collection
    {
        return FunnelTemplate::public()
            ->featured()
            ->orderByDesc('uses_count')
            ->limit(6)
            ->get();
    }

    /**
     * Create pre-built system templates.
     */
    public function seedSystemTemplates(): void
    {
        $templates = [
            [
                'name' => 'Sekwencja powitalna',
                'category' => FunnelTemplate::CATEGORY_WELCOME,
                'description' => '5-krokowa sekwencja powitalna dla nowych subskrybentów',
                'is_public' => true,
                'is_featured' => true,
                'structure' => $this->getWelcomeSequenceStructure(),
            ],
            [
                'name' => 'Re-engagement',
                'category' => FunnelTemplate::CATEGORY_REENGAGEMENT,
                'description' => 'Reaktywacja nieaktywnych subskrybentów',
                'is_public' => true,
                'is_featured' => true,
                'structure' => $this->getReengagementStructure(),
            ],
            [
                'name' => 'Premiera produktu',
                'category' => FunnelTemplate::CATEGORY_LAUNCH,
                'description' => 'Kampania przed premierą z budowaniem napięcia',
                'is_public' => true,
                'is_featured' => true,
                'structure' => $this->getProductLaunchStructure(),
            ],
        ];

        foreach ($templates as $templateData) {
            FunnelTemplate::firstOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($templateData['name'])],
                $templateData
            );
        }
    }

    protected function getWelcomeSequenceStructure(): array
    {
        return [
            'nodes' => [
                ['id' => 'step-1', 'type' => 'start', 'position' => ['x' => 250, 'y' => 50], 'data' => ['name' => 'Start']],
                ['id' => 'step-2', 'type' => 'email', 'position' => ['x' => 250, 'y' => 150], 'data' => ['name' => 'Email powitalny']],
                ['id' => 'step-3', 'type' => 'delay', 'position' => ['x' => 250, 'y' => 250], 'data' => ['name' => 'Czekaj 2 dni', 'delay_value' => 2, 'delay_unit' => 'days']],
                ['id' => 'step-4', 'type' => 'email', 'position' => ['x' => 250, 'y' => 350], 'data' => ['name' => 'Przedstawienie oferty']],
                ['id' => 'step-5', 'type' => 'delay', 'position' => ['x' => 250, 'y' => 450], 'data' => ['name' => 'Czekaj 3 dni', 'delay_value' => 3, 'delay_unit' => 'days']],
                ['id' => 'step-6', 'type' => 'email', 'position' => ['x' => 250, 'y' => 550], 'data' => ['name' => 'Wartościowa treść']],
                ['id' => 'step-7', 'type' => 'end', 'position' => ['x' => 250, 'y' => 650], 'data' => ['name' => 'Koniec']],
            ],
            'edges' => [
                ['id' => 'e-1-2', 'source' => 'step-1', 'target' => 'step-2', 'sourceHandle' => 'default'],
                ['id' => 'e-2-3', 'source' => 'step-2', 'target' => 'step-3', 'sourceHandle' => 'default'],
                ['id' => 'e-3-4', 'source' => 'step-3', 'target' => 'step-4', 'sourceHandle' => 'default'],
                ['id' => 'e-4-5', 'source' => 'step-4', 'target' => 'step-5', 'sourceHandle' => 'default'],
                ['id' => 'e-5-6', 'source' => 'step-5', 'target' => 'step-6', 'sourceHandle' => 'default'],
                ['id' => 'e-6-7', 'source' => 'step-6', 'target' => 'step-7', 'sourceHandle' => 'default'],
            ],
            'trigger_type' => 'list_signup',
            'settings' => [],
        ];
    }

    protected function getReengagementStructure(): array
    {
        return [
            'nodes' => [
                ['id' => 'step-1', 'type' => 'start', 'position' => ['x' => 250, 'y' => 50], 'data' => ['name' => 'Start']],
                ['id' => 'step-2', 'type' => 'email', 'position' => ['x' => 250, 'y' => 150], 'data' => ['name' => 'Tęsknimy za Tobą']],
                ['id' => 'step-3', 'type' => 'delay', 'position' => ['x' => 250, 'y' => 250], 'data' => ['name' => 'Czekaj 3 dni', 'delay_value' => 3, 'delay_unit' => 'days']],
                ['id' => 'step-4', 'type' => 'condition', 'position' => ['x' => 250, 'y' => 350], 'data' => ['name' => 'Czy otworzył?', 'condition_type' => 'email_opened']],
                ['id' => 'step-5', 'type' => 'email', 'position' => ['x' => 100, 'y' => 450], 'data' => ['name' => 'Specjalna oferta']],
                ['id' => 'step-6', 'type' => 'email', 'position' => ['x' => 400, 'y' => 450], 'data' => ['name' => 'Ostatnia szansa']],
                ['id' => 'step-7', 'type' => 'end', 'position' => ['x' => 250, 'y' => 550], 'data' => ['name' => 'Koniec']],
            ],
            'edges' => [
                ['id' => 'e-1-2', 'source' => 'step-1', 'target' => 'step-2', 'sourceHandle' => 'default'],
                ['id' => 'e-2-3', 'source' => 'step-2', 'target' => 'step-3', 'sourceHandle' => 'default'],
                ['id' => 'e-3-4', 'source' => 'step-3', 'target' => 'step-4', 'sourceHandle' => 'default'],
                ['id' => 'e-4-5', 'source' => 'step-4', 'target' => 'step-5', 'sourceHandle' => 'yes'],
                ['id' => 'e-4-6', 'source' => 'step-4', 'target' => 'step-6', 'sourceHandle' => 'no'],
                ['id' => 'e-5-7', 'source' => 'step-5', 'target' => 'step-7', 'sourceHandle' => 'default'],
                ['id' => 'e-6-7', 'source' => 'step-6', 'target' => 'step-7', 'sourceHandle' => 'default'],
            ],
            'trigger_type' => 'tag_added',
            'settings' => [],
        ];
    }

    protected function getProductLaunchStructure(): array
    {
        return [
            'nodes' => [
                ['id' => 'step-1', 'type' => 'start', 'position' => ['x' => 250, 'y' => 50], 'data' => ['name' => 'Start']],
                ['id' => 'step-2', 'type' => 'email', 'position' => ['x' => 250, 'y' => 150], 'data' => ['name' => 'Zapowiedź']],
                ['id' => 'step-3', 'type' => 'delay', 'position' => ['x' => 250, 'y' => 250], 'data' => ['name' => 'Czekaj 2 dni', 'delay_value' => 2, 'delay_unit' => 'days']],
                ['id' => 'step-4', 'type' => 'email', 'position' => ['x' => 250, 'y' => 350], 'data' => ['name' => 'Sneak peek']],
                ['id' => 'step-5', 'type' => 'wait_until', 'position' => ['x' => 250, 'y' => 450], 'data' => ['name' => 'Do dnia premiery', 'wait_until_type' => 'specific_date']],
                ['id' => 'step-6', 'type' => 'email', 'position' => ['x' => 250, 'y' => 550], 'data' => ['name' => 'Premiera!']],
                ['id' => 'step-7', 'type' => 'goal', 'position' => ['x' => 250, 'y' => 650], 'data' => ['name' => 'Zakup', 'goal_type' => 'purchase']],
                ['id' => 'step-8', 'type' => 'end', 'position' => ['x' => 250, 'y' => 750], 'data' => ['name' => 'Koniec']],
            ],
            'edges' => [
                ['id' => 'e-1-2', 'source' => 'step-1', 'target' => 'step-2', 'sourceHandle' => 'default'],
                ['id' => 'e-2-3', 'source' => 'step-2', 'target' => 'step-3', 'sourceHandle' => 'default'],
                ['id' => 'e-3-4', 'source' => 'step-3', 'target' => 'step-4', 'sourceHandle' => 'default'],
                ['id' => 'e-4-5', 'source' => 'step-4', 'target' => 'step-5', 'sourceHandle' => 'default'],
                ['id' => 'e-5-6', 'source' => 'step-5', 'target' => 'step-6', 'sourceHandle' => 'default'],
                ['id' => 'e-6-7', 'source' => 'step-6', 'target' => 'step-7', 'sourceHandle' => 'default'],
                ['id' => 'e-7-8', 'source' => 'step-7', 'target' => 'step-8', 'sourceHandle' => 'default'],
            ],
            'trigger_type' => 'list_signup',
            'settings' => [],
        ];
    }
}
