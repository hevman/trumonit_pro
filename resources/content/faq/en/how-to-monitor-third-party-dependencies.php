<?php

return array (
  'order' => 24,
  'related' => 
  array (
    0 => 'what-causes-website-downtime',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'what-is-a-status-page',
  ),
  'related_solutions' => 
  array (
    0 => 'monitoring-third-party-dependencies',
    1 => 'status-page-for-saas',
    2 => 'reduce-false-alerts',
  ),
  'question' => 'How do I monitor third-party dependencies?',
  'answer' => '<p>Third-party dependencies can take down your customer journey even when your servers are healthy: payments, auth, CDN, DNS, upstream APIs. If you do not monitor them separately, every vendor issue looks like “your outage”.</p>

<h3>Keep it simple</h3>
<ul>
  <li>List the top 5 dependencies that break login/checkout/email delivery.</li>
  <li>Add separate monitors for each vendor (separate from your endpoints).</li>
  <li>Route alerts by severity (not every vendor blip is Sev1).</li>
  <li>Communicate scope via a <a href=\"/en/solutions/status-page-for-saas\">status page</a>.</li>
</ul>

<h3>Common mistakes</h3>
<ul>
  <li>One “everything” monitor with no components.</li>
  <li>No labels, so diagnosis restarts from scratch.</li>
  <li>No comms plan for vendor incidents.</li>
</ul>

<p>See also: <a href=\"/en/solutions/monitoring-third-party-dependencies\">full dependency monitoring scenario</a>.</p>',
  'meta_title' => 'How do I monitor third-party dependencies? | TruMonit',
  'meta_description' => 'Third-party dependency monitoring: separate monitors for CDN/DNS/payments/auth, severity-based alert routing, and clear incident communication via status page.',
);
