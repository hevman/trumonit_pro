<?php

return array (
  'order' => 30,
  'title' => 'Narzędzie monitoringu stron dla agencji',
  'meta_title' => 'Narzędzie monitoringu stron dla agencji | TruMonit',
  'meta_description' => 'Monitoring stron dla agencji: monitoruj wiele serwisów klientów, routuj alerty per projekt, ogranicz fałszywe alarmy i komunikuj incydenty bez ręcznego chaosu.',
  'summary' => 'Setup monitoringu dla agencji utrzymujących wiele stron klientów: czytelny ownership, mniej szumu i szybsze komunikaty do klienta.',
  'body' => '<p>Agencje potrzebują monitoringu, który działa na wielu projektach jednocześnie i nie miesza odpowiedzialności między klientami. Największym kosztem jest zwykle ręczna koordynacja, a nie sama detekcja awarii.</p><p>Dlatego warto wybierać narzędzie, które wspiera podział per klient, per projekt i per krytyczność. To pozwala ograniczyć szum i pokazać klientom przewidywalny standard reakcji.</p><p>W TruMonit łatwiej utrzymać taki model operacyjny, bo monitoring i obsługa incydentu są spięte w jednym workflow agencyjnym.</p>',
  'problem' => 'Monitoring wielokliencki bez jasnego podziału odpowiedzialności generuje opóźnienia i chaos komunikacyjny.',
  'approach' => 
  array (
    0 => 'Najpierw oznacz ścieżki krytyczne biznesowo.',
    1 => 'Ustaw routing i cooldown per severity.',
    2 => 'Weryfikuj proces regularnymi drillami.',
  ),
  'proof' => 
  array (
    0 => 'Lepszy signal-to-noise w alertach.',
    1 => 'Szybszy triage i eskalacja.',
    2 => 'Czytelniejsza komunikacja do klientów.',
  ),
  'checklist' => 
  array (
    0 => 'Monitory krytyczne mają ownera.',
    1 => 'Polityka alertów jest rozdzielona per severity.',
    2 => 'Rytm status update jest zdefiniowany.',
    3 => 'Postmortem jest planowane po incydencie.',
  ),
  'pitfalls' => 
  array (
    0 => 'Jedna polityka dla wszystkich monitorów.',
    1 => 'Spóźniona eskalacja po wielu błędach.',
    2 => 'Brak drillów przed incydentem produkcyjnym.',
  ),
  'related_faq' => 
  array (
    0 => 'what-is-uptime-monitoring',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'what-is-a-status-page',
    3 => 'how-do-i-monitor-api-endpoints',
  ),
);
