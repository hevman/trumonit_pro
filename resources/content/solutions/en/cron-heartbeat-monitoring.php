<?php

return array (
  'order' => 13,
  'title' => 'Cron job monitoring with heartbeats (without alert noise)',
  'meta_title' => 'Cron heartbeat monitoring | TruMonit',
  'meta_description' => 'Heartbeat monitoring for scheduled tasks: detect missed runs and long runtimes, add grace periods and retries, and route alerts without spamming your team.',
  'summary' => 'Heartbeats are the most reliable way to monitor scheduled tasks: you get a success signal and alert when the signal is missing.',
  'body' => '<p>Cron jobs fail silently: a worker dies, a queue stalls, a DB lock never releases, or a job takes an hour instead of a minute. The simplest monitoring model is a <strong>heartbeat</strong>: send a ping after a successful run.</p>

<h3>How it works</h3>
<ul>
  <li>The job finishes successfully and sends a heartbeat.</li>
  <li>If no heartbeat arrives within the expected window, you open an incident.</li>
  <li>For long-running tasks, add a start/finish variant to detect hangs.</li>
</ul>

<h3>Settings that matter</h3>
<ul>
  <li><strong>Interval + grace</strong>: e.g., every 5 minutes plus 2 minutes tolerance.</li>
  <li><strong>Retries</strong>: one missing ping is often jitter, not a real outage.</li>
  <li><strong>Escalation</strong>: keep P0 only for business-critical tasks (billing, backups, sync).</li>
</ul>

<h3>What to watch beyond “missed run”</h3>
<ul>
  <li>Long runtime (hung job or resource pressure).</li>
  <li>Increasing failure rate in the processing pipeline.</li>
  <li>Schedule changes after deployments (configuration regressions).</li>
</ul>

<p>Related: <a href="/en/solutions/reduce-false-alerts">reducing false alerts</a> and <a href="/en/solutions/alert-routing-slack-teams-webhooks">alert routing</a>. See FAQ: <a href="/en/faq/how-to-monitor-cron-jobs">cron job monitoring</a>.</p>',
  'problem' => 'Scheduled tasks can stop working without obvious UI symptoms. Without heartbeats you learn about the failure only after a customer report or a missing report/export.',
  'approach' => array (
    0 => 'Add a heartbeat ping after success (one monitor per critical job).',
    1 => 'Set interval + grace + retries to avoid jitter noise.',
    2 => 'For long tasks, add start/finish or a max runtime alert.',
    3 => 'Route alerts by severity and ownership (P0 only when it is truly critical).',
    4 => 'Re-test after deployments and schedule changes.',
  ),
  'proof' => array (
    0 => 'Detect missed runs before customers notice.',
    1 => 'Reduce alert noise with grace/retry and clear severities.',
    2 => 'Make ownership explicit: who gets paged and what to do next.',
  ),
  'checklist' => array (
    0 => 'Critical tasks have separate heartbeats.',
    1 => 'Intervals and grace match the real schedule.',
    2 => 'Retries/cooldowns reduce spam.',
    3 => 'P0 is reserved for business-impacting jobs.',
  ),
  'pitfalls' => array (
    0 => 'One shared heartbeat for many jobs (hard to diagnose).',
    1 => 'No grace window and constant false alarms.',
    2 => 'No re-test after deploy and schedule drift.',
  ),
  'related_faq' => array (
    0 => 'how-to-monitor-cron-jobs',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'what-causes-website-downtime',
  ),
);