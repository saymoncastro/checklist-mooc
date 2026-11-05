<?php

declare(strict_types=1);

// Patch the stock moodle-docker config.php after it is copied to the Linux volume.
$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Usage: php patch-fast-config.php /path/to/config.php\n");
    exit(1);
}

$config = file_get_contents($path);
if ($config === false) {
    fwrite(STDERR, "Unable to read {$path}\n");
    exit(1);
}

$marker = '// Use MOODLE_LOCAL_FAST=1 for normal local browsing.';
if (str_contains($config, $marker)) {
    exit(0);
}

$original = [
    '// Debug options - possible to be controlled by flag in future..',
    '$CFG->debug = (E_ALL); // DEBUG_DEVELOPER',
    '$CFG->debugdisplay = 1;',
    '$CFG->debugstringids = 1; // Add strings=1 to url to get string ids.',
    '$CFG->perfdebug = 15;',
    '$CFG->debugpageinfo = 1;',
];

$replacement = <<<'PHP'
// Use MOODLE_LOCAL_FAST=1 for normal local browsing. Set it to 0 for full developer diagnostics.
$localfast = filter_var(getenv('MOODLE_LOCAL_FAST') ?: '0', FILTER_VALIDATE_BOOLEAN);
$CFG->debug = $localfast ? 0 : E_ALL; // E_ALL is DEBUG_DEVELOPER before setup.php loads Moodle constants.
$CFG->debugdisplay = $localfast ? 0 : 1;
$CFG->debugstringids = $localfast ? 0 : 1;
$CFG->perfdebug = $localfast ? 0 : 15;
$CFG->debugpageinfo = $localfast ? 0 : 1;
$CFG->cachejs = true;
$CFG->langstringcache = true;
$CFG->themedesignermode = false;
PHP;

$newline = str_contains($config, "\r\n") ? "\r\n" : "\n";
$replacement = str_replace("\n", $newline, $replacement);
$pattern = '/' . implode('\\R', array_map(static fn(string $line): string => preg_quote($line, '/'), $original)) . '/';
$updated = preg_replace_callback($pattern, static fn(): string => $replacement, $config, 1, $count);
if ($updated === null || $count !== 1) {
    fwrite(STDERR, "The expected moodle-docker debug block was not found in {$path}\n");
    exit(1);
}
if (file_put_contents($path, $updated) === false) {
    fwrite(STDERR, "Unable to patch {$path}\n");
    exit(1);
}
