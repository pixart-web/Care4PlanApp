/* Read-only browser navigation and modal checks on a local fictional fixture. */
const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.CARE4ALL_PLAYWRIGHT || 'playwright');
const base = process.env.CARE4ALL_TEST_URL;
const password = process.env.CARE4ALL_TEST_PASSWORD;
if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(base || '') || !password) throw Error('Explicit local fixture URL and password required');
(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  const results = []; const errors = [];
  try {
    for (const [role, username, routes] of [
      ['ADMIN', 'Andreia', ['dashboard','scales','carers','patients','users','finances','reports','settings']],
      ['EDITOR', 'fixture-editor', ['dashboard','scales','carers','patients','reports','settings']],
      ['CARER', 'fixture-carer-1', ['dashboard','scales','pocket','profile','reports']]
    ]) {
      const context = await browser.newContext();
      await context.route('https://api.ipma.pt/**', route => route.abort());
      const page = await context.newPage();
      page.on('pageerror', e => errors.push(e.message));
      page.on('response', r => { if (r.url().startsWith(base) && r.status() >= 400) errors.push(r.status() + ' ' + r.url()); });
      await page.goto(base + '/index.php?page=login');
      await page.locator('[name=username]').fill(username);
      await page.locator('[name=password]').fill(password);
      await page.getByRole('button', {name:'Entrar',exact:true}).click();
      await page.waitForURL('**page=dashboard');
      const links = new Set(); let modals = 0;
      for (const width of [1440, 768, 390]) {
        await page.setViewportSize({width, height:1000});
        for (const route of routes) {
          await page.goto(base + '/index.php?page=' + route);
          await page.reload();
          assert.equal(await page.locator('main').count(), 1);
          const toggle = page.locator('[data-navigation-toggle]');
          if (await toggle.isVisible()) {
            await toggle.click(); assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
            assert.equal(await page.locator('#mobile-navigation').isVisible(), true);
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('#mobile-navigation').isVisible(), false);
          }
          for (const href of await page.locator('a[href]').evaluateAll(nodes => nodes.map(n => n.href))) {
            const url = new URL(href);
            if (url.origin === base && !url.hash) links.add(href);
          }
          const triggers = page.locator('[data-open-modal]');
          for (let i=0; i<await triggers.count(); i++) {
            const trigger = triggers.nth(i);
            if (!await trigger.isVisible() || !await trigger.isEnabled()) continue;
            const id = await trigger.getAttribute('data-open-modal');
            await trigger.click(); assert.equal(await page.locator('#'+id).isVisible(), true);
            await page.keyboard.press('Escape'); assert.equal(await page.locator('#'+id).isVisible(), false);
            modals++;
          }
        }
      }
      for (const link of links) {
        const response = await context.request.get(link);
        assert.equal(response.status(), 200, role + ': ' + link);
      }
      results.push({role, pages:routes.length*3, internalLinks:links.size, modalOpenClose:modals});
      await context.close();
    }
    assert.deepEqual(errors, []);
    fs.mkdirSync('output/technical-audit', {recursive:true});
    fs.writeFileSync('output/technical-audit/navigation.json', JSON.stringify({results,errors},null,2));
    console.log(JSON.stringify(results));
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode=1; });
