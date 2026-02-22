<?php

return array (
  'order' => 4,
  'title' => 'Status page for SaaS',
  'meta_title' => 'Status Page for SaaS | TruMonit',
  'meta_description' => 'Build trust during incidents: publish a status page with components, incident updates, and history. Reduce support load during outages.',
  'summary' => 'A status page turns incidents into communication, reduces support pressure, and improves trust.',
  'body' => '<p>A status page is not a marketing page. It is an incident communication tool: what is down, who is impacted, and when you will update next.</p>

<h3>What to publish</h3>
<ul>
  <li>Service status by components (API, dashboard, webhooks).</li>
  <li>Incident updates with timestamps.</li>
  <li>History for transparency and postmortems.</li>
</ul>

<h3>How it reduces support load</h3>
<ul>
  <li>Less \"is it just me?\" tickets.</li>
  <li>Consistent updates for sales/support.</li>
  <li>Single source of truth during an outage.</li>
</ul>',
  'problem' => 'Without a public status page, every incident creates a support storm and inconsistent messaging. Teams lose time answering the same question.',
  'approach' => 
  array (
    0 => 'Create a status page and define components that match your architecture.',
    1 => 'Publish an acknowledgment quickly, then update at fixed intervals.',
    2 => 'Link the status page from your app and incident playbooks.',
  ),
  'proof' => 
  array (
    0 => 'Lower ticket volume during incidents.',
    1 => 'Better customer trust through transparent updates.',
  ),
  'checklist' => 
  array (
    0 => 'Components defined and mapped to monitors.',
    1 => 'Update cadence defined (every 15-30 minutes during active incident).',
    2 => 'History enabled for credibility.',
  ),
  'pitfalls' => 
  array (
    0 => 'Publishing too late (after users notice).',
    1 => 'No next-update time (users keep refreshing/support keeps getting pings).',
  ),
  'related_faq' => 
  array (
    0 => 'what-is-a-status-page',
    1 => 'how-to-use-status-page-during-incident',
    2 => 'what-should-incident-postmortem-include',
  ),
);

