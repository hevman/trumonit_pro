<?php

return array (
  'order' => 25,
  'related' => 
  array (
    0 => 'what-is-a-status-page',
    1 => 'what-is-an-incident-response-playbook',
    2 => 'what-should-incident-postmortem-include',
  ),
  'related_solutions' => 
  array (
    0 => 'status-page-for-saas',
    1 => 'incident-response-playbook',
  ),
  'question' => 'How should I use a status page during an incident?',
  'answer' => '<p>A status page works when it is fast and predictable. The worst patterns are 30 minutes of silence or update spam with no substance. Customers want two things: <strong>is the problem on your side</strong> and <strong>when is the next update</strong>.</p>

<h3>Minimum communication pattern</h3>
<ol>
  <li><strong>Acknowledge</strong> (2–5 min): "Investigating", short summary, scope.</li>
  <li><strong>Updates</strong> every 15–30 min: even if there is no new info, post the next update time.</li>
  <li><strong>Resolve</strong>: what happened and what changed (short).</li>
</ol>

<h3>Always include</h3>
<ul>
  <li>Impact scope (who is affected, which components).</li>
  <li>Workaround (if any) and what customers can do now.</li>
  <li>Next update time ("next update at 12:30").</li>
</ul>

<h3>Avoid</h3>
<ul>
  <li>ETAs without evidence ("fixed in 10 minutes").</li>
  <li>Overly technical details that do not help customers.</li>
  <li>No cadence (support gets pinged constantly).</li>
</ul>

<p>A good status page reduces ticket volume and improves trust because customers can see the incident is managed.</p>',
  'meta_title' => 'How should I use a status page during an incident? | TruMonit',
  'meta_description' => 'Status page during incidents: acknowledge in 2–5 minutes, update every 15–30 minutes, include impact scope and next update time. Reduce tickets and improve trust.',
);
