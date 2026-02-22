<?php

return array (
  'order' => 7,
  'title' => 'Synthetic monitoring for login flows',
  'meta_title' => 'Synthetic Monitoring for Login Flows (Playwright) | TruMonit',
  'meta_description' => 'Monitor real login journeys in a browser (Playwright): validate post-login elements, catch JS regressions, redirect loops, and CSP issues that HTTP checks miss.',
  'summary' => 'Synthetic checks (Playwright) verify real journeys (login/checkout) and catch UI/JS regressions before customers do.',
  'body' => '<p><strong>HTTP checks</strong> confirm reachability. <strong>Synthetic monitoring</strong> confirms a <em>real user journey</em>: can someone log in, follow redirects, and land on the correct post-login screen?</p>

<h3>When synthetic checks are the right tool</h3>
<ul>
  <li>Login returns 200 but the UI is broken (JS error, blocked resource, CSP).</li>
  <li>Critical flows: login, password reset, onboarding, checkout.</li>
  <li>You want to catch UX regressions right after a deploy.</li>
</ul>

<h3>What to validate in a login journey</h3>
<ul>
  <li>Redirect chain ends on the expected host and path.</li>
  <li>A stable post-login element is visible (menu, avatar, dashboard header).</li>
  <li>No critical error banner / auth failure / redirect loop.</li>
  <li>Realistic timeouts for network + rendering.</li>
</ul>

<h3>How to reduce flakiness</h3>
<ul>
  <li>Use stable selectors (e.g. <code>data-testid</code>), not CSS classes.</li>
  <li>Validate 1–2 critical elements, not the whole UI.</li>
  <li>Use retries + alert cooldown to avoid noisy paging.</li>
</ul>

<h3>Security considerations</h3>
<ul>
  <li>Use a dedicated test account with minimal permissions (no admin).</li>
  <li>Store and rotate credentials via secrets management.</li>
  <li>Avoid CAPTCHA/MFA in production journeys; use a test-only bypass if needed.</li>
</ul>

<p>In short: if you want to monitor what users actually experience, use <strong>browser-based user journey monitoring</strong> (Playwright), not just HTTP.</p>',
  'problem' => 'Login and checkout can look "up" (HTTP 200) while being broken for real users (JS errors, CSP issues, redirect loops, broken buttons). Synthetic checks detect those failures.',
  'approach' => 
  array (
    0 => 'Define the exact journey and success criteria (a post-login element).',
    1 => 'Use a test user and stable selectors (data-testid).',
    2 => 'Tune timeouts, retries, and cooldown to reduce flakiness.',
  ),
  'proof' => 
  array (
    0 => 'Detects UI/JS regressions earlier than basic HTTP checks.',
    1 => 'Better signal for customer-critical journeys.',
    2 => 'Helps reduce "silent failures" after deploys.',
  ),
  'checklist' => 
  array (
    0 => 'Dedicated test account created.',
    1 => 'Selectors are stable and not brittle.',
    2 => 'Credentials stored securely and rotated.',
    3 => 'Alerts configured with retries/cooldown.',
  ),
  'pitfalls' => 
  array (
    0 => 'Using real user credentials (security risk).',
    1 => 'Brittle selectors that flake on minor UI changes.',
    2 => 'CAPTCHA/MFA without a test bypass.',
  ),
  'related_faq' => 
  array (
    0 => 'how-to-monitor-login-flow-synthetically',
    1 => 'synthetic-checks-vs-http-checks',
    2 => 'can-i-monitor-private-internal-services',
  ),
);
