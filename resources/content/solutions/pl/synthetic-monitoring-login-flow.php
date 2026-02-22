<?php

return array (
  'order' => 7,
  'title' => 'Synthetic monitoring flow logowania',
  'meta_title' => 'Synthetic monitoring logowania | TruMonit',
  'meta_description' => 'Monitoruj realne user journeys jak login: skryptuj flow, waliduj elementy i lap regresje front-end, ktorych nie wykryje HTTP check.',
  'summary' => 'Synthetic checks weryfikuja realne flow (login/checkout) i szybciej wykrywaja regresje UI/JS.',
  'body' => '<p>HTTP check potwierdza dostepnosc. Synthetic check potwierdza <strong>user journey</strong>: czy uzytkownik faktycznie moze sie zalogowac i dojsc do kluczowego ekranu.</p>

<h3>Dobre praktyki</h3>
<ul>
  <li>Dedykowane konto testowe z minimalnymi uprawnieniami.</li>
  <li>Stabilne selektory (nie flakujace).</li>
  <li>Opcjonalnie multi-region dla globalnych flow.</li>
</ul>

<h3>Bezpieczenstwo</h3>
<ul>
  <li>Rotuj hasla przez secrets management.</li>
  <li>Uzywaj test-only kont i srodowisk, gdy to mozliwe.</li>
  <li>Ogranicz blast radius (bez admin uprawnien).</li>
</ul>',
  'problem' => 'Login/checkout potrafi byc \"up\" (200), ale nie dzialac dla uzytkownika (JS error, redirect loop, CSP). Synthetic checks wykrywaja takie awarie.',
  'approach' => 
  array (
    0 => 'Zdefiniuj flow i kryterium sukcesu (element po zalogowaniu).',
    1 => 'Uzyj test usera i stabilnych selektorow.',
    2 => 'Alertuj z retry i cooldown.',
  ),
  'proof' => 
  array (
    0 => 'Wykrywa regresje UI/JS szybciej niz HTTP checks.',
    1 => 'Lepszy sygnal dla flow krytycznych dla klienta.',
  ),
  'checklist' => 
  array (
    0 => 'Konto testowe istnieje.',
    1 => 'Selektory sa stabilne.',
    2 => 'Sekrety sa przechowywane i rotowane bezpiecznie.',
  ),
  'pitfalls' => 
  array (
    0 => 'Uzywanie realnych kont (ryzyko).',
    1 => 'Brittle selektory i flaki.',
  ),
  'related_faq' => 
  array (
    0 => 'how-to-monitor-login-flow-synthetically',
    1 => 'synthetic-checks-vs-http-checks',
    2 => 'can-i-monitor-private-internal-services',
  ),
);

