<?php

/*
|--------------------------------------------------------------------------
| Inertia
|--------------------------------------------------------------------------
|
| Only the keys that differ from the package defaults
| (vendor/inertiajs/inertia-laravel/config/inertia.php); everything else is
| merged in from there.
|
| The package looks for page components in resources/js/pages, while this
| app keeps them in resources/js/Pages. On macOS (case-insensitive) both
| resolve; on Linux, where CI and the Docker image run, the lowercase path
| does not exist and assertInertia()->component() reports every page missing.
|
*/

return [

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [
            resource_path('js/Pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

];
