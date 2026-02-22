<?php

return array (
  'order' => 10,
  'related' => 
  array (
    0 => 'how-often-should-i-check-my-website',
    1 => 'what-is-uptime-monitoring',
    2 => 'how-do-i-monitor-api-endpoints',
  ),
  'related_solutions' => 
  array (
    0 => 'choose-monitoring-plan',
  ),
  'question' => 'How should I choose a website monitoring plan?',
  'answer' => '<p>Pick a monitoring plan based on <strong>how fast you need to know</strong> something is broken and <strong>how expensive downtime is</strong>. Two sites with the same number of URLs can need very different monitoring setups.</p>

<h3>What matters most</h3>
<ul>
  <li><strong>Monitor count</strong>: do not equate this with pages. Monitor business-critical flows (login, payments, webhooks).</li>
  <li><strong>Frequency</strong>: higher downtime cost usually means shorter intervals for P0 checks.</li>
  <li><strong>Alert routing</strong>: Slack/Teams/webhooks, escalation rules, and who gets P0.</li>
  <li><strong>Check types</strong>: uptime/HTTP vs synthetic flows and content assertions.</li>
  <li><strong>Reporting</strong>: data you need for SLO/SLA discussions and audits.</li>
</ul>

<h3>A simple heuristic</h3>
<ul>
  <li>Small site, “is it up”: start with uptime + SSL.</li>
  <li>SaaS with critical actions: add API checks + 1–2 synthetic flows.</li>
  <li>Agencies/multi-project: notification channels and workflow become the differentiator.</li>
</ul>

<h3>How to avoid overspending</h3>
<ul>
  <li>Start with 5–10 high-signal monitors, then expand.</li>
  <li>Reduce false alerts before increasing frequency.</li>
  <li>Separate P0 monitors from “nice to have” checks.</li>
</ul>

<p>See also: <a href=\"/en/solutions/choose-monitoring-plan\">the plan selection use case</a> and pricing: <a href=\"/en/pricing\">/en/pricing</a>.</p>',
  'meta_title' => 'How should I choose a website monitoring plan? | TruMonit',
  'meta_description' => 'How to choose a monitoring plan: monitors vs critical flows, frequency, alert routing, check types (uptime, synthetic, content), and a simple heuristic for SaaS and agencies.',
);
