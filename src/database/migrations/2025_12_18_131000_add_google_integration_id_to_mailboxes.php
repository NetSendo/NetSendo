<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\GoogleIntegration;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add google_integration_id to mailboxes
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->foreignId('google_integration_id')->nullable()->constrained('google_integrations')->nullOnDelete();
        });

        // 2. Migrate existing settings (if any) to a new GoogleIntegration record
        $clientId = Setting::where('key', 'google_client_id')->value('value');
        $clientSecret = Setting::where('key', 'google_client_secret')->value('value');

        if ($clientId && $clientSecret) {
            $user = \App\Models\User::first(); // Assign to first admin/user
            if ($user) {
                $integration = GoogleIntegration::create([
                    'user_id' => $user->id,
                    'name' => 'Domyślna Integracja (Zmigrowana)',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'status' => 'active',
                ]);

                // Update existing Gmail mailboxes to use this integration
                \App\Models\Mailbox::where('provider', 'gmail')->update([
                    'google_integration_id' => $integration->id
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropForeign(['google_integration_id']);
            $table->dropColumn('google_integration_id');
        });
    }
};
