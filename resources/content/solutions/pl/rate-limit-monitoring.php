<?php

return array (
  'order' => 22,
  'title' => 'Monitoring limitów API (429) i quota exhaustion',
  'meta_title' => 'Monitoring limitów API i 429 | TruMonit',
  'meta_description' => 'Wykrywaj rate limit (429), limity dostawców i quota exhaustion zanim wywali produkcję. Jak monitorować, alertować i robić backoff.',
  'summary' => 'Rate limit i quota to częsta przyczyna “losowych” awarii. Monitoruj 429, opóźnienia i błędy zależności, zanim system zacznie się sypać.',
  'body' => '<p>Limity API są podstępne: wszystko działa, dopóki rośnie ruch albo zmienia się zachowanie klienta. Potem nagle pojawiają się 429, retry, rośnie latency i spada dostępność. Jeśli integrujesz się z zewnętrznymi usługami, monitoring limitów to must-have.</p>

<h3>Co obserwować</h3>
<ul>
  <li>Wzrost <strong>429</strong> i 5xx na endpointach zależnych.</li>
  <li>Skoki latency (często wynik retry/backoff).</li>
  <li>Zmiany w wolumenie requestów po deployu.</li>
</ul>

<h3>Jak reagować</h3>
<ul>
  <li>Backoff i retry z limitem (bez “retry storm”).</li>
  <li>Cache i batching, jeśli to możliwe.</li>
  <li>Alertowanie zanim osiągniesz twardą quota.</li>
</ul>

<p>Powiązane: <a href="/pl/solutions/monitoring-third-party-dependencies">monitoring zależności third-party</a> i <a href="/pl/solutions/api-endpoint-monitoring">monitoring API</a>. FAQ: <a href="/pl/faq/how-to-monitor-api-rate-limits">jak monitorować rate limits</a>.</p>',
  'problem' => 'Rate limiting potrafi wyglądać jak “awaria aplikacji”, ale przyczyna jest w limicie dostawcy albo w niekontrolowanym retry. Bez monitoringu 429 i wolumenu requestów diagnoza trwa zbyt długo.',
  'approach' => array (
    0 => 'Monitoruj endpointy zależne pod kątem 429/5xx i latency.',
    1 => 'Dodaj alerty na trendy (rosnące 429), nie tylko pojedyncze błędy.',
    2 => 'Zastosuj backoff, cache i limity retry po stronie klienta.',
    3 => 'Wydziel krytyczne integracje jako osobne monitory.',
  ),
  'proof' => array (
    0 => 'Szybciej wykrywasz quota exhaustion i spadek jakości.',
    1 => 'Mniej awarii “łańcuchowych” przez retry storms.',
    2 => 'Lepsza współpraca z dostawcą dzięki jasnym metrykom.',
  ),
  'checklist' => array (
    0 => 'Masz monitoring 429, 5xx i latency dla integracji.',
    1 => 'Retry/backoff są kontrolowane i ograniczone.',
    2 => 'Alerty reagują na trend, a nie jeden spike.',
  ),
  'pitfalls' => array (
    0 => 'Brak limitu retry (sam sobie robisz DDoS).',
    1 => 'Monitoring tylko “czy 200”, bez 429 i wolumenu.',
    2 => 'Brak oddzielnych monitorów dla zależności.',
  ),
  'related_faq' => array (
    0 => 'how-to-monitor-api-rate-limits',
    1 => 'how-to-monitor-third-party-dependencies',
    2 => 'what-causes-website-downtime',
  ),
);