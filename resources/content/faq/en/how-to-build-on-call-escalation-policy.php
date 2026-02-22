<?php

return array (
  'order' => 18,
  'related' => 
  array (
    0 => 'best-alert-channels-for-uptime-monitoring',
    1 => 'what-is-an-incident-response-playbook',
    2 => 'what-is-mttr-and-how-to-improve-it',
  ),
  'related_solutions' => 
  array (
    0 => 'alert-routing-slack-teams-webhooks',
    1 => 'incident-response-playbook',
    2 => 'reduce-false-alerts',
  ),
  'question' => 'How do I build an on-call escalation policy?',
  'answer' => '<p>A good on-call escalation policy reduces chaos: every alert reaches the right person fast, with clear next steps. Most teams do not need more alerts, they need better <strong>rules</strong>.</p>

<h3>1) Define severity levels</h3>
<ul>
  <li><strong>P0</strong>: critical outage (login, checkout, core API down).</li>
  <li><strong>P1</strong>: partial degradation (elevated errors, major latency).</li>
  <li><strong>P2</strong>: risk signals (SSL expiry soon, slowly increasing error rate).</li>
</ul>

<h3>2) Set response targets and escalation timers</h3>
<ul>
  <li>P0: acknowledge in 5–10 minutes, escalate after 10–15 minutes.</li>
  <li>P1: acknowledge in 15–30 minutes, escalate after 30–60 minutes.</li>
  <li>P2: typically handled in business hours.</li>
</ul>

<h3>3) Assign roles: primary, secondary, and owner</h3>
<ul>
  <li><strong>Primary</strong>: takes ownership and drives mitigation.</li>
  <li><strong>Secondary</strong>: backs up primary or provides domain expertise.</li>
  <li><strong>Owner/manager</strong>: handles business decisions, customer communication, and priorities.</li>
</ul>

<h3>4) Standardize where alerts go</h3>
<ul>
  <li>P0: immediate channel (phone/push) plus Slack/Teams.</li>
  <li>P1/P2: Slack/Teams plus email depending on your team.</li>
  <li>Use <a href=\"/en/solutions/alert-routing-slack-teams-webhooks\">alert routing</a> to avoid blasting everyone.</li>
</ul>

<h3>5) Add a minimal runbook</h3>
<ul>
  <li>Links to dashboards, status page, rollback steps, feature flags, and vendor contacts.</li>
  <li>A “first 5 minutes” checklist.</li>
  <li>Postmortem process and corrective actions.</li>
</ul>

<p>If you are drowning in noise, fix signal quality first: <a href=\"/en/solutions/reduce-false-alerts\">reducing false alerts</a>. To tie it all into a process, see the <a href=\"/en/solutions/incident-response-playbook\">incident response playbook</a>.</p>',
  'meta_title' => 'How do I build an on-call escalation policy? | TruMonit',
  'meta_description' => 'A practical on-call escalation policy: P0/P1/P2 severities, response targets, primary/secondary roles, escalation timers, alert routing, and a minimal runbook to cut MTTR.',
);
