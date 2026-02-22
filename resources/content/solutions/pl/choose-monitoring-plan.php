<?php

return array (
  'order' => 8,
  'title' => 'Jak wybrać plan monitoringu (check frequency, limity)',
  'meta_title' => 'Jak wybrać plan monitoringu: limity i częstotliwość | TruMonit',
  'meta_description' => 'Dobierz plan według liczby monitorów, częstotliwości checków, workflow zespołu i potrzeby status page. Zacznij lean i skaluj, gdy rośnie governance i ryzyko.',
  'summary' => 'Plan dobieraj pod liczbę monitorów, częstotliwość, alerty/on‑call i potrzeby komunikacji (status page). Najpierw pokryj krytyczne ścieżki, potem skaluj.',
  'body' => '<p>Cennik ma sens tylko wtedy, gdy mapuje się na realne potrzeby operacyjne: liczbę monitorów, częstotliwość skanów, retencję danych oraz poziom koordynacji w incydentach.</p>

<h3>Zacznij „lean” (żeby nie przepalać budżetu i nie robić szumu)</h3>
<ul>
  <li>Monitoruj <strong>tylko krytyczne ścieżki</strong>: logowanie, API, checkout, webhooki.</li>
  <li>Ustaw wyższą częstotliwość dla krytycznych (np. 1 min), a resztę zostaw na 5 min.</li>
  <li>Dodawaj monitory dopiero wtedy, gdy wiesz, jakie incydenty realnie się powtarzają.</li>
</ul>

<h3>Co wpływa na wybór planu</h3>
<ul>
  <li><strong>Liczba monitorów</strong>: ile usług/endpointów jest realnie krytycznych.</li>
  <li><strong>Check frequency</strong>: 1 min vs 5 min (czas wykrycia + koszt operacyjny).</li>
  <li><strong>Alerting i ownership</strong>: kiedy potrzebujesz eskalacji, rotacji i porządku w on‑call.</li>
  <li><strong>Status page</strong>: jeśli obsługujesz klientów, komunikacja w incydencie to część produktu.</li>
  <li><strong>Retencja</strong>: ile historii chcesz trzymać do analizy trendów/MTTR.</li>
</ul>

<h3>Kiedy podnieść plan (praktyczne triggery)</h3>
<ul>
  <li>Masz więcej krytycznych monitorów lub chcesz gęstszych checków.</li>
  <li>Incydenty wymagają koordynacji zespołowej i eskalacji do ownerów.</li>
  <li>Potrzebujesz publicznej status page dla klientów.</li>
</ul>

<p>Najczęstszy błąd to „monitoring wszystkiego” od razu. Lepsza strategia: najpierw krytyczne ścieżki, potem rozszerzanie pokrycia.</p>',
  'problem' => 'Zespoły albo przepalają budżet (monitoring wszystkiego), albo monitorują za mało (blind spoty). Dobry wybór planu zależy od ryzyka, wolumenu i dojrzałości workflow.',
  'approach' => 
  array (
    0 => 'Policz krytyczne endpointy i user journeys.',
    1 => 'Dobierz częstotliwość do wpływu (1 min krytyczne, 5 min reszta).',
    2 => 'Dodaj eskalację, gdy ownership jest zespołowy.',
    3 => 'Zdefiniuj triggery upgrade: więcej monitorów, gęstsze checki, status page, retencja.',
  ),
  'proof' => 
  array (
    0 => 'Koszt dopasowany do wolumenu monitorów i częstotliwości.',
    1 => 'Jasne triggery upgrade (operacyjna dojrzałość).',
    2 => 'Mniej szumu, bo startujesz od priorytetów.',
  ),
  'checklist' => 
  array (
    0 => 'Krytyczne monitory są policzone i priorytetyzowane.',
    1 => 'Częstotliwość jest dobrana pod ryzyko.',
    2 => 'Workflow alertów pasuje do wielkości zespołu.',
    3 => 'Wiesz, czy potrzebujesz status page i większej retencji.',
  ),
  'pitfalls' => 
  array (
    0 => 'Monitoring wszystkiego od razu (szum i koszt).',
    1 => 'Monitoring tylko minimum (blind spoty).',
    2 => 'Jedna częstotliwość dla wszystkich monitorów.',
  ),
  'related_faq' => 
  array (
    0 => 'how-to-choose-a-website-monitoring-plan',
    1 => 'how-many-monitors-do-i-need',
    2 => 'best-alert-channels-for-uptime-monitoring',
  ),
);
