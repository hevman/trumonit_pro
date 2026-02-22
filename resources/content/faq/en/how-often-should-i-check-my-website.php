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
  'answer' => '<p>Most teams start with <strong>1–5 minute</strong> checks. For critical flows (checkout, login, core API), <strong>1 minute</strong> is common. For low-risk pages, 5 minutes is usually enough.</p>

<h3>How to pick frequency</h3>
<ul>
  <li><strong>1 min</strong>: critical endpoints and "money path" journeys.</li>
  <li><strong>5 min</strong>: informational pages and lower-risk integrations.</li>
  <li><strong>Mix intervals</strong>: do not use one frequency for everything.</li>
</ul>

<h3>More important than frequency</h3>
<ul>
  <li>Consecutive failures (2–3) + retries + cooldown to reduce noise.</li>
  <li>Multi-region checks for critical monitors (DNS/routing issues happen).</li>
  <li>Reasonable timeouts and stable content assertions.</li>
</ul>

<p>Rule of thumb: cover your critical paths at 1 min first, then expand coverage at 5 min.</p>',
  'meta_title' => 'How often should I check my website? | TruMonit',
  'meta_description' => 'How to choose monitoring frequency: 1 min for critical paths, 5 min for lower-risk pages. Noise reduction: retries, consecutive failures, cooldown, multi-region.',
);
