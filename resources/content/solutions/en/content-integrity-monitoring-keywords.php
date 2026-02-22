<?php

return array(
    'order' => 11,
    'title' => 'Content integrity monitoring (keywords and regex)',
    'meta_title' => 'Content integrity monitoring: keywords and regex | TruMonit',
    'meta_description' => 'Detect defacement, error messages, missing UI markers, and broken flows using allow/deny keyword and regex rules. Catch issues users see, not just HTTP 200.',
    'summary' => 'Detect when a page is “up” (HTTP 200) but wrong: missing markers, defacement, error banners, or unexpected content changes.',
    'body' => '<p><strong>HTTP 200 does not mean “working”.</strong> Pages can return 200 while showing an error, a maintenance banner, or even defaced content.</p>

<h3>What to check</h3>
<ul>
  <li><strong>Allow markers</strong>: a stable token that must exist (e.g., <code>data-testid=\"dashboard\"</code>).</li>
  <li><strong>Deny keywords</strong>: strings that must never appear (e.g., <code>hacked by</code>, <code>SQLSTATE</code>, <code>Fatal error</code>).</li>
  <li><strong>Regex</strong>: detect patterns (stack traces, error codes, maintenance modes).</li>
</ul>

<h3>How to keep it stable</h3>
<ul>
  <li>Prefer stable markers (<code>data-testid</code>) over CSS classes.</li>
  <li>Validate 1-2 critical markers, not the whole page.</li>
  <li>Use maintenance windows during planned changes.</li>
</ul>

<p>Related: <a href="/en/solutions/synthetic-monitoring-login-flow">User journey monitoring (Playwright)</a> and <a href="/en/solutions/reduce-false-alerts">Reduce false alerts</a>.</p>',
    'problem' => 'Pure uptime checks miss “silent failures” where the server responds but users cannot complete the task.',
    'approach' => array(
        'Pick a stable allow marker for critical pages.',
        'Add deny keywords/regex for common failure modes.',
        'Tune thresholds and use maintenance windows around deployments.',
    ),
    'proof' => array(
        'Catches UX-visible failures earlier than user reports.',
        'Reduces time to diagnose by pointing to the “wrong content”.',
        'Works well as a cheap guardrail for critical pages.',
    ),
    'checklist' => array(
        'Allow marker is stable and unique.',
        'Deny keywords cover the most common error banners/stack traces.',
        'Maintenance windows are used during planned releases.',
    ),
    'pitfalls' => array(
        'Using brittle selectors/keywords tied to layout changes.',
        'Validating too many elements (flaky checks).',
        'No maintenance window (false alerts during deployments).',
    ),
    'related_faq' => array(
        'how-to-monitor-keyword-on-page',
        'network-error-vs-application-error-monitoring',
        'synthetic-checks-vs-http-checks',
    ),
);

