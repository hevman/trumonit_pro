<?php

return array (
  'order' => 26,
  'related' => 
  array (
    0 => 'what-is-an-incident-response-playbook',
    1 => 'what-is-mttr-and-how-to-improve-it',
    2 => 'how-to-use-status-page-during-incident',
  ),
  'related_solutions' => 
  array (
    0 => 'incident-response-playbook',
    1 => 'status-page-for-saas',
    2 => 'alert-routing-slack-teams-webhooks',
    3 => 'incident-postmortem-template',
  ),
  'question' => 'What should an incident postmortem include?',
  'answer' => '<p>An <strong>incident postmortem</strong> is a learning document. The goal is to reduce repeat incidents by turning what happened into concrete fixes. It is not about blame, it is about systems and process.</p>

<h3>1) Facts: timeline and impact</h3>
<ul>
  <li><strong>Timeline</strong>: detection, escalation, mitigation, resolution, and customer communication.</li>
  <li><strong>Impact</strong>: who was affected, how long, which features, SLA/SLO impact, revenue impact (if known).</li>
  <li><strong>Detection</strong>: how you found out (monitoring vs customer report) and the time to detect.</li>
</ul>

<h3>2) Root cause and contributing factors</h3>
<ul>
  <li><strong>Root cause</strong>: a concise technical statement (e.g., bad migration, third party API failure, DB saturation).</li>
  <li><strong>Contributing factors</strong>: what amplified the incident (missing rate limits, noisy alerts, lack of maintenance windows).</li>
  <li><strong>What worked</strong>: rollback path, feature flags, runbooks, on-call coordination.</li>
</ul>

<h3>3) Corrective actions (most important)</h3>
<ul>
  <li>Each action has an <strong>owner</strong>, a <strong>due date</strong>, and a clear “done” definition.</li>
  <li>Split actions into quick wins (days) and systemic fixes (weeks).</li>
  <li>Add monitoring actions: new checks, better thresholds, better routing, fewer false alerts.</li>
</ul>

<h3>4) Customer communication</h3>
<p>Use a consistent template: what happened, impact, what you did, and what you are changing. A <a href=\"/en/solutions/status-page-for-saas\">status page</a> helps a lot.</p>

<p>Related: <a href=\"/en/solutions/incident-response-playbook\">incident response playbook</a>, <a href=\"/en/solutions/alert-routing-slack-teams-webhooks\">alert routing (Slack/Teams/webhooks)</a>, and FAQ: <a href=\"/en/faq/what-is-mttr-and-how-to-improve-it\">MTTR</a>.</p>',
  'meta_title' => 'What should an incident postmortem include? | TruMonit',
  'meta_description' => 'A practical postmortem template: timeline, impact, root cause, contributing factors, and corrective actions with owners and due dates. Plus common mistakes to avoid.',
);
