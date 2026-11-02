# Module listing — English

## Name

Pathly - checkout monitoring

## Short summary (under 200 characters)

Know before your customers when your checkout breaks. Ping checks that the shop answers, Flow walks the checkout up to the payment page, after every update.

## Description

### The problem

The server answers, the home page loads, and yet nobody can order: an updated module broke the "Order" button, the cart stays empty, the payment page is blank. A plain uptime check does not see it. You learn it from a customer, or from falling sales.

### What the module does

Pathly watches the path. Ping if it's up. Flow if a user can walk it. Chain if the services behind it hold.

- **Ping**: once connected, Pathly checks every 5 minutes that your shop answers. A Ping already created for the shop is attached, never duplicated.
- **Flow prepared by Lumi**: Lumi, the Pathly assistant, recognises PrestaShop and prepares the Flow of your checkout: add a product to the cart, then go up to the payment page. Each proposed Flow is replayed before it is shown to you. You validate it in one click.
- **Replay after an update**: when PrestaShop or a module is updated, Pathly replays the Flow at once. Within minutes you know whether the update broke the checkout.
- **Dashboard widget**: status of the Ping and the Flow, date and screenshot of the last failure, plan and quotas, link to Pathly.
- **Availability badge** in the footer, linked to your public status page. Off by default.

### Lumi does nothing behind your back

It never pays, never sends a form and only types test values. You validate each Flow before it is saved.

### Data and security

- Nothing is sent to Pathly before you connect the module.
- Then only the address of the shop and the versions of PrestaShop and of the module are sent. No customer data, no order.
- The API key is encrypted in the shop and never displayed again.
- We apply the GDPR. App, API, workers and screenshots: GCP europe-west1. DPA available.
- Screens reserved to administrators.

### Good to know

Each run of the Flow creates a test cart on the shop. It can trigger your abandoned-cart reminders.

### Price

The module is free. It connects to a Pathly account: the Solo plan is free, without a credit card. Paid plans give more Ping and Flow and shorter intervals (prices on pathlyhq.com).

### Compatibility

PrestaShop 1.7.7 to 9, PHP 7.2 or later. Multistore: the module watches the shop of the current context.

## FAQ

**Do I need a Pathly account?**
Yes. The module offers to create a free account, without a credit card, then you paste an API key.

**Which API key should I create?**
In Pathly, Settings then API keys. Preferably a key limited to one Space, with the scopes `org:read scenarios:read scenarios:write runs:read runs:trigger assistant:run assistant:save status:read status:write`.

**Does the Flow place real orders?**
No. It stops before payment and only types test values. It does create a test cart at each run.

**What happens after a module update?**
PrestaShop 9 and 8 tell the module through their update hooks, and the module also compares the installed versions. Pathly then replays the Flow. Two replays are at least 10 minutes apart.

**Do my customers see anything?**
Only if you turn the badge on: a small image in the footer, loaded from pathlyhq.com, that links to your status page.

**What if I uninstall the module?**
Its settings are deleted from the shop. Your Ping and Flow stay in your Pathly account, where you can delete them.

**Does Lumi recognise every shop?**
It recognises PrestaShop. If your theme is heavily customised and Lumi does not find the checkout, you can record the Flow yourself in Pathly, without code.

## Changelog

### 1.0.0

- Connection with an API key, link to create a free account.
- Ping of the shop created or attached automatically.
- Checkout Flow prepared by Lumi, validated in one click.
- Flow replayed after an update of PrestaShop or of a module.
- Dashboard widget: status, last failure with screenshot, plan and quotas.
- Availability badge, off by default.
- French, English, Spanish and German translations.
