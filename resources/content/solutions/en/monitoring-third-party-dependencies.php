<?php

return array(
    'order' => 12,
    'title' => 'Monitoring third-party dependencies (CDN, payment, APIs)',
    'meta_title' => 'Monitoring third-party dependencies | TruMonit',
    'meta_description' => 'Monitor external dependencies separately so you can diagnose faster, reduce false alerts, and communicate incidents clearly on a status page.',
    'summary' => 'Monitor critical external dependencies as separate components so you can tell “our outage” from “vendor outage” in minutes.',
    'body' => '<p>Modern apps fail through dependencies: payment gateways, identity providers, CDNs, email delivery, and upstream APIs. If you do not monitor them separately, every vendor issue looks like “your app is down”.</p>

<h3>What to monitor</h3>
<ul>
  <li>Critical upstream APIs (auth, payments, search).</li>
  <li>CDN edge availability and DNS resolution.</li>
  <li>Third-party scripts that break the UI (use <a href=\"/en/solutions/synthetic-monitoring-login-flow\">journey checks</a>).</li>
</ul>

<h3>How to use it operationally</h3>
<ul>
  <li>Use separate monitors and label them as vendor components.</li>
  <li>Route alerts differently (lower severity if it is a non-critical dependency).</li>
  <li>Communicate clearly via <a href=\"/en/solutions/status-page-for-saas\">status page</a>.</li>
</ul>

<p>Related: <a href=\"/en/solutions/reduce-false-alerts\">Reduce false alerts</a> and <a href=\"/en/solutions/incident-response-playbook\">Incident response playbook</a>.</p>',
    'problem' => 'Without dependency monitoring you waste time diagnosing the wrong system and you spam the team with alerts that are not actionable.',
    'approach' => array(
        'List the top 5 dependencies that can break customer journeys.',
        'Add separate monitors for them and route alerts by severity.',
        'Use status page components to communicate scope and impact.',
    ),
    'proof' => array(
        'Faster root-cause direction (vendor vs your app).',
        'Cleaner incident comms and less escalation noise.',
        'More accurate SLO and error budgeting.',
    ),
    'checklist' => array(
        'Top dependencies are monitored and labeled.',
        'Alert routing is configured per dependency severity.',
        'Status page components map to dependencies.',
    ),
    'pitfalls' => array(
        'Treating every vendor blip as Sev1.',
        'No labels (you cannot tell what is failing).',
        'No comms plan for vendor incidents.',
    ),
    'related_faq' => array(
        'how-to-monitor-third-party-dependencies',
        'what-causes-website-downtime',
        'what-is-a-status-page',
    ),
);

