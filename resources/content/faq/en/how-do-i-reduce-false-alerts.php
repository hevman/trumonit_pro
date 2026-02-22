<?php

return array (
  'order' => 6,
  'related' => 
  array (
    0 => 'how-often-should-i-check-my-website',
    1 => 'synthetic-checks-vs-http-checks',
    2 => 'what-is-an-incident-response-playbook',
  ),
  'related_solutions' => 
  array (
    0 => 'reduce-false-alerts',
  ),
  'question' => 'How do I reduce false alerts?',
  'answer' => '<p>False alerts usually come from overly aggressive thresholds and missing basics like retries and cooldown. The goal is "high signal, low noise" so on-call trusts the pager.</p>

<h3>Settings that matter most</h3>
<ul>
  <li>Alert after <strong>2–3 consecutive failures</strong>, not after one.</li>
  <li><strong>Retries</strong> to separate transient glitches from real outages.</li>
  <li><strong>Cooldown</strong> + deduplication: one incident should not page repeatedly.</li>
  <li><strong>Maintenance windows</strong> for planned work.</li>
  <li><strong>Multi-region</strong> for critical endpoints (DNS/routing issues are regional).</li>
</ul>

<h3>Common noise sources</h3>
<ul>
  <li>Timeouts set too tight for normal variance.</li>
  <li>Brittle content assertions (dynamic fields, timestamps).</li>
  <li>No separation: network vs app vs 3rd-party failures.</li>
</ul>

<p>If alerts are noisy, people mute them. That is how incidents get missed.</p>',
  'meta_title' => 'How do I reduce false alerts? | TruMonit',
  'meta_description' => 'Reduce false alerts with consecutive failures + retries, cooldown/deduplication, maintenance windows, and multi-region checks. Practical defaults for high signal.',
);
