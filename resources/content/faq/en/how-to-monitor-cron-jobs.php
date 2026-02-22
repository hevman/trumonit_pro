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
  ),
  'question' => 'How do I monitor cron jobs and scheduled tasks?',
  'answer' => '<p>Use heartbeat monitoring: each successful run sends a ping. If no ping arrives before a deadline, trigger an incident.</p><p>Track both missed runs and excessive run duration to catch queue stalls and silent failures.</p>',
  'meta_title' => 'How do I monitor cron jobs and scheduled tasks? | TruMonit',
  'meta_description' => 'Use heartbeat monitoring: each successful run sends a ping. If no ping arrives before a deadline, trigger an incident.Track both missed runs and excessive run d...',
);
