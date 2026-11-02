<?php

namespace PrestaShop\Module\Pathly\Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Pathly\Adapter\ConfigurationStore;
use PrestaShop\Module\Pathly\Adapter\InstalledModules;
use PrestaShop\Module\Pathly\Adapter\PhpEncryptionCipher;
use PrestaShop\Module\Pathly\Api\CurlTransport;
use PrestaShop\Module\Pathly\Config\Settings;
use PrestaShop\Module\Pathly\Tests\Support\FakeTransport;

/**
 * The module class, run against the PrestaShop doubles of tests/stubs.
 */
class ModuleTest extends TestCase
{
    public const NOW = 1793600000;

    /** @var FakeTransport */
    private $api;

    /** @var \TestablePathly */
    private $module;

    protected function setUp(): void
    {
        \Configuration::$values = [];
        \Tools::$values = [];
        \Db::$rows = [['name' => 'ps_checkout', 'version' => '1.0.0']];
        \Module::$installResult = true;
        \Context::reset();
        $this->api = new FakeTransport();
        $this->module = new \TestablePathly($this->api, self::NOW);
    }

    private function submit($action, array $values = [])
    {
        \Tools::$values = array_merge([
            'submitPathly' => '1',
            'pathly_token' => 'token-AdminModules',
            'pathly_action' => $action,
        ], $values);

        return $this->module->getContent();
    }

    private function connected()
    {
        $this->module->settings()->saveApiKey('sp_testkey1234');
    }

    public function testIdentityAndInstall()
    {
        $this->assertSame('pathly', $this->module->name);
        $this->assertSame('1.7.7.0', $this->module->ps_versions_compliancy['min']);
        $this->assertTrue($this->module->install());
        $this->assertSame(\Pathly::HOOKS, $this->module->registeredHooks);
        \Module::$installResult = false;
        $this->assertFalse($this->module->install());
        $this->assertSame([], $this->api->calls);
    }

    public function testUninstallDeletesEverySetting()
    {
        $this->connected();
        \Configuration::$values['PS_OTHER'] = 'kept';
        $this->assertTrue($this->module->uninstall());
        $this->assertSame(['PS_OTHER' => 'kept'], \Configuration::$values);
    }

    public function testOnlyASuperAdminSeesTheSettings()
    {
        \Context::getContext()->employee->superAdmin = false;
        $this->assertStringStartsWith('[error]', $this->module->getContent());
        $this->assertSame('', $this->module->hookDashboardZoneOne([]));
        \Context::getContext()->employee = null;
        $this->assertStringStartsWith('[error]', $this->module->getContent());
    }

    public function testSettingsPageWithoutSubmit()
    {
        $this->assertSame('[fetch configure.tpl]', $this->module->getContent());
        $vars = \Context::getContext()->smarty->vars['pathly'];
        $this->assertFalse($vars['configured']);
        $this->assertSame('https://pathlyhq.com/fr/login?mode=signup&ref=prestashop&utm_source=prestashop&utm_medium=cms_module', $vars['signupUrl']);
        $this->assertSame('index.php?controller=AdminModules&configure=pathly', $vars['action']);
        $this->assertSame('token-AdminModules', $vars['token']);
        $this->assertNull($vars['pingUrl']);
        $this->assertSame('/modules/pathly/views/js/configure.js?v=1.0.0', $vars['jsUrl']);
        $this->assertSame([], $this->api->calls);
    }

    public function testAWrongTokenIsRefused()
    {
        $out = $this->submit('save_key', ['pathly_token' => 'forged', 'pathly_api_key' => 'sp_testkey1234']);
        $this->assertStringStartsWith('[error] The form has expired', $out);
        $this->assertSame([], $this->api->calls);
    }

    public function testUnknownActionDoesNothing()
    {
        $this->assertSame('[fetch configure.tpl]', $this->submit('nope'));
    }

    public function testSaveKeyRejectsSomethingElse()
    {
        $this->assertStringStartsWith('[error] This is not a Pathly API key', $this->submit('save_key', ['pathly_api_key' => 'hello']));
        $this->assertSame([], $this->api->calls);
    }

