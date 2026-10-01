<?php

declare(strict_types=1);

$file = $argv[1] ?? 'build/coverage.xml';
$threshold = (float) ($argv[2] ?? 85.0);

if (! is_file($file)) {
    fwrite(STDERR, "Coverage file {$file} not found.\n");
    exit(1);
}

$xml = simplexml_load_file($file);
if ($xml === false || ! isset($xml->project->metrics)) {
    fwrite(STDERR, "Coverage file {$file} is not a valid Clover report.\n");
    exit(1);
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percentage = $statements === 0 ? 100.0 : ($covered / $statements) * 100.0;

printf("Coverage: %.2f%% (%d/%d statements); minimum %.2f%%\n", $percentage, $covered, $statements, $threshold);

if ($percentage < $threshold) {
    exit(1);
}
