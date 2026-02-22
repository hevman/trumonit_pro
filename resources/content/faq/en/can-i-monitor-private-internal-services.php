<?php

return array (
  'order' => 30,
  'related' => 
  array (
    0 => 'how-to-monitor-login-flow-synthetically',
    1 => 'how-to-monitor-third-party-dependencies',
    2 => 'how-to-choose-a-website-monitoring-plan',
  ),
  'related_solutions' => 
  array (
    0 => 'synthetic-monitoring-login-flow',
    1 => 'api-endpoint-monitoring',
    2 => 'monitoring-third-party-dependencies',
  ),
  'question' => 'Can I monitor private or internal services?',
  'answer' => '<p>Yes, but not directly from the public Internet. If your service lives behind a VPN, in a VPC, or inside a private network, you need to <strong>run checks from inside that network</strong> (a private probe/agent) or expose a safe health endpoint.</p>

<h3>Common approaches</h3>
<ul>
  <li><strong>Private agent</strong> running in your network (VM, Docker, Kubernetes) that executes checks and reports results.</li>
  <li><strong>IP allowlisting</strong> if you can restrict access by source IP (simple but less flexible).</li>
  <li><strong>Dedicated health endpoints</strong> that reveal no sensitive data.</li>
</ul>

<h3>Security tips</h3>
<ul>
  <li>Do not use admin credentials for monitoring. Use least privilege.</li>
  <li>Segment secrets and network access so monitoring cannot access everything.</li>
  <li>Log and rate limit monitoring paths where possible.</li>
</ul>

<p>If you want to validate end-to-end flows (not just HTTP), see <a href=\"/en/solutions/synthetic-monitoring-login-flow\">synthetic login flow monitoring</a>. For outages caused by vendors, use <a href=\"/en/solutions/monitoring-third-party-dependencies\">third-party dependency monitoring</a>.</p>',
  'meta_title' => 'Can I monitor private or internal services? | TruMonit',
  'meta_description' => 'Yes: use a private probe/agent inside your network, IP allowlisting, or dedicated health endpoints. Practical security rules to keep monitoring from expanding blast radius.',
);
