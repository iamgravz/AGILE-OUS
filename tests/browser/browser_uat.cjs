'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');

// Never enable these browser scripts on a host with real student information.
const BASE = process.env.AGILE_UAT_BASE_URL || 'https://127.0.0.1:8443';
if (process.env.CI !== 'true' ||
    process.env.RUN_E2E_TESTS !== 'yes' ||
    BASE !== 'https://127.0.0.1:8443') {
  process.stderr.write('Refusing browser UAT outside isolated loopback synthetic CI.\n');
  process.exit(2);
}
const paths = ['/', '/privacy', '/apply', '/vacancies', '/updates', '/welfare',
               '/welfare/track', '/login'];
const results = { kind: 'synthetic_disposable_chromium', viewports: [],
                  pages_checked: 0, serious_critical_wcag_violations: 0,
                  application_submitted: false, welfare_status_privacy: false,
                  csrf_rejected: false, secure_sessions: false,
                  private_routes_denied: false, success: false };
const noSecrets = /@example\.invalid|TEST-13-2026|tracking_token|recovery_code/i;
let browser;

async function checkAccessibility(page, pathname, viewport) {
  await page.addScriptTag({ path: require.resolve('axe-core/axe.min.js') });
  const info = await page.evaluate(async () => {
    const { violations, incomplete } = await window.axe.run(document, {
      runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] },
    });
    return {
      errors: violations.filter(v => v.impact === 'serious' || v.impact === 'critical')
                        .map(v => ({ id: v.id, impact: v.impact })),
      needsManualInspection: incomplete.length,
    };
  });
  results.serious_critical_wcag_violations += info.errors.length;
  if (info.errors.length) {
    // Report only rule IDs: selectors, HTML, narratives and form values are
    // intentionally never printed to logs or uploaded to build artifacts.
    const ruleIds = [...new Set(info.errors.map(e => e.id))].join(',');
    throw new Error('WCAG serious/critical issue in ' + viewport + ':' +
                    pathname + ' — axe rule IDs: ' + ruleIds);
  }
  console.log('PASS: ' + viewport + ' ' + pathname + ' serious/critical WCAG baseline');
}

async function layoutAndNavigation(page, viewportName) {
  const width = await page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    viewportWidth: document.documentElement.clientWidth,
  }));
  assert.ok(width.scrollWidth <= width.viewportWidth + 1,
    'Horizontal overflow at ' + viewportName + ' (document width only)');
  assert.equal(await page.getByRole('main').count(), 1,
    'Exactly one main landmark is expected');
  const first = page.locator('nav a').first();
  // Exercise a real keyboard Tab navigation, not just programmatic focus().
  await page.keyboard.press('Tab');
  assert.equal(await first.evaluate(el => document.activeElement === el), true,
    'First navigation link must be reached from the keyboard');
}

