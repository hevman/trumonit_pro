<?php

return array (
  'order' => 11,
  'related' => 
  array (
    0 => 'what-is-uptime-monitoring',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'how-to-build-on-call-escalation-policy',
  ),
  'related_solutions' => 
  array (
    0 => 'uptime-monitoring-for-saas',
    1 => 'ssl-certificate-expiry-monitoring',
    2 => 'monitoring-third-party-dependencies',
  ),
  'question' => 'What are the most common causes of website downtime?',
  'answer' => '<p>Downtime is rarely one single thing. Most outages are a mix of deployments, dependencies, and capacity limits. The good news is that most causes can be detected earlier with the right monitoring.</p>

<h3>The top causes (common patterns)</h3>
<ul>
  <li><strong>Deployment mistakes</strong>: bad migrations, regressions, config changes.</li>
  <li><strong>Database saturation</strong>: locks, CPU/IO bottlenecks, slow queries.</li>
  <li><strong>Expired TLS certificates</strong>: sudden browser failures.</li>
  <li><strong>DNS issues</strong>: wrong records, propagation, provider incidents.</li>
  <li><strong>Third-party outages</strong>: CDNs, payments, email, external APIs.</li>
  <li><strong>Rate limits</strong> and quotas: 429 responses, WAF throttling.</li>
  <li><strong>Configuration</strong>: env mistakes, proxy/firewall changes, CORS issues.</li>
</ul>

<h3>What to monitor to catch it early</h3>
<ul>
  <li>Uptime + status code + latency for critical endpoints.</li>
  <li>SSL expiry and certificate errors.</li>
  <li>Third-party dependencies as separate monitors.</li>
  <li>Content markers for silent regressions after releases.</li>
</ul>

<h3>How to shorten MTTR</h3>
<ul>
  <li>Use P0/P1/P2 severities and clear alert routing.</li>
  <li>Add maintenance windows during deployments to reduce noise.</li>
  <li>Run postmortems with concrete corrective actions.</li>
</ul>

<p>Related: <a href=\"/en/solutions/uptime-monitoring-for-saas\">uptime monitoring for SaaS</a>, <a href=\"/en/solutions/ssl-certificate-expiry-monitoring\">SSL expiry monitoring</a>, and <a href=\"/en/solutions/monitoring-third-party-dependencies\">third-party dependency monitoring</a>.</p>',
  'meta_title' => 'What are the most common causes of website downtime? | TruMonit',
  'meta_description' => 'Common downtime causes: deployments, database saturation, SSL expiry, DNS errors, third-party outages, and rate limits. What to monitor to detect issues earlier and cut MTTR.',
);
