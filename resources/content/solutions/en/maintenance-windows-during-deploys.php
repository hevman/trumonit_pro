<?php

return array(
    'order' => 10,
    'title' => 'Maintenance windows during deploys (no hidden outages)',
    'meta_title' => 'Maintenance windows during deploys | TruMonit',
    'meta_description' => 'Use maintenance windows to silence alerts during planned changes, without masking real incidents. Keep deploy verification and post-deploy checks reliable.',
    'summary' => 'Silence alerts during planned changes, but keep visibility and post-deploy verification so you do not hide real incidents.',
    'body' => '<p><strong>Maintenance windows</strong> are a safety tool: they prevent alert spam during controlled changes, but they should not become a way to hide broken deploys.</p>

<h3>When to use a maintenance window</h3>
<ul>
  <li>Database migrations and schema changes.</li>
  <li>Infrastructure changes (DNS, load balancers, CDN rules).</li>
  <li>Large releases that temporarily increase error rates.</li>
</ul>

<h3>Best practices</h3>
<ul>
  <li>Keep the window short and explicit (start/end time).</li>
  <li>Assign an owner (who ends the window if the deploy finishes early).</li>
  <li>Run a post-deploy verification check (critical pages + key APIs).</li>
  <li>Combine with <a href="/en/solutions/synthetic-monitoring-login-flow">journey checks</a> for login/checkout.</li>
</ul>

<p>See also: <a href="/en/solutions/reduce-false-alerts">Reduce false alerts</a>.</p>',
    'problem' => 'Deploys can create noise and false alerts. Without maintenance windows, teams ignore alerts. With poorly used windows, teams can hide real incidents.',
    'approach' => array(
        'Define when maintenance windows are allowed and who can create them.',
        'Keep windows short and owned; end early when possible.',
        'Always run a post-deploy verification checklist.',
    ),
    'proof' => array(
        'Less alert noise during planned work.',
        'Better signal quality for real incidents.',
        'Faster post-deploy confidence (verification is part of the process).',
    ),
    'checklist' => array(
        'Window has a clear owner and end time.',
        'Critical monitors are verified right after deploy.',
        'Incident workflow defines what to do if the deploy fails.',
    ),
    'pitfalls' => array(
        'Leaving windows enabled for too long.',
        'Using windows as “mute forever”.',
        'No post-deploy verification (issues discovered by users).',
    ),
    'related_faq' => array(
        'what-is-maintenance-window-in-monitoring',
        'what-causes-website-downtime',
        'how-to-use-status-page-during-incident',
    ),
);

