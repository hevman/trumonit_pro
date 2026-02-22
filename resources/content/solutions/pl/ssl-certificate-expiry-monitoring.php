<?php

return array (
  'order' => 3,
  'title' => 'Monitoring wygasania certyfikatu SSL',
  'meta_title' => 'Monitoring wygasania SSL | TruMonit',
  'meta_description' => 'Zapobiegaj latwym awariom: monitoruj wygasanie TLS/SSL z alertami 30/14/7 dni i eskalacja do ownera.',
  'summary' => 'Wykryj wygasanie SSL zanim popsuje logowanie, API i zaufanie uzytkownikow. Proste i bardzo oplacalne.',
  'body' => '<p>Wygasanie SSL to jeden z najbardziej oplacalnych monitorow: chroni przed awaria, ktora jest w 100% do unikniecia.</p>

<h3>Okna alertow</h3>
<ul>
  <li>30 dni: wczesne ostrzezenie</li>
  <li>14 dni: trzeba dzialac</li>
  <li>7 dni: eskalacja</li>
</ul>

<h3>Co uwzglednic</h3>
<ul>
  <li>Date wygasniecia</li>
  <li>Zakres hostow (SAN)</li>
  <li>Owner: kto odpowiada za odnowienie</li>
</ul>',
  'problem' => 'Wygasly certyfikat powoduje ostrzezenia przegladarki, bledy API i problemy z integracjami. Zespoly czesto dowiaduja sie o tym od klientow.',
  'approach' => 
  array (
    0 => 'Dodaj checki SSL dla domen produkcyjnych i kluczowych subdomen.',
    1 => 'Alertuj 30/14/7 dni przed wygasnieciem.',
    2 => 'Eskaluj do ownera, jesli 14 dni minelo bez reakcji.',
  ),
  'proof' => 
  array (
    0 => 'Brak niespodzianek z SSL.',
    1 => 'Jasna eskalacja i slad decyzji.',
  ),
  'checklist' => 
  array (
    0 => 'Wszystkie domeny publiczne sa pokryte (apex + subdomeny).',
    1 => 'Alerty 30/14/7 dni sa ustawione.',
    2 => 'Owner i eskalacja sa przypisane.',
  ),
  'pitfalls' => 
  array (
    0 => 'Monitoring tylko apex domeny.',
    1 => 'Brak eskalacji - alerty znikaja.',
  ),
  'related_faq' => 
  array (
    0 => 'how-do-i-monitor-ssl-certificate-expiry',
    1 => 'what-causes-website-downtime',
  ),
);

