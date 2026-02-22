<?php

return array(
    'order' => 11,
    'title' => 'Monitoring treści: keyword/regex (defacement, błędy, UI)',
    'meta_title' => 'Monitoring treści: keyword i regex | TruMonit',
    'meta_description' => 'Wykrywaj defacement, błędne komunikaty i brak kluczowych elementów UI (mimo HTTP 200) używając reguł allow/deny keyword i regex.',
    'summary' => 'Wykrywaj “ciche awarie”, gdy strona zwraca HTTP 200, ale treść jest błędna: error banner, defacement, brak markera UI.',
    'body' => '<p><strong>HTTP 200 nie oznacza “działa”.</strong> Strona może zwracać 200 i jednocześnie wyświetlać błąd, tryb maintenance albo zostać podmieniona (defacement).</p>

<h3>Co warto sprawdzać</h3>
<ul>
  <li><strong>Allow marker</strong>: stabilny token, który musi istnieć (np. <code>data-testid=\"dashboard\"</code>).</li>
  <li><strong>Deny keywords</strong>: frazy, które nie mogą się pojawić (np. <code>hacked by</code>, <code>SQLSTATE</code>, <code>Fatal error</code>).</li>
  <li><strong>Regex</strong>: wzorce błędów (stacktrace, error codes, pętle redirectów).</li>
</ul>

<h3>Jak ograniczyć flaki</h3>
<ul>
  <li>Używaj stabilnych markerów (<code>data-testid</code>), nie klas CSS.</li>
  <li>Waliduj 1–2 krytyczne elementy, nie całą stronę.</li>
  <li>Stosuj okna serwisowe podczas deployów.</li>
</ul>

<p>Powiązane: <a href=\"/pl/solutions/synthetic-monitoring-login-flow\">Monitoring user journey (Playwright)</a> i <a href=\"/pl/solutions/reduce-false-alerts\">redukcja fałszywych alertów</a>.</p>',
    'problem' => 'Uptime checks nie wykrywają sytuacji, gdy serwer odpowiada, ale użytkownik widzi błąd albo nie może wykonać akcji.',
    'approach' => array(
        'Wybierz stabilny allow marker dla krytycznych stron.',
        'Dodaj deny keywords/regex dla typowych awarii.',
        'Dostrój progi i używaj okien serwisowych przy wdrożeniach.',
    ),
    'proof' => array(
        'Wykrywa problemy widoczne dla użytkownika wcześniej niż zgłoszenia.',
        'Ułatwia diagnozę (“co jest nie tak z treścią”).',
        'Tani guardrail dla krytycznych stron.',
    ),
    'checklist' => array(
        'Allow marker jest stabilny i unikalny.',
        'Deny keywords obejmują typowe error bannery i stacktrace.',
        'Okna serwisowe są używane przy planowanych zmianach.',
    ),
    'pitfalls' => array(
        'Brittle selektory/keywordy zależne od layoutu.',
        'Walidacja zbyt wielu elementów (flaki).',
        'Brak okna serwisowego (fałszywe alerty przy deployu).',
    ),
    'related_faq' => array(
        'how-to-monitor-keyword-on-page',
        'network-error-vs-application-error-monitoring',
        'synthetic-checks-vs-http-checks',
    ),
);

