# Pathly for PrestaShop

Know before your customers when your checkout breaks.

[Pathly](https://pathlyhq.com) watches the path. **Ping** if it's up. **Flow** if a user can walk it. **Chain** if the services behind it hold.

This module connects a PrestaShop shop to Pathly:

- **Ping**: as soon as the API key is saved, Pathly checks every 5 minutes that the shop answers. An existing Ping of the shop is attached, never duplicated.
- **Flow prepared by Lumi**: Lumi, the Pathly assistant, recognises PrestaShop and prepares the Flow of the checkout (add a product to the cart, go up to the payment page). Each proposed Flow is replayed before it is shown. You validate it in one click.
- **Replay after an update**: when PrestaShop or a module is updated, Pathly replays the Flow at once.
- **Dashboard widget**: status of the Ping and the Flow, date and screenshot of the last failure, plan and quotas, links to Pathly.
- **Availability badge** in the shop footer, linked to your public status page. Off by default.

Compatible with PrestaShop 1.7.7 to 9, PHP 7.2 or later.

## Installation

1. Download `pathly-x.y.z.zip` from the [releases](https://github.com/Pathly-Monitoring/pathly-prestashop/releases) (or from PrestaShop Addons once listed).
2. In the back office: **Modules > Module Manager > Upload a module**, then choose the zip.
3. Click **Configure**. No account yet? Use **Create a free account** (the Solo plan is free, without a card).
4. In Pathly, create an API key under **Settings > API keys**. Prefer a key limited to one Space, with these scopes:
   `org:read scenarios:read scenarios:write runs:read runs:trigger assistant:run assistant:save status:read status:write`
5. Paste the key in the module and click **Connect**. The Ping is created. Then click **Prepare the checkout Flow with Lumi**.

Only an administrator (SuperAdmin profile) can see the settings page and the widget.

## What is sent to Pathly

- Nothing before an administrator saves an API key.
- Then only: the address of the shop, and the versions of PrestaShop and of this module (in the `User-Agent` header).
- No customer data, no order, no list of modules: the versions of the installed modules are compared inside the shop, and only the run request goes to Pathly.
- The module only calls `https://api.pathlyhq.com`. It never calls an address typed by a user.
- The API key is stored encrypted with the shop's own secret (`PhpEncryption`), never displayed again and never logged.

When the badge is on, the browsers of your visitors load the badge image from `api.pathlyhq.com`, like any image hosted elsewhere. Mention it in your privacy policy if you turn the badge on.

## Good to know

- **Test carts.** Each run of the Flow creates a real cart on the shop, with test values. It can trigger your abandoned-cart reminders and appear in your cart statistics.
- **Updates.** PrestaShop 9 signals module updates with the `actionModuleUpgradeAfter` hook, PrestaShop 8 with `actionBeforeUpgradeModule` (the replay starts once the upgrade request ends). On every version, the module also compares the installed versions of PrestaShop and of the modules, at most every 5 minutes, when an administrator opens the back office. Two replays are at least 10 minutes apart.
- **Uninstall** deletes every setting of the module from the shop. Your Ping and Flow stay in your Pathly account.

## Development

```bash
composer install
vendor/bin/phpunit                                   # unit tests, simulated API
php -d memory_limit=2G vendor/bin/phpunit --path-coverage --coverage-cobertura build/cobertura.xml
php tools/coverage-check.php build/cobertura.xml     # 100 % of lines and branches, or failure
composer install --working-dir=tools/cs
tools/cs/vendor/bin/php-cs-fixer fix --dry-run       # PrestaShop coding standard
php tools/translations.php                           # rebuild translations/ from tools/i18n/*.json
tools/build.sh                                       # build/pathly-x.y.z.zip
php tools/validate.php build/pathly                  # rules of the PrestaShop Validator
tests/integration/run.sh 8                           # real PrestaShop in Docker (1.7.8, 8 or 9)
```

The lines `exit; // @codeCoverageIgnore` are the `_PS_VERSION_` guards required by PrestaShop: they cannot run in a test.

## License

[Academic Free License 3.0](LICENSE.md).
