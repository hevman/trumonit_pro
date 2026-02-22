<?php

return array (
  'order' => 6,
  'title' => 'Playbook incydentowy (reakcja na awarie)',
  'meta_title' => 'Playbook incydentowy: MTTR, status page, postmortem | TruMonit',
  'meta_description' => 'Zbuduj playbook incydentowy: triage, komunikacja, mitygacja i postmortem. Skróć MTTR, popraw powtarzalność reakcji i uspokój support w trakcie awarii.',
  'summary' => 'Dobrze spisany playbook incydentowy skraca MTTR, porządkuje komunikację i zmniejsza chaos, kiedy wpada alert.',
  'body' => '<p>Playbook reakcji na incydent to system szybkich decyzji. Nie zastępuje kompetencji zespołu, ale <strong>eliminuje chaos</strong> w pierwszych minutach awarii: kto dowodzi, co sprawdzamy i jak komunikujemy wpływ.</p>

<h3>Minimalny playbook (który realnie działa)</h3>
<ul>
  <li><strong>Severity</strong>: poziomy (np. Sev1/Sev2/Sev3) i cele reakcji.</li>
  <li><strong>Incident owner</strong>: jedna osoba dowodzi (reszta wykonuje zadania).</li>
  <li><strong>Triage</strong>: potwierdź impact, izoluj zakres, uruchom mitygację.</li>
  <li><strong>Komunikacja</strong>: kanał wewnętrzny + status page + rytm update’ów.</li>
  <li><strong>Postmortem</strong>: szablon, akcje follow‑up i domykanie długów.</li>
</ul>

<h3>Przebieg incydentu: 4 etapy</h3>
<ol>
  <li><strong>Detect</strong>: alert → potwierdź, czy to realne (retry, multi‑region, porównanie monitorów).</li>
  <li><strong>Stabilize</strong>: szybka mitygacja (rollback, feature flag, ograniczenie ruchu, wyłączenie funkcji).</li>
  <li><strong>Recover</strong>: przywróć usługę i upewnij się, że metryki wracają do normy.</li>
  <li><strong>Learn</strong>: postmortem + akcje prewencyjne (monitoring, testy, limity, runbook).</li>
</ol>

<h3>Co mierzyć (żeby poprawiać proces)</h3>
<ul>
  <li><strong>MTTA</strong> (czas do potwierdzenia) i <strong>MTTR</strong> (czas do przywrócenia).</li>
  <li>Najczęstsze przyczyny incydentów (deploy, zależność 3rd‑party, baza danych, DNS).</li>
  <li>Jeśli masz SLO: burn rate / error budget (czy incydenty „zjadają” budżet jakości).</li>
</ul>

<h3>Praktyczna wskazówka</h3>
<p>Największy zysk daje połączenie: dobry monitoring + status page + ownership. Bez ownera nawet najlepszy monitoring nie skraca MTTR.</p>',
  'problem' => 'Bez playbooka zespół improwizuje pod presją. Reakcja jest wolniejsza, niespójna i psuje komunikację z klientami (a support dostaje lawinę ticketów).',
  'approach' => 
  array (
    0 => 'Zdefiniuj severity, ownership i rolę incident ownera.',
    1 => 'Kieruj incydenty do on‑call i ustaw eskalacje.',
    2 => 'Używaj status page z ustaloną częstotliwością update’ów.',
    3 => 'Rób postmortemy i domykaj follow‑upy (akcje prewencyjne).',
  ),
  'proof' => 
  array (
    0 => 'Niższy MTTR dzięki powtarzalnemu triage i eskalacji.',
    1 => 'Lepsze zaufanie przez konsekwentne update’y na status page.',
    2 => 'Mniej powrotów tych samych incydentów dzięki follow‑upom.',
  ),
  'checklist' => 
  array (
    0 => 'Poziomy severity są zdefiniowane.',
    1 => 'Incident owner jest przypisany dla Sev1/Sev2.',
    2 => 'Polityka eskalacji działa.',
    3 => 'Proces status page jest opisany (cadence + szablon update’u).',
    4 => 'Szablon postmortem i follow‑upy są trackowane i domykane.',
  ),
  'pitfalls' => 
  array (
    0 => 'Brak jednego incident ownera (wszyscy robią wszystko).',
    1 => 'Brak postmortem: problemy wracają.',
    2 => 'Komunikacja bez rytmu (klienci i support dopytują co 5 minut).',
  ),
  'related_faq' => 
  array (
    0 => 'what-is-an-incident-response-playbook',
    1 => 'what-is-mttr-and-how-to-improve-it',
    2 => 'what-should-incident-postmortem-include',
  ),
);
