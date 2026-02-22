<?php

return array (
  'order' => 15,
  'related' => 
  array (
    0 => 'how-do-i-monitor-ssl-certificate-expiry',
    1 => 'what-causes-website-downtime',
    2 => 'what-is-a-status-page',
  ),
  'related_solutions' => 
  array (
    0 => 'ssl-certificate-expiry-monitoring',
    1 => 'status-page-for-saas',
    2 => 'incident-response-playbook',
  ),
  'question' => 'How do I monitor domain expiration?',
  'answer' => '<p>Domain expiration is one of the most expensive yet easiest-to-prevent outages. Treat it like a critical payment and add multiple layers of safeguards.</p>

<h3>Minimum setup that works</h3>
<ul>
  <li><strong>Autorenew</strong> at the registrar + a valid payment method.</li>
  <li><strong>Multiple reminders</strong>: 90/60/30/14/7 days (not a single email).</li>
  <li><strong>Two owners</strong>: engineering + business (avoid single point of failure).</li>
  <li><strong>Domain inventory</strong>: list of domains, registrar account access, DNS ownership.</li>
</ul>

<h3>Extra hardening</h3>
<ul>
  <li>Enable 2FA on the registrar account and use domain lock.</li>
  <li>Monitor critical hosts (web/API) independently; “paid domain” does not mean “healthy service”.</li>
  <li>During incidents, communicate clearly via a <a href=\"/en/solutions/status-page-for-saas\">status page</a>.</li>
</ul>

<p>Related: <a href=\"/en/solutions/ssl-certificate-expiry-monitoring\">SSL expiry monitoring</a> and <a href=\"/en/solutions/incident-response-playbook\">incident response playbook</a>.</p>',
  'meta_title' => 'How do I monitor domain expiration? | TruMonit',
  'meta_description' => 'Domain expiration monitoring checklist: 90/60/30/14/7 reminders, autorenew, 2FA, two owners, and incident communication via status page.',
);
