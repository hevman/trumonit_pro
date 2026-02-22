<?php

return array (
  'order' => 1,
  'related' => 
  array (
    0 => 'how-often-should-i-check-my-website',
    1 => 'what-is-sla-vs-slo',
    2 => 'what-is-a-status-page',
  ),
  'related_solutions' => 
  array (
    0 => 'uptime-monitoring-for-saas',
  ),
  'question' => 'What is uptime monitoring?',
  'answer' => '<p><strong>Uptime monitoring</strong> is scheduled checking of whether your website or API is reachable and healthy. It typically validates:</p>

<ul>
  <li>HTTP status (e.g. 2xx/3xx),</li>
  <li>response time (latency),</li>
  <li>optional content rules (keyword/JSON) to avoid "false 200".</li>
</ul>

<p>The goal is fast outage detection, immediate alerting, and a clear availability history (incident timeline).</p>

<h3>What it gives you in practice</h3>
<ul>
  <li>Earlier detection (fewer customer reports).</li>
  <li>Better MTTR through clear alerts and escalation.</li>
  <li>Operational proof: when it broke and for how long (useful for SLA/SLO).</li>
</ul>

<p>Key point: uptime monitoring must be high-signal. If it is too noisy, teams stop using it.</p>',
  'meta_title' => 'What is uptime monitoring? | TruMonit',
  'meta_description' => 'Uptime monitoring checks website/API availability (status, latency, content rules) and alerts on outages. It provides incident history and supports SLA/SLO.',
);
