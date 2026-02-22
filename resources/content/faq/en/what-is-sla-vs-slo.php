<?php

return array (
  'order' => 7,
  'related' => 
  array (
    0 => 'what-is-uptime-monitoring',
    1 => 'what-is-a-status-page',
    2 => 'what-is-an-incident-response-playbook',
  ),
  'related_solutions' => 
  array (
    0 => 'choose-monitoring-plan',
    1 => 'uptime-monitoring-for-saas',
    2 => 'incident-response-playbook',
    3 => 'slo-sla-error-budgets',
  ),
  'question' => 'What is SLA vs SLO in monitoring?',
  'answer' => '<p><strong>SLO</strong> (Service Level Objective) is an <strong>internal</strong> reliability goal, for example 99.9% monthly availability for an API. <strong>SLA</strong> (Service Level Agreement) is an <strong>external</strong> contractual promise to customers, often tied to financial remedies (credits/discounts).</p>

<h3>How this maps to monitoring</h3>
<ul>
  <li><strong>SLI</strong>: what you measure (availability, error rate, latency percentile).</li>
  <li><strong>SLO</strong>: the target for that SLI (e.g., 99.9% per 30 days).</li>
  <li><strong>SLA</strong>: what you commit to customers (often lower than SLO to reduce risk).</li>
</ul>

<h3>Example (practical)</h3>
<ul>
  <li>SLI: “p95 API latency &lt; 800 ms”</li>
  <li>SLO: “99.9% per 30 days”</li>
  <li>SLA: “99.5% per 30 days + service credits if breached”</li>
</ul>

<h3>Common mistakes</h3>
<ul>
  <li>One SLO for everything. Landing pages and checkout flows have different risk profiles than internal admin.</li>
  <li>Setting SLA without baseline data from monitoring.</li>
  <li>Alerting without context: you want signals that reflect SLO impact, not raw noise.</li>
</ul>

<p>For a guided implementation, see <a href=\"/en/solutions/choose-monitoring-plan\">choosing a monitoring plan</a> and the <a href=\"/en/solutions/incident-response-playbook\">incident response playbook</a>.</p>',
  'meta_title' => 'What is SLA vs SLO in monitoring? | TruMonit',
  'meta_description' => 'SLA vs SLO (and SLI) explained: definitions, a concrete example, common mistakes, and how to connect monitoring data to reliability goals and customer commitments.',
);
