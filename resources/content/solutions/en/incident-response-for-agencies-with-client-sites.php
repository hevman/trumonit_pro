<?php

return array (
  'order' => 28,
  'title' => 'How agencies can standardize incident response for client sites',
  'meta_title' => 'How agencies can standardize incident response for client sites | TruMonit',
  'meta_description' => 'A practical incident response model for agencies: alert routing, ownership, client updates, status pages, and postmortems across many managed websites.',
  'summary' => 'A simple incident workflow for agencies managing many client websites: alert, owner, client update, resolution, and postmortem.',
  'body' => '<p>Agencies do not fail because they lack alerts. They fail because every incident becomes improvisation: who checks it, who informs the client, what severity it is, and where the status lives. Standardize the workflow.</p>

<h3>The minimum agency incident model</h3>
<ol>
  <li>Detection: one alert reaches the right owner.</li>
  <li>Triage: classify severity and affected client/project.</li>
  <li>Communication: publish a short internal or client-facing status update.</li>
  <li>Resolution: document what changed and when service recovered.</li>
  <li>Review: note what monitoring or process should improve next time.</li>
</ol>

<h3>What helps the most</h3>
<ul>
  <li>Clear ownership by project or team.</li>
  <li>Status page or incident timeline instead of repeated manual messages.</li>
  <li>Templates for first update, next update, and resolution note.</li>
  <li>Separate rules for critical client flows vs low-risk brochure pages.</li>
</ul>

<p>Related: <a href="/en/solutions/status-page-for-agencies">status page for agencies</a>, <a href="/en/solutions/incident-response-playbook">incident response playbook</a>, and FAQ: <a href="/en/faq/what-is-an-incident-response-playbook">what is an incident response playbook</a>.</p>',
  'problem' => 'Without a standard workflow, agencies lose margin and trust during incidents because every outage triggers manual checking, repeated client messages, and unclear ownership.',
  'approach' => array (
    0 => 'Define owner, severity, and update format for every managed project.',
    1 => 'Route alerts by client/project instead of sending everything to everyone.',
    2 => 'Use a status page or shared incident timeline for repeatable communication.',
    3 => 'Review every incident and update checks or runbooks.',
  ),
  'proof' => array (
    0 => 'Less chaos during client-facing incidents.',
    1 => 'Faster ownership and escalation.',
    2 => 'Better trust with repeatable communication.',
  ),
  'checklist' => array (
    0 => 'Owner and severity model is documented.',
    1 => 'Client update templates exist.',
    2 => 'Critical sites have stronger checks than low-risk pages.',
  ),
  'pitfalls' => array (
    0 => 'One alert channel for every client and every site.',
    1 => 'No single status source during incidents.',
    2 => 'No post-incident review.',
  ),
  'related_faq' => array (
    0 => 'what-is-an-incident-response-playbook',
    1 => 'how-to-build-on-call-escalation-policy',
    2 => 'how-to-use-status-page-during-incident',
  ),
);
