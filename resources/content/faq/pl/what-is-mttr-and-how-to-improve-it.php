<?php

return array (
  'order' => 17,
  'related' => 
  array (
    0 => 'what-is-an-incident-response-playbook',
    1 => 'how-to-build-on-call-escalation-policy',
    2 => 'best-alert-channels-for-uptime-monitoring',
  ),
  'related_solutions' => 
  array (
    0 => 'incident-response-playbook',
    1 => 'uptime-monitoring-for-saas',
  ),
  'question' => 'Czym jest MTTR i jak go poprawić?',
  'answer' => '<p><strong>MTTR</strong> (Mean Time To Recovery/Resolve) to średni czas potrzebny na przywrócenie usługi po incydencie. W praktyce MTTR składa się z kilku etapów: detekcja → potwierdzenie → mitygacja → recovery.</p>

<h3>Jak poprawić MTTR (największe dźwignie)</h3>
<ul>
  <li><strong>Szybsza detekcja</strong>: monitoring o wysokim sygnale (bez szumu), 1‑min checki dla krytycznych ścieżek.</li>
  <li><strong>Lepszy triage</strong>: playbook incydentowy i checklisty dla typowych awarii.</li>
  <li><strong>Mitygacja</strong>: szybki rollback, feature flags, ograniczenie ruchu, izolacja zależności.</li>
  <li><strong>Komunikacja</strong>: status page z rytmem update’ów (support mniej przeszkadza).</li>
</ul>

<h3>Co konkretnie wdrożyć</h3>
<ul>
  <li>Incident owner dla Sev1/Sev2 (jedna osoba dowodzi).</li>
  <li>Eskalacja on‑call (primary + secondary) i jasne kanały alertów.</li>
  <li>Regularne „drille” (symulacje) i aktualizacja playbooków po incydentach.</li>
  <li>Postmortem + follow‑upy (żeby problem nie wrócił).</li>
</ul>

<p>Najważniejsze: MTTR spada, gdy masz powtarzalny proces i monitoring, któremu zespół ufa.</p>',
  'meta_title' => 'Czym jest MTTR i jak go poprawić? | TruMonit',
  'meta_description' => 'MTTR to średni czas przywrócenia usługi po incydencie. Poprawisz go przez lepszą detekcję (monitoring), triage (playbook), mitygację (rollback/flags) i status page.',
);
