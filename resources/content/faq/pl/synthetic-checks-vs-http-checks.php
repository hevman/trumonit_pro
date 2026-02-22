<?php

return array (
  'order' => 5,
  'related' => 
  array (
    0 => 'how-do-i-monitor-api-endpoints',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'what-is-sla-vs-slo',
  ),
  'related_solutions' => 
  array (
    0 => 'synthetic-monitoring-login-flow',
    1 => 'api-endpoint-monitoring',
  ),
  'question' => 'Synthetic checks vs HTTP checks: jaka jest różnica?',
  'answer' => '<p>To dwa różne poziomy monitoringu:</p>

<ul>
  <li><strong>HTTP checks</strong> są lekkie i szybkie: sprawdzają status, czas odpowiedzi i proste reguły treści (np. keyword/JSON).</li>
  <li><strong>Synthetic checks</strong> (np. Playwright) symulują przeglądarkę i ścieżkę użytkownika: kliknięcia, redirecty, render UI, logowanie, checkout.</li>
</ul>

<h3>Kiedy wystarczy HTTP check</h3>
<ul>
  <li>Chcesz szybki sygnał dostępności (SLA uptime).</li>
  <li>Endpoint jest stabilny, a UI nie jest krytyczne.</li>
  <li>Wystarczy walidacja: status + latency + prosty marker w payloadzie.</li>
</ul>

<h3>Kiedy potrzebujesz synthetic check</h3>
<ul>
  <li>Masz „ciche awarie”: HTTP 200, ale użytkownik nie może się zalogować lub kliknąć kluczowej akcji.</li>
  <li>Chcesz wykrywać regresje front‑endu (JS error, CSP, pętla redirectów).</li>
  <li>Monitorujesz pełne flow: login, onboarding, checkout, reset hasła.</li>
</ul>

<p>Praktyka: używaj HTTP checks jako „baseline” dla wielu endpointów, a synthetic checks dla kilku najważniejszych user journeys.</p>',
  'meta_title' => 'Synthetic checks vs HTTP checks: jaka jest różnica? | TruMonit',
  'meta_description' => 'HTTP checks są szybkie (status/latency/keyword). Synthetic checks (Playwright) monitorują przeglądarkę i user journey (login/checkout), wykrywając błędy UI/JS.',
);
