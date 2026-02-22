<?php

return array (
  'order' => 20,
  'title' => 'Incident postmortem template (timeline, impact, actions)',
  'meta_title' => 'Incident postmortem template | TruMonit',
  'meta_description' => 'A postmortem without fluff: timeline, impact, root cause, contributing factors, and corrective actions with owners and due dates.',
  'summary' => 'A good postmortem turns an incident into concrete changes. Corrective actions with owners and dates matter most.',
  'body' => '<p>A postmortem only matters if it ends with <strong>concrete changes</strong>. If it stops at “it was bad”, the same incident will happen again.</p>

<h3>A minimal template</h3>
<ul>
  <li><strong>Summary</strong>: what happened and what impact it had.</li>
  <li><strong>Timeline</strong>: detection, escalation, mitigation, resolution, communication.</li>
  <li><strong>Root cause</strong> and contributing factors.</li>
  <li><strong>Corrective actions</strong>: owner, due date, definition of done.</li>
</ul>

<h3>Actions that reduce repeat risk</h3>
<ul>
  <li>Add monitoring checks (synthetic for critical flows).</li>
  <li>Improve alert routing and severities.</li>
  <li>Use maintenance windows during releases to reduce noise.</li>
</ul>

<p>Related: <a href="/en/solutions/incident-response-playbook">incident response playbook</a> and <a href="/en/solutions/status-page-for-saas">status pages</a>. See FAQ: <a href="/en/faq/what-should-incident-postmortem-include">what a postmortem should include</a>.</p>',
  'problem' => 'Without postmortems incidents repeat. Teams put out the fire and move on, only to face the same outage again. A template and a process change that.',
  'approach' => array (
    0 => 'Capture timeline and impact immediately after the incident.',
    1 => 'Write root cause and key contributing factors.',
    2 => 'Define corrective actions with owners and due dates.',
    3 => 'Make monitoring and alerting improvements a standard section.',
  ),
  'proof' => array (
    0 => 'Fewer repeat incidents and faster recovery.',
    1 => 'Actions are trackable (owner + due date).',
    2 => 'Better customer and internal communication.',
  ),
  'checklist' => array (
    0 => 'Timeline and impact are complete.',
    1 => 'Actions have owners and due dates.',
    2 => 'At least one action improves monitoring/alerting.',
  ),
  'pitfalls' => array (
    0 => 'Postmortem with no actions (nice doc, no change).',
    1 => 'No owner: “someone will do it”.',
    2 => 'Overly vague root causes without specifics.',
  ),
  'related_faq' => array (
    0 => 'what-should-incident-postmortem-include',
    1 => 'what-is-an-incident-response-playbook',
    2 => 'how-to-use-status-page-during-incident',
  ),
);