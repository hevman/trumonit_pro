<?php

return array (
  'order' => 2,
  'title' => 'API endpoint monitoring',
  'meta_title' => 'API Endpoint Monitoring | TruMonit',
  'meta_description' => 'Monitor API endpoints with status, latency, and content assertions. Catch timeouts, 5xx spikes, and rate-limit regressions before users do.',
  'summary' => 'Monitor APIs with the right checks: status, latency, keywords, and rate-limits, without false alarms.',
  'body' => '<p>API monitoring fails when checks are too shallow (only status code) or too strict (brittle content rules). The goal is to detect customer-impacting failures with high signal.</p>

<h3>Recommended setup</h3>
<ul>
  <li>Monitor a <strong>health</strong> endpoint and a <strong>real business</strong> endpoint.</li>
  <li>Define acceptable status ranges (2xx/3xx) and timeouts.</li>
  <li>Add a stable marker: a JSON key or keyword (avoid dynamic text).</li>
</ul>

<h3>Common API incident patterns</h3>
<ul>
  <li>Timeouts due to DB saturation or slow upstream.</li>
  <li>5xx errors after deploy.</li>
  <li>429 spikes (rate limits) and retry storms.</li>
</ul>',
  'problem' => 'APIs can be \"up\" (200) while the business flow is broken (slow, wrong payload, rate-limited). Teams need monitoring that matches user journeys.',
  'approach' => 
  array (
    0 => 'Add monitors for health + one business-critical endpoint.',
    1 => 'Set timeout and latency thresholds based on real SLOs.',
    2 => 'Add stable keyword/JSON assertions for correctness.',
    3 => 'Monitor rate limits and 429 patterns.',
    4 => 'Route incidents to the owning team via escalation policy.',
  ),
  'proof' => 
  array (
    0 => 'Early detection of timeouts, 5xx, and rate-limit regressions.',
    1 => 'Clear separation of network vs application errors.',
    2 => 'Incident history you can use for postmortems.',
  ),
  'checklist' => 
  array (
    0 => 'Health + business endpoint monitored.',
    1 => 'Timeouts and latency thresholds configured.',
    2 => 'Assertion uses stable marker (not dynamic copy).',
    3 => '429 monitoring and alerting in place.',
  ),
  'pitfalls' => 
  array (
    0 => 'Only monitoring /health and missing business failures.',
    1 => 'Brittle assertions that alert on harmless changes.',
    2 => 'No dedicated test endpoints for synthetic flows.',
  ),
  'related_faq' => 
  array (
    0 => 'how-do-i-monitor-api-endpoints',
    1 => 'network-error-vs-application-error-monitoring',
    2 => 'how-to-monitor-api-rate-limits',
    3 => 'how-to-monitor-webhook-endpoints',
  ),
);

