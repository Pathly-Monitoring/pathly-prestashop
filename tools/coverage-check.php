<?php
/*
 * Fails unless the Cobertura report shows 100 % of lines and 100 % of branches.
 * Usage: php tools/coverage-check.php build/cobertura.xml
 */
$file = isset($argv[1]) ? $argv[1] : 'build/cobertura.xml';
$xml = @simplexml_load_file($file);
if ($xml === false) {
    fwrite(STDERR, "Coverage report not found: $file\n");
    exit(1);
}
$failed = false;
foreach ($xml->xpath('//class') as $class) {
    $lines = (float) $class['line-rate'];
    $branches = (float) $class['branch-rate'];
    if ($lines < 1 || $branches < 1) {
        $failed = true;
        $missing = [];
        foreach ($class->lines->line as $line) {
            if ((int) $line['hits'] === 0 || (isset($line['condition-coverage']) && strpos((string) $line['condition-coverage'], '100%') !== 0)) {
                $missing[] = (string) $line['number'];
            }
        }
        printf("%s: lines %.2f %%, branches %.2f %% (lines %s)\n", $class['filename'], $lines * 100, $branches * 100, implode(', ', $missing));
    }
}
$total = $xml->attributes();
printf("Total: lines %.2f %%, branches %.2f %%\n", (float) $total['line-rate'] * 100, (float) $total['branch-rate'] * 100);
if ((float) $total['line-rate'] < 1 || (float) $total['branch-rate'] < 1) {
    $failed = true;
}
exit($failed ? 1 : 0);
