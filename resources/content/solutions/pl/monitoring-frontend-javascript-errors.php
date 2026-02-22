<?php

return array (
  'order' => 26,
  'title' => 'Monitoring błędów JavaScript i regresji UI (synthetic)',
  'meta_title' => 'Monitoring błędów JS i UI | TruMonit',
  'meta_description' => 'HTTP 200 nie znaczy, że UI działa. Synthetic monitoring wykrywa błędy JS, brak elementów, pętle redirectów i awarie UX po deployu.',
  'summary' => 'Najdroższe awarie to te “niewidoczne” dla HTTP. Synthetic checks widzą to, co widzi użytkownik: UI, redirecty i błędy JS.',
  'body' => '<p>Twoja strona może zwracać 200, a użytkownik i tak nie może nic zrobić: błąd JS blokuje kliknięcie, formularz nie renderuje się, albo aplikacja wchodzi w pętlę redirectów. Tego nie złapie prosty uptime check. Złapie to <strong>synthetic monitoring</strong>.</p>

<h3>Co synthetic potrafi wykryć</h3>
<ul>
  <li>Błędy JavaScript w konsoli.</li>
  <li>Brak krytycznych elementów (np. przycisku, formularza).</li>
  <li>Redirect-loop w realnej przeglądarce.</li>
  <li>Problemy z CSP, cookies, cross-site.</li>
</ul>

<h3>Jak zrobić to stabilnie</h3>
<ul>
  <li>Używaj selektorów typu <code>data-testid</code>.</li>
  <li>Waliduj 1–2 elementy krytyczne, nie cały layout.</li>
  <li>Ustaw retry/cooldown, żeby nie pagować za flaki.</li>
</ul>

<p>Powiązane: <a href="/pl/solutions/synthetic-monitoring-login-flow">monitoring login flow</a> i <a href="/pl/solutions/release-regression-monitoring">release checks</a>.</p>',
  'problem' => 'UI może być zepsute mimo zielonego statusu HTTP. Bez synthetic monitoringu dowiadujesz się o błędzie JS z supportu, a nie z systemu.',
  'approach' => array (
    0 => 'Zdefiniuj krytyczny flow (login/checkout) i warunek sukcesu.',
    1 => 'Użyj stabilnych selektorów i ogranicz liczbę asercji.',
    2 => 'Włącz wykrywanie błędów JS i pętli redirectów.',
    3 => 'Dodaj retry/cooldown i routing alertów.',
  ),
  'proof' => array (
    0 => 'Wykrywasz regresje UI szybciej niż klienci.',
    1 => 'Mniej “cichych awarii” po deployu.',
    2 => 'Lepszy sygnał dla ścieżek krytycznych.',
  ),
  'checklist' => array (
    0 => 'Flow i selektory są stabilne (data-testid).',
    1 => 'Sprawdzasz tylko krytyczne elementy.',
    2 => 'Alerty mają retry/cooldown.',
  ),
  'pitfalls' => array (
    0 => 'Brittle selektory i flaki po zmianie CSS.',
    1 => 'Zbyt dużo asercji i ciągłe false positives.',
    2 => 'Brak kont testowych i ryzyko bezpieczeństwa.',
  ),
  'related_faq' => array (
    0 => 'synthetic-checks-vs-http-checks',
    1 => 'how-to-monitor-login-flow-synthetically',
    2 => 'what-causes-website-downtime',
  ),
);