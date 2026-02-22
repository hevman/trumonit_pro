<?php

return array (
  'order' => 5,
  'title' => 'Reduce false alerts',
  'meta_title' => 'How to Reduce False Alerts in Monitoring | TruMonit',
  'meta_description' => 'Reduce alert fatigue with consecutive failures, retries, cooldowns, maintenance windows, and multi-region checks. Keep signal high and noise low.',
  'summary' => 'False alerts kill trust in monitoring. This playbook shows how to tune retries, thresholds, and ownership so paging stays actionable.',
  'body' => '<p>False alerts are a reliability problem. Once engineers stop trusting alerts, real incidents get ignored or noticed late. The goal is simple: <strong>high signal, low noise</strong>.</p>

<h3>Defaults that work in practice</h3>
<ul>
  <li>Alert after <strong>2–3 consecutive failures</strong> (not after one).</li>
  <li>Add <strong>retries</strong> to separate transient glitches from real outages.</li>
  <li>Use a <strong>cooldown</strong> so you do not page repeatedly for the same incident.</li>
  <li>Schedule <strong>maintenance windows</strong> for planned work and migrations.</li>
  <li>Use <strong>multi-region</strong> checks for critical endpoints (DNS/routing issues happen).</li>
</ul>

<h3>Common sources of noise</h3>
<ul>
  <li>Timeouts set too tight for normal variance.</li>
  <li>Brittle content assertions (dynamic text/timestamps).</li>
  <li>Third-party dependency failures with no separate classification.</li>
  <li>No clear owner, so alerts bounce around or get ignored.</li>
</ul>

<h3>Weekly review (15 minutes)</h3>
<ol>
  <li>List false positives and classify the cause (timeout, DNS, deploy, 3rd-party).</li>
  <li>Tune thresholds: timeout, consecutive failures, retries, cooldown.</li>
  <li>Update playbooks and escalation owners.</li>
</ol>

<p>If monitoring is part of your sales pitch, paging cannot be noisy. Noise is process debt.</p>',
  'problem' => 'Alert fatigue makes teams slower and less reliable. Monitoring must be designed for trust: high signal, low noise, and clear ownership.',
  'approach' => 
  array (
    0 => 'Use consecutive failures before alerting.',
    1 => 'Add retries, cooldowns, and deduplication.',
    2 => 'Use maintenance windows for planned changes.',
    3 => 'Adopt multi-region checks for critical endpoints.',
    4 => 'Review alert noise weekly and tune ownership.',
  ),
  'proof' => 
  array (
    0 => 'Less noise, faster response to real incidents.',
    1 => 'Better on-call quality and fewer ignored alerts.',
    2 => 'Clear history of incidents and alert patterns.',
  ),
  'checklist' => 
  array (
    0 => 'Consecutive failures enabled.',
    1 => 'Retries + cooldown/deduplication enabled.',
    2 => 'Maintenance windows used.',
    3 => 'Critical endpoints have multi-region checks.',
    4 => 'Escalation owners and playbooks defined.',
  ),
  'pitfalls' => 
  array (
    0 => 'Over-tight thresholds that alert on normal variance.',
    1 => 'No clear owner rotation/assignment.',
    2 => 'One rule for everything (no separation: network vs app vs 3rd-party).',
  ),
  'related_faq' => 
  array (
    0 => 'how-do-i-reduce-false-alerts',
    1 => 'multi-region-monitoring-why-important',
    2 => 'network-error-vs-application-error-monitoring',
  ),
);
