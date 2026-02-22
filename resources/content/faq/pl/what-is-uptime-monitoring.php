<?php

return array (
  'order' => 1,
  'related' => 
  array (
    0 => 'how-often-should-i-check-my-website',
    1 => 'what-is-sla-vs-slo',
    2 => 'what-is-a-status-page',
  ),
  'related_solutions' => 
  array (
    0 => 'uptime-monitoring-for-saas',
  ),
  'question' => 'Czym jest monitoring uptime?',
  'answer' => '<p><strong>Monitoring uptime</strong> to regułarne sprawdzanie, czy Twoja strona lub API są dostępne i działają poprawnie. Najczęściej weryfikuje:</p>

<ul>
  <li>status HTTP (np. 2xx/3xx),</li>
  <li>czas odpowiedzi (latency),</li>
  <li>opcjonalnie prostą regułę treści (keyword/JSON), żeby uniknąć „fałszywego 200”.</li>
</ul>

<p>Celem jest szybkie wykrycie awarii, natychmiastowy alert dla zespołu i widoczna historia dostępności (timeline incydentów).</p>

<h3>Co monitoring uptime daje w praktyce</h3>
<ul>
  <li>Szybsze wykrycie awarii (mniej zgłoszeń od klientów).</li>
  <li>Lepsze MTTR dzięki jasnym alertom i eskalacji.</li>
  <li>Dowód operacyjny: kiedy było źle i jak długo (przydatne dla SLA/SLO).</li>
</ul>

<p>Najważniejsze: monitoring uptime musi mieć wysoki sygnał. Jeśli jest zbyt głośny, zespół przestaje go używać.</p>',
  'meta_title' => 'Czym jest monitoring uptime? | TruMonit',
  'meta_description' => 'Monitoring uptime sprawdza dostępność strony/API (status, latency, reguły treści) i wysyła alerty. Daje historię incydentów i wspiera SLA/SLO.',
);