async function main() {
  browser = await chromium.launch({ headless: true });
  for (const viewport of [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'mobile', width: 390, height: 844 },
    { name: 'small-mobile', width: 320, height: 690 },
  ]) {
    const context = await browser.newContext({
      viewport: { width: viewport.width, height: viewport.height },
      // Disposable self-signed CI TLS only; existing curl tests validate
      // hostname and certificate explicitly. Never for real staging.
      ignoreHTTPSErrors: true,
      reducedMotion: 'reduce',
    });
    const page = await context.newPage();
    try {
      for (const pathname of paths) {
        const response = await page.goto(BASE + pathname, { waitUntil: 'domcontentloaded' });
        assert.equal(response.status(), 200, 'Public page '+pathname+' should be available');
        await layoutAndNavigation(page, viewport.name + ':' + pathname);
        await checkAccessibility(page, pathname, viewport.name);
        results.pages_checked++;
      }
      results.viewports.push(viewport.name);
    } finally { await context.close(); }
  }

  const context = await browser.newContext({
    viewport: { width: 1280, height: 800 },
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();
  try {
    const response = await page.goto(BASE + '/');
    const headers = response.headers();
    assert.ok((headers['strict-transport-security'] || '').includes('max-age='),
      'HSTS missing from HTTPS response');
    assert.equal((headers['x-frame-options'] || '').toUpperCase(), 'DENY');
    assert.equal((headers['x-content-type-options'] || '').toLowerCase(), 'nosniff');
    assert.ok((headers['permissions-policy'] || '').includes('camera=()'),
      'Permissions policy must deny unauthorized camera access');
    const cookies = await context.cookies(BASE);
    const session = cookies.find(c => c.name === 'AGILESESSID');
    assert.ok(session && session.secure && session.httpOnly && session.sameSite === 'Lax',
      'Staging session cookie must be Secure, HttpOnly, SameSite=Lax');
    results.secure_sessions = true;
    console.log('PASS: HTTPS headers and browser session cookie attributes');

    for (const path of ['/app/bootstrap.php', '/router.php',
                         '/scripts/migrate.php', '/storage/private/document.pdf',
                         '/.env', '/index.php']) {
      const r = await page.request.get(BASE + path, { maxRedirects: 0 });
      assert.equal(r.status(), 404, 'Private source should not be directly fetchable: ' + path);
    }
    for (const path of ['/dashboard', '/staff/welfare', '/staff/academic',
                        '/staff/members', '/staff/hr']) {
      const r = await page.request.get(BASE + path, { maxRedirects: 0 });
      assert.equal(r.status(), 303, 'Anonymous access should be redirected: ' + path);
      assert.equal(new URL(r.headers().location, BASE).pathname, '/login');
    }
    results.private_routes_denied = true;
    console.log('PASS: private resources and anonymous staff access are denied');

    const csrfAttempt = await page.request.post(BASE + '/apply', { data: {
      full_name: 'Synthetic Rejected First Request',
      email: 'not-submitted@example.invalid',
      student_number: 'TEST-CSRF-13',
      desired_role: 'General Member',
    } });
    assert.equal(csrfAttempt.status(), 419, 'Missing CSRF must fail');
    results.csrf_rejected = true;
    console.log('PASS: browser request without CSRF is rejected');

    await page.goto(BASE + '/apply');
    const role = page.locator('#desired-role');
    assert.equal(await role.inputValue(), 'General Member');
    assert.equal(await page.locator('fieldset[data-role="General Member"]').isVisible(), true);
    assert.equal(await page.locator('fieldset[data-role="Committee Member"]').isVisible(), false);
    await role.selectOption('Committee Member');
    assert.equal(await page.locator('fieldset[data-role="General Member"]').isVisible(), false);
    assert.equal(await page.locator('fieldset[data-role="Committee Member"]').isVisible(), true);
    assert.equal(await page.locator('fieldset[data-role="Committee Member"] [name="answers[preferred_committee]"]').isEnabled(), true);
    assert.equal(await page.locator('fieldset[data-role="General Member"] textarea').isDisabled(), true);
    await role.selectOption('The Source Code');
    assert.equal(await page.locator('fieldset[data-role="The Source Code"]').isVisible(), true);
    assert.equal(await page.locator('fieldset[data-role="Committee Member"]').isVisible(), false);
    await page.locator('fieldset[data-role="The Source Code"] [name="answers[publication_position]"]')
      .selectOption('Writer');
    // Restore the intended test role; other role answers must stay disabled.
    await role.selectOption('Committee Member');

    await page.locator('[name="full_name"]').fill('Synthetic Browser Applicant');
    await page.locator('[name="email"]').fill('phase13-browser@example.invalid');
    await page.locator('[name="student_number"]').fill('TEST-13-2026');
    await page.locator('[name="motivation"]').fill(
      'Synthetic CI registration to verify the real responsive browser workflow.');
    await page.locator('fieldset[data-role="Committee Member"] [name="answers[preferred_committee]"]').fill(
      'Membership and Student Welfare');
    await page.locator('fieldset[data-role="Committee Member"] [name="answers[relevant_skills]"]').fill(
      'Synthetic volunteer collaboration and online events');
    await page.locator('input[name="privacy_consent"]').check();
    await page.getByRole('button', { name: 'Submit Application' }).click();
    assert.equal(await page.getByRole('heading', { name: 'Application Submitted' }).count(), 1,
      'Browser submission did not reach confirmation');
    assert.match(await page.locator('main strong').innerText(), /^AG-[A-F0-9]+$/);
    results.application_submitted = true;
    console.log('PASS: conditional role questions and actual synthetic application submission');

    await page.goto(BASE + '/welfare');
    await page.locator('[name="reporter_name"]').fill('Synthetic Browser Welfare Reporter');
    await page.locator('[name="reporter_email"]').fill('phase13-welfare@example.invalid');
    await page.locator('[name="category"]').selectOption({ label: 'Academic' });
    const summary = 'Phase 13 confidential synthetic welfare summary';
    const narrative = 'CONFIDENTIAL_SYNTHETIC_NARRATIVE_MUST_NOT_APPEAR_ON_PUBLIC_STATUS';
    await page.locator('[name="summary"]').fill(summary);
    await page.locator('[name="details"]').fill(narrative +
      ' only for temporary isolated acceptance testing');
    await page.locator('[name="privacy_consent"]').check();
    await page.getByRole('button', { name: 'Submit Confidential Concern' }).click();
    assert.equal(await page.getByRole('heading', { name: 'Welfare Concern Submitted' }).count(), 1);
    const reference = (await page.locator('main strong').innerText()).trim();
    const token = (await page.locator('main code').innerText()).trim();
    assert.ok(reference.length > 10 && token.length > 20);

    await page.goto(BASE + '/welfare/track');
    await page.locator('[name="reference"]').fill(reference);
    await page.locator('[name="token"]').fill(token);
    await page.getByRole('button', { name: 'Check My Status' }).click();
    assert.equal(await page.getByRole('heading', { name: 'Concern Status' }).count(), 1);
    const text = await page.locator('main').innerText();
    assert.ok(text.includes('Current status:'));
    assert.ok(!text.includes(summary) && !text.includes(narrative) && !text.includes(token),
      'Tracking status leaked sensitive case contents or private token');
    await page.goto(BASE + '/welfare/track');
    await page.locator('[name="reference"]').fill(reference);
    await page.locator('[name="token"]').fill(token.substring(1) + '0');
    const rejected = page.waitForResponse(r => r.url().endsWith('/welfare/track') &&
        r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Check My Status' }).click();
    assert.equal((await rejected).status(), 404, 'Wrong welfare token must be refused');
    results.welfare_status_privacy = true;
    console.log('PASS: submitted welfare case can be tracked by private token without exposing narrative');

    await page.goto(BASE + '/login');
    await page.getByLabel('Email').fill('nonexistent@example.invalid');
    await page.getByLabel('Password').fill('synthetic-wrong-password');
    await page.getByRole('button', { name: 'Sign In' }).click();
    assert.equal(await page.locator('main .error').count() > 0, true,
      'Unknown account must not authenticate');
    console.log('PASS: unknown user cannot authenticate');
  } finally { await context.close(); }

  results.success = true;
  const report = process.env.AGILE_BROWSER_UAT_REPORT;
  if (!report || !report.startsWith('/')) {
    throw new Error('Absolute synthetic runner-only report path is required.');
  }
  const output = JSON.stringify(results, null, 2) + '\n';
  if (noSecrets.test(output)) throw new Error('Refusing to write a sensitive browser report.');
  fs.writeFileSync(report, output, { flag: 'wx', mode: 0o600 });
  console.log('PASS: browser UAT results contain aggregate statistics only');
}

main().catch(err => {
  // Never include page HTML, form data, secrets, or full browser stack in logs.
  console.error('FAIL: synthetic browser acceptance: ' + (err.message || 'unknown error'));
  process.exitCode = 1;
}).finally(async () => {
  if (browser) await browser.close();
});