    public function testSaveKeyConnectsAndCreatesThePing()
    {
        $this->api
            ->on('GET', '/v1/account', 200, ['organization' => ['name' => 'Boutique']])
            ->on('GET', '/v1/scenarios', 200, ['items' => []])
            ->on('POST', '/v1/scenarios', 201, ['id' => 'ping-1'])
            ->on('GET', '/v1/flow-assistant/trials/x', 404, []);
        $out = $this->submit('save_key', ['pathly_api_key' => ' sp_testkey1234 ']);
        $this->assertStringContainsString('[ok] Your shop is connected to Pathly.', $out);
        $this->assertStringContainsString('A Ping now checks your shop every 5 minutes.', $out);
        $settings = $this->module->settings();
        $this->assertSame('sp_testkey1234', $settings->apiKey());
        $this->assertSame('Boutique', $settings->get(Settings::ORG_NAME));
        $this->assertSame('ping-1', $settings->get(Settings::PING_ID));
        $this->assertSame(['core' => '8.2.0', 'modules' => ['ps_checkout' => '1.0.0']], $settings->json(Settings::SNAPSHOT));
        $this->assertSame('https://shop.example/', $this->api->calls[2]['body']['url']);
        $this->assertStringNotContainsString('sp_testkey1234', json_encode(\Context::getContext()->smarty->vars));
        $this->assertSame('PathlyPrestaShop/1.0.0 PrestaShop/8.2.0', $this->api->calls[0]['headers']['User-Agent']);
    }

    public function testSaveKeyAttachesAnExistingPing()
    {
        $this->api
            ->on('GET', '/v1/account', 200, [])
            ->on('GET', '/v1/scenarios', 200, ['items' => [['id' => 'ping-1', 'type' => 'http', 'url' => 'https://shop.example']]]);
        $out = $this->submit('save_key', ['pathly_api_key' => 'sp_testkey1234']);
        $this->assertStringContainsString('The existing Ping of your shop is attached.', $out);
        $this->assertSame('', $this->module->settings()->get(Settings::ORG_NAME) ?: '');
    }

    public function testSaveKeyWarnsWhenThePingCannotBeCreated()
    {
        $this->api
            ->on('GET', '/v1/account', 200, [])
            ->on('GET', '/v1/scenarios', 200, ['items' => []])
            ->on('POST', '/v1/scenarios', 403, ['error' => 'limit', 'code' => 'PLAN_LIMIT']);
        $out = $this->submit('save_key', ['pathly_api_key' => 'sp_testkey1234']);
        $this->assertStringContainsString('[warning] The Ping of the shop could not be created: Your Pathly plan has reached its limit.', $out);
    }

    /**
     * @dataProvider apiErrors
     */
    public function testApiErrorsAreExplained($status, $body, $expected)
    {
        $this->api->on('GET', '/v1/account', $status, $body);
        $out = $this->submit('save_key', ['pathly_api_key' => 'sp_testkey1234']);
        $this->assertStringContainsString($expected, $out);
        $this->assertFalse($this->module->settings()->isConfigured());
    }

    public function apiErrors()
    {
        return [
            'revoked' => [401, ['error' => 'x'], 'Pathly refused this API key'],
            'scope' => [403, ['error' => 'x'], 'This API key lacks a permission'],
            'busy' => [429, ['code' => 'assistant_busy'], 'Lumi is very busy'],
            'quota' => [403, ['code' => 'assistant_quota'], 'all of today'],
            'off' => [403, ['code' => 'assistant_off'], 'Lumi is paused'],
            'blocked' => [400, ['code' => 'url_blocked'], 'cannot be checked from the internet'],
            'other' => [500, ['error' => 'Oups'], 'Pathly answered with an error: Oups'],
        ];
    }

    public function testNetworkErrorIsExplained()
    {
        $this->api->failNetwork('GET', '/v1/account');
        $this->assertStringContainsString('Pathly cannot be reached from your server', $this->submit('save_key', ['pathly_api_key' => 'sp_testkey1234']));
    }

