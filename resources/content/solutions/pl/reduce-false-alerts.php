<?php

return array (
  'order' => 5,
  'title' => 'Jak ograniczyc falszywe alerty',
  'meta_title' => 'Jak ograniczyc falszywe alerty | TruMonit',
  'meta_description' => 'Zmniejsz alert fatigue: progi kolejnych bledow, cooldown, maintenance windows i multi-region. Wysoki sygnal, niski szum.',
  'summary' => 'Praktyczny playbook, zeby ograniczyc szum alertow i utrzymac zaufanie do monitoringu.',
  'body' => '<p>Falszywe alerty to problem niezawodnosci. Gdy zespol przestaje ufac alertom, realne incydenty sa przegapiane.</p>

<h3>Wysoki sygnal - domyslne ustawienia</h3>
<ul>
  <li>Alert po <strong>2-3 kolejnych bledach</strong>.</li>
  <li><strong>Cooldown</strong>, zeby nie pageowac kilka razy tego samego outage.</li>
  <li><strong>Multi-region</strong> dla krytycznych endpointow.</li>
  <li><strong>Maintenance windows</strong> na planowane prace.</li>
</ul>

<h3>Przeglad tygodniowy</h3>
<ol>
  <li>Wypisz falszywe alerty i skategoryzuj przyczyne.</li>
  <li>Dostosuj progi albo dodaj stabilne asercje.</li>
  <li>Uaktualnij playbooki i ownerow.</li>
</ol>',
  'problem' => 'Alert fatigue spowalnia reakcje i obniza jakosc. Monitoring musi byc zaprojektowany pod zaufanie: wysoki sygnal, niski szum.',
  'approach' => 
  array (
    0 => 'Wlacz progi kolejnych bledow przed alertem.',
    1 => 'Dodaj cooldown i deduplikacje.',
    2 => 'Uzywaj maintenance windows.',
    3 => 'Dodaj multi-region dla krytycznych monitorow.',
  ),
  'proof' => 
  array (
    0 => 'Mniej szumu i szybsza reakcja na realne incydenty.',
    1 => 'Historia incydentow i wzorcow alertowania.',
  ),
  'checklist' => 
  array (
    0 => 'Kolejne bledy przed alertem wlaczone.',
    1 => 'Cooldown/deduplikacja wlaczona.',
    2 => 'Maintenance windows skonfigurowane.',
    3 => 'Multi-region dla krytycznych endpointow.',
  ),
  'pitfalls' => 
  array (
    0 => 'Zbyt ciasne progi (alerty na normalna wariancje).',
    1 => 'Brak ownerow (alerty trafiaja losowo).',
  ),
  'related_faq' => 
  array (
    0 => 'how-do-i-reduce-false-alerts',
    1 => 'multi-region-monitoring-why-important',
    2 => 'network-error-vs-application-error-monitoring',
  ),
);

