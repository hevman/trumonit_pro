<?php

return array (
  'order' => 4,
  'title' => 'Status page for SaaS',
  'meta_title' => 'Status Page for SaaS | TruMonit',
  'meta_description' => 'Build trust during incidents: publish a status page with components, a fixed update cadence, and incident history. Reduce support load during outages.',
  'summary' => 'A status page turns incidents into structured communication: fewer tickets, clearer updates, and higher customer trust.',
  'body' => '<p>A status page is not a marketing page. It is an <strong>incident communication tool</strong>: what is affected, who is impacted, what you are doing, and when you will update next.</p>

<h3>What to publish</h3>
<ul>
  <li>Component status aligned with your architecture (API, dashboard, webhooks, login, payments).</li>
  <li>Incident updates with timestamps: what you know, what you tried, what is next.</li>
  <li>Incident history and planned maintenance windows.</li>
</ul>

<h3>Update cadence (to avoid chaos)</h3>
<ul>
  <li>Start: acknowledge within 2–5 minutes ("investigating").</li>
  <li>During: update every 15–30 minutes (even if there is no new info).</li>
  <li>End: summarize the root cause and next prevention step.</li>
</ul>

<h3>How it reduces support load</h3>
<ul>
  <li>Fewer "is it just me?" tickets and less duplicate work.</li>
  <li>One source of truth for support/sales/CS.</li>
  <li>More trust through transparency (instead of silence).</li>
</ul>

<h3>Best practices</h3>
<ul>
  <li>Link the status page from your app and your incident playbook.</li>
  <li>Do not promise ETAs without evidence; promise the next update time instead.</li>
  <li>Keep components simple: 5–12 is a practical range.</li>
</ul>

<p>If you want fewer tickets during outages and stronger credibility, a status page is one of the fastest improvements you can ship.</p>',
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
    2 => 'Less time lost to repetitive messaging.',
  ),
  'checklist' => 
  array (
    0 => 'Components defined and mapped to monitors.',
    1 => 'Update cadence defined (every 15-30 minutes during active incident).',
    2 => 'History enabled for credibility.',
    3 => 'Status page link is visible to users.',
  ),
  'pitfalls' => 
  array (
    0 => 'Publishing too late (after users notice).',
    1 => 'No next-update time (users keep refreshing/support keeps getting pings).',
    2 => 'Too many components and unclear structure.',
  ),
  'related_faq' => 
  array (
    0 => 'what-is-a-status-page',
    1 => 'how-to-use-status-page-during-incident',
    2 => 'what-should-incident-postmortem-include',
  ),
);
