<?php

return array(
    'order' => 12,
    'title' => 'Monitoring zależności zewnętrznych (CDN, płatności, API)',
    'meta_title' => 'Monitoring zależności zewnętrznych | TruMonit',
    'meta_description' => 'Monitoruj zewnętrzne zależności osobno, żeby szybciej diagnozować problemy, ograniczać fałszywe alerty i lepiej komunikować incydenty na status page.',
    'summary' => 'Monitoruj krytyczne zależności (CDN, bramki płatności, upstream API) jako osobne komponenty, aby szybciej odróżnić awarię dostawcy od awarii Twojej usługi.',
    'body' => '<p>W nowoczesnych systemach awarie często zaczynają się od zależności: bramka płatności, provider logowania, CDN, email delivery, upstream API. Bez osobnych monitorów każda awaria dostawcy wygląda jak “Twoja strona nie działa”.</p>

<h3>Co monitorować</h3>
<ul>
  <li>Krytyczne upstream API (auth, payments, search).</li>
  <li>Dostępność edge/CDN i DNS.</li>
  <li>Skrypty zewnętrzne psujące UI (tu pomaga <a href=\"/pl/solutions/synthetic-monitoring-login-flow\">user journey monitoring</a>).</li>
</ul>

<h3>Jak używać tego operacyjnie</h3>
<ul>
  <li>Dodaj osobne monitory i oznacz je jako komponenty dostawców.</li>
  <li>Routing alertów ustaw inaczej (nie wszystko musi być Sev1).</li>
  <li>Komunikuj scope przez <a href=\"/pl/solutions/status-page-for-saas\">status page</a>.</li>
</ul>

<p>Powiązane: <a href=\"/pl/solutions/reduce-false-alerts\">redukcja fałszywych alertów</a> i <a href=\"/pl/solutions/incident-response-playbook\">playbook incydentowy</a>.</p>',
    'problem' => 'Bez monitoringu zależności tracisz czas na złą diagnozę i produkujesz alerty, których zespół nie może szybko “naprawić”.',
    'approach' => array(
        'Wypisz top 5 zależności, które psują user journey.',
        'Dodaj osobne monitory i routing alertów po severity.',
        'Użyj status page komponentów do komunikacji.',
    ),
    'proof' => array(
        'Szybszy kierunek root-cause (dostawca vs Twoja usługa).',
        'Czytelniejsze komunikaty i mniej eskalacji “na ślepo”.',
        'Lepsze SLO i error budgeting.',
    ),
    'checklist' => array(
        'Zależności są monitorowane i opisane.',
        'Routing alertów jest ustawiony per zależność i severity.',
        'Status page ma komponenty mapujące zależności.',
    ),
    'pitfalls' => array(
        'Traktowanie każdego blipa dostawcy jako Sev1.',
        'Brak labeli (nie wiadomo co faktycznie padło).',
        'Brak planu komunikacji dla incydentów dostawców.',
    ),
    'related_faq' => array(
        'how-to-monitor-third-party-dependencies',
        'what-causes-website-downtime',
        'what-is-a-status-page',
    ),
);

