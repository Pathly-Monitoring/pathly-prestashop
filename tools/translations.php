<?php
/*
 * Builds translations/{fr,es,de}.php from tools/i18n/{lang}.json.
 * Fails when a string of the module has no translation (run by the CI with --check).
 *
 * Usage: php tools/translations.php [--check] [--list]
 */
$root = dirname(__DIR__);
$sources = [];
$php = file_get_contents($root . '/pathly.php');
preg_match_all("/->l\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $php, $m);
foreach ($m[1] as $raw) {
    $sources[] = ['pathly', stripslashes($raw)];
}
foreach (glob($root . '/views/templates/*/*.tpl') as $tpl) {
    preg_match_all("/\\{l s='((?:[^'\\\\]|\\\\.)*)' mod='pathly'\\}/", file_get_contents($tpl), $m);
    foreach ($m[1] as $raw) {
        $sources[] = [basename($tpl, '.tpl'), stripslashes($raw)];
    }
}
if (in_array('--list', $argv, true)) {
    foreach ($sources as $s) {
        echo $s[1], "\n";
    }
    exit(0);
}
$missing = 0;
foreach (['fr', 'es', 'de'] as $lang) {
    $dict = json_decode(file_get_contents(__DIR__ . "/i18n/$lang.json"), true);
    $out = file_get_contents(__DIR__ . '/php-header.txt') . "\nglobal \$_MODULE;\n\$_MODULE = [];\n";
    $seen = [];
    foreach ($sources as $s) {
        list($file, $text) = $s;
        if (!isset($dict[$text]) || $dict[$text] === '') {
            fwrite(STDERR, "[$lang] missing: $text\n");
            ++$missing;
            continue;
        }
        $key = '<{pathly}prestashop>' . strtolower($file) . '_' . md5(str_replace("'", "\\'", $text));
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out .= '$_MODULE[' . var_export($key, true) . '] = ' . var_export($dict[$text], true) . ";\n";
    }
    $target = $root . "/translations/$lang.php";
    if (in_array('--check', $argv, true)) {
        if (!is_file($target) || file_get_contents($target) !== $out) {
            fwrite(STDERR, "translations/$lang.php is out of date: run php tools/translations.php\n");
            ++$missing;
        }
    } else {
        file_put_contents($target, $out);
    }
}
exit($missing > 0 ? 1 : 0);
