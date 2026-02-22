<?php

return array (
  'order' => 21,
  'title' => 'Latency monitoring with p95/p99 (alerts that make sense)',
  'meta_title' => 'Latency monitoring p95/p99 | TruMonit',
  'meta_description' => 'How to monitor latency: p95/p99 thresholds, network vs application diagnosis, retries, and when to alert. Practical settings for APIs and websites.',
  'summary' => 'Uptime is not enough: your service can be “up” and still be slow. Track p95/p99 to catch degradations before they become outages.',
  'body' => '<p>The most frustrating failures are not 500 errors, but “it works, just slow”. Users see spinners and abandon flows while dashboards stay green. Good latency monitoring catches <strong>degradations</strong> before they become full outages.</p>

<h3>How to set thresholds</h3>
<ul>
  <li>Do not rely on averages. Use <strong>p95</strong> or <strong>p99</strong>.</li>
  <li>Different thresholds for different flows: login and checkout are more sensitive.</li>
  <li>Alert on sustained trends, not one-off spikes.</li>
</ul>

<h3>Diagnosis: network vs application</h3>
<ul>
  <li>Latency plus timeouts often points to app/DB pressure.</li>
  <li>Latency without errors can be CDN/proxy or third-party dependencies.</li>
</ul>

<p>Related: <a href="/en/solutions/reduce-false-alerts">reducing false alerts</a> and <a href="/en/solutions/monitoring-third-party-dependencies">dependency monitoring</a>. See FAQ: <a href="/en/faq/network-error-vs-application-error-monitoring">network vs application errors</a>.</p>',
  'problem' => 'High latency is hidden downtime. Without p95/p99 monitoring you lose conversion and trust long before you see clear 5xx errors.',
  'approach' => array (
    0 => 'Set p95/p99 thresholds per critical endpoint/page.',
    1 => 'Use retries and cooldowns to avoid noise.',
    2 => 'Track latency alongside error rate for faster diagnosis.',
    3 => 'Monitor external dependencies separately.',
  ),
  'proof' => array (
    0 => 'Catch degradations before they become outages.',
    1 => 'Reduce “silent” conversion loss in slow flows.',
    2 => 'Faster diagnosis by correlating latency and errors.',
  ),
  'checklist' => array (
    0 => 'p95/p99 thresholds exist, not only averages.',
    1 => 'Critical flows have separate thresholds.',
    2 => 'Alerts use retries and cooldowns.',
  ),
  'pitfalls' => array (
    0 => 'Alerting on average latency (hides the tail).',
    1 => 'One threshold for everything.',
    2 => 'No correlation with error rate and no diagnosis.',
  ),
  'related_faq' => array (
    0 => 'network-error-vs-application-error-monitoring',
    1 => 'how-often-should-i-check-my-website',
    2 => 'how-do-i-reduce-false-alerts',
  ),
);