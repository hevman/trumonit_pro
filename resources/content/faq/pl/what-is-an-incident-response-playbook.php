<?php

return array (
  'order' => 9,
  'related' => 
  array (
    0 => 'how-do-i-reduce-false-alerts',
    1 => 'what-is-a-status-page',
    2 => 'what-is-sla-vs-slo',
  ),
  'related_solutions' => 
  array (
    0 => 'incident-response-playbook',
    1 => 'status-page-for-saas',
  ),
  'question' => 'Czym jest playbook incydentowy (reakcja na incydent)?',
  'answer' => '<p><strong>Playbook incydentowy</strong> to spis ról i kroków, które wykonujesz po alercie: triage, komunikacja, mitygacja i postmortem. Jego zadanie to skrócić MTTR i ujednolicić reakcję między zmianami/zespołami.</p>

<h3>Co powinien zawierać playbook</h3>
<ul>
  <li>Poziomy severity (Sev1/Sev2/Sev3) i cele reakcji.</li>
  <li>Rola <strong>incident owner</strong>: jedna osoba dowodzi.</li>
  <li>Triage: potwierdzenie wpływu, zakres, hipotezy, pierwsza mitygacja.</li>
  <li>Status page: szablon update’u i rytm (np. co 15–30 min).</li>
  <li>Postmortem: szablon + lista akcji follow‑up i domykanie.</li>
</ul>

<h3>Dlaczego to działa</h3>
<ul>
  <li>Mniej chaosu w pierwszych minutach (najdroższe minuty incydentu).</li>
  <li>Szybsza eskalacja do ownera i krótszy MTTR.</li>
  <li>Lepsza komunikacja z klientami (mniej ticketów).</li>
</ul>

<p>Playbook nie musi być długi. Ważne, żeby był używany i aktualizowany po incydentach.</p>',
  'meta_title' => 'Czym jest playbook incydentowy (reakcja na incydent)? | TruMonit',
  'meta_description' => 'Playbook incydentowy: role, triage, komunikacja (status page), mitygacja i postmortem. Skraca MTTR i zwiększa powtarzalność reakcji zespołu.',
);
