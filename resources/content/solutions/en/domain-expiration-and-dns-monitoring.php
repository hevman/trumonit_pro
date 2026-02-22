<?php

return array (
  'order' => 16,
  'title' => 'Domain expiry and DNS risk monitoring',
  'meta_title' => 'Domain and DNS monitoring | TruMonit',
  'meta_description' => 'Detect domain expiry risk, DNS record changes, misconfigurations, and resolver issues. Set reminders and a checklist before critical renewal dates.',
  'summary' => 'Domain and DNS outages are classic “it worked yesterday”. Monitor expiry dates and key records before your site disappears.',
  'body' => '<p>Domain expiry or DNS mistakes are brutal: your app can be healthy and users still cannot reach it. Many of these incidents are organizational, not code-level. Treat DNS and domain expiry as a first-class operational risk.</p>

<h3>What to monitor</h3>
<ul>
  <li><strong>Domain expiry</strong>: reminders at 30/14/7 days before renewal.</li>
  <li><strong>Key DNS records</strong>: A/AAAA, CNAME, MX, TXT depending on your stack.</li>
  <li><strong>Changes</strong>: alert when records change unexpectedly.</li>
  <li><strong>Resolver behavior</strong>: slow lookups and resolution failures.</li>
</ul>

<h3>How to reduce risk</h3>
<ul>
  <li>Enable auto-renew and set backup payment methods.</li>
  <li>Have an owner and a backup contact for registrar access.</li>
  <li>Before migrations: validate records and redirect behavior.</li>
</ul>

<p>Related: <a href="/en/solutions/redirect-chain-monitoring">redirect chain monitoring</a> and <a href="/en/solutions/uptime-monitoring-for-saas">uptime monitoring</a>. See FAQ: <a href="/en/faq/how-to-monitor-domain-expiration">domain expiration monitoring</a>.</p>',
  'problem' => 'If a domain expires or DNS breaks, your service can be effectively offline even if the application is fine. Prevention is cheap, impact is huge.',
  'approach' => array (
    0 => 'Set expiry alerts at 30/14/7 days before renewal.',
    1 => 'Monitor key DNS records and alert on unexpected changes.',
    2 => 'Enable auto-renew and keep backup contacts and payments.',
    3 => 'After migrations, verify redirects and final URLs.',
  ),
  'proof' => array (
    0 => 'Avoid “domain expired” incidents.',
    1 => 'Faster diagnosis: DNS vs application.',
    2 => 'A repeatable checklist before critical dates.',
  ),
  'checklist' => array (
    0 => 'Auto-renew is enabled and tested.',
    1 => 'Expiry alerts reach the right owners.',
    2 => 'Key records are monitored and changes are intentional.',
  ),
  'pitfalls' => array (
    0 => 'Registrar contact email is outdated or unmonitored.',
    1 => 'Live DNS changes without rollback plan.',
    2 => 'No record monitoring and surprises after migrations.',
  ),
  'related_faq' => array (
    0 => 'how-to-monitor-domain-expiration',
    1 => 'what-causes-website-downtime',
    2 => 'multi-region-monitoring-why-important',
  ),
);