<?php

return array (
  'order' => 17,
  'related' => 
  array (
    0 => 'what-is-an-incident-response-playbook',
    1 => 'how-to-build-on-call-escalation-policy',
    2 => 'best-alert-channels-for-uptime-monitoring',
  ),
  'related_solutions' => 
  array (
    0 => 'incident-response-playbook',
    1 => 'uptime-monitoring-for-saas',
  ),
  'question' => 'What is MTTR and how do I improve it?',
  'answer' => '<p><strong>MTTR</strong> (Mean Time To Recovery/Resolve) is the average time it takes to restore service after an incident. In practice it is a pipeline: detection → confirmation → mitigation → recovery.</p>

<h3>How to improve MTTR (highest leverage)</h3>
<ul>
  <li><strong>Faster detection</strong>: high-signal monitoring (low noise), 1-min checks for critical paths.</li>
  <li><strong>Better triage</strong>: playbooks and checklists for common failures.</li>
  <li><strong>Mitigation</strong>: fast rollback, feature flags, traffic shaping, dependency isolation.</li>
  <li><strong>Communication</strong>: status page updates reduce support pressure and uncertainty.</li>
</ul>

<h3>Concrete actions</h3>
<ul>
  <li>Assign an incident owner for Sev1/Sev2.</li>
  <li>Set up on-call escalation (primary + secondary) and clear alert channels.</li>
  <li>Run regular drills and update playbooks after incidents.</li>
  <li>Do postmortems and close follow-up actions.</li>
</ul>

<p>MTTR drops when you have a repeatable process and monitoring the team trusts.</p>',
  'meta_title' => 'What is MTTR and how do I improve it? | TruMonit',
  'meta_description' => 'MTTR is the average time to restore service. Improve it with better detection (monitoring), triage (playbooks), mitigation (rollback/flags), and status page comms.',
);
