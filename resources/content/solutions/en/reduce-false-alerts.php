<?php

return array (
  'order' => 5,
  'title' => 'Reduce false alerts',
  'meta_title' => 'How to Reduce False Alerts in Monitoring | TruMonit',
  'meta_description' => 'Reduce alert fatigue: retries, consecutive failures, cooldowns, maintenance windows, and multi-region checks. Keep signal high.',
  'summary' => 'A practical playbook to reduce alert noise and keep incidents actionable.',
  'body' => '<p>False alerts are a reliability problem. If engineers stop trusting alerts, real incidents get missed.</p>

<h3>High-signal defaults</h3>
<ul>
  <li>Alert after <strong>2-3 consecutive failures</strong>.</li>
  <li>Use a <strong>cooldown</strong> to avoid repeated pages for the same outage.</li>
  <li>Use <strong>multi-region</strong> checks for critical endpoints.</li>
  <li>Schedule <strong>maintenance windows</strong> for planned work.</li>
</ul>

<h3>Weekly review</h3>
<ol>
  <li>List false positives and classify the cause.</li>
  <li>Adjust thresholds or add stable assertions.</li>
  <li>Update playbooks and escalation owners.</li>
</ol>',
  'problem' => 'Alert fatigue makes teams slower and less reliable. Monitoring must be designed for trust: high signal, low noise.',
  'approach' => 
  array (
    0 => 'Use consecutive failures before alerting.',
    1 => 'Add cooldowns and deduplication.',
    2 => 'Use maintenance windows for planned changes.',
    3 => 'Adopt multi-region checks for critical endpoints.',
  ),
  'proof' => 
  array (
    0 => 'Less noise, faster response to real incidents.',
    1 => 'Clear history of incidents and alert patterns.',
  ),
  'checklist' => 
  array (
    0 => 'Consecutive failures enabled.',
    1 => 'Cooldown/deduplication enabled.',
    2 => 'Maintenance windows used.',
    3 => 'Critical endpoints have multi-region checks.',
  ),
  'pitfalls' => 
  array (
    0 => 'Over-tight thresholds that alert on normal variance.',
    1 => 'No owner rotation (alerts go to the wrong person).',
  ),
  'related_faq' => 
  array (
    0 => 'how-do-i-reduce-false-alerts',
    1 => 'multi-region-monitoring-why-important',
    2 => 'network-error-vs-application-error-monitoring',
  ),
);

