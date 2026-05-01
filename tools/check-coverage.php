<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf OIDC.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
global $argc, $argv;

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tools/check-coverage.php <clover.xml> <minimum-percent>\n");
    exit(2);
}

$file = $argv[1];
$minimum = (float) $argv[2];

if (! is_file($file)) {
    fwrite(STDERR, sprintf("Coverage file not found: %s\n", $file));
    exit(2);
}

$coverage = simplexml_load_file($file);

if ($coverage === false) {
    fwrite(STDERR, sprintf("Invalid coverage file: %s\n", $file));
    exit(2);
}

$metrics = $coverage->project->metrics;
$statements = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];
$percent = $statements === 0 ? 100.0 : ($coveredStatements / $statements) * 100;

printf("Statement coverage: %.2f%% (minimum %.2f%%)\n", $percent, $minimum);

if ($percent < $minimum) {
    exit(1);
}
