<?php

return array (
  'order' => 27,
  'title' => 'A status page for agencies and multiple client projects',
  'meta_title' => 'Status pages for agencies | TruMonit',
  'meta_description' => 'Agencies lose time answering “is it down?”. A status page with incident updates reduces support load and builds trust with clients.',
  'summary' => 'For agencies, a status page is not a nice-to-have. It is a fast way to reduce support noise and communicate incidents clearly.',
  'body' => '<p>In an agency, the problem is not only downtime. The problem is <strong>time</strong>: one client asks “is it down?”, someone checks manually, then another client asks the same thing. A status page with clear updates can save hours.</p>

<h3>What a good status page includes</h3>
<ul>
  <li>Services/projects list and current status.</li>
  <li>Incident history with timestamps.</li>
  <li>A short impact description (what was affected).</li>
  <li>A support link for client-side issues (DNS, hosting).</li>
</ul>

<h3>How to run it in an agency</h3>
<ul>
  <li>Decide what is public vs internal-only.</li>
  <li>During incidents, publish updates on a schedule.</li>
  <li>After incidents, add a short summary and actions.</li>
</ul>

<p>Related: <a href="/en/solutions/status-page-for-saas">status pages for SaaS</a> and <a href="/en/solutions/incident-postmortem-template">postmortem templates</a>.</p>',
  'problem' => 'Without a status page, agencies waste time repeating the same incident updates to many clients. In a crisis, the lack of a single source of truth increases chaos and frustration.',
  'approach' => array (
    0 => 'Group monitors into client projects and decide what is public.',
    1 => 'Use an incident update template and publish every 30–60 minutes.',
    2 => 'Route internal alerts to the team and share the status page link with clients.',
    3 => 'After incidents, write a short summary and corrective actions.',
  ),
  'proof' => array (
    0 => 'Fewer “is it down?” tickets and less manual checking.',
    1 => 'Higher client trust through transparency.',
    2 => 'A smoother incident workflow for the team.',
  ),
  'checklist' => array (
    0 => 'Status page shows services and incident history.',
    1 => 'An incident communication template exists.',
    2 => 'Alert routing and ownership are defined.',
  ),
  'pitfalls' => array (
    0 => 'A status page without updates (loses credibility).',
    1 => 'Exposing sensitive details publicly.',
    2 => 'No process for who posts and when.',
  ),
  'related_faq' => array (
    0 => 'what-is-a-status-page',
    1 => 'how-to-use-status-page-during-incident',
    2 => 'what-should-incident-postmortem-include',
  ),
);