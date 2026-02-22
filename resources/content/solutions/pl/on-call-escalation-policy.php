<?php

return array (
  'order' => 18,
  'title' => 'Polityka eskalacji on-call dla małego zespołu',
  'meta_title' => 'Polityka eskalacji on-call | TruMonit',
  'meta_description' => 'Jak zbudować on-call bez chaosu: P0/P1/P2, primary/secondary, timery eskalacji i routing alertów. Minimalny runbook, który działa.',
  'summary' => 'Eskalacja nie może być improwizacją. Ustal priorytety, czasy reakcji, ownership i kanały, żeby incydenty nie “wisialy” bez opiekuna.',
  'body' => '<p>Najczęstszy powód długiego MTTR w małych zespołach to brak zasad: alert wpada na Slack, wszyscy go widzą i nikt nie bierze odpowiedzialności. Polityka eskalacji ma sprawić, że <strong>każdy incydent ma właściciela</strong> i nie “wisi” w próżni.</p>

<h3>Minimalny model, który działa</h3>
<ul>
  <li><strong>P0</strong>: biznes stoi (login, płatności, core API) – reakcja 5–10 min.</li>
  <li><strong>P1</strong>: poważna degradacja – reakcja 15–30 min.</li>
  <li><strong>P2</strong>: ryzyko i ostrzeżenia – w godzinach pracy.</li>
</ul>

<h3>Role</h3>
<ul>
  <li><strong>Primary</strong>: bierze incydent i prowadzi mitigację.</li>
  <li><strong>Secondary</strong>: wchodzi po czasie albo gdy potrzeba kompetencji.</li>
  <li><strong>Owner</strong>: decyzje biznesowe i komunikacja (rollback, status page).</li>
</ul>

<h3>Timery eskalacji</h3>
<ul>
  <li>P0: eskaluj po 10–15 min braku reakcji.</li>
  <li>P1: eskaluj po 30–60 min.</li>
  <li>P2: często wystarczy przypomnienie i ticket.</li>
</ul>

<p>Powiązane: <a href="/pl/solutions/alert-routing-slack-teams-webhooks">routing alertów</a>, <a href="/pl/solutions/incident-response-playbook">playbook incydentowy</a> oraz FAQ: <a href="/pl/faq/how-to-build-on-call-escalation-policy">jak zbudować politykę eskalacji</a>.</p>',
  'problem' => 'Bez jasnych zasad eskalacji alerty trafiają do wielu osób, ale nie mają właściciela. Efekt to opóźniona reakcja i niepotrzebne koszty downtime.',
  'approach' => array (
    0 => 'Zdefiniuj P0/P1/P2 i czasy reakcji.',
    1 => 'Przypisz primary/secondary per obszar i dodaj timery eskalacji.',
    2 => 'Ustal kanały per priorytet (push/Slack/e-mail) i routing alertów.',
    3 => 'Dodaj minimalny runbook i linki do dashboardów.',
  ),
  'proof' => array (
    0 => 'Krótszy czas reakcji i mniej incydentów “bez właściciela”.',
    1 => 'Mniej szumu dzięki priorytetom i routingowi.',
    2 => 'Lepsza komunikacja do klientów w krytycznych sytuacjach.',
  ),
  'checklist' => array (
    0 => 'Masz P0/P1/P2 i timery eskalacji.',
    1 => 'Primary i secondary są przypisani dla krytycznych usług.',
    2 => 'Routing alertów jest spójny z priorytetami.',
    3 => 'Runbook zawiera “pierwsze 5 minut”.',
  ),
  'pitfalls' => array (
    0 => 'Jedna “ważność” dla wszystkich alertów (spam i ignorowanie).',
    1 => 'Brak timerów: czekasz, aż ktoś “zauważy”.',
    2 => 'Brak ownera i brak procedury komunikacji.',
  ),
  'related_faq' => array (
    0 => 'how-to-build-on-call-escalation-policy',
    1 => 'best-alert-channels-for-uptime-monitoring',
    2 => 'what-is-mttr-and-how-to-improve-it',
  ),
);