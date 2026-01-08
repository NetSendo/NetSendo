<?php

namespace Database\Seeders;

use App\Models\Name;
use Illuminate\Database\Seeder;

class PolishNamesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds common Polish first names with gender assignments.
     */
    public function run(): void
    {
        $maleNames = [
            'adam', 'adrian', 'aleksander', 'andrzej', 'antoni', 'arkadiusz', 'artur',
            'bartek', 'bartłomiej', 'bartosz', 'błażej', 'bogdan', 'bogusław',
            'cezary', 'czesław', 'cyprian',
            'damian', 'daniel', 'dariusz', 'dawid', 'denis', 'dominik', 'dorian',
            'edward', 'emil', 'ernest', 'eugeniusz',
            'fabian', 'feliks', 'filip', 'franciszek',
            'grzegorz', 'gustaw',
            'henryk', 'hubert',
            'ignacy', 'igor', 'ireneusz',
            'jacek', 'jakub', 'jan', 'janusz', 'jarosław', 'jerzy', 'jędrzej', 'józef', 'julian', 'juliusz',
            'kacper', 'kajetan', 'kamil', 'karol', 'kazimierz', 'konrad', 'kornel', 'krystian', 'krzysztof',
            'lech', 'leon', 'leszek', 'łukasz',
            'maciej', 'maks', 'maksymilian', 'marcin', 'marek', 'marian', 'mariusz', 'mateusz', 'maurycy', 'michał', 'mieczysław', 'mikołaj', 'miłosz',
            'nikodem', 'norbert',
            'olaf', 'olek', 'olivier',
            'patryk', 'paweł', 'piotr', 'przemek', 'przemysław',
            'radek', 'radosław', 'rafał', 'robert', 'roman', 'ryszard',
            'sebastian', 'sławek', 'sławomir', 'stanisław', 'stefan', 'szymon',
            'tadeusz', 'tomasz', 'tymon',
            'wacław', 'waldemar', 'wiesław', 'wiktor', 'witold', 'władysław', 'wojciech',
            'zbigniew', 'zenon', 'zdzisław',
        ];

        $femaleNames = [
            'ada', 'adrianna', 'agata', 'agnieszka', 'aleksandra', 'alicja', 'amelia', 'anastazja', 'ania', 'anna', 'antonina',
            'barbara', 'beata', 'bernardyna', 'blanka', 'bogna', 'bożena',
            'cecylia', 'celina',
            'dagmara', 'danuta', 'daria', 'diana', 'dominika', 'dorota',
            'edyta', 'eliza', 'elżbieta', 'emilia', 'ewa', 'ewelina',
            'gabriela', 'grażyna',
            'halina', 'hanna', 'helena',
            'ilona', 'inga', 'irena', 'iwona', 'izabela', 'izabella',
            'jadwiga', 'jagoda', 'janina', 'joanna', 'jolanta', 'julia', 'julita', 'justyna',
            'kaja', 'kamila', 'karina', 'karolina', 'kasia', 'katarzyna', 'kinga', 'klaudia', 'kornelia', 'krystyna',
            'laura', 'lena', 'lidia', 'liliana', 'lucyna',
            'magdalena', 'maja', 'małgorzata', 'maria', 'marianna', 'marlena', 'marta', 'martyna', 'maryna', 'matylda', 'michalina', 'milena', 'monika',
            'nadia', 'natalia', 'nikola', 'nina',
            'ola', 'oliwia',
            'patrycja', 'paulina', 'pola',
            'renata', 'róża', 'rozalia',
            'sandra', 'sara', 'stella', 'sylwia',
            'teresa', 'tina',
            'urszula',
            'wanda', 'weronika', 'wiktoria',
            'zofia', 'zuzanna',
        ];

        // Insert male names
        foreach ($maleNames as $name) {
            Name::updateOrCreate(
                [
                    'name' => $name,
                    'country' => 'PL',
                    'source' => 'system',
                ],
                [
                    'gender' => 'male',
                ]
            );
        }

        // Insert female names
        foreach ($femaleNames as $name) {
            Name::updateOrCreate(
                [
                    'name' => $name,
                    'country' => 'PL',
                    'source' => 'system',
                ],
                [
                    'gender' => 'female',
                ]
            );
        }

        $this->command->info('Polish names seeded: ' . count($maleNames) . ' male, ' . count($femaleNames) . ' female names.');
    }
}
