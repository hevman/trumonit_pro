<?php

return array (
  'order' => 7,
  'related' => 
  array (
    0 => 'what-is-uptime-monitoring',
    1 => 'what-is-a-status-page',
    2 => 'what-is-an-incident-response-playbook',
  ),
  'related_solutions' => 
  array (
    0 => 'choose-monitoring-plan',
    1 => 'uptime-monitoring-for-saas',
    2 => 'incident-response-playbook',
  ),
  'question' => 'SLA vs SLO: jaka jest różnica w monitoringu?',
  'answer' => '<p><strong>SLO</strong> (Service Level Objective) to <strong>wewnętrzny</strong> cel niezawodności, np. 99,9% dostępności dla API w 30 dni. <strong>SLA</strong> (Service Level Agreement) to <strong>zewnętrzna</strong> obietnica w umowie z klientem, często z konsekwencjami finansowymi (kredyty, rabaty).</p>

<h3>Jak to powiązać z monitoringiem</h3>
<ul>
  <li><strong>SLI</strong> (wskaźnik): co mierzysz (np. % żądań 2xx, p95 latency poniżej 800 ms).</li>
  <li><strong>SLO</strong>: jaki jest cel dla SLI (np. 99,9% w miesiącu).</li>
  <li><strong>SLA</strong>: co obiecujesz klientowi (często niżej i ostrożniej niż SLO, np. 99,5%).</li>
</ul>

<h3>Przykład (praktyczny)</h3>
<ul>
  <li>SLI: “p95 czas odpowiedzi API &lt; 800 ms”</li>
  <li>SLO: “99,9% w 30 dni”</li>
  <li>SLA: “99,5% w 30 dni + kredyt w razie naruszenia”</li>
</ul>

<h3>Najczęstsze błędy</h3>
<ul>
  <li>Jedno SLO dla wszystkiego: landing i checkout mają inne ryzyka niż panel admina.</li>
  <li>SLA ustawione “na oko” bez danych z monitoringu.</li>
  <li>Alerty bez kontekstu: warto pracować na error budget i burn rate.</li>
</ul>

<p>Jeśli potrzebujesz gotowej ścieżki wdrożenia i priorytetów, zobacz <a href=\"/pl/solutions/choose-monitoring-plan\">jak dobrać plan monitoringu</a> oraz <a href=\"/pl/solutions/incident-response-playbook\">playbook incydentowy</a>.</p>',
  'meta_title' => 'SLA vs SLO: różnica i jak to mierzyć w monitoringu | TruMonit',
  'meta_description' => 'SLA vs SLO (i SLI) w praktyce: definicje, przykład metryk, typowe błędy i jak spiąć monitoring z celami niezawodności oraz umową z klientem.',
);
