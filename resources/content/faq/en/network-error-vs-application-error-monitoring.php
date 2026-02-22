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
    0 => 'api-endpoint-monitoring',
    1 => 'uptime-monitoring-for-saas',
  ),
  'question' => 'Network error vs application error: how should I treat them?',
  'answer' => '<p>Separate <strong>network errors</strong> from <strong>application errors</strong> from day one. They look similar in dashboards ("service is failing"), but they need different owners, different mitigations, and different urgency in incident workflow.</p>

<p>Network-class failures include DNS resolution errors, TLS handshake failures, connect timeouts, packet loss, and edge routing issues. Application-class failures include HTTP 5xx, bad business logic, invalid payload handling, and degraded responses under load. If you merge both classes into one alert stream, triage slows down and false escalations increase.</p>

<p>A practical model is to tag each incident with a failure domain: <em>network/transport</em> or <em>application/runtime</em>. Then map escalation targets accordingly: infra/on-call network for transport failures, backend/app owners for runtime failures. This simple split often cuts mean time to acknowledge and reduces cross-team ping-pong.</p>

<p>In TruMonit, use separate checks and thresholds for each class, then correlate them in timeline view. Example: if port and DNS fail together, treat as network-first; if transport stays healthy but 5xx spikes, escalate to app team immediately. This is one of the highest-leverage improvements for cleaner incident response.</p>

<p>As a final step, run a monthly review: compare incident count by failure class, check false escalation rate, and adjust routing rules where teams still lose time. This keeps your monitoring model aligned with real production behavior instead of outdated assumptions.</p>',
  'meta_title' => 'Network error vs application error: how should I treat them? | TruMonit',
  'meta_description' => 'Separate network errors from application errors, route incidents to the right owners, and reduce triage time with a practical monitoring model.',
);
