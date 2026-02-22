<?php

return array (
  'order' => 2,
  'title' => 'Monitoring endpointow API',
  'meta_title' => 'Monitoring endpointow API | TruMonit',
  'meta_description' => 'Monitoruj API: status, latency, timeout i asercje tresci. Wykrywaj time-outy, 5xx i regresje rate limit zanim zobacza to uzytkownicy.',
  'summary' => 'Monitoring API z dobrymi checkami: status, latency, keyword/JSON i rate limits, bez falszywych alarmow.',
  'body' => '<p>Monitoring API zawodzi, gdy checki sa zbyt plytkie (tylko status) albo zbyt kruche (brittle asercje). Celem jest szybkie wykrycie problemow odczuwalnych przez klientow.</p>

<h3>Rekomendowany setup</h3>
<ul>
  <li>Monitoruj endpoint <strong>health</strong> i jeden endpoint <strong>biznesowy</strong>.</li>
  <li>Ustaw akceptowalne statusy (2xx/3xx) i timeout.</li>
  <li>Dodaj stabilny marker: klucz JSON lub keyword (unikaj dynamicznego copy).</li>
</ul>

<h3>Typowe wzorce incydentow</h3>
<ul>
  <li>Timeouty przez DB saturation lub wolny upstream.</li>
  <li>5xx po wdrozeniu.</li>
  <li>Skoki 429 (rate limits) i retry storm.</li>
</ul>',
  'problem' => 'API moze zwracac 200, ale flow biznesowy jest zepsuty (wolne odpowiedzi, bledny payload, rate limit). Potrzebujesz monitoringu zgodnego z user journey.',
  'approach' => 
  array (
    0 => 'Dodaj monitory dla health + jednego endpointu biznesowego.',
    1 => 'Ustaw timeouty i progi latency pod realne SLO.',
    2 => 'Dodaj stabilne asercje (keyword/JSON) dla poprawnosci.',
    3 => 'Monitoruj rate limits i wzorce 429.',
    4 => 'Kieruj incydenty do ownera przez eskalacje.',
  ),
  'proof' => 
  array (
    0 => 'Wczesne wykrycie timeoutow, 5xx i problemow rate limit.',
    1 => 'Rozroznienie bledow sieci i aplikacji.',
    2 => 'Historia incydentow pod postmortem.',
  ),
  'checklist' => 
  array (
    0 => 'Health + endpoint biznesowy monitorowane.',
    1 => 'Timeout i progi latency ustawione.',
    2 => 'Asercje sa stabilne i nie flakuja.',
    3 => '429/rate-limit alerting dziala.',
  ),
  'pitfalls' => 
  array (
    0 => 'Monitoring tylko /health.',
    1 => 'Brittle asercje na dynamicznym tekscie.',
    2 => 'Brak testowych endpointow dla synthetic flows.',
  ),
  'related_faq' => 
  array (
    0 => 'how-do-i-monitor-api-endpoints',
    1 => 'network-error-vs-application-error-monitoring',
    2 => 'how-to-monitor-api-rate-limits',
    3 => 'how-to-monitor-webhook-endpoints',
  ),
);

