<?php

return array(
    'order' => 9,
    'title' => 'Alert routing: Slack, Teams, webhooks (without noise)',
    'meta_title' => 'Alert routing: Slack, Teams, webhooks | TruMonit',
    'meta_description' => 'Route alerts by severity, deduplicate incidents, and reduce noise. Configure Slack/Teams/webhook channels so the right people get the right signal.',
    'summary' => 'Route alerts by severity and channel (email, Slack, Teams, Discord, webhooks) so you reduce noise without missing real incidents.',
    'body' => '<p>Most monitoring setups fail for a simple reason: <strong>too many alerts</strong>. People start ignoring notifications, and the one real incident gets missed.</p>

<h3>What “good alert routing” looks like</h3>
<ul>
  <li><strong>Severity first</strong>: decide what is page-worthy vs what can wait.</li>
  <li><strong>Channel per severity</strong>: Slack/Teams for operational work, email for summaries, webhook for automations.</li>
  <li><strong>Deduplication + cooldown</strong>: one incident should not create 50 pings.</li>
</ul>

<h3>Practical setup</h3>
<ol>
  <li>Define a severity scale (e.g., Sev1/Sev2/Sev3) and owners.</li>
  <li>Route Sev1 to fast channels (Slack/Teams), Sev2 to Slack + email, Sev3 to email only.</li>
  <li>Run an incident drill and adjust thresholds to cut false positives.</li>
</ol>

<p>See also: <a href="/en/solutions/reduce-false-alerts">Reduce false alerts</a> and <a href="/en/solutions/incident-response-playbook">Incident response playbook</a>.</p>',
    'problem' => 'Alert fatigue leads to missed incidents. Without routing and severity, monitoring becomes noise instead of signal.',
    'approach' => array(
        'Define severity and ownership (who responds to what).',
        'Configure channels (Slack/Teams/webhook) and minimum severity per channel.',
        'Calibrate thresholds and add cooldown/deduplication to reduce noise.',
    ),
    'proof' => array(
        'Fewer alerts, higher response rate.',
        'Faster diagnosis because the right team sees the incident first.',
        'Cleaner incident history and better operational hygiene.',
    ),
    'checklist' => array(
        'Severity scale is documented and known to the team.',
        'Slack/Teams/webhook endpoints are configured and tested.',
        'Cooldown/deduplication is in place to prevent spam.',
        'Runbook exists for Sev1 incidents.',
    ),
    'pitfalls' => array(
        'Sending everything to one channel.',
        'No clear owner for critical incidents.',
        'No testing (webhooks misconfigured, notifications silently fail).',
    ),
    'related_faq' => array(
        'best-alert-channels-for-uptime-monitoring',
        'how-to-build-on-call-escalation-policy',
        'how-do-i-reduce-false-alerts',
        'what-is-mttr-and-how-to-improve-it',
    ),
);

