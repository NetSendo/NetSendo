<?php
$locales = ['en', 'pl', 'de', 'es'];
$baseDir = __DIR__ . '/src';

foreach ($locales as $locale) {
    $phpFile = "$baseDir/lang/$locale/marketplace.php";
    $jsonFile = "$baseDir/resources/js/locales/$locale.json";

    if (!file_exists($phpFile) || !file_exists($jsonFile)) {
        echo "Missing files for locale $locale\n";
        continue;
    }

    $phpData = require $phpFile;
    $jsonData = json_decode(file_get_contents($jsonFile), true);

    if (!isset($jsonData['marketplace'])) {
        $jsonData['marketplace'] = [];
    }
    
    // Add perplexity
    if (isset($phpData['perplexity'])) {
        $jsonData['marketplace']['perplexity'] = $phpData['perplexity'];
    }
    
    // Add serpapi
    if (isset($phpData['serpapi'])) {
        $jsonData['marketplace']['serpapi'] = $phpData['serpapi'];
    }
    
    // Add AI category
    if (isset($phpData['categories']['ai'])) {
        if (!isset($jsonData['marketplace']['categories'])) {
            $jsonData['marketplace']['categories'] = [];
        }
        $jsonData['marketplace']['categories']['ai'] = $phpData['categories']['ai'];
    }

    file_put_contents($jsonFile, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "Updated $locale.json\n";
}
