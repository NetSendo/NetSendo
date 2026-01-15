<?php

namespace Database\Seeders;

use App\Models\Name;
use Illuminate\Database\Seeder;

class InternationalNamesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds common first names for international countries with gender assignments.
     */
    public function run(): void
    {
        $countries = [
            'DE' => $this->getGermanNames(),
            'CZ' => $this->getCzechNames(),
            'SK' => $this->getSlovakNames(),
            'FR' => $this->getFrenchNames(),
            'IT' => $this->getItalianNames(),
            'ES' => $this->getSpanishNames(),
            'UK' => $this->getBritishNames(),
            'US' => $this->getAmericanNames(),
        ];

        foreach ($countries as $countryCode => $names) {
            $maleCount = 0;
            $femaleCount = 0;

            // Insert male names
            foreach ($names['male'] as $key => $value) {
                // Check if name is key=>value (name=>vocative) or just value (name)
                $name = is_string($key) ? $key : $value;
                $vocative = is_string($key) ? $value : $value; // Default to name if no vocative map

                // For CZ, we used explicit mapping. For others, we default to name.
                // If the array was simple [name1, name2], then key is int, value is name.
                // In that case vocative = name.
                if (is_int($key)) {
                    $name = $value;
                    $vocative = $value;
                }

                Name::updateOrCreate(
                    [
                        'name' => mb_strtolower($name),
                        'country' => $countryCode,
                        'source' => 'system',
                    ],
                    [
                        'gender' => 'male',
                        'vocative' => mb_strtolower($vocative),
                    ]
                );
                $maleCount++;
            }

            // Insert female names
            foreach ($names['female'] as $key => $value) {
                $name = is_string($key) ? $key : $value;
                $vocative = is_string($key) ? $value : $value;

                if (is_int($key)) {
                    $name = $value;
                    $vocative = $value;
                }

                Name::updateOrCreate(
                    [
                        'name' => mb_strtolower($name),
                        'country' => $countryCode,
                        'source' => 'system',
                    ],
                    [
                        'gender' => 'female',
                        'vocative' => mb_strtolower($vocative),
                    ]
                );
                $femaleCount++;
            }

            $this->command->info("Seeded {$countryCode}: {$maleCount} male, {$femaleCount} female names.");
        }
    }

    /**
     * German names (Germany - DE)
     */
    protected function getGermanNames(): array
    {
        return [
            'male' => [
                'Hans', 'Klaus', 'Wolfgang', 'Jürgen', 'Dieter', 'Helmut', 'Günter', 'Manfred',
                'Peter', 'Thomas', 'Michael', 'Andreas', 'Stefan', 'Christian', 'Matthias', 'Frank',
                'Martin', 'Markus', 'Daniel', 'Sebastian', 'Tobias', 'Alexander', 'Florian', 'Jan',
                'Tim', 'Lukas', 'Felix', 'Maximilian', 'Leon', 'Jonas', 'Finn', 'Noah',
                'Elias', 'Luca', 'Paul', 'Ben', 'Luis', 'Moritz', 'Julian', 'Niklas',
                'Friedrich', 'Heinrich', 'Karl', 'Otto', 'Wilhelm', 'Ernst', 'Rudolf', 'Werner',
            ],
            'female' => [
                'Anna', 'Maria', 'Emma', 'Hannah', 'Mia', 'Sofia', 'Emilia', 'Lina',
                'Lea', 'Lena', 'Laura', 'Julia', 'Leonie', 'Lisa', 'Sarah', 'Michelle',
                'Katharina', 'Christina', 'Stefanie', 'Nicole', 'Sandra', 'Claudia', 'Sabine', 'Petra',
                'Barbara', 'Ursula', 'Monika', 'Helga', 'Ingrid', 'Renate', 'Brigitte', 'Gerda',
                'Gisela', 'Erika', 'Heike', 'Karin', 'Martina', 'Susanne', 'Andrea', 'Birgit',
                'Charlotte', 'Sophie', 'Amelie', 'Johanna', 'Franziska', 'Luise', 'Martha', 'Elisabeth',
            ],
        ];
    }

    /**
     * Czech names (Czech Republic - CZ)
     */
    protected function getCzechNames(): array
    {
        return [
            'male' => [
                'Jan' => 'Jane',
                'Petr' => 'Petře',
                'Pavel' => 'Pavle',
                'Martin' => 'Martine',
                'Tomáš' => 'Tomáši',
                'Jakub' => 'Jakube',
                'Jiří' => 'Jiří',
                'Lukáš' => 'Lukáši',
                'David' => 'Davide',
                'Ondřej' => 'Ondřeji',
                'Filip' => 'Filipe',
                'Michal' => 'Michale',
                'Adam' => 'Adame',
                'Marek' => 'Marku',
                'Vojtěch' => 'Vojtěchu',
                'Daniel' => 'Danieli',
                'Václav' => 'Václave',
                'Josef' => 'Josefe',
                'František' => 'Františku',
                'Karel' => 'Karle',
                'Jaroslav' => 'Jaroslave',
                'Miroslav' => 'Miroslave',
                'Zdeněk' => 'Zdeňku',
                'Milan' => 'Milane',
                'Vladimír' => 'Vladimíre',
                'Radek' => 'Radku',
                'Roman' => 'Romane',
                'Stanislav' => 'Stanislave',
                'Ladislav' => 'Ladislave',
                'Aleš' => 'Aleši',
                'Patrik' => 'Patriku',
                'Matěj' => 'Matěji',
            ],
            'female' => [
                'Marie' => 'Marie',
                'Jana' => 'Jano',
                'Eva' => 'Evo',
                'Anna' => 'Anno',
                'Hana' => 'Hano',
                'Petra' => 'Petro',
                'Lenka' => 'Lenko',
                'Kateřina' => 'Kateřino',
                'Lucie' => 'Lucie',
                'Veronika' => 'Veroniko',
                'Martina' => 'Martino',
                'Michaela' => 'Michaelo',
                'Tereza' => 'Terezo',
                'Markéta' => 'Markéto',
                'Barbora' => 'Barboro',
                'Kristýna' => 'Kristýno',
                'Eliška' => 'Eliško',
                'Adéla' => 'Adélo',
                'Natalie' => 'Natalie',
                'Simona' => 'Simono',
                'Denisa' => 'Deniso',
                'Monika' => 'Moniko',
                'Ivana' => 'Ivano',
                'Zuzana' => 'Zuzano',
                'Jitka' => 'Jitko',
                'Alena' => 'Aleno',
                'Jaroslava' => 'Jaroslavo',
                'Ludmila' => 'Ludmilo',
                'Dana' => 'Dano',
                'Irena' => 'Ireno',
                'Věra' => 'Věro',
                'Dagmar' => 'Dagmar',
            ],
        ];
    }

    /**
     * Slovak names (Slovakia - SK)
     */
    protected function getSlovakNames(): array
    {
        return [
            'male' => [
                'Ján', 'Peter', 'Pavol', 'Martin', 'Tomáš', 'Jakub', 'Juraj', 'Lukáš',
                'Dávid', 'Ondrej', 'Filip', 'Michal', 'Adam', 'Marek', 'Vojtech', 'Daniel',
                'Štefan', 'Jozef', 'František', 'Karol', 'Jaroslav', 'Miroslav', 'Zdenko', 'Milan',
                'Vladimír', 'Radoslav', 'Roman', 'Stanislav', 'Ladislav', 'Aleš', 'Patrik', 'Matej',
            ],
            'female' => [
                'Mária', 'Jana', 'Eva', 'Anna', 'Hana', 'Petra', 'Lenka', 'Katarína',
                'Lucia', 'Veronika', 'Martina', 'Michaela', 'Terézia', 'Margita', 'Barbora', 'Kristína',
                'Elíška', 'Adela', 'Natália', 'Simona', 'Denisa', 'Monika', 'Ivana', 'Zuzana',
                'Jitka', 'Alena', 'Jaroslava', 'Ľudmila', 'Dana', 'Irena', 'Viera', 'Dagmar',
            ],
        ];
    }

    /**
     * French names (France - FR)
     */
    protected function getFrenchNames(): array
    {
        return [
            'male' => [
                'Jean', 'Pierre', 'Michel', 'André', 'Philippe', 'Jacques', 'Bernard', 'François',
                'Louis', 'Alain', 'Christophe', 'Nicolas', 'Laurent', 'Julien', 'Mathieu', 'Thomas',
                'Alexandre', 'Maxime', 'Antoine', 'Hugo', 'Lucas', 'Léo', 'Raphaël', 'Arthur',
                'Nathan', 'Théo', 'Gabriel', 'Ethan', 'Louis', 'Adam', 'Paul', 'Victor',
            ],
            'female' => [
                'Marie', 'Jeanne', 'Françoise', 'Monique', 'Catherine', 'Nathalie', 'Isabelle', 'Christine',
                'Sophie', 'Valérie', 'Sandrine', 'Julie', 'Céline', 'Aurélie', 'Camille', 'Léa',
                'Emma', 'Manon', 'Chloé', 'Louise', 'Jade', 'Alice', 'Lina', 'Inès',
                'Rose', 'Ambre', 'Anna', 'Charlotte', 'Sarah', 'Clara', 'Eva', 'Margot',
            ],
        ];
    }

    /**
     * Italian names (Italy - IT)
     */
    protected function getItalianNames(): array
    {
        return [
            'male' => [
                'Giuseppe', 'Giovanni', 'Antonio', 'Mario', 'Luigi', 'Francesco', 'Angelo', 'Vincenzo',
                'Pietro', 'Salvatore', 'Carlo', 'Franco', 'Domenico', 'Bruno', 'Paolo', 'Michele',
                'Giorgio', 'Enzo', 'Marco', 'Andrea', 'Luca', 'Alessandro', 'Matteo', 'Lorenzo',
                'Davide', 'Simone', 'Federico', 'Leonardo', 'Riccardo', 'Gabriele', 'Tommaso', 'Edoardo',
            ],
            'female' => [
                'Maria', 'Anna', 'Giuseppina', 'Rosa', 'Angela', 'Giovanna', 'Teresa', 'Lucia',
                'Carmela', 'Francesca', 'Antonia', 'Paola', 'Elena', 'Chiara', 'Laura', 'Sara',
                'Giulia', 'Valentina', 'Alessia', 'Martina', 'Giorgia', 'Sofia', 'Aurora', 'Alice',
                'Beatrice', 'Emma', 'Gaia', 'Ginevra', 'Matilde', 'Vittoria', 'Camilla', 'Elisa',
            ],
        ];
    }

    /**
     * Spanish names (Spain - ES)
     */
    protected function getSpanishNames(): array
    {
        return [
            'male' => [
                'Antonio', 'José', 'Manuel', 'Francisco', 'Juan', 'David', 'Javier', 'Daniel',
                'Carlos', 'Jesús', 'Miguel', 'Alejandro', 'Rafael', 'Pedro', 'Pablo', 'Ángel',
                'Fernando', 'Luis', 'Alberto', 'Sergio', 'Diego', 'Adrián', 'Álvaro', 'Rubén',
                'Hugo', 'Martín', 'Lucas', 'Leo', 'Mateo', 'Nicolás', 'Mario', 'Iván',
            ],
            'female' => [
                'María', 'Carmen', 'Ana', 'Isabel', 'Laura', 'Cristina', 'Marta', 'Sara',
                'Paula', 'Elena', 'Lucía', 'Alba', 'Sofía', 'Claudia', 'Julia', 'Irene',
                'Andrea', 'Raquel', 'Patricia', 'Rosa', 'Pilar', 'Teresa', 'Mercedes', 'Dolores',
                'Martina', 'Valeria', 'Daniela', 'Carla', 'Emma', 'Noa', 'Olivia', 'Victoria',
            ],
        ];
    }

    /**
     * British names (United Kingdom - UK)
     */
    protected function getBritishNames(): array
    {
        return [
            'male' => [
                'James', 'John', 'William', 'David', 'Richard', 'Thomas', 'Michael', 'Daniel',
                'Matthew', 'Christopher', 'Andrew', 'Mark', 'Paul', 'Steven', 'Peter', 'Robert',
                'Oliver', 'Harry', 'Jack', 'Charlie', 'George', 'Noah', 'Oscar', 'Leo',
                'Arthur', 'Henry', 'Alfie', 'Freddie', 'Archie', 'Edward', 'Alexander', 'Samuel',
            ],
            'female' => [
                'Mary', 'Elizabeth', 'Sarah', 'Emma', 'Louise', 'Charlotte', 'Claire', 'Victoria',
                'Sophie', 'Laura', 'Rebecca', 'Rachel', 'Hannah', 'Jessica', 'Amy', 'Katie',
                'Olivia', 'Amelia', 'Isla', 'Ava', 'Mia', 'Emily', 'Grace', 'Poppy',
                'Lily', 'Ivy', 'Rosie', 'Florence', 'Freya', 'Isabella', 'Sienna', 'Alice',
            ],
        ];
    }

    /**
     * American names (United States - US)
     */
    protected function getAmericanNames(): array
    {
        return [
            'male' => [
                'James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph',
                'Thomas', 'Charles', 'Christopher', 'Daniel', 'Matthew', 'Anthony', 'Mark', 'Donald',
                'Liam', 'Noah', 'Oliver', 'Elijah', 'Lucas', 'Mason', 'Logan', 'Alexander',
                'Ethan', 'Jacob', 'Aiden', 'Jackson', 'Sebastian', 'Henry', 'Benjamin', 'Theodore',
            ],
            'female' => [
                'Mary', 'Patricia', 'Jennifer', 'Linda', 'Barbara', 'Elizabeth', 'Susan', 'Jessica',
                'Sarah', 'Karen', 'Lisa', 'Nancy', 'Margaret', 'Betty', 'Sandra', 'Ashley',
                'Olivia', 'Emma', 'Charlotte', 'Amelia', 'Ava', 'Sophia', 'Isabella', 'Mia',
                'Evelyn', 'Harper', 'Luna', 'Camila', 'Gianna', 'Elizabeth', 'Eleanor', 'Ella',
            ],
        ];
    }
}
