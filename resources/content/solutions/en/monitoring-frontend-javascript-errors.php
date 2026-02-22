<?php

return array (
  'order' => 26,
  'title' => 'Monitoring frontend JavaScript errors and UI regressions (synthetic)',
  'meta_title' => 'Frontend JS and UI monitoring | TruMonit',
  'meta_description' => 'HTTP 200 does not mean the UI works. Synthetic monitoring detects JavaScript errors, missing elements, redirect loops, and UX breakages after releases.',
  'summary' => 'The most expensive failures are invisible to HTTP checks. Synthetic checks see what users see: UI, redirects, and JS errors.',
  'body' => '<p>Your site can return 200 while users cannot do anything: a JS error blocks interactions, a form never renders, or your app loops redirects. Basic uptime checks miss this. <strong>Synthetic monitoring</strong> catches it.</p>

<h3>What synthetic can detect</h3>
<ul>
  <li>JavaScript console errors.</li>
  <li>Missing critical UI elements (buttons, forms).</li>
  <li>Redirect loops in a real browser.</li>
  <li>CSP and cookie-related issues.</li>
</ul>

<h3>How to make it stable</h3>
<ul>
  <li>Use <code>data-testid</code> selectors.</li>
  <li>Assert 1–2 critical elements, not full layouts.</li>
  <li>Add retries and cooldowns to avoid paging on flakes.</li>
</ul>

<p>Related: <a href="/en/solutions/synthetic-monitoring-login-flow">login flow monitoring</a> and <a href="/en/solutions/release-regression-monitoring">release checks</a>.</p>',
  'problem' => 'UI can be broken while HTTP stays green. Without synthetic checks you learn about JS regressions from support tickets, not from monitoring.',
  'approach' => array (
    0 => 'Define a critical flow and a success condition.',
    1 => 'Use stable selectors and keep assertions minimal.',
    2 => 'Detect JS errors and redirect loops.',
    3 => 'Add retries/cooldowns and route alerts.',
  ),
  'proof' => array (
    0 => 'Catch UI regressions before customers do.',
    1 => 'Reduce silent post-release failures.',
    2 => 'Better signal for critical journeys.',
  ),
  'checklist' => array (
    0 => 'Selectors are stable (data-testid).',
    1 => 'Assertions focus on critical elements.',
    2 => 'Alerts use retries and cooldowns.',
  ),
  'pitfalls' => array (
    0 => 'Brittle selectors that break on CSS changes.',
    1 => 'Too many assertions and constant false positives.',
    2 => 'No test accounts and security risk.',
  ),
  'related_faq' => array (
    0 => 'synthetic-checks-vs-http-checks',
    1 => 'how-to-monitor-login-flow-synthetically',
    2 => 'what-causes-website-downtime',
  ),
);