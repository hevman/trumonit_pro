<?php

return array (
  'order' => 22,
  'related' => 
  array (
    0 => 'what-is-sla-vs-slo',
    1 => 'what-is-mttr-and-how-to-improve-it',
    2 => 'how-to-choose-a-website-monitoring-plan',
  ),
  'related_solutions' => 
  array (
    0 => 'incident-response-playbook',
    1 => 'maintenance-windows-during-deploys',
    2 => 'reduce-false-alerts',
    3 => 'slo-sla-error-budgets',
  ),
  'question' => 'Czym jest error budget w monitoringu SLO?',
  'answer' => '<p><strong>Error budget</strong> to dopuszczalny margines błędów (niedostępności lub degradacji), który wynika z Twojego <strong>SLO</strong>. Przykład: jeśli SLO to 99,9% dostępności w miesiącu, to “budżet błędów” wynosi ok. 0,1% czasu w miesiącu. Gdy budżet się kończy, zespół powinien przełączyć priorytety z feature’ów na stabilność.</p>

<h3>Dlaczego to działa w praktyce</h3>
<ul>
  <li><strong>Urealnia dyskusję</strong>: zamiast “mamy za dużo awarii”, masz liczby i trend spalania.</li>
  <li><strong>Chroni roadmapę</strong>: dopóki budżet jest “zdrowy”, można dostarczać szybciej. Gdy się pali, trzeba spłacać dług stabilności.</li>
  <li><strong>Łączy monitoring z decyzjami</strong>: alerty i incydenty mają jasny wpływ na SLO, a nie tylko “czerwone lampki”.</li>
</ul>

<h3>Jak liczyć spalanie (prosto)</h3>
<ul>
  <li>Wybierz <strong>okno</strong> (np. 30 dni) i <strong>definicję awarii</strong> (np. HTTP 5xx lub czas odpowiedzi powyżej progu).</li>
  <li>Zdefiniuj SLO: np. <strong>99,9%</strong> dla strony marketingowej i <strong>99,95%</strong> dla API.</li>
  <li>Monitoruj <strong>burn rate</strong>: czy budżet wypala się wolno (ok), czy “w godzinę” (krytyczne).</li>
</ul>

<h3>Najczęstsze błędy</h3>
<ul>
  <li>Jedno SLO dla wszystkiego: inne cele ma landing, inne checkout, inne webhook.</li>
  <li>Brak wyjątków na wdrożenia: jeśli masz prace serwisowe, ustaw <a href=\"/pl/solutions/maintenance-windows-during-deploys\">maintenance windows</a>.</li>
  <li>Za agresywne alerty: spalają budżet “szumem”. Warto wdrożyć <a href=\"/pl/solutions/reduce-false-alerts\">redukcję fałszywych alarmów</a>.</li>
</ul>

<p>Jeśli chcesz spiąć to w proces, zobacz też <a href=\"/pl/solutions/incident-response-playbook\">playbook reakcji na incydenty</a> oraz FAQ: <a href=\"/pl/faq/what-is-sla-vs-slo\">SLA vs SLO</a>.</p>',
  'meta_title' => 'Czym jest error budget w monitoringu SLO? | TruMonit',
  'meta_description' => 'Error budget (budżet błędów) to margines niedostępności wynikający z SLO. Jak go liczyć, monitorować burn rate i kiedy wstrzymać wdrożenia, żeby nie złamać celów.',
);
