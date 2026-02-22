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
  'answer' => '<p>A <strong>maintenance window</strong> is a scheduled time period when alerts are temporarily silenced during planned work: deploys, migrations, infrastructure changes.</p>

<h3>Why it matters</h3>
<ul>
  <li>Prevents alert spam during changes you expect to be noisy.</li>
  <li>Keeps incident history cleaner (fewer false positives).</li>
</ul>

<h3>How to use it safely</h3>
<ul>
  <li>Always set a clear <strong>start and end</strong> time.</li>
  <li>Assign an <strong>owner</strong> to end the window early if needed.</li>
  <li>Run a post-deploy verification checklist for critical pages and APIs.</li>
</ul>

<p>More: <a href=\"/en/solutions/maintenance-windows-during-deploys\">maintenance windows during deploys (practical guide)</a>.</p>',
  'meta_title' => 'What is a maintenance window in monitoring? | TruMonit',
  'meta_description' => 'Maintenance windows in monitoring: what they are, how to set start/end and ownership, and how to avoid masking real incidents. Practical post-deploy checklist.',
);
