<?php

return array (
  'order' => 4,
  'title' => 'Status page dla SaaS',
  'meta_title' => 'Status page dla SaaS | TruMonit',
  'meta_description' => 'Buduj zaufanie w incydentach: status page z komponentami, aktualizacjami i historia. Mniej ticketow do supportu podczas awarii.',
  'summary' => 'Status page zamienia incydenty w komunikacje, odciaza support i poprawia zaufanie.',
  'body' => '<p>Status page to narzedzie komunikacji incydentowej: co nie dziala, kogo dotyczy i kiedy bedzie kolejny update.</p>

<h3>Co publikowac</h3>
<ul>
  <li>Status komponentow (API, dashboard, webhooks).</li>
  <li>Aktualizacje incydentu z timestampami.</li>
  <li>Historia incydentow.</li>
</ul>

<h3>Jak odciaza support</h3>
<ul>
  <li>Mniej pytan \"czy to tylko u mnie?\".</li>
  <li>Jedno zrodlo prawdy dla support/sales.</li>
  <li>Transparentnosc w czasie awarii.</li>
</ul>',
  'problem' => 'Bez publicznej status page kazdy incydent tworzy burze ticketow i niespojna komunikacje. Zespol traci czas na powtarzalne odpowiedzi.',
  'approach' => 
  array (
    0 => 'Utworz status page i komponenty zgodne z architektura.',
    1 => 'Publikuj szybko potwierdzenie, potem update w stalych odstepach.',
    2 => 'Podlacz status page do playbookow i procesow.',
  ),
  'proof' => 
  array (
    0 => 'Mniej ticketow w incydencie.',
    1 => 'Wieksze zaufanie przez transparentne update.',
  ),
  'checklist' => 
  array (
    0 => 'Komponenty zdefiniowane i mapowane do monitorow.',
    1 => 'Cadence update ustalony (np. co 15-30 min).',
    2 => 'Historia wlaczona.',
  ),
  'pitfalls' => 
  array (
    0 => 'Publikacja za pozno.',
    1 => 'Brak czasu kolejnego update.',
  ),
  'related_faq' => 
  array (
    0 => 'what-is-a-status-page',
    1 => 'how-to-use-status-page-during-incident',
    2 => 'what-should-incident-postmortem-include',
  ),
);

