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
  'answer' => '<p><strong>SSL/TLS certificate expiry monitoring</strong> is one of the simplest checks that prevents high-impact outages. When certificates expire, users see trust warnings, APIs fail, webhooks break, and incident load spikes fast.</p>

<p>Start with a clear warning ladder: 30 days (early reminder), 14 days (owner escalation), and 7 days (critical). For business-critical domains, add 3-day and 1-day reminders.</p>

<h3>Scope to monitor</h3>
<ul>
  <li>Primary domain and all production subdomains (app, api, status, cdn).</li>
  <li>Certificate SAN coverage and wildcard assumptions.</li>
  <li>External dependencies that terminate TLS on your behalf.</li>
</ul>

<h3>Operational checklist</h3>
<ul>
  <li>Assign one owner and one backup owner per certificate group.</li>
  <li>Store renewal runbook: where certs live, how they are issued, rollback plan.</li>
  <li>After renewal, verify certificate chain and expiry date from multiple regions.</li>
</ul>

<p>If your team is comparing uptime checker tools, SSL monitoring should be a default, not an optional add-on. It is low effort and usually high ROI.</p>

<p>Related: <a href="/en/solutions/ssl-certificate-expiry-monitoring">SSL certificate expiry monitoring</a> and <a href="/en/solutions/domain-expiration-and-dns-monitoring">domain and DNS monitoring</a>.</p>',
  'meta_title' => 'How do I monitor SSL/TLS certificate expiry? | TruMonit',
  'meta_description' => 'Monitor SSL certificate expiry with 30/14/7-day alerts, SAN coverage checks, and clear ownership to prevent avoidable downtime and trust warnings.',
);
