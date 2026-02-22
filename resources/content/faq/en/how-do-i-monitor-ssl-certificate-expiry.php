<?php

return array (
  'order' => 4,
  'related' => 
  array (
    0 => 'what-is-uptime-monitoring',
    1 => 'how-do-i-monitor-api-endpoints',
    2 => 'what-is-a-status-page',
  ),
  'related_solutions' => 
  array (
    0 => 'ssl-certificate-expiry-monitoring',
  ),
  'question' => 'How do I monitor SSL/TLS certificate expiry?',
  'answer' => '<p><strong>SSL/TLS expiry monitoring</strong> is one of the highest ROI checks you can add. Set early alerts and assign ownership so renewals do not get lost in noise.</p>

<h3>Recommended alert windows</h3>
<ul>
  <li><strong>30 days</strong>: early warning.</li>
  <li><strong>14 days</strong>: start escalation to the owner.</li>
  <li><strong>7 days</strong>: critical reminders.</li>
</ul>

<h3>What to include</h3>
<ul>
  <li>Subdomains and SAN coverage (api, app, status, cdn).</li>
  <li>Wildcard usage: verify it matches real hosts.</li>
  <li>Your renewal process (e.g. Let\'s Encrypt) and who owns it.</li>
</ul>

<p>Early SSL alerts prevent avoidable downtime, browser trust warnings, and failed integrations.</p>',
  'meta_title' => 'How do I monitor SSL/TLS certificate expiry? | TruMonit',
  'meta_description' => 'SSL/TLS expiry monitoring: 30/14/7 day alerts, subdomain (SAN) coverage, and owner escalation to prevent avoidable downtime.',
);
