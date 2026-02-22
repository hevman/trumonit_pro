<?php

return array (
  'order' => 8,
  'title' => 'How to choose a monitoring plan',
  'meta_title' => 'How to Choose a Website Monitoring Plan | TruMonit',
  'meta_description' => 'Choose a monitoring plan based on monitor count, check frequency, on-call workflows, and status pages. Start lean and upgrade when governance needs grow.',
  'summary' => 'Pick a plan based on monitor volume, frequency, alerting workflows, and status page needs.',
  'body' => '<p>Pricing should map to operational reality: number of monitors, check frequency, and how much coordination your team needs during incidents.</p>

<h3>Start lean</h3>
<ul>
  <li>Monitor only critical paths.</li>
  <li>Use 5-minute checks for low-risk endpoints.</li>
  <li>Add more monitors as you learn where incidents happen.</li>
</ul>

<h3>Upgrade when</h3>
<ul>
  <li>You need more monitors or higher frequency.</li>
  <li>You need escalation policies and team workflows.</li>
  <li>You need public status pages for clients.</li>
</ul>',
  'problem' => 'Teams either overbuy early or under-monitor critical paths. A good plan selection matches risk, volume, and workflow maturity.',
  'approach' => 
  array (
    0 => 'Count critical endpoints and user journeys.',
    1 => 'Choose frequency based on impact (1 min for critical, 5 min for secondary).',
    2 => 'Add escalation policies when multiple people share ownership.',
  ),
  'proof' => 
  array (
    0 => 'Predictable costs aligned with monitor volume.',
    1 => 'Clear upgrade triggers based on operations maturity.',
  ),
  'checklist' => 
  array (
    0 => 'Critical monitors counted and prioritized.',
    1 => 'Frequency chosen per risk level.',
    2 => 'Alerting workflows match team size.',
  ),
  'pitfalls' => 
  array (
    0 => 'Monitoring everything (noise and cost).',
    1 => 'Monitoring too little (blind spots).',
  ),
  'related_faq' => 
  array (
    0 => 'how-to-choose-a-website-monitoring-plan',
    1 => 'how-many-monitors-do-i-need',
    2 => 'best-alert-channels-for-uptime-monitoring',
  ),
);

