<?php
/*
 * Local version of the rules checked by the PrestaShop Validator
 * (https://validator.prestashop.com) on the built module:
 *   - an index.php in every folder;
 *   - the licence header and the _PS_VERSION_ guard in every PHP file of the module;
 *   - no forbidden or debug function;
 *   - every Smarty variable escaped (|escape, |intval) in the templates;
 *   - logo.png, LICENSE.md and the module class name.
 *
 * Usage: php tools/validate.php build/pathly
 */
$dir = rtrim(isset($argv[1]) ? $argv[1] : 'build/pathly', '/');
$errors = [];
if (!is_file("$dir/pathly.php")) {
    fwrite(STDERR, "No module in $dir: run tools/build.sh first\n");
    exit(1);
}
foreach (['logo.png', 'LICENSE.md', 'index.php', 'composer.json'] as $file) {
    if (!is_file("$dir/$file")) {
        $errors[] = "missing $file";
    }
}
if (strpos(file_get_contents("$dir/pathly.php"), 'class Pathly extends Module') === false) {
    $errors[] = 'pathly.php must declare class Pathly extends Module';
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
$forbidden = '/\b(eval|exec|shell_exec|system|passthru|popen|proc_open|pcntl_exec|assert|create_function|var_dump|print_r|var_export|debug_zval_dump|serialize|unserialize|die)\s*\(/i';
foreach ($iterator as $path => $info) {
    $relative = substr($path, strlen($dir) + 1);
    if ($info->isDir()) {
        if (!is_file("$path/index.php")) {
            $errors[] = "no index.php in $relative";
        }
        continue;
    }
    $inVendor = strpos($relative, 'vendor/') === 0;
    if (substr($path, -4) === '.php' && !$inVendor && basename($path) !== 'index.php') {
        $code = file_get_contents($path);
        if (strpos($code, '@license   https://opensource.org/licenses/AFL-3.0') === false) {
            $errors[] = "no licence header in $relative";
        }
        if (strpos($relative, 'translations/') !== 0 && strpos($code, "if (!defined('_PS_VERSION_')) {") === false) {
            $errors[] = "no _PS_VERSION_ guard in $relative";
        }
        $tokens = token_get_all($code);
        $stripped = '';
        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true)) {
                continue;
            }
            $stripped .= is_array($token) ? $token[1] : $token;
        }
        if (preg_match($forbidden, $stripped, $m)) {
            $errors[] = "forbidden function {$m[1]}() in $relative";
        }
    }
    if (substr($path, -4) === '.tpl') {
        preg_match_all('/\{\$[^}]+\}/', file_get_contents($path), $vars);
        foreach ($vars[0] as $var) {
            if (!preg_match('/\|(escape:|intval)/', $var)) {
                $errors[] = "unescaped Smarty variable $var in $relative";
            }
        }
    }
}

foreach ($errors as $error) {
    echo "ERROR $error\n";
}
echo count($errors) === 0 ? "Validator rules: OK\n" : count($errors) . " error(s)\n";
exit(count($errors) === 0 ? 0 : 1);
