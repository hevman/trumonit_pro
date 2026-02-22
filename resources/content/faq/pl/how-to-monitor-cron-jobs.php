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
  'question' => 'Jak monitorowac cron jobs i zadania cykliczne?',
  'answer' => '<p>Najprosciej przez heartbeat: po kazdym poprawnym wykonaniu job wysyla sygnal. Brak sygnalu w czasie oznacza incydent.</p><p>Warto monitorowac nie tylko brak wykonania, ale tez zbyt dlugi czas trwania zadania.</p>',
  'meta_title' => 'Jak monitorowac cron jobs i zadania cykliczne? | TruMonit',
  'meta_description' => 'Najprosciej przez heartbeat: po kazdym poprawnym wykonaniu job wysyla sygnal. Brak sygnalu w czasie oznacza incydent.Warto monitorowac nie tylko brak wykonania,...',
);
