<?php

return array (
  'order' => 8,
  'title' => 'Jak wybrac plan monitoringu',
  'meta_title' => 'Jak wybrac plan monitoringu | TruMonit',
  'meta_description' => 'Dobierz plan wedlug liczby monitorow, czestotliwosci checkow, workflow zespolu i status page. Zacznij lean i skaluj, gdy rosnie governance.',
  'summary' => 'Plan dobieraj pod liczbe monitorow, czestotliwosc, workflow alertow i potrzebe status page.',
  'body' => '<p>Cennik ma sens tylko wtedy, gdy mapuje sie na realne potrzeby operacyjne: liczbe monitorow, czestotliwosc i poziom koordynacji w incydentach.</p>

<h3>Zacznij lean</h3>
<ul>
  <li>Monitoruj tylko krytyczne sciezki.</li>
  <li>5 minut dla endpointow o nizszym ryzyku.</li>
  <li>Dodawaj monitory, gdy wiesz, gdzie realnie powstaja incydenty.</li>
</ul>

<h3>Kiedy podniesc plan</h3>
<ul>
  <li>Potrzebujesz wiecej monitorow lub wyzszej czestotliwosci.</li>
  <li>Potrzebujesz eskalacji i workflow zespolowego.</li>
  <li>Potrzebujesz publicznej status page dla klientow.</li>
</ul>',
  'problem' => 'Zespoly albo przepalaja budzet, albo monitoruja za malo. Dobry wybor planu zalezy od ryzyka, wolumenu i dojrzalosci workflow.',
  'approach' => 
  array (
    0 => 'Policz krytyczne endpointy i user journeys.',
    1 => 'Dobierz czestotliwosc do wplywu (1 min krytyczne, 5 min reszta).',
    2 => 'Dodaj eskalacje, gdy ownership jest zespolowy.',
  ),
  'proof' => 
  array (
    0 => 'Koszt dopasowany do wolumenu monitorow.',
    1 => 'Jasne triggery upgrade (operacyjna dojrzalosc).',
  ),
  'checklist' => 
  array (
    0 => 'Krytyczne monitory policzone i priorytetyzowane.',
    1 => 'Czestotliwosc dobrana pod ryzyko.',
    2 => 'Workflow alertow pasuje do wielkosci zespolu.',
  ),
  'pitfalls' => 
  array (
    0 => 'Monitoring wszystkiego (szum i koszt).',
    1 => 'Monitoring tylko minimum (blind spoty).',
  ),
  'related_faq' => 
  array (
    0 => 'how-to-choose-a-website-monitoring-plan',
    1 => 'how-many-monitors-do-i-need',
    2 => 'best-alert-channels-for-uptime-monitoring',
  ),
);

