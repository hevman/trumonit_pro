<?php

return array(
    'order' => 9,
    'title' => 'Routing alertów: Slack, Teams i webhooki (bez spamu)',
    'meta_title' => 'Routing alertów: Slack, Teams i webhooki | TruMonit',
    'meta_description' => 'Ustaw routing alertów po severity, deduplikację i cooldown. Skonfiguruj Slack/Teams/webhook tak, aby właściwe osoby dostawały właściwy sygnał.',
    'summary' => 'Routing alertów po severity i kanałach (email, Slack, Teams, Discord, webhook) zmniejsza szum bez ryzyka, że przegapisz realny incydent.',
    'body' => '<p>Najczęstszy powód, dla którego monitoring nie działa biznesowo, to <strong>zmęczenie alertami</strong>. Gdy wszystko “pinguje”, zespół przestaje reagować.</p>

<h3>Jak wygląda dobry routing alertów</h3>
<ul>
  <li><strong>Severity najpierw</strong>: co jest Sev1, a co może poczekać.</li>
  <li><strong>Kanał na severity</strong>: Slack/Teams do działań operacyjnych, email do podsumowań, webhook do automatyzacji.</li>
  <li><strong>Deduplikacja + cooldown</strong>: jeden incydent nie powinien generować 50 powiadomień.</li>
</ul>

<h3>Praktyczne kroki</h3>
<ol>
  <li>Zdefiniuj skale severity i ownerów (kto reaguje).</li>
  <li>Skieruj Sev1 do szybkich kanałów (Slack/Teams), Sev2 do Slack+email, Sev3 tylko email.</li>
  <li>Zrób drill incydentu i dostrój progi, żeby wyciąć fałszywe alarmy.</li>
</ol>

<p>Zobacz też: <a href=\"/pl/solutions/reduce-false-alerts\">Jak ograniczyć fałszywe alerty</a> oraz <a href=\"/pl/solutions/incident-response-playbook\">Playbook reakcji na incydent</a>.</p>',
    'problem' => 'Bez routingu i severity monitoring staje się spamem. To obniża reakcję zespołu i zwiększa ryzyko przeoczenia incydentu.',
    'approach' => array(
        'Zdefiniuj severity i ownership (kto odpowiada za co).',
        'Skonfiguruj kanały (Slack/Teams/webhook) i minimalną severity per kanał.',
        'Kalibruj progi i dodaj cooldown/deduplikację, aby zmniejszyć szum.',
    ),
    'proof' => array(
        'Mniej alertów, większa reakcja zespołu.',
        'Szybsza diagnoza, bo właściwy zespół widzi incydent pierwszy.',
        'Lepsza higiena operacyjna i czytelniejsza historia incydentów.',
    ),
    'checklist' => array(
        'Severity jest spisana i znana zespołowi.',
        'Kanały (Slack/Teams/webhook) są skonfigurowane i przetestowane.',
        'Cooldown/deduplikacja ogranicza spam powiadomień.',
        'Istnieje runbook dla Sev1.',
    ),
    'pitfalls' => array(
        'Wysyłanie wszystkiego do jednego kanału.',
        'Brak jasnego ownera dla Sev1.',
        'Brak testów (webhooki źle skonfigurowane, powiadomienia znikają).',
    ),
    'related_faq' => array(
        'best-alert-channels-for-uptime-monitoring',
        'how-to-build-on-call-escalation-policy',
        'how-do-i-reduce-false-alerts',
        'what-is-mttr-and-how-to-improve-it',
    ),
);

