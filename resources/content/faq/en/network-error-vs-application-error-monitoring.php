<?php

return array (
  'order' => 21,
  'related' => 
  array (
    0 => 'multi-region-monitoring-why-important',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'how-do-i-monitor-api-endpoints',
  ),
  'related_solutions' => 
  array (
  ),
  'question' => 'Network error vs application error: how should I treat them?',
  'answer' => '<p>Separate transport failures (DNS, TLS, timeout, connect errors) from application failures (5xx, logic errors, invalid responses).</p><p>Different classes usually need different responders and different mitigation paths.</p>',
  'meta_title' => 'Network error vs application error: how should I treat them? | TruMonit',
  'meta_description' => 'Separate transport failures (DNS, TLS, timeout, connect errors) from application failures (5xx, logic errors, invalid responses).Different classes usually need...',
);
