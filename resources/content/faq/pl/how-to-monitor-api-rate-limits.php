<?php

return array (
  'order' => 27,
  'related' => 
  array (
    0 => 'how-do-i-monitor-api-endpoints',
    1 => 'how-to-monitor-webhook-endpoints',
    2 => 'network-error-vs-application-error-monitoring',
  ),
  'related_solutions' => 
  array (
    0 => 'api-endpoint-monitoring',
  ),
  'question' => 'Jak monitorować rate limit API (429)?',
  'answer' => '<p>Rate limit zwykle objawia się kodem <strong>429</strong>. Sam 429 nie zawsze oznacza „awarię”, ale jest mocnym sygnałem przeciążenia, złego limitu lub „retry storm” po stronie klientów.</p>

<h3>Co monitorować</h3>
<ul>
  <li>Odsetek odpowiedzi 429 w czasie (skoki vs baseline).</li>
  <li>Korelację 429 z rosnącą latency i 5xx.</li>
  <li>Nagłówki limitów/usage (jeśli API je zwraca) i tempo zbliżania się do limitu.</li>
  <li>Endpointy o największym wolumenie (często krytyczne biznesowo).</li>
</ul>

<h3>Jak alertować (żeby mieć sygnał, nie szum)</h3>
<ul>
  <li>Osobny alert dla 429 (throttling) i osobny dla 5xx (błąd aplikacji).</li>
  <li>Retry + cooldown, aby uniknąć alert storm.</li>
  <li>Alertuj wcześniej (trend), nie dopiero po „twardym” limicie.</li>
</ul>

<p>Uwaga na retry storm: agresywny retry bez backoff/jitter może pogorszyć incydent. Warto wymusić backoff po stronie klientów.</p>',
  'meta_title' => 'Jak monitorować rate limit API (429)? | TruMonit',
  'meta_description' => 'Monitoring rate limit API: skoki 429, korelacja z latency/5xx, osobne alerty throttling vs błąd aplikacji oraz ochrona przed retry storm (backoff/jitter).',
);
