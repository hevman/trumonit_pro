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
    0 => 'api-endpoint-monitoring',
    1 => 'reduce-false-alerts',
    2 => 'alert-routing-slack-teams-webhooks',
    3 => 'cron-heartbeat-monitoring',
  ),
  'question' => 'Jak monitorować cron jobs i zadania cykliczne?',
  'answer' => '<p>Najprostszy i najbardziej niezawodny sposób to <strong>heartbeat</strong>: po każdym poprawnym wykonaniu zadania (cron/job) wysyłasz sygnał, a brak sygnału w oczekiwanym czasie oznacza incydent. To działa lepiej niż “sprawdzanie logów”, bo jest automatyczne i mierzalne.</p>

<h3>Wariant 1: jeden ping po sukcesie (najczęstszy)</h3>
<ul>
  <li>Po zakończeniu zadania wysyłasz request do unikalnego URL.</li>
  <li>Ustawiasz interwał (np. co 5 min) i <strong>grace period</strong> (np. +2 min) na opóźnienia.</li>
  <li>Jeśli ping nie przyjdzie, dostajesz alert.</li>
</ul>

<h3>Wariant 2: start + finish (gdy ważny jest czas trwania)</h3>
<ul>
  <li>Ping “start” na początku zadania i ping “finish” na końcu.</li>
  <li>Alarmujesz, gdy “finish” nie nadejdzie w czasie (job się zawiesił) albo gdy trwa za długo.</li>
  <li>To jest szczególnie dobre dla eksportów, backupów i ETL.</li>
</ul>

<h3>Najczęstsze błędy</h3>
<ul>
  <li>Ping tylko “na końcu”, ale job kończy się błędem zanim wyśle sygnał: dodaj ping w obsłudze wyjątków albo status w payloadzie.</li>
  <li>Brak tolerancji na opóźnienia: ustaw “grace” i retry, żeby nie generować szumu.</li>
  <li>Jeden monitor dla wielu jobów: rozdziel krytyczne zadania na osobne heartbeat’y.</li>
</ul>

<p>Powiązane: <a href=\"/pl/solutions/api-endpoint-monitoring\">monitoring endpointów API</a>, <a href=\"/pl/solutions/reduce-false-alerts\">redukcja fałszywych alarmów</a> i <a href=\"/pl/solutions/alert-routing-slack-teams-webhooks\">routing alertów</a>.</p>',
  'meta_title' => 'Jak monitorować cron jobs i zadania cykliczne? | TruMonit',
  'meta_description' => 'Jak monitorować cron jobs: heartbeat (ping po sukcesie), grace period, wariant start+finish dla długich zadań oraz typowe błędy. Praktyczne wskazówki pod alerting.',
);
