<?php

return array (
  'order' => 20,
  'title' => 'Szablon postmortem incydentu (timeline, wpływ, akcje)',
  'meta_title' => 'Szablon postmortem incydentu | TruMonit',
  'meta_description' => 'Postmortem bez lania wody: timeline, wpływ, root cause, czynniki i konkretne akcje z ownerem/terminem. Jak robić to szybko i sensownie.',
  'summary' => 'Dobry postmortem zamienia incydent w konkretne zmiany. Najważniejsze są akcje naprawcze z ownerem i terminem.',
  'body' => '<p>Postmortem ma sens tylko wtedy, gdy kończy się <strong>konkretnymi zmianami</strong>. Jeśli dokument kończy się na “było źle”, to za miesiąc wydarzy się to samo.</p>

<h3>Minimalny szablon</h3>
<ul>
  <li><strong>Podsumowanie</strong>: co się stało i jaki był wpływ.</li>
  <li><strong>Timeline</strong>: wykrycie, eskalacja, mitigacja, rozwiązanie, komunikacja.</li>
  <li><strong>Root cause</strong> i czynniki towarzyszące.</li>
  <li><strong>Akcje naprawcze</strong>: owner, termin, definicja done.</li>
</ul>

<h3>Akcje, które realnie zmniejszają ryzyko</h3>
<ul>
  <li>Nowe monitory/checki (np. synthetic na krytyczny flow).</li>
  <li>Lepszy routing alertów i priorytety.</li>
  <li>Maintenance windows na deploye, żeby ograniczyć szum.</li>
</ul>

<p>Powiązane: <a href="/pl/solutions/incident-response-playbook">playbook incydentowy</a>, <a href="/pl/solutions/status-page-for-saas">status page</a>. FAQ: <a href="/pl/faq/what-should-incident-postmortem-include">co powinno być w postmortem</a>.</p>',
  'problem' => 'Bez postmortem incydenty powtarzają się w kółko. Zespół gasi pożar, wraca do pracy i za chwilę ma kolejny pożar. Szablon i proces robią różnicę.',
  'approach' => array (
    0 => 'Zapisz timeline i wpływ zaraz po incydencie (później pamięć zawodzi).',
    1 => 'Opisz root cause i 2–3 czynniki, które pogorszyły sytuację.',
    2 => 'Zdefiniuj akcje naprawcze z ownerem i terminem.',
    3 => 'Dodaj zmiany w monitoringu i alertingu jako standardowy punkt.',
  ),
  'proof' => array (
    0 => 'Mniej powtarzalnych awarii i szybsze odzyskiwanie.',
    1 => 'Łatwiej rozliczać poprawki (owner + termin).',
    2 => 'Lepsza komunikacja do klientów i wewnątrz zespołu.',
  ),
  'checklist' => array (
    0 => 'Timeline i wpływ są kompletne.',
    1 => 'Akcje naprawcze mają ownera i termin.',
    2 => 'Co najmniej jedna akcja dotyczy monitoringu/alertingu.',
  ),
  'pitfalls' => array (
    0 => 'Postmortem bez akcji (ładny dokument, zero zmian).',
    1 => 'Brak ownera: “ktoś to zrobi”.',
    2 => 'Zbyt ogólne przyczyny bez konkretów technicznych.',
  ),
  'related_faq' => array (
    0 => 'what-should-incident-postmortem-include',
    1 => 'what-is-an-incident-response-playbook',
    2 => 'how-to-use-status-page-during-incident',
  ),
);