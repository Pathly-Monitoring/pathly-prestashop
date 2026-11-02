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
<section id="pathly-dashboard" class="panel widget pathly-widget">
  <header class="panel-heading"><i class="icon-signal"></i> Pathly</header>
  {if !$pathly.configured}
    <p>{l s='Know before your customers when your checkout breaks.' mod='pathly'}</p>
    <p>
      <a class="btn btn-primary" href="{$pathly_signup_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{l s='Create a free account' mod='pathly'}</a>
      <a class="btn btn-default" href="{$pathly_settings_url|escape:'htmlall':'UTF-8'}">{l s='Connect the module' mod='pathly'}</a>
    </p>
  {elseif $pathly.keyRejected}
    <div class="alert alert-danger">{l s='Pathly refused the API key of this shop.' mod='pathly'} <a href="{$pathly_settings_url|escape:'htmlall':'UTF-8'}">{l s='Open the settings' mod='pathly'}</a></div>
  {elseif $pathly.unreachable}
    <div class="alert alert-warning">{l s='Pathly cannot be reached for now. The widget will try again on the next visit.' mod='pathly'}</div>
  {else}
    <ul class="pathly-checks">
      {foreach from=['ping' => 'Ping', 'flow' => 'Flow'] key=kind item=label}
        <li>
          <strong>{$label|escape:'htmlall':'UTF-8'}</strong>
          {if !$pathly[$kind]}
            {if $kind == 'flow'}
              <span class="pathly-status pathly-status-none">{l s='not set up' mod='pathly'}</span>
              <a href="{$pathly_settings_url|escape:'htmlall':'UTF-8'}">{l s='Prepare it with Lumi' mod='pathly'}</a>
            {else}
              <span class="pathly-status pathly-status-none">{l s='not set up' mod='pathly'}</span>
            {/if}
          {else}
            {assign var=status value=$pathly[$kind].status}
            {if $status == 'ok'}
              <span class="pathly-status pathly-status-ok">{l s='OK' mod='pathly'}</span>
            {elseif $status == 'fail' || $status == 'error'}
              <span class="pathly-status pathly-status-fail">{l s='Failing' mod='pathly'}</span>
            {elseif $status == 'degraded'}
              <span class="pathly-status pathly-status-warn">{l s='Slow' mod='pathly'}</span>
            {elseif $status == 'missing'}
              <span class="pathly-status pathly-status-none">{l s='deleted in Pathly' mod='pathly'}</span>
            {else}
              <span class="pathly-status pathly-status-none">{l s='waiting for the first run' mod='pathly'}</span>
            {/if}
            <a href="{$pathly[$kind].url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{l s='Open in Pathly' mod='pathly'}</a>
          {/if}
        </li>
      {/foreach}
    </ul>
    {if $pathly.lastFailure}
      <div class="pathly-failure">
        <p><strong>{l s='Last failure:' mod='pathly'}</strong> {$pathly.lastFailure.at|escape:'htmlall':'UTF-8'}
          {if $pathly.lastFailure.message}<br><span class="text-muted">{$pathly.lastFailure.message|escape:'htmlall':'UTF-8'}</span>{/if}
        </p>
        {if $pathly.lastFailure.screenshotUrl}
          <a href="{$pathly.lastFailure.screenshotUrl|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">
            <img src="{$pathly.lastFailure.screenshotUrl|escape:'htmlall':'UTF-8'}" alt="{l s='Screenshot of the failing step' mod='pathly'}" class="pathly-shot" loading="lazy">
          </a>
        {/if}
      </div>
    {/if}
    {if $pathly.lastReplay}
      <p class="text-muted">{l s='Last replay after an update:' mod='pathly'} {$pathly.lastReplay.at|escape:'htmlall':'UTF-8'} ({$pathly.lastReplay.reason|escape:'htmlall':'UTF-8'})</p>
    {/if}
    {if $pathly.account}
      <p class="pathly-plan">
        {l s='Plan' mod='pathly'} <strong>{$pathly.account.plan|escape:'htmlall':'UTF-8'}</strong>
        {foreach from=$pathly.account.quotas key=name item=quota}
          {if $name == 'ping' || $name == 'flow'}
            · {if $name == 'ping'}Ping{else}Flow{/if} {$quota.used|intval}/{if $quota.limit === null}∞{else}{$quota.limit|intval}{/if}
          {/if}
        {/foreach}
        · <a href="{$pathly.account.billingUrl|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{l s='Change plan' mod='pathly'}</a>
      </p>
    {/if}
  {/if}
</section>
