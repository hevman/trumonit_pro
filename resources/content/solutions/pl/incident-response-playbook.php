<?php

return array (
  'order' => 6,
  'title' => 'Playbook reakcji na incydent',
  'meta_title' => 'Playbook reakcji na incydent | TruMonit',
  'meta_description' => 'Zbuduj playbook incydentowy: triage, komunikacja, mitygacja i postmortem. Popraw MTTR i powtarzalnosc reakcji.',
  'summary' => 'Powtarzalny proces reakcji na incydenty skraca MTTR i poprawia komunikacje z klientami.',
  'body' => '<p>Playbook to system szybkich decyzji. Zapobiega chaosowi, gdy wpada alert.</p>

<h3>Minimalny playbook</h3>
<ul>
  <li>Poziomy severity i cele reakcji.</li>
  <li>Triage: potwierdz impact, izoluj, mityguj.</li>
  <li>Komunikacja: kanal wewnetrzny + cadence status page.</li>
  <li>Szablon postmortem i tracking akcji.</li>
</ul>

<h3>Co mierzyc</h3>
<ul>
  <li>MTTA i MTTR.</li>
  <li>Najczestsze przyczyny incydentow.</li>
  <li>Burn rate error budget (jesli masz SLO).</li>
</ul>',
  'problem' => 'Bez playbooka zespol improwizuje pod presja. Reakcja jest wolniejsza, niespojna i psuje komunikacje z klientami.',
  'approach' => 
  array (
    0 => 'Zdefiniuj severity i ownership.',
    1 => 'Kieruj incydenty do on-call i ustaw eskalacje.',
    2 => 'Uzywaj status page z ustalona czestotliwoscia update.',
    3 => 'Rob postmortemy i domykaj follow-upy.',
  ),
  'proof' => 
  array (
    0 => 'Nizszy MTTR dzieki powtarzalnemu triage i eskalacji.',
    1 => 'Lepsze zaufanie przez konsekwentne update.',
  ),
  'checklist' => 
  array (
    0 => 'Poziomy severity sa zdefiniowane.',
    1 => 'Polityka eskalacji dziala.',
    2 => 'Proces status page jest opisany.',
    3 => 'Szablon postmortem i follow-upy sa trackowane.',
  ),
  'pitfalls' => 
  array (
    0 => 'Brak single incident ownera.',
    1 => 'Brak postmortem - problemy wracaja.',
  ),
  'related_faq' => 
  array (
    0 => 'what-is-an-incident-response-playbook',
    1 => 'what-is-mttr-and-how-to-improve-it',
    2 => 'what-should-incident-postmortem-include',
  ),
);

