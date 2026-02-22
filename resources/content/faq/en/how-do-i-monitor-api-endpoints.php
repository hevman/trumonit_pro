<?php

return array (
  'order' => 3,
  'related' => 
  array (
    0 => 'synthetic-checks-vs-http-checks',
    1 => 'how-do-i-monitor-ssl-certificate-expiry',
    2 => 'how-do-i-reduce-false-alerts',
  ),
  'related_solutions' => 
  array (
    0 => 'api-endpoint-monitoring',
  ),
  'question' => 'How do I monitor API endpoints?',
  'answer' => '<p>Good <strong>API monitoring</strong> is not just “status 200”. The goal is to detect customer-impacting failures: timeouts, 5xx spikes, rate limiting (429), and wrong payloads.</p>

<h3>Minimum set of API monitors</h3>
<ul>
  <li><strong>/health</strong> (technical): a fast availability signal.</li>
  <li><strong>Business endpoint</strong>: a real request customers rely on (e.g. invoices list).</li>
  <li><strong>Webhook/ingest</strong> (if applicable): a separate monitor for integrations.</li>
</ul>

<h3>What to validate</h3>
<ul>
  <li>Status: acceptable ranges (usually 2xx; sometimes 3xx).</li>
  <li>Latency: warning/critical thresholds aligned with your SLO.</li>
  <li>Payload: stable marker (JSON key/value or keyword), not dynamic copy/timestamps.</li>
  <li>Auth: dedicated test token/user with minimal scope.</li>
</ul>

<h3>Common mistakes</h3>
<ul>
  <li>Only monitoring /health and missing business failures.</li>
  <li>Timeouts set too tight, creating noise.</li>
  <li>Brittle assertions that alert on harmless changes.</li>
</ul>

<p>Start with two monitors: /health + one business endpoint. Then add 429/rate-limit and webhook coverage if you need it.</p>',
  'meta_title' => 'How do I monitor API endpoints? | TruMonit',
  'meta_description' => 'Practical API monitoring: /health + business endpoint, latency thresholds, stable JSON/keyword assertions, and separating 429 (rate limit) from 5xx.',
);
