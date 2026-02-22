<?php

return array (
  'order' => 14,
  'related' => 
  array (
    0 => 'how-do-i-monitor-api-endpoints',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'what-causes-website-downtime',
  ),
  'related_solutions' => 
  array (
  ),
  'question' => 'Jak monitorować cron jobs i zadania cykliczne?',
  'answer' => '<p>Najprościej przez heartbeat: po każdym poprawnym wykonaniu job wysyla sygnał. Brak sygnału w czasie oznacza incydent.</p><p>Warto monitorować nie tylko brak wykonania, ale też zbyt długi czas trwania zadania.</p>',
  'meta_title' => 'Jak monitorować cron jobs i zadania cykliczne? | TruMonit',
  'meta_description' => 'Najprościej przez heartbeat: po każdym poprawnym wykonaniu job wysyla sygnał. Brak sygnału w czasie oznacza incydent.Warto monitorować nie tylko brak wykonania,...',
);
