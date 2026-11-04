<?php

/*
 * Integration scenario, run inside the official PrestaShop container:
 *   php scenario.php configure     settings, Ping, Lumi, badge, dashboard, update hooks
 *   php scenario.php after-request check of the replay started at the end of the previous request (PS 8)
 *   php scenario.php clean         after uninstall: no setting left
 *
 * The API is the simulator of mock-api.php: every request it received is in /work/requests.jsonl.
 */
$step = isset($argv[1]) ? $argv[1] : 'configure';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/config/config.inc.php';

$failures = 0;
function check($ok, $label)
{
    global $failures;
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . "\n";
    if (!$ok) {
        ++$failures;
    }
}

function requests()
{
    $lines = is_file('/work/requests.jsonl') ? file('/work/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    return array_map(function ($line) {
        return json_decode($line, true);
    }, $lines);
}

function seen($method, $prefix, $from = 0)
{
    foreach (array_slice(requests(), $from) as $r) {
        if ($r['method'] === $method && strpos($r['target'], $prefix) === 0) {
            return $r;
        }
    }

    return null;
}

function submit(Module $module, $action, array $values = [])
{
    $_POST = array_merge([
        'submitPathly' => '1',
        'pathly_token' => Tools::getAdminTokenLite('AdminModules'),
        'pathly_action' => $action,
    ], $values);

    return $module->getContent();
}

$context = Context::getContext();
$context->employee = new Employee(1);
$context->language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
$major = (int) explode('.', _PS_VERSION_)[0];
$major = $major === 1 ? (int) explode('.', _PS_VERSION_)[1] : $major;
echo 'PrestaShop ' . _PS_VERSION_ . ', PHP ' . PHP_VERSION . ", step $step\n";

// The Addons team tests in debug mode and rejects a module that raises any PHP
// notice, warning or deprecation: each one raised from the module fails here.
$moduleErrors = [];
error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) use (&$moduleErrors) {
    if ((error_reporting() & $level) && strpos($file, '/modules/pathly/') !== false) {
        $moduleErrors[] = "$message (" . basename($file) . ":$line)";
    }

    return false;
});

