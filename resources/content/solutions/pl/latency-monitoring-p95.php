<?php

return array (
  'order' => 21,
  'title' => 'Monitoring latencji (p95/p99) i alerty, które mają sens',
  'meta_title' => 'Monitoring latencji p95/p99 | TruMonit',
  'meta_description' => 'Jak monitorować latency: progi p95/p99, różnica sieć vs aplikacja, retry i kiedy alertować. Praktyczne ustawienia dla API i stron.',
  'summary' => 'Uptime to za mało: serwis może “działać”, ale być wolny. Monitoruj p95/p99, żeby łapać degradacje zanim staną się downtime.',
  'body' => '<p>Najbardziej frustrujące awarie to nie 500, tylko “działa, ale wolno”. Użytkownik widzi spinner, porzuca koszyk, a Ty masz dalej zielony status. Monitoring latencji powinien łapać <strong>degradacje</strong> zanim zamienią się w pełny outage.</p>

<h3>Jak ustawiać progi</h3>
<ul>
  <li>Nie patrz tylko na średnią. Ustaw progi na <strong>p95</strong> albo <strong>p99</strong>.</li>
  <li>Różne progi dla różnych ścieżek: login i checkout są bardziej wrażliwe.</li>
  <li>Alertuj na zmianę trendu, nie tylko na jednorazowy spike.</li>
</ul>

<h3>Diagnoza: sieć vs aplikacja</h3>
<ul>
  <li>Jeśli rośnie latency i rosną timeouty, to często problem po stronie aplikacji lub bazy.</li>
  <li>Jeśli rośnie latency bez błędów, to bywa CDN/proxy albo zależność third-party.</li>
</ul>

<p>Powiązane: <a href="/pl/solutions/reduce-false-alerts">redukcja fałszywych alarmów</a> i <a href="/pl/solutions/monitoring-third-party-dependencies">monitoring zależności</a>. FAQ: <a href="/pl/faq/network-error-vs-application-error-monitoring">network vs application errors</a>.</p>',
  'problem' => 'Wysoka latencja to “ukryty downtime”. Bez monitoringu p95/p99 tracisz konwersję i zaufanie, zanim zobaczysz twarde 5xx.',
  'approach' => array (
    0 => 'Ustal p95/p99 progi per krytyczny endpoint/stronę.',
    1 => 'Dodaj retry i cooldown w alertach, żeby unikać szumu.',
    2 => 'Monitoruj razem latency i error rate, żeby szybciej diagnozować.',
    3 => 'Wydziel zależności zewnętrzne jako osobne monitory.',
  ),
  'proof' => array (
    0 => 'Wykrywasz degradacje zanim staną się outage.',
    1 => 'Mniej “znikającej” konwersji przez wolne ścieżki.',
    2 => 'Lepsza diagnoza dzięki połączeniu latency i error rate.',
  ),
  'checklist' => array (
    0 => 'Progi p95/p99 są ustawione, nie tylko średnia.',
    1 => 'Krytyczne ścieżki mają osobne progi.',
    2 => 'Alerty mają retry i cooldown.',
  ),
  'pitfalls' => array (
    0 => 'Alert na średnią (maskuje ogon).',
    1 => 'Jednakowy próg dla wszystkiego.',
    2 => 'Brak korelacji z error rate i brak diagnozy.',
  ),
  'related_faq' => array (
    0 => 'network-error-vs-application-error-monitoring',
    1 => 'how-often-should-i-check-my-website',
    2 => 'how-do-i-reduce-false-alerts',
  ),
);