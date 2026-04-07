<?php

return array (
  'order' => 6,
  'related' => 
  array (
    0 => 'how-often-should-i-check-my-website',
    1 => 'synthetic-checks-vs-http-checks',
    2 => 'what-is-an-incident-response-playbook',
  ),
  'related_solutions' => 
  array (
    0 => 'reduce-false-alerts',
  ),
  'question' => 'Jak ograniczyć fałszywe alerty (alert fatigue)?',
  'answer' => '<p>Fałszywe alerty zwykle wynikają ze zbyt agresywnych progów i braku podstawowych mechanizmów typu retry/cooldown. Celem jest „wysoki sygnał, niski szum”, żeby on‑call ufał pagerowi.</p>

<h3>Najważniejsze ustawienia</h3>
<ul>
  <li>Alert dopiero po <strong>2–3 kolejnych błędach</strong> (nie po 1).</li>
  <li><strong>Retry</strong>: odróżnij chwilowy glitch od realnej awarii.</li>
  <li><strong>Cooldown</strong> + deduplikacja: jeden incident = jeden paging.</li>
  <li><strong>Maintenance windows</strong> na planowane prace.</li>
  <li><strong>Multi‑region</strong> dla krytycznych endpointów (DNS/routing potrafią „oszukać” jeden region).</li>
</ul>

<h3>Co najczęściej generuje szum</h3>
<ul>
  <li>Za krótkie timeouty (normalna wariancja wygląda jak awaria).</li>
  <li>Brittle keyword/JSON assertions (dynamiczne pola, timestampy).</li>
  <li>Brak rozdziału alertów: sieć vs aplikacja vs 3rd‑party.</li>
</ul>

<p>Jeśli alerty są głośne, ludzie je wyciszają. To prosta droga do przegapionych incydentów.</p>',
  'meta_title' => 'Jak ograniczyć fałszywe alerty (alert fatigue)? | TruMonit',
  'meta_description' => 'Fałszywe alerty: kolejne błędy + ponowienia, wygaszanie/deduplikacja, okna serwisowe i wiele regionów. Praktyczne ustawienia, które redukują szum bez ukrywania awarii.',
);
