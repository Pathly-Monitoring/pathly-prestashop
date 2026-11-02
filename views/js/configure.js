/**
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
 */
(function () {
  var running = document.querySelector('[data-pathly-refresh]');
  if (!running) {
    return;
  }
  var seconds = parseInt(running.getAttribute('data-pathly-refresh'), 10) || 10;
  window.setTimeout(function () {
    // A GET, so that the form that started Lumi is not sent again.
    window.location.href = running.getAttribute('data-pathly-url');
  }, seconds * 1000);
})();
