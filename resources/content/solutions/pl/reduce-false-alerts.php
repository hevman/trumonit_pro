<?php

return array (
  'order' => 5,
  'title' => 'Jak ograniczyć fałszywe alerty (alert fatigue)',
  'meta_title' => 'Jak ograniczyć fałszywe alerty | TruMonit',
  'meta_description' => 'Zmniejsz alert fatigue: progi kolejnych błędów, cooldown, maintenance windows i multi‑region. Monitoring o wysokim sygnale i niskim szumie.',
  'summary' => 'Fałszywe alerty niszczą zaufanie do monitoringu. Ten playbook pokazuje, jak ustawić progi, retry i proces, żeby pager był „dla incydentów”, nie dla szumu.',
  'body' => '<p>Fałszywe alerty to problem niezawodności. Kiedy zespół przestaje ufać alertom, prawdziwe incydenty są ignorowane albo zauważane zbyt późno. Dobrze zaprojektowany monitoring ma prosty cel: <strong>wysoki sygnał, niski szum</strong>.</p>

<h3>Ustawienia, które realnie działają</h3>
<ul>
  <li>Alert dopiero po <strong>2–3 kolejnych błędach</strong> (zamiast po 1).</li>
  <li><strong>Cooldown</strong>: nie page’uj kilka razy tego samego outage’u.</li>
  <li><strong>Retry</strong>: odróżnij chwilowy glitch od realnej awarii.</li>
  <li><strong>Maintenance windows</strong> na planowane prace i migracje.</li>
  <li><strong>Multi‑region</strong> dla krytycznych endpointów (szczególnie przy problemach routingu/DNS).</li>
</ul>

<h3>Źródła fałszywych alarmów (najczęstsze)</h3>
<ul>
  <li>Zbyt ciasne timeouty (normalna wariancja ruchu wygląda jak awaria).</li>
  <li>Za agresywne reguły treści (brittle keyword/JSON).</li>
  <li>Błędy zależności zewnętrznych (3rd‑party) bez osobnego oznaczenia/alertu.</li>
  <li>Brak ownera: alert trafia „w próżnię”.</li>
</ul>

<h3>Prosty proces tygodniowy (15 minut)</h3>
<ol>
  <li>Wypisz fałszywe alerty i przypisz przyczynę (timeout, DNS, 3rd‑party, deploy).</li>
  <li>Dostosuj progi: timeout, liczba kolejnych błędów, retry, cooldown.</li>
  <li>Zaktualizuj ownership i playbook: kto reaguje i co robi w 1. kolejności.</li>
</ol>

<p>Jeśli monitoring ma dowozić klientów i spokój zespołu, pager nie może być hałaśliwy. Fałszywe alerty to dług techniczny procesu.</p>',
  'problem' => 'Alert fatigue spowalnia reakcję i obniża jakość on‑call. Monitoring musi być zaprojektowany pod zaufanie: wysoki sygnał, niski szum, jasne ownership.',
  'approach' => 
  array (
    0 => 'Włącz progi kolejnych błędów przed alertem.',
    1 => 'Dodaj retry, cooldown i deduplikację.',
    2 => 'Używaj maintenance windows.',
    3 => 'Dodaj multi‑region dla krytycznych monitorów.',
    4 => 'Przeglądaj alerty tygodniowo i poprawiaj progi/ownership.',
  ),
  'proof' => 
  array (
    0 => 'Mniej szumu i szybsza reakcja na realne incydenty.',
    1 => 'Lepsza jakość on‑call i mniej „wyciszania” alertów na ślepo.',
    2 => 'Historia incydentów i wzorców alertowania do optymalizacji.',
  ),
  'checklist' => 
  array (
    0 => 'Kolejne błędy przed alertem są włączone.',
    1 => 'Retry + cooldown + deduplikacja są włączone.',
    2 => 'Maintenance windows są skonfigurowane.',
    3 => 'Multi‑region jest włączony dla krytycznych endpointów.',
    4 => 'Ownerzy i playbook są przypisani.',
  ),
  'pitfalls' => 
  array (
    0 => 'Zbyt ciasne progi (alerty na normalną wariancję).',
    1 => 'Brak ownerów (alerty trafiają losowo).',
    2 => 'Jedna reguła dla wszystkiego (brak rozdziału: sieć vs aplikacja vs 3rd‑party).',
  ),
  'related_faq' => 
  array (
    0 => 'how-do-i-reduce-false-alerts',
    1 => 'multi-region-monitoring-why-important',
    2 => 'network-error-vs-application-error-monitoring',
  ),
);
