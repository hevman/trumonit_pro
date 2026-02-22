<?php

return array (
  'order' => 2,
  'related' => 
  array (
    0 => 'what-is-uptime-monitoring',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'synthetic-checks-vs-http-checks',
  ),
  'related_solutions' => 
  array (
    0 => 'uptime-monitoring-for-saas',
    1 => 'choose-monitoring-plan',
  ),
  'question' => 'How often should I check my website?',
  'answer' => '<p>For most teams, the right starting point is <strong>1-5 minute checks</strong>. Use 1 minute for revenue-critical paths (checkout, login, API auth), and 5 minutes for lower-risk pages. One frequency for everything is rarely optimal.</p>

<h3>Practical interval model</h3>
<ul>
  <li><strong>1 minute:</strong> money-path endpoints and business-critical APIs.</li>
  <li><strong>3 minutes:</strong> important but non-critical service components.</li>
  <li><strong>5 minutes:</strong> informational pages and low-risk integrations.</li>
</ul>

<h3>What matters more than interval</h3>
<ul>
  <li>Consecutive failures (2-3) before alerting.</li>
  <li>Short retries and cooldown to prevent alert storms.</li>
  <li>Multi-region checks for high-impact monitors.</li>
  <li>Content assertions to detect false 200 responses.</li>
</ul>

<p>If your uptime checker is noisy, faster intervals only amplify noise. Tune thresholds and routing first, then increase frequency where the business impact justifies it.</p>

<p>Related: <a href="/en/solutions/uptime-checker-for-multi-site-teams">uptime checker for multi-site teams</a>, <a href="/en/solutions/ping-monitoring-vs-http-monitoring">ping monitoring vs HTTP monitoring</a>, and <a href="/en/solutions/choose-monitoring-plan">choose monitoring plan</a>.</p>',
  'meta_title' => 'How often should I check my website? | TruMonit',
  'meta_description' => 'Choose monitoring frequency by risk: 1 minute for critical flows, 5 minutes for lower-risk pages, plus retries and cooldown to reduce alert noise.',
);