    public function testDisconnect()
    {
        $this->connected();
        $this->assertStringContainsString('[ok] The API key is deleted', $this->submit('disconnect'));
        $this->assertFalse($this->module->settings()->isConfigured());
    }

    public function testLumiNeedsTheOwnerConfirmation()
    {
        $this->connected();
        $this->assertStringContainsString('[error] Confirm that you manage this shop.', $this->submit('lumi'));
        $this->assertSame([], $this->api->calls);
    }

    public function testLumiStartsAndTheSettingsPageFollowsIt()
    {
        $this->connected();
        $this->api
            ->on('POST', '/v1/flow-assistant/detect', 200, ['available' => true, 'siteKind' => 'prestashop'])
            ->on('POST', '/v1/flow-assistant/trials', 202, ['id' => 'trial-1'])
            ->on('GET', '/v1/flow-assistant/trials/trial-1', 200, ['item' => ['status' => 'exploring']]);
        $this->assertSame('[fetch configure.tpl]', $this->submit('lumi', ['pathly_confirm_owner' => '1']));
        $vars = \Context::getContext()->smarty->vars['pathly'];
        $this->assertSame(['state' => 'running', 'status' => 'exploring'], $vars['lumi']);
        $this->assertNull($vars['lumiError']);
    }

    /**
     * @dataProvider lumiReasons
     */
    public function testLumiUnavailableReasons($reason, $expected)
    {
        $this->connected();
        $this->api->on('POST', '/v1/flow-assistant/detect', 200, ['available' => false, 'reason' => $reason]);
        $this->assertStringContainsString($expected, $this->submit('lumi', ['pathly_confirm_owner' => '1']));
    }

    public function lumiReasons()
    {
        return [
            ['quota', 'all of today'],
            ['unreachable', 'Pathly cannot reach your shop'],
            ['url_blocked', 'local or private address'],
            ['off', 'Lumi is paused'],
        ];
    }

    public function testLumiErrorOnTheSettingsPage()
    {
        $this->connected();
        $this->module->settings()->set(Settings::TRIAL_ID, 't1');
        $this->api->on('GET', '/v1/flow-assistant/trials/t1', 500, ['error' => 'boom']);
        $this->module->getContent();
        $this->assertSame('Pathly answered with an error: boom', \Context::getContext()->smarty->vars['pathly']['lumiError']);
    }

    public function testValidateCancelAndReplay()
    {
        $this->connected();
        $settings = $this->module->settings();
        $settings->set(Settings::TRIAL_ID, 't1');
        $this->api
            ->on('POST', '/v1/flow-assistant/trials/t1/save', 201, ['item' => ['id' => 'flow-1']])
            ->on('POST', '/v1/scenarios/flow-1/run', 200, ['ok' => true]);
        $this->assertStringContainsString('[ok] Your Flow is saved.', $this->submit('validate', ['pathly_index' => '1']));
        $vars = \Context::getContext()->smarty->vars['pathly'];
        $this->assertSame('https://pathlyhq.com/monitors/flow-1', $vars['flowUrl']);
        $this->assertNull($vars['lumi']);

        $this->assertStringContainsString('[ok] Pathly is running your Flow now.', $this->submit('replay'));
        $this->assertSame('manual', $settings->get(Settings::LAST_REPLAY_REASON));
        $this->assertStringContainsString('[warning] Nothing to run yet', $this->submit('replay'));

        $settings->set(Settings::TRIAL_ID, 't2');
        $this->submit('cancel_trial');
        $this->assertNull($settings->get(Settings::TRIAL_ID));
    }

