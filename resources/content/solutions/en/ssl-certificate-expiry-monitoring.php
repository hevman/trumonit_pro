<?php

return array (
  'order' => 3,
  'title' => 'SSL certificate expiry monitoring',
  'meta_title' => 'SSL Certificate Expiry Monitoring | TruMonit',
  'meta_description' => 'Prevent avoidable downtime: monitor TLS/SSL expiry with 30/14/7 day alerts and ownership-based escalation.',
  'summary' => 'Catch SSL expiry before it breaks logins, APIs, and trust. Simple, high-ROI monitoring.',
  'body' => '<p>SSL expiry is one of the highest ROI monitors you can set up: it prevents downtime that is 100% avoidable.</p>

<h3>Best practice alert windows</h3>
<ul>
  <li>30 days: early warning</li>
  <li>14 days: action required</li>
  <li>7 days: escalation</li>
</ul>

<h3>What to include</h3>
<ul>
  <li>Certificate expiration date</li>
  <li>Hostname coverage (SANs)</li>
  <li>Ownership: who is responsible for renewal</li>
</ul>',
  'problem' => 'Expired certificates cause browser warnings, failed API calls, and broken integrations. Teams often discover it only after customers report issues.',
  'approach' => 
  array (
    0 => 'Add SSL expiry checks for customer-facing domains and key subdomains.',
    1 => 'Alert 30/14/7 days before expiration.',
    2 => 'Escalate to the owner if the 14-day window is missed.',
  ),
  'proof' => 
  array (
    0 => 'No surprise certificate expirations.',
    1 => 'Clear escalation path and audit trail for renewals.',
  ),
  'checklist' => 
  array (
    0 => 'All public domains covered (apex + important subdomains).',
    1 => '30/14/7 day alerts configured.',
    2 => 'Owner and escalation path assigned.',
  ),
  'pitfalls' => 
  array (
    0 => 'Monitoring only the apex domain and missing subdomains.',
    1 => 'No escalation, so alerts get ignored.',
  ),
  'related_faq' => 
  array (
    0 => 'how-do-i-monitor-ssl-certificate-expiry',
    1 => 'what-causes-website-downtime',
  ),
);

