<?php

return array (
  'order' => 16,
  'related' => 
  array (
    0 => 'how-do-i-reduce-false-alerts',
    1 => 'what-is-an-incident-response-playbook',
    2 => 'what-is-mttr-and-how-to-improve-it',
  ),
  'related_solutions' => 
  array (
    0 => 'maintenance-windows-during-deploys',
    1 => 'reduce-false-alerts',
    2 => 'incident-response-playbook',
  ),
  'question' => 'What is a maintenance window in monitoring?',
  'answer' => '<p>A <strong>maintenance window</strong> is a planned period where selected alerts are muted while you run expected-risk work such as deployments, migrations, or infrastructure changes. It protects on-call focus and keeps incident history meaningful.</p>

<h3>When to use it</h3>
<ul>
  <li>Version releases that can restart services.</li>
  <li>Database or cache migrations.</li>
  <li>Network changes, DNS moves, CDN reconfiguration.</li>
</ul>

<h3>How to avoid masking real outages</h3>
<ul>
  <li>Mute only relevant monitors, not the whole environment.</li>
  <li>Set exact start and end times with one accountable owner.</li>
  <li>Keep critical synthetic checks visible where possible.</li>
  <li>Run a post-change verification checklist before closing the window.</li>
</ul>

<p>A good maintenance-window policy reduces false alerts without hiding actual incidents. Teams that skip policy usually either drown in noise or miss real regressions.</p>

<p>Related: <a href="/en/solutions/maintenance-windows-during-deploys">maintenance windows during deploys</a>, <a href="/en/solutions/reduce-false-alerts">reduce false alerts</a>, and <a href="/en/solutions/release-regression-monitoring">release regression monitoring</a>.</p>',
  'meta_title' => 'What is a maintenance window in monitoring? | TruMonit',
  'meta_description' => 'Maintenance windows mute expected deployment noise while preserving signal. Use scoped muting, clear ownership, and post-deploy verification to avoid blind spots.',
);