    public function testBadgeOnAndOff()
    {
        $this->connected();
        $page = ['id' => 'sp1', 'public' => true, 'url' => 'https://pathlyhq.com/status/a', 'badgeUrl' => 'https://api.pathlyhq.com/public/status/a/badge.svg'];
        $this->api
            ->on('GET', '/v1/status-pages', 200, ['items' => []])
            ->on('POST', '/v1/status-pages', 201, $page)
            ->on('PATCH', '/v1/status-pages/sp1', 200, $page);
        $this->assertSame('', $this->module->hookDisplayFooter([]));
        $this->assertStringContainsString('[ok] The badge is shown', $this->submit('badge_on'));
        $this->assertTrue(\Context::getContext()->smarty->vars['pathly']['badgeEnabled']);
        $calls = count($this->api->calls);
        $this->assertSame('[display badge.tpl]', $this->module->hookDisplayFooter([]));
        $this->assertSame(['badgeUrl' => $page['badgeUrl'], 'statusUrl' => $page['url']], \Context::getContext()->smarty->vars['pathly_badge']);
        $this->assertCount($calls, $this->api->calls);
        $this->assertStringContainsString('[ok] The badge is hidden.', $this->submit('badge_off'));
        $this->assertSame('', $this->module->hookDisplayFooter([]));
    }

    public function testDashboardWidget()
    {
        $this->assertSame('[display dashboard.tpl]', $this->module->hookDashboardZoneOne([]));
        $this->assertSame(['configured' => false], \Context::getContext()->smarty->vars['pathly']);

        $this->connected();
        $settings = $this->module->settings();
        $settings->set(Settings::PING_ID, 'ping-1');
        $settings->set(Settings::LAST_REPLAY_AT, self::NOW - 60);
        $settings->set(Settings::LAST_REPLAY_REASON, 'ps_checkout 1 → 2');
        $this->api
            ->on('GET', '/v1/account', 200, ['plan' => ['name' => 'Solo']])
            ->on('GET', '/v1/scenarios/ping-1', 200, ['lastStatus' => 'fail'])
            ->on('GET', '/v1/runs', 200, ['items' => [['id' => 'r1', 'status' => 'fail', 'created_at' => '2026-11-02T10:00:00Z']]])
            ->on('GET', '/v1/runs/r1', 200, []);
        $this->module->hookDashboardZoneOne([]);
        $vars = \Context::getContext()->smarty->vars;
        $this->assertSame(date('Y-m-d H:i', strtotime('2026-11-02T10:00:00Z')), $vars['pathly']['lastFailure']['at']);
        $this->assertSame(date('Y-m-d H:i', self::NOW - 60), $vars['pathly']['lastReplay']['at']);
        $this->assertSame('index.php?controller=AdminModules&configure=pathly', $vars['pathly_settings_url']);

        $settings->set(Settings::DASHBOARD_CACHE, null);
        $this->api->on('GET', '/v1/runs/r1', 200, []);
        \Configuration::$values[Settings::DASHBOARD_CACHE] = json_encode([
            'configured' => true, 'fetchedAt' => self::NOW, 'lastFailure' => ['at' => 'not a date'],
        ]);
        $this->module->hookDashboardZoneOne([]);
        $this->assertSame('', \Context::getContext()->smarty->vars['pathly']['lastFailure']['at']);
    }

    public function testBackOfficeHeaderComparesVersionsAndLoadsTheCss()
    {
        $this->assertSame('', $this->module->hookDisplayBackOfficeHeader([]));
        \Tools::$values = ['controller' => 'AdminDashboard'];
        $this->assertStringContainsString('views/css/admin.css?v=1.0.0', $this->module->hookDisplayBackOfficeHeader([]));
        \Tools::$values = ['configure' => 'pathly'];
        $this->assertStringContainsString('admin.css', $this->module->hookDisplayBackOfficeHeader([]));

        $this->connected();
        $settings = $this->module->settings();
        $settings->set(Settings::FLOW_ID, 'flow-1');
        $settings->setJson(Settings::SNAPSHOT, ['core' => '8.1.0', 'modules' => ['ps_checkout' => '1.0.0']]);
        $this->api->on('POST', '/v1/scenarios/flow-1/run', 200, ['ok' => true]);
        $this->module->hookDisplayBackOfficeHeader([]);
        $this->assertSame('PrestaShop 8.1.0 → 8.2.0', $settings->get(Settings::LAST_REPLAY_REASON));
    }

