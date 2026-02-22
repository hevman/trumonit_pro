<?php

return array (
  'order' => 22,
  'title' => 'Rate limit (429) and quota exhaustion monitoring',
  'meta_title' => 'API rate limit and 429 monitoring | TruMonit',
  'meta_description' => 'Detect 429 rate limits and quota exhaustion before production breaks. Monitor trends, latency spikes, and prevent retry storms with backoff.',
  'summary' => 'Rate limits are a common source of “random” outages. Monitor 429s and dependency latency before your system cascades.',
  'body' => '<p>API limits are sneaky: everything works until traffic grows or behavior changes. Then 429s appear, retries kick in, latency spikes, and availability drops. If you rely on third parties, rate limit monitoring is a must.</p>

<h3>What to watch</h3>
<ul>
  <li>Rising <strong>429</strong> and 5xx on dependency paths.</li>
  <li>Latency spikes (often from retries/backoff).</li>
  <li>Request volume changes after releases.</li>
</ul>

<h3>How to respond</h3>
<ul>
  <li>Backoff and bounded retries (avoid retry storms).</li>
  <li>Caching and batching where possible.</li>
  <li>Alert before you hit hard quotas.</li>
</ul>

<p>Related: <a href="/en/solutions/monitoring-third-party-dependencies">third-party dependency monitoring</a> and <a href="/en/solutions/api-endpoint-monitoring">API endpoint monitoring</a>. See FAQ: <a href="/en/faq/how-to-monitor-api-rate-limits">rate limit monitoring</a>.</p>',
  'problem' => 'Rate limiting can look like an application outage, but the root cause is a vendor quota or uncontrolled retries. Without tracking 429 trends and volumes, diagnosis takes too long.',
  'approach' => array (
    0 => 'Monitor 429/5xx rates and latency for dependency endpoints.',
    1 => 'Alert on trends (rising 429), not only single errors.',
    2 => 'Implement backoff, caching, and bounded retries.',
    3 => 'Split critical dependencies into separate monitors.',
  ),
  'proof' => array (
    0 => 'Detect quota exhaustion early.',
    1 => 'Avoid cascading failures from retry storms.',
    2 => 'Clear metrics for vendor conversations.',
  ),
  'checklist' => array (
    0 => '429, 5xx, and latency are monitored for dependencies.',
    1 => 'Retries are bounded and use backoff.',
    2 => 'Alerts trigger on sustained trends.',
  ),
  'pitfalls' => array (
    0 => 'Unbounded retries that create self-inflicted DDoS.',
    1 => 'Only monitoring 200/500 without 429 visibility.',
    2 => 'No separate dependency monitors.',
  ),
  'related_faq' => array (
    0 => 'how-to-monitor-api-rate-limits',
    1 => 'how-to-monitor-third-party-dependencies',
    2 => 'what-causes-website-downtime',
  ),
);