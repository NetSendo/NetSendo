<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Per-agent Brain modes now decide everything an agent does, the advisory (manual) mode
 * included, and saving them stores only the agents that differ from the global mode.
 *
 * Saving the agent modes used to store a mode for every agent, freezing the global mode of that
 * moment: after a later switch of the global mode the agents kept the old one, and the header
 * showed a mode nobody was in. With a manual global mode the overrides had no effect on the
 * chat, which asked only the global mode whether to advise; they are cleared so these accounts
 * stay advisory. Elsewhere only the entries equal to the global mode are dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        $valid = ['autonomous', 'semi_auto', 'manual'];

        DB::table('ai_brain_settings')
            ->whereNotNull('agent_modes')
            ->orderBy('id')
            ->each(function ($row) use ($valid) {
                $modes = json_decode((string) $row->agent_modes, true);
                $modes = is_array($modes) ? $modes : [];

                if ($row->work_mode === 'manual') {
                    $modes = [];
                } else {
                    $modes = array_filter(
                        $modes,
                        fn ($mode) => in_array($mode, $valid, true) && $mode !== $row->work_mode,
                    );
                }

                DB::table('ai_brain_settings')
                    ->where('id', $row->id)
                    ->update(['agent_modes' => $modes ? json_encode($modes) : null]);
            });
    }

    public function down(): void
    {
        // The frozen copies of the global mode are not restored
    }
};
