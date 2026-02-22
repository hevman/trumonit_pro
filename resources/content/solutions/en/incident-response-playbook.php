<?php

return array (
  'order' => 6,
  'title' => 'Incident response playbook',
  'meta_title' => 'Incident Response Playbook for SaaS Teams | TruMonit',
  'meta_description' => 'Build an incident response playbook: triage, communication, mitigation, and postmortems. Improve MTTR and consistency across shifts.',
  'summary' => 'A repeatable incident response process reduces MTTR and improves customer communication.',
  'body' => '<p>A playbook is a fast decision system. It prevents chaos when an alert fires.</p>

<h3>Minimum playbook</h3>
<ul>
  <li>Severity levels and response targets.</li>
  <li>Triage steps: confirm impact, isolate, mitigate.</li>
  <li>Communication: internal channel + status page cadence.</li>
  <li>Postmortem template and action tracking.</li>
</ul>

<h3>What to measure</h3>
<ul>
  <li>MTTA (acknowledge) and MTTR (resolve).</li>
  <li>Top incident causes by category.</li>
  <li>Error budget burn rate (if you use SLOs).</li>
</ul>',
  'problem' => 'Without a playbook, teams improvise under pressure. Response becomes slower and inconsistent, and customer communication breaks down.',
  'approach' => 
  array (
    0 => 'Define severity levels and ownership.',
    1 => 'Route incidents to on-call with escalation policy.',
    2 => 'Use a status page with fixed update cadence.',
    3 => 'Run postmortems and track follow-ups.',
  ),
  'proof' => 
  array (
    0 => 'Lower MTTR via repeatable triage and escalation.',
    1 => 'Better customer trust via consistent updates.',
  ),
  'checklist' => 
  array (
    0 => 'Severity levels defined.',
    1 => 'Escalation policy in place.',
    2 => 'Status page update process defined.',
    3 => 'Postmortem template and follow-ups tracked.',
  ),
  'pitfalls' => 
  array (
    0 => 'No single incident owner (coordination fails).',
    1 => 'Skipping postmortems (problems repeat).',
  ),
  'related_faq' => 
  array (
    0 => 'what-is-an-incident-response-playbook',
    1 => 'what-is-mttr-and-how-to-improve-it',
    2 => 'what-should-incident-postmortem-include',
  ),
);

