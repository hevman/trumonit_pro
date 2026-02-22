<?php

return array (
  'order' => 17,
  'title' => 'Monitoring private services (VPC, VPN, intranet)',
  'meta_title' => 'Private services monitoring | TruMonit',
  'meta_description' => 'How to monitor services behind VPN/VPC: private agent/probe, least-privilege access, dedicated health endpoints, and safe alert routing.',
  'summary' => 'You cannot monitor private IPs from the public Internet. Use a private agent inside your network and safe health checks.',
  'body' => '<p>If an endpoint sits behind a VPN, in a VPC, or inside a corporate network, public monitoring cannot reach it. The safest model is a <strong>private agent</strong> that runs inside your network and reports results.</p>

<h3>A reliable architecture</h3>
<ul>
  <li>The agent runs in your environment (VM, Docker, Kubernetes).</li>
  <li>It checks private services (HTTP, TCP, synthetic flows).</li>
  <li>It reports only results and minimal metadata back to the dashboard.</li>
</ul>

<h3>Security basics</h3>
<ul>
  <li>Least privilege: avoid admin access and broad network permissions.</li>
  <li>Dedicated health endpoints that expose no sensitive data.</li>
  <li>Segmentation so monitoring does not expand blast radius.</li>
</ul>

<p>Related: <a href="/en/solutions/synthetic-monitoring-login-flow">synthetic flow monitoring</a> and <a href="/en/solutions/monitoring-third-party-dependencies">dependency monitoring</a>. See FAQ: <a href="/en/faq/can-i-monitor-private-internal-services">monitoring private services</a>.</p>',
  'problem' => 'Your most important systems are often private (internal APIs, admin panels, databases). If you only monitor public URLs you can be green while core services are down inside the network.',
  'approach' => array (
    0 => 'Run a private agent inside your VPC/VPN close to the services.',
    1 => 'Start with critical endpoints and safe health checks.',
    2 => 'Apply least privilege and segment secrets/network access.',
    3 => 'Route alerts based on service criticality.',
  ),
  'proof' => array (
    0 => 'Real signal for core availability, not only the edge.',
    1 => 'Better diagnosis: inside vs outside failures.',
    2 => 'No need to expose private services to the public Internet.',
  ),
  'checklist' => array (
    0 => 'The agent runs inside your network with minimal permissions.',
    1 => 'Health endpoints do not leak sensitive data.',
    2 => 'Alert routing matches criticality.',
  ),
  'pitfalls' => array (
    0 => 'Exposing a private endpoint publicly “just for monitoring”.',
    1 => 'Over-privileged agent credentials.',
    2 => 'Trying to monitor everything instead of critical services first.',
  ),
  'related_faq' => array (
    0 => 'can-i-monitor-private-internal-services',
    1 => 'how-to-monitor-login-flow-synthetically',
    2 => 'how-to-monitor-third-party-dependencies',
  ),
);