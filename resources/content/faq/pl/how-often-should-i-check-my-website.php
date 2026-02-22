<?php

return array (
  'order' => 2,
  'related' => 
  array (
    0 => 'what-is-uptime-monitoring',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'synthetic-checks-vs-http-checks',
  ),
  'related_solutions' => 
  array (
    0 => 'uptime-monitoring-for-saas',
    1 => 'choose-monitoring-plan',
  ),
  'question' => 'Jak często sprawdzać stronę (1 min vs 5 min)?',
  'answer' => '<p>Większość zespołów zaczyna od interwału <strong>1–5 minut</strong>. Dla krytycznych flow (checkout, logowanie, kluczowe API) sensowne jest <strong>1 minuta</strong>. Dla mniej istotnych stron zwykle wystarczy 5 minut.</p>

<h3>Jak dobrać częstotliwość</h3>
<ul>
  <li><strong>1 min</strong>: krytyczne endpointy i ścieżki „money path”.</li>
  <li><strong>5 min</strong>: strony informacyjne, mniej krytyczne integracje.</li>
  <li><strong>Różne częstotliwości</strong>: nie ustawiaj jednego interwału dla wszystkiego.</li>
</ul>

<h3>Co jest ważniejsze niż sama częstotliwość</h3>
<ul>
  <li>Progi kolejnych błędów (2–3) + retry + cooldown (żeby nie robić szumu).</li>
  <li>Multi‑region dla krytycznych monitorów (DNS/routing potrafią dawać fałszywe alarmy).</li>
  <li>Stabilne warunki (timeouty i reguły treści nie mogą być zbyt agresywne).</li>
</ul>

<p>Praktyczna zasada: najpierw pokryj krytyczne ścieżki 1‑min, potem rozszerzaj monitoring na 5‑min.</p>',
  'meta_title' => 'Jak często sprawdzać stronę (1 min vs 5 min)? | TruMonit',
  'meta_description' => 'Jak dobrać częstotliwość monitoringu: 1 min dla krytycznych ścieżek, 5 min dla reszty. Ważne też: retry, kolejne błędy, cooldown i multi‑region.',
);