    public function testTheBackOfficeNeverBreaks()
    {
        $this->connected();
        $this->module->settings()->setJson(Settings::SNAPSHOT, ['core' => '8.1.0', 'modules' => []]);
        $this->module->failVersions = true;
        $this->assertSame('', $this->module->hookDisplayBackOfficeHeader([]));
    }

    public function testModuleUpgradeAfterReplaysAtOnce()
    {
        $this->connected();
        $settings = $this->module->settings();
        $settings->set(Settings::FLOW_ID, 'flow-1');
        $this->api->on('POST', '/v1/scenarios/flow-1/run', 200, ['ok' => true]);
        $this->module->hookActionModuleUpgradeAfter(['module_name' => 'ps_checkout', 'old_version' => '1.0.0', 'new_version' => '1.1.0']);
        $this->assertSame('ps_checkout 1.0.0 → 1.1.0', $settings->get(Settings::LAST_REPLAY_REASON));
        $this->assertSame(['POST /v1/scenarios/flow-1/run'], $this->api->paths());
        $this->assertNotNull($settings->json(Settings::SNAPSHOT));
        $this->module->hookActionModuleUpgradeAfter([]);
        $this->assertCount(1, $this->api->calls);
    }

    public function testBeforeUpgradeComparesAtTheEndOfTheRequest()
    {
        $this->module->hookActionBeforeUpgradeModule(['moduleName' => 'ps_checkout']);
        $this->module->hookActionBeforeUpgradeModule(['moduleName' => 'ps_other']);
        $this->connected();
        $settings = $this->module->settings();
        $settings->set(Settings::FLOW_ID, 'flow-1');
        $settings->setJson(Settings::SNAPSHOT, ['core' => '8.2.0', 'modules' => ['ps_checkout' => '0.9.0']]);
        $settings->set(Settings::SNAPSHOT_CHECKED_AT, self::NOW);
        $this->api->on('POST', '/v1/scenarios/flow-1/run', 200, ['ok' => true]);
        $this->module->checkAfterUpgrade();
        $this->assertSame('ps_checkout 0.9.0 → 1.0.0', $settings->get(Settings::LAST_REPLAY_REASON));
    }

    public function testRealWiring()
    {
        $module = new \Pathly();
        $this->assertSame(['ps_checkout' => '1.0.0'], $module->moduleVersions());
        $this->assertInstanceOf(CurlTransport::class, self::invoke($module, 'transport'));
        $this->assertEqualsWithDelta(time(), self::invoke($module, 'now'), 5);
        $this->assertInstanceOf(\PrestaShop\Module\Pathly\Api\Client::class, self::invoke($module, 'client'));
    }

    private static function invoke($object, $method)
    {
        $reflection = new \ReflectionMethod($object, $method);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        return $reflection->invoke($object);
    }

    public function testAdapters()
    {
        $store = new ConfigurationStore();
        $this->assertNull($store->get('PATHLY_X'));
        $store->set('PATHLY_X', 'v');
        $this->assertSame('v', $store->get('PATHLY_X'));
        $store->delete('PATHLY_X');
        $this->assertArrayNotHasKey('PATHLY_X', \Configuration::$values);

        $cipher = new PhpEncryptionCipher('secret');
        $this->assertSame('sp_abc', $cipher->decrypt($cipher->encrypt('sp_abc')));
        $this->assertNull($cipher->decrypt('tampered'));
        $this->assertNull($cipher->decrypt('enc:'));

        \Db::$rows = [['name' => 'a', 'version' => '1'], ['name' => 'b', 'version' => '2']];
        $this->assertSame(['a' => '1', 'b' => '2'], InstalledModules::versions());
        $this->assertStringContainsString('FROM `ps_module`', \Db::$lastQuery);
        \Db::$rows = false;
        $this->assertSame([], InstalledModules::versions());
    }
}
