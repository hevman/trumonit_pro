<?php

return array (
  'order' => 19,
  'title' => 'SLO/SLA i error budget w praktyce (bez akademickiej teorii)',
  'meta_title' => 'SLO, SLA i error budget w praktyce | TruMonit',
  'meta_description' => 'Jak ustawić SLI/SLO/SLA, liczyć error budget i burn rate oraz podejmować decyzje o deployach. Przykłady dla SaaS, API i checkout.',
  'summary' => 'SLO i error budget mają pomagać podejmować decyzje: kiedy możesz wdrażać szybciej, a kiedy musisz inwestować w stabilność.',
  'body' => '<p>SLO/SLA/error budget często brzmią jak “SRE dla dużych firm”, ale w praktyce to po prostu narzędzia do podejmowania decyzji. Jeśli mierzysz uptime i błędy, możesz policzyć budżet błędów i używać go jako hamulca, gdy stabilność siada.</p>

<h3>Prosty model</h3>
<ul>
  <li><strong>SLI</strong>: co mierzysz (np. % 2xx, p95 latency, error rate).</li>
  <li><strong>SLO</strong>: cel wewnętrzny (np. 99,9%/30 dni).</li>
  <li><strong>SLA</strong>: obietnica dla klienta (często niższa).</li>
  <li><strong>Error budget</strong>: dopuszczalny margines awarii wynikający z SLO.</li>
</ul>

<h3>Jak używać error budget</h3>
<ul>
  <li>Jeśli budget pali się szybko (burn rate), wstrzymaj ryzykowne wdrożenia.</li>
  <li>Jeśli budget jest zdrowy, możesz dostarczać szybciej.</li>
  <li>Ustal, które usługi są P0 i mają ostrzejsze cele.</li>
</ul>

<p>Powiązane: <a href="/pl/solutions/maintenance-windows-during-deploys">maintenance windows</a>, <a href="/pl/solutions/incident-response-playbook">playbook incydentowy</a>. FAQ: <a href="/pl/faq/what-is-sla-vs-slo">SLA vs SLO</a> i <a href="/pl/faq/what-is-error-budget-for-slo">error budget</a>.</p>',
  'problem' => 'Bez metryk stabilności decyzje są “na czuja”. SLO i error budget pozwalają związać monitoring z procesem wdrożeń i priorytetami, zamiast reagować dopiero po incydencie.',
  'approach' => array (
    0 => 'Zdefiniuj SLI dla kluczowych usług (API, login, checkout).',
    1 => 'Ustal SLO per powierzchnia (nie jedno globalne).',
    2 => 'Policz error budget i obserwuj burn rate.',
    3 => 'Ustal reguły: kiedy wstrzymujesz wdrożenia i robisz stabilność.',
  ),
  'proof' => array (
    0 => 'Mniej “gaszenia pożarów” i lepsze priorytety.',
    1 => 'Łatwiej uzasadnić inwestycje w stabilność.',
    2 => 'Monitorowanie ma kontekst biznesowy (SLA/SLO).',
  ),
  'checklist' => array (
    0 => 'Masz SLI/SLO dla 2–3 krytycznych usług.',
    1 => 'Masz error budget i wiesz, jak szybko się spala.',
    2 => 'Masz zasady dla deployów przy wysokim burn rate.',
  ),
  'pitfalls' => array (
    0 => 'Jedno SLO dla wszystkiego.',
    1 => 'Cele bez danych z monitoringu.',
    2 => 'Brak reguł decyzyjnych (error budget “na slajdach”).',
  ),
  'related_faq' => array (
    0 => 'what-is-sla-vs-slo',
    1 => 'what-is-error-budget-for-slo',
    2 => 'how-to-choose-a-website-monitoring-plan',
  ),
);