<?php

return array (
  'order' => 14,
  'related' => 
  array (
    0 => 'how-do-i-monitor-api-endpoints',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'what-causes-website-downtime',
  ),
  'related_solutions' => 
  array (
    0 => 'api-endpoint-monitoring',
    1 => 'reduce-false-alerts',
    2 => 'alert-routing-slack-teams-webhooks',
  ),
  'question' => 'How do I monitor cron jobs and scheduled tasks?',
  'answer' => '<p>The most reliable approach is <strong>heartbeat monitoring</strong>: every successful run sends a ping, and a missing ping within an expected interval triggers an incident. This is more dependable than reading logs because it is explicit and measurable.</p>

<h3>Option 1: one ping on success (common)</h3>
<ul>
  <li>Send a request to a unique URL after the job finishes successfully.</li>
  <li>Set an interval (e.g., every 5 minutes) plus a <strong>grace period</strong> for normal delays.</li>
  <li>Alert if the ping does not arrive.</li>
</ul>

<h3>Option 2: start + finish (when duration matters)</h3>
<ul>
  <li>Send a “start” ping at the beginning and a “finish” ping at the end.</li>
  <li>Alert if “finish” never arrives (hung job) or arrives too late (slow job).</li>
  <li>This works well for exports, backups, and ETL pipelines.</li>
</ul>

<h3>Common pitfalls</h3>
<ul>
  <li>Only pinging on success: add error handling so failures still report a signal you can alert on.</li>
  <li>No grace window: tune thresholds and retries to avoid noise.</li>
  <li>One monitor for many jobs: split critical scheduled tasks into separate heartbeats.</li>
</ul>

<p>Related: <a href=\"/en/solutions/api-endpoint-monitoring\">API endpoint monitoring</a>, <a href=\"/en/solutions/reduce-false-alerts\">reducing false alerts</a>, and <a href=\"/en/solutions/alert-routing-slack-teams-webhooks\">alert routing</a>.</p>',
  'meta_title' => 'How do I monitor cron jobs and scheduled tasks? | TruMonit',
  'meta_description' => 'How to monitor cron jobs with heartbeats: interval + grace period, start/finish pings to catch hung jobs, and common pitfalls that cause silent failures and noise.',
);
