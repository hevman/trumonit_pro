<?php

return array (
  'order' => 19,
  'related' => 
  array (
    0 => 'synthetic-checks-vs-http-checks',
    1 => 'how-do-i-monitor-api-endpoints',
    2 => 'how-do-i-reduce-false-alerts',
  ),
  'related_solutions' => 
  array (
    0 => 'synthetic-monitoring-login-flow',
  ),
  'question' => 'How do I monitor a login flow with synthetic checks (Playwright)?',
  'answer' => '<p>Login monitoring with <strong>synthetic checks</strong> means simulating a real user in a browser (e.g. Playwright): open the login page, submit credentials, and validate a post-login element.</p>

<h3>Minimum scenario (works for most teams)</h3>
<ol>
  <li>Open the login page.</li>
  <li>Enter test credentials (email/password).</li>
  <li>Click "Sign in".</li>
  <li>Validate a stable post-login element (menu, avatar, dashboard header).</li>
</ol>

<h3>How to keep it stable (avoid flakiness)</h3>
<ul>
  <li>Use stable selectors like <code>data-testid</code>, not CSS classes.</li>
  <li>Validate 1–2 critical elements, not the full UI.</li>
  <li>Use retries and alert cooldown.</li>
</ul>

<h3>Security and test accounts</h3>
<ul>
  <li>Use a dedicated test account with minimal permissions (no admin).</li>
  <li>Store and rotate credentials via secrets management.</li>
  <li>Avoid CAPTCHA/MFA in monitored flows; add a test-only bypass if needed.</li>
</ul>

<p>In practice, synthetic login monitoring is the best way to catch UI/JS regressions right after deploys.</p>',
  'meta_title' => 'How do I monitor a login flow with synthetic checks? | TruMonit',
  'meta_description' => 'Synthetic login monitoring (Playwright): step-by-step scenario, stable selectors (data-testid), retries/cooldown, and secure test account practices.',
);
