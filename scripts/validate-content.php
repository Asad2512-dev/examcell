<?php

declare(strict_types=1);

$files = glob(__DIR__.'/../resources/content/*.json') ?: [];
$invalid = false;

foreach ($files as $file) {
    try {
        json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        fwrite(STDOUT, 'Valid: '.basename($file).PHP_EOL);
    } catch (JsonException $error) {
        $invalid = true;
        fwrite(STDERR, 'Invalid: '.basename($file).' — '.$error->getMessage().PHP_EOL);
    }
}

if ($files === []) {
    fwrite(STDERR, "No content JSON files were found.\n");
    exit(1);
}

exit($invalid ? 1 : 0);
