<?php

return array (
  'order' => 2,
  'title' => 'API endpoint monitoring',
  'meta_title' => 'API Endpoint Monitoring | TruMonit',
  'meta_description' => 'Monitor API endpoints with status, latency, and payload assertions. Catch timeouts, 5xx spikes, bad JSON, and rate-limit regressions (429) before users do.',
  'summary' => 'High-signal API monitoring combines status + latency + stable payload checks, and separates 429/rate-limit from 5xx to reduce noise.',
  'body' => '<p>API monitoring is easy to get wrong: you can see <strong>HTTP 200</strong> while the experience is broken due to slow responses, wrong JSON, auth issues, or rate limiting. The goal is to detect <em>customer-impacting</em> failures with high signal and low noise.</p>

<h3>The minimum set of API monitors that works</h3>
<ul>
  <li><strong>Health</strong>: a fast technical endpoint (e.g. /health) for basic availability.</li>
  <li><strong>Business endpoint</strong>: a real request customers rely on (e.g. /api/v1/invoices).</li>
  <li><strong>Webhook/ingest</strong> (if applicable): a separate check for critical integrations.</li>
</ul>

<h3>Assertions: what to validate (and what to avoid)</h3>
<ul>
  <li>Status: acceptable ranges (usually 2xx; sometimes 3xx for redirects).</li>
  <li>Latency: warning + critical thresholds aligned with your SLO.</li>
  <li>Payload: stable marker (JSON key/value, keyword). Avoid dynamic text and timestamps.</li>
  <li>Auth: use a dedicated test token/user with the smallest possible scope.</li>
</ul>

<h3>Rate limits and 429: monitoring patterns</h3>
<ul>
  <li>Track 429 spikes and correlate with rising latency and error rate.</li>
  <li>Watch out for retry storms: aggressive client retries can amplify incidents.</li>
  <li>Separate alerts: "rate limited" vs "application error" routes to different owners.</li>
</ul>

<h3>Common API incident patterns</h3>
<ul>
  <li>Timeouts due to DB saturation, locks, slow upstream dependencies.</li>
  <li>5xx after deploy (serialization, migrations, config).</li>
  <li>200 with bad JSON (contract changes, nulls, broken validation).</li>
</ul>

<p>Rule of thumb: <strong>monitor what customers pay for</strong>. If the only check is /health, you will learn about incidents from support.</p>',
  'problem' => 'APIs can look "up" (200) while the business flow is broken (slow, wrong payload, auth issues, rate-limited). You need monitoring that measures both availability and response quality.',
  'approach' => 
  array (
    0 => 'Add monitors for health + one business-critical endpoint.',
    1 => 'Set timeout and latency thresholds based on real SLOs.',
    2 => 'Add stable keyword/JSON assertions for correctness.',
    3 => 'Monitor 429/rate limits and separate them from 5xx.',
    4 => 'Route incidents to the owning team via escalation policy and cooldown.',
  ),
  'proof' => 
  array (
    0 => 'Early detection of timeouts, 5xx, and rate-limit regressions.',
    1 => 'Clearer separation of network vs app vs throttling issues.',
    2 => 'Incident history you can use for MTTR review and postmortems.',
  ),
  'checklist' => 
  array (
    0 => 'Health + business endpoint monitored.',
    1 => 'Timeouts and latency thresholds configured.',
    2 => 'Assertion uses stable marker (not dynamic copy).',
    3 => '429/rate-limit monitoring and alerting in place.',
  ),
  'pitfalls' => 
  array (
    0 => 'Only monitoring /health and missing business failures.',
    1 => 'Brittle assertions that alert on harmless changes.',
    2 => 'No dedicated test data/token for auth-protected endpoints.',
  ),
  'related_faq' => 
  array (
    0 => 'how-do-i-monitor-api-endpoints',
    1 => 'network-error-vs-application-error-monitoring',
    2 => 'how-to-monitor-api-rate-limits',
    3 => 'how-to-monitor-webhook-endpoints',
  ),
);
