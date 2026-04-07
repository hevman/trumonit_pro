<?php

return array (
  'order' => 27,
  'title' => 'Strona statusu dla agencji i wielu projektów (mniej wsparcia)',
  'meta_title' => 'Strona statusu dla agencji | TruMonit',
  'meta_description' => 'Agencje tracą czas na pytania „czy działa?”. Strona statusu dla projektów i komunikaty incydentowe redukują obciążenie wsparcia i budują zaufanie u klientów.',
  'summary' => 'Strona statusu w agencji to nie bajer. To najszybszy sposób, żeby ograniczyć pingowanie i tłumaczenie incydentów w kółko.',
  'body' => '<p>W agencji problemem nie jest tylko downtime. Problemem jest <strong>czas</strong>: klient pisze “czy coś nie działa?”, ktoś sprawdza ręcznie, potem kolejny klient pyta to samo. Status page i jasna komunikacja potrafią odjąć godziny supportu.</p>

<h3>Co powinno być na status page</h3>
<ul>
  <li>Lista usług/projektów i ich aktualny status.</li>
  <li>Historia incydentów (co się stało i kiedy).</li>
  <li>Krótki opis wpływu (co nie działało).</li>
  <li>Link do kontaktu, gdy problem jest po stronie klienta (np. DNS, hosting).</li>
</ul>

<h3>Jak to wdrożyć w agencji</h3>
<ul>
  <li>Ustal, które monitory są publiczne, a które tylko dla zespołu.</li>
  <li>W trakcie incydentu publikuj komunikat i aktualizacje.</li>
  <li>Po incydencie: krótkie podsumowanie (postmortem) i działania.</li>
</ul>

<p>Powiązane: <a href="/pl/solutions/status-page-for-saas">status page dla SaaS</a> oraz <a href="/pl/solutions/incident-postmortem-template">szablon postmortem</a>.</p>',
  'problem' => 'Bez status page agencja traci czas na powtarzanie tych samych informacji wielu klientom. W kryzysie brak jednego źródła prawdy zwiększa chaos i frustrację.',
  'approach' => array (
    0 => 'Zbierz monitory w projekty i zdecyduj, co jest publiczne.',
    1 => 'Ustal szablon komunikatu na incydent i aktualizacje co 30–60 min.',
    2 => 'Zintegruj alerty z kanałami zespołu, a klientom dawaj link do status page.',
    3 => 'Po incydencie spisz krótkie podsumowanie i akcje.',
  ),
  'proof' => array (
    0 => 'Mniej zgłoszeń typu “czy działa?” i mniej ręcznego sprawdzania.',
    1 => 'Większe zaufanie klientów dzięki transparentności.',
    2 => 'Lepszy workflow incydentowy w zespole.',
  ),
  'checklist' => array (
    0 => 'Status page ma listę usług i historię incydentów.',
    1 => 'Masz gotowy szablon komunikacji w trakcie incydentu.',
    2 => 'Alerty i ownership są dopięte.',
  ),
  'pitfalls' => array (
    0 => 'Status page bez aktualizacji (traci wiarygodność).',
    1 => 'Zbyt dużo danych wrażliwych na stronie publicznej.',
    2 => 'Brak procesu: kto publikuje, kiedy i jak.',
  ),
  'related_faq' => array (
    0 => 'what-is-a-status-page',
    1 => 'how-to-use-status-page-during-incident',
    2 => 'what-should-incident-postmortem-include',
  ),
);