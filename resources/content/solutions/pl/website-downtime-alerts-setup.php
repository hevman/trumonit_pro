<?php

return array (
  'order' => 33,
  'title' => 'Alerty awarii strony, które naprawdę działają',
  'meta_title' => 'Alerty awarii strony, które naprawdę działają | TruMonit',
  'meta_description' => 'Jak ustawić alerty o awarii strony: ponowienia, kolejne błędy, kierowanie alertów, wygaszanie i odpowiedzialność, żeby alerty były szybkie, ale bez szumu.',
  'summary' => 'Skonfiguruj alerty o awarii strony tak, żeby łapały realne problemy szybko, ale nie spamowały zespołu na każdym chwilowym błędzie.',
  'body' => '<p>Skuteczne alerty awarii strony to kompromis między szybkością a jakością sygnału. Zbyt agresywne reguły powodują szum, a zbyt łagodne opóźniają reakcję na realny problem.</p><p>Najlepszy setup zaczyna się od prostych zasad: retry, liczba kolejnych błędów, cooldown i różne progi dla URL-i krytycznych oraz pomocniczych. Potem dopiero warto rozszerzać konfigurację.</p><p>TruMonit umożliwia takie etapowe podejście, dzięki czemu alerty stają się wiarygodne już na starcie, bez tygodni strojenia.</p>',
  'problem' => 'Źle ustawione progi alertów powodują albo ciągły szum, albo zbyt późne wykrywanie realnych awarii.',
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
