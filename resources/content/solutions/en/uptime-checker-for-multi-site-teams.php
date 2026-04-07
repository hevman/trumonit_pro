<?php

return array (
  'order' => 34,
  'title' => 'Uptime Checker for Multi-Site Teams',
  'meta_title' => 'Uptime Checker for Multi-Site Teams | TruMonit',
  'meta_description' => 'Uptime checker for multi-site teams: prioritize incidents by project, route alerts to the right owners, and reduce cross-portfolio noise.',
  'summary' => 'A practical comparison for teams managing many sites with different priorities and owners.',
  'body' => '<p>Multi-site teams face a different challenge than single-product teams: priorities compete across projects. A basic uptime checker is not enough without clear ownership and client-level routing.</p><p>When evaluating tools, test whether monitors can be grouped by client/project and escalated through separate channels. This is what reduces operational chaos and missed critical incidents.</p><p>TruMonit supports this model by combining detection, escalation, and incident history across multiple services in one workflow.</p>',
  'problem' => 'Without project-level prioritization, teams react late to the incidents that matter most.',
  'approach' => 
  array (
    0 => 'Group monitors by project or client, not just by check type.',
    1 => 'Assign escalation paths per project based on business priority.',
    2 => 'Review incident data monthly to rebalance thresholds across the portfolio.',
  ),
  'proof' => 
  array (
    0 => 'Critical projects are no longer buried under low-priority alerts.',
    1 => 'Teams resolve incidents faster because ownership is explicit.',
    2 => 'Client communication improves with project-specific incident context.',
  ),
  'checklist' => 
  array (
    0 => 'Each monitor is assigned to a project/client group.',
    1 => 'Priority and escalation channel are defined per group.',
    2 => 'Noise filters are tuned separately for low and high priority sites.',
    3 => 'Owners receive regular reports for recurring incidents.',
  ),
  'pitfalls' => 
  array (
    0 => 'Applying one threshold profile to every site.',
    1 => 'Routing all alerts into one shared channel.',
    2 => 'Ignoring repeated low-grade incidents on revenue-critical sites.',
  ),
  'related_faq' => 
  array (
    0 => 'what-is-uptime-monitoring',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'what-is-a-status-page',
    3 => 'how-do-i-monitor-api-endpoints',
  ),
);