if ($step === 'configure') {
    $module = Module::getInstanceByName('pathly');
    check($module instanceof Pathly && Module::isInstalled('pathly'), 'module installed');
    foreach (Pathly::HOOKS as $hook) {
        check($module->isRegisteredInHook($hook), "hook $hook registered");
    }
    $page = $module->getContent();
    check(strpos($page, 'utm_source=prestashop') !== false, 'settings page shows the signup link');
    check(strpos($page, 'Créer un compte gratuit') !== false, 'settings page translated (shop in French)');
    check(requests() === [], 'nothing sent to Pathly before configuration');

    $out = submit($module, 'save_key', ['pathly_api_key' => 'sp_integration_test_key', 'pathly_token' => 'forged']);
    check(strpos($out, 'expir') !== false && requests() === [], 'forged token refused, nothing sent');

    $out = submit($module, 'save_key', ['pathly_api_key' => 'sp_wrong_key_123456']);
    check(strpos($out, 'sp_wrong_key_123456') === false, 'refused key never displayed');
    check(Configuration::get('PATHLY_API_KEY') === false, 'refused key not stored');

    $out = submit($module, 'save_key', ['pathly_api_key' => 'sp_integration_test_key']);
    check(strpos($out, 'sp_integration_test_key') === false, 'saved key never displayed');
    $stored = (string) Configuration::get('PATHLY_API_KEY');
    check($stored !== '' && strpos($stored, 'sp_integration_test_key') === false, 'key stored encrypted');
    $create = seen('POST', '/v1/scenarios');
    check($create !== null && $create['body']['type'] === 'http', 'Ping created through POST /v1/scenarios');
    check($create !== null && $create['body']['url'] === $context->shop->getBaseURL(true), 'Ping on the shop URL');
    check($create !== null && strpos($create['userAgent'], 'PathlyPrestaShop/') === 0 && strpos($create['userAgent'], 'PrestaShop/' . _PS_VERSION_) !== false, 'only module and PrestaShop versions in the user agent');
    check(Configuration::get('PATHLY_PING_ID') === 'ping-1', 'Ping id stored');

    $before = count(requests());
    submit($module, 'save_key', ['pathly_api_key' => 'sp_integration_test_key']);
    check(seen('POST', '/v1/scenarios', $before) === null, 'second connection attaches the Ping, no duplicate');

    submit($module, 'lumi', ['pathly_confirm_owner' => '1']);
    check(seen('POST', '/v1/flow-assistant/trials') !== null, 'Lumi trial started');
    $page = submit($module, 'nothing');
    check(strpos($page, 'pathly_index') !== false, 'Lumi proposals shown');
    submit($module, 'validate', ['pathly_index' => '0']);
    check(Configuration::get('PATHLY_FLOW_ID') === 'flow-1', 'Flow validated in one click');

    $widget = $module->hookDashboardZoneOne([]);
    check(strpos($widget, 'pathly-dashboard') !== false && strpos($widget, 'storage.example/shot.jpg') !== false, 'dashboard widget with the last failure screenshot');
    check(strpos((string) Hook::exec('dashboardZoneOne', [], (int) $module->id), 'pathly-dashboard') !== false, 'widget rendered through the dashboardZoneOne hook');

    check($module->hookDisplayFooter([]) === '', 'badge off by default');
    submit($module, 'badge_on');
    check(strpos($module->hookDisplayFooter([]), 'badge.svg') !== false, 'badge shown once turned on');
    $home = (string) @file_get_contents('http://localhost/');
    check(strpos($home, 'api.pathlyhq.com/public/status/boutique-test/badge.svg') !== false, 'badge in the footer of the real home page');

    // Update of a module, signalled by the version comparison (every version).
    Configuration::updateValue('PATHLY_LAST_REPLAY_AT', 0);
    Configuration::updateValue('PATHLY_SNAPSHOT_CHECKED_AT', 0);
    Db::getInstance()->update('module', ['version' => '0.1.' . time()], "name = 'ps_emailsubscription'");
    $before = count(requests());
    Hook::exec('displayBackOfficeHeader', [], (int) $module->id);
    check(seen('POST', '/v1/scenarios/flow-1/run', $before) !== null, 'version comparison replays the Flow');

    if ($major >= 9) {
        Configuration::updateValue('PATHLY_LAST_REPLAY_AT', 0);
        $before = count(requests());
        Hook::exec('actionModuleUpgradeAfter', ['module_name' => 'ps_banner', 'old_version' => '2.1.0', 'new_version' => '2.1.1'], (int) $module->id);
        check(seen('POST', '/v1/scenarios/flow-1/run', $before) !== null, 'actionModuleUpgradeAfter replays the Flow');
        check(Configuration::get('PATHLY_LAST_REPLAY_REASON') === 'ps_banner 2.1.0 → 2.1.1', 'reason of the replay kept');
    }
    if ($major === 8 || $major >= 9) {
        // actionBeforeUpgradeModule: the replay starts at the end of this request.
        Configuration::updateValue('PATHLY_LAST_REPLAY_AT', 0);
        Hook::exec('actionBeforeUpgradeModule', ['moduleName' => 'ps_banner', 'source' => null], (int) $module->id);
        Db::getInstance()->update('module', ['version' => '0.2.' . time()], "name = 'ps_emailsubscription'");
        file_put_contents('/work/mark', (string) count(requests()));
    }
}

if ($step === 'after-request' && !is_file('/work/mark')) {
    echo "  skip (no actionBeforeUpgradeModule hook in this version)\n";
} elseif ($step === 'after-request') {
    $from = (int) file_get_contents('/work/mark');
    check(seen('POST', '/v1/scenarios/flow-1/run', $from) !== null, 'actionBeforeUpgradeModule replays the Flow after the upgrade');
}

if ($step === 'clean') {
    $left = Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . "configuration` WHERE name LIKE 'PATHLY\\_%'");
    check((int) $left === 0, 'uninstall deleted every PATHLY_ setting');
    check(!Module::isInstalled('pathly'), 'module uninstalled');
    $leaks = array_filter(requests(), function ($r) {
        return strpos(json_encode($r['body']), '@') !== false;
    });
    check(count(requests()) > 0 && $leaks === [], 'no e-mail address in any of the ' . count(requests()) . ' requests');
}

check($moduleErrors === [], 'no PHP notice, warning or deprecation from the module' . ($moduleErrors === [] ? '' : ': ' . implode('; ', array_unique($moduleErrors))));

echo $failures === 0 ? "PASS\n" : "$failures FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
