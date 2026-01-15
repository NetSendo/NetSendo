<?php

use App\Models\Name;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update Czech names with specific vocatives
        $czechNames = [
            'jan' => 'jane',
            'petr' => 'petře',
            'pavel' => 'pavle',
            'martin' => 'martine',
            'tomáš' => 'tomáši',
            'jakub' => 'jakube',
            'jiří' => 'jiří',
            'lukáš' => 'lukáši',
            'david' => 'davide',
            'ondřej' => 'ondřeji',
            'filip' => 'filipe',
            'michal' => 'michale',
            'adam' => 'adame',
            'marek' => 'marku',
            'vojtěch' => 'vojtěchu',
            'daniel' => 'danieli',
            'václav' => 'václave',
            'josef' => 'josefe',
            'františek' => 'františku',
            'karel' => 'karle',
            'jaroslav' => 'jaroslave',
            'miroslav' => 'miroslave',
            'zdeněk' => 'zdeňku',
            'milan' => 'milane',
            'vladimír' => 'vladimíre',
            'radek' => 'radku',
            'roman' => 'romane',
            'stanislav' => 'stanislave',
            'ladislav' => 'ladislave',
            'aleš' => 'aleši',
            'patrik' => 'patriku',
            'matěj' => 'matěji',
            'marie' => 'marie',
            'jana' => 'jano',
            'eva' => 'evo',
            'anna' => 'anno',
            'hana' => 'hano',
            'petra' => 'petro',
            'lenka' => 'lenko',
            'kateřina' => 'kateřino',
            'lucie' => 'lucie',
            'veronika' => 'veroniko',
            'martina' => 'martino',
            'michaela' => 'michaelo',
            'tereza' => 'terezo',
            'markéta' => 'markéto',
            'barbora' => 'barboro',
            'kristýna' => 'kristýno',
            'eliška' => 'eliško',
            'adéla' => 'adélo',
            'natalie' => 'natalie',
            'simona' => 'simono',
            'denisa' => 'deniso',
            'monika' => 'moniko',
            'ivana' => 'ivano',
            'zuzana' => 'zuzano',
            'jitka' => 'jitko',
            'alena' => 'aleno',
            'jaroslava' => 'jaroslavo',
            'ludmila' => 'ludmilo',
            'dana' => 'dano',
            'irena' => 'ireno',
            'věra' => 'věro',
            'dagmar' => 'dagmar',
        ];

        foreach ($czechNames as $name => $vocative) {
            Name::where('country', 'CZ')
                ->where('name', $name)
                ->update(['vocative' => $vocative]);
        }

        // 2. For all other non-Polish names (including SK) where vocative is null, set vocative = name
        Name::where('country', '!=', 'PL')
            ->whereNull('vocative')
            ->update(['vocative' => \Illuminate\Support\Facades\DB::raw('name')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We generally don't want to nullify vocatives on rollback as it destroys data,
        // but strictly speaking, reverse would be setting them back to null for non-PL/CZ.
        // Or just leave them.
    }
};
