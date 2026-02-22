<?php

return array (
  'order' => 18,
  'related' => 
  array (
    0 => 'best-alert-channels-for-uptime-monitoring',
    1 => 'what-is-an-incident-response-playbook',
    2 => 'what-is-mttr-and-how-to-improve-it',
  ),
  'related_solutions' => 
  array (
    0 => 'alert-routing-slack-teams-webhooks',
    1 => 'incident-response-playbook',
    2 => 'reduce-false-alerts',
  ),
  'question' => 'Jak zbudować politykę eskalacji on-call?',
  'answer' => '<p>Dobra polityka eskalacji on-call ma ograniczyć chaos: każdy alert ma trafić do właściwej osoby, w rozsądnym czasie, z jasnym “co dalej”. Najczęściej problemem nie jest brak alertów, tylko brak <strong>reguł</strong>.</p>

<h3>1) Zdefiniuj poziomy krytyczności</h3>
<ul>
  <li><strong>P0</strong>: pełna niedostępność / krytyczna funkcja (np. checkout, logowanie) w dół.</li>
  <li><strong>P1</strong>: częściowa degradacja / rosnące błędy.</li>
  <li><strong>P2</strong>: ryzyko (np. zbliżające się wygaśnięcie SSL, duży wzrost latency).</li>
</ul>

<h3>2) Ustal czasy reakcji i timery eskalacji</h3>
<ul>
  <li>P0: pierwsza reakcja 5–10 min, eskalacja po 10–15 min.</li>
  <li>P1: reakcja 15–30 min, eskalacja po 30–60 min.</li>
  <li>P2: najczęściej “w godzinach pracy”.</li>
</ul>

<h3>3) Wprowadź role: primary, secondary, manager</h3>
<ul>
  <li><strong>Primary</strong>: bierze incydent i prowadzi działania.</li>
  <li><strong>Secondary</strong>: wspiera, gdy primary nie odpowiada lub potrzeba kompetencji.</li>
  <li><strong>Manager/owner</strong>: eskalacja “biznesowa” (np. decyzja o rollbacku, komunikacja do klientów).</li>
</ul>

<h3>4) Standard: gdzie idą alerty</h3>
<ul>
  <li>P0: kanał natychmiastowy (telefon/push) + Slack/Teams.</li>
  <li>P1/P2: Slack/Teams + e-mail (zależnie od zespołu).</li>
  <li>W praktyce pomaga <a href=\"/pl/solutions/alert-routing-slack-teams-webhooks\">routing alertów</a>, żeby nie “zalać” ludzi powiadomieniami.</li>
</ul>

<h3>5) Minimalny runbook</h3>
<ul>
  <li>Link do dashboardu, status page, rollback, feature flags, kontakt do dostawców.</li>
  <li>Jedna “checklista pierwszych 5 minut”.</li>
  <li>Proces postmortem i akcje naprawcze.</li>
</ul>

<p>Jeśli widzisz dużo szumu, najpierw popraw jakość sygnału: <a href=\"/pl/solutions/reduce-false-alerts\">redukcja fałszywych alarmów</a>. A jeśli chcesz spiąć cały proces, zobacz <a href=\"/pl/solutions/incident-response-playbook\">playbook reakcji na incydenty</a>.</p>',
  'meta_title' => 'Jak zbudować politykę eskalacji on-call? | TruMonit',
  'meta_description' => 'Praktyczna polityka eskalacji on-call: poziomy P0/P1/P2, czasy reakcji, primary/secondary, kanały alertów i minimalny runbook. Jak ograniczyć szum i skrócić MTTR.',
);
