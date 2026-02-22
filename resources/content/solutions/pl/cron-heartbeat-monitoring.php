<?php

return array (
  'order' => 13,
  'title' => 'Monitoring cron jobs (heartbeat) bez fałszywych alarmów',
  'meta_title' => 'Monitoring cron jobs (heartbeat) | TruMonit',
  'meta_description' => 'Heartbeat dla zadań cyklicznych: wykrywaj brak wykonania i zbyt długi runtime, ustaw grace period, retry i eskalacje bez spamu.',
  'summary' => 'Heartbeat to najpewniejszy sposób na monitoring zadań cyklicznych: dostajesz sygnał po sukcesie i alert, gdy sygnału brakuje.',
  'body' => '<p>Cron i joby cykliczne psują się “po cichu”: kolejka stoi, worker padł, lock w bazie nie puścił, albo job trwa godzinę zamiast minuty. Najprostszy model monitoringu to <strong>heartbeat</strong>: po udanym wykonaniu wysyłasz ping.</p>

<h3>Jak to działa</h3>
<ul>
  <li>Job kończy się sukcesem i wysyła sygnał (ping).</li>
  <li>Jeśli sygnał nie nadejdzie w oknie czasu, powstaje incydent.</li>
  <li>Dla długich zadań możesz dodać wariant start/finish.</li>
</ul>

<h3>Ustawienia, które robią różnicę</h3>
<ul>
  <li><strong>Interval + grace</strong>: np. co 5 min + 2 min tolerancji (cache, chwilowe opóźnienia).</li>
  <li><strong>Retry</strong>: jeden brak ping to często nie awaria, tylko jitter.</li>
  <li><strong>Escalation</strong>: P0 tylko dla krytycznych zadań (backup, billing, integracje).</li>
</ul>

<h3>Co monitorować oprócz “brak ping”</h3>
<ul>
  <li>Za długi czas trwania (job wisi lub zasysa zasoby).</li>
  <li>Wzrost liczby błędów w przetwarzaniu (np. failed jobs).</li>
  <li>Zmiany w harmonogramie po deployu (regresje konfiguracji).</li>
</ul>

<p>Powiązane: <a href="/pl/solutions/reduce-false-alerts">redukcja fałszywych alarmów</a> oraz <a href="/pl/solutions/alert-routing-slack-teams-webhooks">routing alertów</a>. Zobacz też FAQ: <a href="/pl/faq/how-to-monitor-cron-jobs">jak monitorować cron jobs</a>.</p>',
  'problem' => 'Zadania cykliczne potrafią przestać działać bez widocznego błędu na stronie. Bez heartbeat dowiadujesz się o problemie dopiero, gdy klient zgłosi brak maili, brak raportu albo brak syncu.',
  'approach' => array (
    0 => 'Dodaj heartbeat: po sukcesie job wysyła ping (osobny monitor per krytyczny job).',
    1 => 'Ustaw interval + grace + retry, żeby uniknąć alertów od jitteru.',
    2 => 'Dla długich zadań rozważ start/finish i alert na przekroczenie czasu.',
    3 => 'Skonfiguruj routing alertów i priorytety (P0 tylko dla jobów krytycznych).',
    4 => 'Po zmianach w deployu/konfiguracji testuj heartbeat ponownie.',
  ),
  'proof' => array (
    0 => 'Szybko wykrywasz brak wykonania zadania, zanim problem trafi do klienta.',
    1 => 'Mniej fałszywych alarmów dzięki grace/retry i priorytetom.',
    2 => 'Jasne ownership: wiadomo, kto dostaje alert i co ma zrobić.',
  ),
  'checklist' => array (
    0 => 'Masz osobny heartbeat dla krytycznych zadań.',
    1 => 'Interval i grace odpowiadają realnemu harmonogramowi.',
    2 => 'Retry/cooldown ograniczają alert spam.',
    3 => 'P0 jest zarezerwowane dla zadań o realnym wpływie na biznes.',
  ),
  'pitfalls' => array (
    0 => 'Jedno “zbiorcze” heartbeat dla wielu zadań (trudna diagnoza).',
    1 => 'Brak grace period i alerty od drobnych opóźnień.',
    2 => 'Brak testu po deployu: cron działa inaczej niż zakładałeś.',
  ),
  'related_faq' => array (
    0 => 'how-to-monitor-cron-jobs',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'what-causes-website-downtime',
  ),
);