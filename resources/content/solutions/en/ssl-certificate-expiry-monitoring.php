<?php

return array (
  'order' => 3,
  'title' => 'SSL certificate expiry monitoring',
  'meta_title' => 'SSL Certificate Expiry Monitoring | TruMonit',
  'meta_description' => 'Prevent avoidable downtime: monitor TLS/SSL expiry with 30/14/7 day alerts, cover critical subdomains (SAN), and use ownership-based escalation.',
  'summary' => 'SSL expiry breaks logins, APIs, and integrations. This is a fast, cheap monitor with extremely high ROI because the failure is fully predictable.',
  'body' => '<p>SSL/TLS certificate expiry is a classic incident: everything works until suddenly browsers show warnings and APIs start failing. It is one of the <strong>highest ROI</strong> monitors because the failure is fully predictable.</p>

<h3>Recommended alert windows</h3>
<ul>
  <li><strong>30 days</strong>: early warning (time to schedule renewal work).</li>
  <li><strong>14 days</strong>: action required (start escalation to the owner).</li>
  <li><strong>7 days</strong>: critical (higher priority, reminders).</li>
</ul>

<h3>What to include in SSL monitoring</h3>
<ul>
  <li>Expiration date and days remaining.</li>
  <li>Hostname coverage (SAN): apex + key subdomains (api, app, status, cdn).</li>
  <li>Wildcard usage: confirm it matches all real hosts.</li>
  <li>Ownership: who renews, and where escalations go.</li>
</ul>

<h3>Common failure sources</h3>
<ul>
  <li>Let\'s Encrypt auto-renewal failed due to ACME/DNS or infra changes.</li>
  <li>Forgotten subdomains (new services, legacy hostnames).</li>
  <li>Wrong certificate deployed on a load balancer during rollout.</li>
</ul>

<p>If you want a quick reliability win without changing application code, SSL expiry monitoring is the best place to start.</p>',
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
    2 => 'Fewer after-hours incidents caused by renewals.',
  ),
  'checklist' => 
  array (
    0 => 'All public domains covered (apex + important subdomains).',
    1 => '30/14/7 day alerts configured.',
    2 => 'Owner and escalation path assigned.',
    3 => 'The host list includes API/app/status and other critical subdomains.',
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
