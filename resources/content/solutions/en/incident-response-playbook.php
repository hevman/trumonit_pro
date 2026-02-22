<?php

return array (
  'order' => 6,
  'title' => 'Incident response playbook',
  'meta_title' => 'Incident Response Playbook for SaaS Teams | TruMonit',
  'meta_description' => 'Build an incident response playbook: severity, triage, communication, mitigation, and postmortems. Reduce MTTR and keep customer updates consistent.',
  'summary' => 'A good incident playbook reduces MTTR, clarifies ownership, and turns outages into structured communication.',
  'body' => '<p>An incident response playbook is a fast decision system. It does not replace engineering skill, but it <strong>removes chaos</strong> in the first minutes: who leads, what to check, and how to communicate impact.</p>

<h3>The minimum playbook (that actually works)</h3>
<ul>
  <li><strong>Severity</strong>: levels (Sev1/Sev2/Sev3) and response targets.</li>
  <li><strong>Incident owner</strong>: one person leads, others execute tasks.</li>
  <li><strong>Triage</strong>: confirm impact, isolate scope, start mitigation.</li>
  <li><strong>Communication</strong>: internal channel + status page cadence.</li>
  <li><strong>Postmortems</strong>: template, follow-up actions, closure.</li>
</ul>

<h3>Incident lifecycle (4 stages)</h3>
<ol>
  <li><strong>Detect</strong>: alert → validate quickly (retries, multi-region, cross-check monitors).</li>
  <li><strong>Stabilize</strong>: mitigation first (rollback, feature flag, traffic shaping).</li>
  <li><strong>Recover</strong>: restore service and verify metrics return to baseline.</li>
  <li><strong>Learn</strong>: postmortem + prevention work (tests, limits, monitoring, runbooks).</li>
</ol>

<h3>What to measure (to improve)</h3>
<ul>
  <li><strong>MTTA</strong> and <strong>MTTR</strong>.</li>
  <li>Top causes by category (deploy, DB, DNS, 3rd-party).</li>
  <li>Error budget burn rate (if you use SLOs).</li>
</ul>

<p>Biggest leverage comes from combining: monitoring + status page + clear ownership. Without an owner, MTTR does not improve.</p>',
  'problem' => 'Without a playbook, teams improvise under pressure. Response becomes slower and inconsistent, and customer communication breaks down.',
  'approach' => 
  array (
    0 => 'Define severity levels, ownership, and an incident owner role.',
    1 => 'Route incidents to on-call with escalation policy.',
    2 => 'Use a status page with fixed update cadence.',
    3 => 'Run postmortems and track follow-ups.',
  ),
  'proof' => 
  array (
    0 => 'Lower MTTR via repeatable triage and escalation.',
    1 => 'Better customer trust via consistent updates.',
    2 => 'Fewer repeat incidents thanks to follow-up closure.',
  ),
  'checklist' => 
  array (
    0 => 'Severity levels defined.',
    1 => 'Escalation policy in place.',
    2 => 'Status page update process defined.',
    3 => 'Postmortem template and follow-ups tracked.',
    4 => 'Incident owner assigned for Sev1/Sev2.',
  ),
  'pitfalls' => 
  array (
    0 => 'No single incident owner (coordination fails).',
    1 => 'Skipping postmortems (problems repeat).',
    2 => 'No update cadence (support gets pinged constantly).',
  ),
  'related_faq' => 
  array (
    0 => 'what-is-an-incident-response-playbook',
    1 => 'what-is-mttr-and-how-to-improve-it',
    2 => 'what-should-incident-postmortem-include',
  ),
);
