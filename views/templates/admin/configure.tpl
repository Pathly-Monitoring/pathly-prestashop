{**
 * Pathly for PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    Pathly <https://pathlyhq.com>
 * @copyright Pathly
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}
<div class="pathly-settings">
  <div class="panel">
    <div class="panel-heading"><i class="icon-plug"></i> {l s='Pathly account' mod='pathly'}</div>
    {if $pathly.configured}
      <p>
        {l s='Connected to' mod='pathly'} <strong>{$pathly.organization|escape:'htmlall':'UTF-8'}</strong>.
        {l s='API key ending with' mod='pathly'} <code>…{$pathly.keyHint|escape:'htmlall':'UTF-8'}</code>
      </p>
      <form method="post" action="{$pathly.action|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="pathly_token" value="{$pathly.token|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="pathly_action" value="disconnect">
        <button type="submit" name="submitPathly" class="btn btn-default">{l s='Delete the key from this shop' mod='pathly'}</button>
      </form>
    {else}
      <p>{l s='Pathly checks that your shop answers (Ping) and that a visitor can walk the checkout up to the payment page (Flow).' mod='pathly'}</p>
      <p>
        <a class="btn btn-primary" href="{$pathly.signupUrl|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{l s='Create a free account' mod='pathly'}</a>
      </p>
      <p class="help-block">{l s='Then create an API key in Pathly, under Settings then API keys. Prefer a key limited to one Space, with these scopes:' mod='pathly'}
        <code>org:read scenarios:read scenarios:write runs:read runs:trigger assistant:run assistant:save status:read status:write</code>
      </p>
      <form method="post" action="{$pathly.action|escape:'htmlall':'UTF-8'}" class="form-inline" autocomplete="off">
        <input type="hidden" name="pathly_token" value="{$pathly.token|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="pathly_action" value="save_key">
        <label for="pathly_api_key">{l s='API key' mod='pathly'}</label>
        <input type="password" id="pathly_api_key" name="pathly_api_key" class="form-control" placeholder="sp_…" autocomplete="off" required>
        <button type="submit" name="submitPathly" class="btn btn-primary">{l s='Connect' mod='pathly'}</button>
      </form>
      <p class="help-block">{l s='Nothing is sent to Pathly before you connect. Then only the address of the shop and the versions of PrestaShop and of this module are sent. No customer data, no order.' mod='pathly'}</p>
    {/if}
  </div>

  {if $pathly.configured}
    <div class="panel">
      <div class="panel-heading"><i class="icon-signal"></i> Ping</div>
      {if $pathly.pingUrl}
        <p>{l s='Your shop is checked every 5 minutes:' mod='pathly'} <code>{$pathly.shopUrl|escape:'htmlall':'UTF-8'}</code></p>
        <a href="{$pathly.pingUrl|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{l s='Open the Ping in Pathly' mod='pathly'}</a>
      {else}
        <p>{l s='No Ping yet. Connect the key again to create it.' mod='pathly'}</p>
      {/if}
    </div>

    <div class="panel">
      <div class="panel-heading"><i class="icon-shopping-cart"></i> Flow</div>
      {if $pathly.flowUrl}
        <p>{l s='Your checkout Flow is active. Pathly also replays it after each update of PrestaShop or of a module.' mod='pathly'}</p>
        <form method="post" action="{$pathly.action|escape:'htmlall':'UTF-8'}">
          <input type="hidden" name="pathly_token" value="{$pathly.token|escape:'htmlall':'UTF-8'}">
          <input type="hidden" name="pathly_action" value="replay">
          <a href="{$pathly.flowUrl|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{l s='Open the Flow in Pathly' mod='pathly'}</a>
          <button type="submit" name="submitPathly" class="btn btn-default">{l s='Run it now' mod='pathly'}</button>
        </form>
      {else}
        {if $pathly.lumiError}
          <div class="alert alert-warning">{$pathly.lumiError|escape:'htmlall':'UTF-8'}</div>
        {/if}
        {if !$pathly.lumi}
          <p>{l s='Lumi, the Pathly assistant, recognises PrestaShop and prepares the Flow of your checkout: add a product to the cart, then go up to the payment page. It never pays, never sends a form and only types test values. You validate the Flow before it is saved.' mod='pathly'}</p>
          <p class="help-block">{l s='Each run of the Flow creates a test cart. It can trigger your abandoned-cart reminders.' mod='pathly'}</p>
          <form method="post" action="{$pathly.action|escape:'htmlall':'UTF-8'}">
            <input type="hidden" name="pathly_token" value="{$pathly.token|escape:'htmlall':'UTF-8'}">
            <input type="hidden" name="pathly_action" value="lumi">
            <div class="checkbox">
              <label><input type="checkbox" name="pathly_confirm_owner" value="1" required> {l s='I manage this shop and allow Pathly to browse it.' mod='pathly'}</label>
            </div>
            <button type="submit" name="submitPathly" class="btn btn-primary">{l s='Prepare the checkout Flow with Lumi' mod='pathly'}</button>
          </form>
        {elseif $pathly.lumi.state == 'running'}
          <p class="pathly-running" data-pathly-refresh="10" data-pathly-url="{$pathly.action|escape:'htmlall':'UTF-8'}">{l s='Lumi is exploring your shop and replays each Flow before showing it. This takes about two minutes. This page refreshes by itself.' mod='pathly'}</p>
        {elseif $pathly.lumi.state == 'proposals'}
          <p>{l s='Lumi replayed these Flow with success. Choose the one to keep:' mod='pathly'}</p>
          {foreach from=$pathly.lumi.proposals item=proposal}
            <form method="post" action="{$pathly.action|escape:'htmlall':'UTF-8'}" class="pathly-proposal">
              <input type="hidden" name="pathly_token" value="{$pathly.token|escape:'htmlall':'UTF-8'}">
              <input type="hidden" name="pathly_action" value="validate">
              <input type="hidden" name="pathly_index" value="{$proposal.index|intval}">
              <strong>
                {if $proposal.kind == 'cart'}{l s='Checkout' mod='pathly'}{elseif $proposal.kind == 'login'}{l s='Login' mod='pathly'}{elseif $proposal.kind == 'contact'}{l s='Contact form' mod='pathly'}{else}{l s='Other journey' mod='pathly'}{/if}
              </strong>
              · {$proposal.steps|intval} {l s='steps' mod='pathly'}
              {if $proposal.stoppedBefore}· {l s='stops before' mod='pathly'} « {$proposal.stoppedBefore|escape:'htmlall':'UTF-8'} »{/if}
              <button type="submit" name="submitPathly" class="btn btn-primary">{l s='Validate this Flow' mod='pathly'}</button>
            </form>
          {/foreach}
        {else}
          <div class="alert alert-warning">{l s='Lumi could not prepare a Flow on this shop. You can try again, or record the Flow yourself in Pathly.' mod='pathly'}</div>
          <form method="post" action="{$pathly.action|escape:'htmlall':'UTF-8'}">
            <input type="hidden" name="pathly_token" value="{$pathly.token|escape:'htmlall':'UTF-8'}">
            <input type="hidden" name="pathly_action" value="cancel_trial">
            <button type="submit" name="submitPathly" class="btn btn-default">{l s='Start again' mod='pathly'}</button>
          </form>
        {/if}
      {/if}
    </div>

    <div class="panel">
      <div class="panel-heading"><i class="icon-certificate"></i> {l s='Availability badge' mod='pathly'}</div>
      <p>{l s='A small badge in the footer of your shop links to your public status page. Visitors\' browsers load the badge image from pathlyhq.com.' mod='pathly'}</p>
      <form method="post" action="{$pathly.action|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="pathly_token" value="{$pathly.token|escape:'htmlall':'UTF-8'}">
        {if $pathly.badgeEnabled}
          <input type="hidden" name="pathly_action" value="badge_off">
          <button type="submit" name="submitPathly" class="btn btn-default">{l s='Hide the badge' mod='pathly'}</button>
        {else}
          <input type="hidden" name="pathly_action" value="badge_on">
          <button type="submit" name="submitPathly" class="btn btn-default">{l s='Show the badge' mod='pathly'}</button>
        {/if}
      </form>
    </div>
  {/if}
</div>
<script src="{$pathly.jsUrl|escape:'htmlall':'UTF-8'}"></script>
