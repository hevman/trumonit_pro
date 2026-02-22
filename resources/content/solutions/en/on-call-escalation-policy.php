<?php

return array (
  'order' => 18,
  'title' => 'An on-call escalation policy for a small team',
  'meta_title' => 'On-call escalation policy | TruMonit',
  'meta_description' => 'Build on-call without chaos: P0/P1/P2, primary/secondary ownership, escalation timers, and alert routing. A minimal runbook that works.',
  'summary' => 'Escalation cannot be improvised. Define severities, response targets, ownership, and channels so incidents do not get stuck in limbo.',
  'body' => '<p>The most common MTTR killer in small teams is missing rules: an alert lands in Slack, everyone sees it, and nobody takes ownership. An escalation policy ensures <strong>every incident has an owner</strong> and does not stall.</p>

<h3>A minimal model that works</h3>
<ul>
  <li><strong>P0</strong>: business is down (login, payments, core API) – acknowledge in 5–10 minutes.</li>
  <li><strong>P1</strong>: major degradation – acknowledge in 15–30 minutes.</li>
  <li><strong>P2</strong>: risk and warnings – business hours.</li>
</ul>

<h3>Roles</h3>
<ul>
  <li><strong>Primary</strong>: owns the incident and drives mitigation.</li>
  <li><strong>Secondary</strong>: joins after a timer or when expertise is needed.</li>
  <li><strong>Owner</strong>: business decisions and customer communication.</li>
</ul>

<h3>Escalation timers</h3>
<ul>
  <li>P0: escalate after 10–15 minutes without response.</li>
  <li>P1: escalate after 30–60 minutes.</li>
  <li>P2: reminders and tickets are often enough.</li>
</ul>

<p>Related: <a href="/en/solutions/alert-routing-slack-teams-webhooks">alert routing</a>, <a href="/en/solutions/incident-response-playbook">incident response playbook</a>, and FAQ: <a href="/en/faq/how-to-build-on-call-escalation-policy">building an escalation policy</a>.</p>',
  'problem' => 'Without clear escalation rules alerts reach many people but have no owner. That delays response and increases downtime cost.',
  'approach' => array (
    0 => 'Define P0/P1/P2 and response targets.',
    1 => 'Assign primary/secondary and add escalation timers.',
    2 => 'Standardize channels per severity and route alerts accordingly.',
    3 => 'Create a minimal runbook with “first 5 minutes” steps.',
  ),
  'proof' => array (
    0 => 'Shorter time to acknowledge and fewer ownerless incidents.',
    1 => 'Less noise thanks to severities and routing.',
    2 => 'Better customer communication during critical incidents.',
  ),
  'checklist' => array (
    0 => 'Severities and escalation timers are defined.',
    1 => 'Primary and secondary responders are assigned.',
    2 => 'Alert routing matches severities.',
    3 => 'A runbook exists for the first minutes.',
  ),
  'pitfalls' => array (
    0 => 'Everything is “high priority” and alerts get ignored.',
    1 => 'No timers: you wait until someone notices.',
    2 => 'No owner and no communication process.',
  ),
  'related_faq' => array (
    0 => 'how-to-build-on-call-escalation-policy',
    1 => 'best-alert-channels-for-uptime-monitoring',
    2 => 'what-is-mttr-and-how-to-improve-it',
  ),
);