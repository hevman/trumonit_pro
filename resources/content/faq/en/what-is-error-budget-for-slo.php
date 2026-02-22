<?php

return array (
  'order' => 22,
  'related' => 
  array (
    0 => 'what-is-sla-vs-slo',
    1 => 'what-is-mttr-and-how-to-improve-it',
    2 => 'how-to-choose-a-website-monitoring-plan',
  ),
  'related_solutions' => 
  array (
    0 => 'incident-response-playbook',
    1 => 'maintenance-windows-during-deploys',
    2 => 'reduce-false-alerts',
  ),
  'question' => 'What is an error budget in SLO monitoring?',
  'answer' => '<p>An <strong>error budget</strong> is the allowed amount of failure implied by your <strong>SLO</strong>. Example: if your monthly availability SLO is 99.9%, your error budget is roughly 0.1% of the month. When the budget is spent, you should shift priority from shipping features to restoring reliability.</p>

<h3>Why it is useful</h3>
<ul>
  <li><strong>Clear decisions</strong>: it turns reliability into a measurable constraint, not a feeling.</li>
  <li><strong>Better tradeoffs</strong>: you can move faster while the budget is healthy, and slow down when it is burning.</li>
  <li><strong>Monitoring with context</strong>: incidents and alerts map to a goal instead of just red lights.</li>
</ul>

<h3>How to track burn rate (simple)</h3>
<ul>
  <li>Pick a <strong>window</strong> (e.g., 30 days) and define what counts as “bad” (5xx, timeout, latency over threshold).</li>
  <li>Set SLOs per surface (landing, checkout, API, webhook) instead of one global number.</li>
  <li>Watch how fast the budget is consumed. Sudden spikes usually mean a regression or dependency outage.</li>
</ul>

<h3>Common mistakes</h3>
<ul>
  <li>No deployment exceptions: use <a href=\"/en/solutions/maintenance-windows-during-deploys\">maintenance windows</a> so planned work does not create noisy incidents.</li>
  <li>Too many false alerts: tune checks and retry logic, see <a href=\"/en/solutions/reduce-false-alerts\">reducing false alerts</a>.</li>
  <li>Conflating SLA and SLO: SLA is contractual, SLO is internal. See <a href=\"/en/faq/what-is-sla-vs-slo\">SLA vs SLO</a>.</li>
</ul>

<p>To operationalize this, use an <a href=\"/en/solutions/incident-response-playbook\">incident response playbook</a> and treat error budgets as a real input to release decisions.</p>',
  'meta_title' => 'What is an error budget in SLO monitoring? | TruMonit',
  'meta_description' => 'An error budget is the failure margin implied by your SLO. Learn how to track burn rate, use maintenance windows, reduce false alerts, and decide when to pause releases.',
);
