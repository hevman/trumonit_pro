<?php

return array (
  'order' => 20,
  'related' => 
  array (
    0 => 'synthetic-checks-vs-http-checks',
    1 => 'how-often-should-i-check-my-website',
    2 => 'network-error-vs-application-error-monitoring',
  ),
  'related_solutions' => 
  array (
    0 => 'content-integrity-monitoring-keywords',
    1 => 'reduce-false-alerts',
    2 => 'synthetic-monitoring-login-flow',
  ),
  'question' => 'How do I monitor a keyword or content marker on a page?',
  'answer' => '<p>Keyword monitoring is really <strong>content integrity monitoring</strong>: you verify that an expected marker is present (text, HTML fragment, JSON field). This catches silent regressions like missing buttons, broken embeds, or redirects to login pages.</p>

<h3>What to check (best markers)</h3>
<ul>
  <li><strong>Stable HTML markers</strong>: an id, a <code>data-testid</code> attribute, or a unique element.</li>
  <li><strong>Stable JSON fields</strong> for API responses.</li>
  <li><strong>Short technical text</strong> over long marketing copy.</li>
</ul>

<h3>How to reduce false alerts</h3>
<ul>
  <li>Avoid dynamic content (A/B tests, personalization, locale-specific copy) as your primary signal.</li>
  <li>Use both <strong>allow</strong> and <strong>deny</strong> markers (e.g., require “Checkout”, block “Error”).</li>
  <li>Use retries and thresholds so one transient glitch does not create an incident.</li>
</ul>

<h3>Practical examples</h3>
<ul>
  <li>Login page: form marker is present and “Invalid CSRF token” is absent.</li>
  <li>Checkout: “Pay” button marker exists and cart is not empty.</li>
  <li>Integration pages: “Connected” marker exists after deploy.</li>
</ul>

<p>Related: <a href=\"/en/solutions/content-integrity-monitoring-keywords\">content integrity checks (allow/deny keywords)</a>, <a href=\"/en/solutions/reduce-false-alerts\">reducing false alerts</a>, and <a href=\"/en/solutions/synthetic-monitoring-login-flow\">synthetic flow monitoring</a>.</p>',
  'meta_title' => 'How do I monitor a keyword or content marker on a page? | TruMonit',
  'meta_description' => 'How to monitor content markers: stable HTML/JSON signals, allow/deny keywords, examples for login and checkout, and tips to reduce false alerts when UI copy changes.',
);
