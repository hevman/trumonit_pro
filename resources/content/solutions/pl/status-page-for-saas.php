<?php

return array (
  'order' => 4,
  'title' => 'Status page (strona statusu) dla SaaS',
  'meta_title' => 'Status page dla SaaS (strona statusu) | TruMonit',
  'meta_description' => 'Buduj zaufanie w incydentach: status page (strona statusu) z komponentami, aktualizacjami i historią. Mniej ticketów do supportu podczas awarii.',
  'summary' => 'Status page zamienia incydenty w komunikację: redukuje presję na support, porządkuje update’y i poprawia zaufanie klientów.',
  'body' => '<p>Status page (strona statusu) to nie landing marketingowy. To <strong>narzędzie komunikacji incydentowej</strong>: co nie działa, kogo dotyczy, jaki jest wpływ i kiedy będzie kolejny update.</p>

<h3>Co publikować na status page</h3>
<ul>
  <li>Komponenty zgodne z architekturą: API, dashboard, webhooks, logowanie, płatności.</li>
  <li>Aktualizacje incydentu z timestampami: co wiemy, co robimy, co dalej.</li>
  <li>Historia incydentów i planowane prace (maintenance windows).</li>
</ul>

<h3>Cadence aktualizacji (żeby nie robić chaosu)</h3>
<ul>
  <li>Start: szybkie potwierdzenie w 2–5 minut („investigating”).</li>
  <li>W trakcie: update co 15–30 minut (stały rytm, nawet jeśli „brak zmian”).</li>
  <li>Koniec: podsumowanie + co zrobicie, żeby nie wróciło (krótki post-incident note).</li>
</ul>

<h3>Jak status page odciąża support i sprzedaż</h3>
<ul>
  <li>Mniej pytań „czy to tylko u mnie?” i mniej duplikatów zgłoszeń.</li>
  <li>Jedno źródło prawdy dla support/sales/CS.</li>
  <li>Więcej zaufania: transparentność zamiast ciszy.</li>
</ul>

<h3>Dobre praktyki</h3>
<ul>
  <li>Linkuj status page w aplikacji (np. w stopce) i w playbooku incydentowym.</li>
  <li>Nie obiecuj ETA bez danych: lepiej „kolejny update o 12:30”.</li>
  <li>Trzymaj proste komponenty: 5–12 to zwykle optimum.</li>
</ul>

<p>Jeśli chcesz mniej ticketów podczas awarii i lepszą wiarygodność, status page to jeden z najszybszych „upgrade’ów” procesu.</p>',
  'problem' => 'Bez publicznej status page każdy incydent tworzy burzę ticketów i niespójną komunikację. Zespół traci czas na powtarzalne odpowiedzi zamiast naprawiać problem.',
  'approach' => 
  array (
    0 => 'Utwórz status page i komponenty zgodne z architekturą.',
    1 => 'Publikuj szybkie potwierdzenie, potem update’y w stałych odstępach.',
    2 => 'Podepnij status page do playbooków i procesu incydentowego.',
  ),
  'proof' => 
  array (
    0 => 'Mniej ticketów w incydencie i krótsze przerwy w pracy zespołu.',
    1 => 'Większe zaufanie dzięki transparentnym update’om.',
    2 => 'Lepsza komunikacja między zespołem a klientami.',
  ),
  'checklist' => 
  array (
    0 => 'Komponenty są zdefiniowane i mapowane do monitorów.',
    1 => 'Cadence update jest ustalony (np. co 15–30 min).',
    2 => 'Historia incydentów jest włączona.',
    3 => 'Link do status page jest widoczny dla użytkowników.',
  ),
  'pitfalls' => 
  array (
    0 => 'Publikacja za późno (support już „płonie”).',
    1 => 'Brak czasu kolejnego update (chaos i frustracja klientów).',
    2 => 'Zbyt wiele komponentów i nieczytelna struktura.',
  ),
  'related_faq' => 
  array (
    0 => 'what-is-a-status-page',
    1 => 'how-to-use-status-page-during-incident',
    2 => 'what-should-incident-postmortem-include',
  ),
);
