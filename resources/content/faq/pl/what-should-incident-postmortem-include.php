<?php

return array (
  'order' => 26,
  'related' => 
  array (
    0 => 'what-is-an-incident-response-playbook',
    1 => 'what-is-mttr-and-how-to-improve-it',
    2 => 'how-to-use-status-page-during-incident',
  ),
  'related_solutions' => 
  array (
    0 => 'incident-response-playbook',
    1 => 'status-page-for-saas',
    2 => 'alert-routing-slack-teams-webhooks',
    3 => 'incident-postmortem-template',
  ),
  'question' => 'Co powinno być w postmortem incydentu?',
  'answer' => '<p><strong>Postmortem</strong> (retrospektywa incydentu) ma jeden cel: wyciągnąć wnioski, które realnie zmniejszą ryzyko powtórki. To nie jest dokument “kto zawinił”, tylko “co w systemie i procesie zadziałało źle”.</p>

<h3>1) Fakty: timeline i wpływ</h3>
<ul>
  <li><strong>Timeline</strong>: wykrycie, eskalacja, mitigacja, rozwiązanie, komunikacja.</li>
  <li><strong>Wpływ</strong>: ilu użytkowników/klientów, ile czasu, jakie funkcje, utracone zamówienia, SLA/SLO.</li>
  <li><strong>Detekcja</strong>: jak dowiedzieliście się o awarii (monitoring vs klient), jak szybko.</li>
</ul>

<h3>2) Przyczyna i czynniki towarzyszące</h3>
<ul>
  <li><strong>Root cause</strong>: jednozdaniowy opis techniczny (np. błąd w migracji, limit w zewnętrznym API, degradacja bazy).</li>
  <li><strong>Contributing factors</strong>: co pogorszyło sytuację (brak rate limitów, brak retry, zły alerting, brak maintenance window).</li>
  <li><strong>Co zadziałało</strong>: rzeczy, które uratowały sytuację (feature flag, rollback, runbook).</li>
</ul>

<h3>3) Akcje naprawcze (to jest najważniejsze)</h3>
<ul>
  <li>Każda akcja ma <strong>owner</strong>, <strong>termin</strong> i <strong>kryterium done</strong>.</li>
  <li>Podziel na: <strong>quick wins</strong> (1–3 dni) i <strong>systemowe</strong> (2–6 tygodni).</li>
  <li>Dodaj akcje pod monitoring: nowe checki, progi, redukcja fałszywych alarmów, eskalacje.</li>
</ul>

<h3>4) Komunikacja do klientów</h3>
<p>Warto mieć stały schemat: co się stało, jaki był wpływ, co zrobiliście, co zmieniacie. W praktyce pomaga <a href=\"/pl/solutions/status-page-for-saas\">status page</a> i jasne kanały alertów.</p>

<p>Powiązane: <a href=\"/pl/solutions/incident-response-playbook\">playbook reakcji na incydenty</a>, <a href=\"/pl/solutions/alert-routing-slack-teams-webhooks\">routing alertów (Slack/Teams/webhook)</a> oraz FAQ: <a href=\"/pl/faq/what-is-mttr-and-how-to-improve-it\">MTTR</a>.</p>',
  'meta_title' => 'Co powinno być w postmortem incydentu? | TruMonit',
  'meta_description' => 'Postmortem incydentu: timeline, wpływ na klienta, root cause i najważniejsze: konkretne akcje naprawcze z ownerem i terminem. Przykładowy szablon i częste błędy.',
);
