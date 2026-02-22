<?php

return array (
  'order' => 3,
  'title' => 'Monitoring wygaśnięcia certyfikatu SSL/TLS',
  'meta_title' => 'Monitoring wygaśnięcia SSL/TLS | TruMonit',
  'meta_description' => 'Zapobiegaj łatwym awariom: monitoruj wygaśnięcie TLS/SSL z alertami 30/14/7 dni i eskalacją do ownera. Prosty monitor o bardzo wysokim ROI.',
  'summary' => 'Wygaśnięcie SSL potrafi zabić logowanie, API i integracje. Ten monitor jest tani, szybki we wdrożeniu i eliminuje 100% „unikalnych” awarii.',
  'body' => '<p>Wygaśnięcie certyfikatu SSL/TLS to klasyk: wszystko działa do momentu, aż nagle przeglądarka pokazuje ostrzeżenie, a aplikacja i API zaczynają sypać błędami. To jedna z awarii o <strong>najwyższym ROI</strong> w monitoringu, bo jest w 100% przewidywalna.</p>

<h3>Okna alertów (praktyczny standard)</h3>
<ul>
  <li><strong>30 dni</strong>: wczesne ostrzeżenie (czas na zaplanowanie odnowienia).</li>
  <li><strong>14 dni</strong>: trzeba działać (włącz eskalację do ownera).</li>
  <li><strong>7 dni</strong>: krytyczne (wyższy priorytet, przypomnienia).</li>
</ul>

<h3>Co uwzględnić w monitoringu SSL</h3>
<ul>
  <li>Data wygaśnięcia i liczba dni do końca ważności.</li>
  <li>Zakres hostów (SAN): nie tylko apex domeny, ale też kluczowe subdomeny.</li>
  <li>Wildcardy: sprawdź, czy obejmują wszystkie realnie używane hosty.</li>
  <li>Właściciel (owner): kto odpowiada za odnowienie i gdzie idzie eskalacja.</li>
</ul>

<h3>Najczęstsze źródła problemów</h3>
<ul>
  <li>Let’s Encrypt: automatyczne odnowienie nie zadziałało (DNS/ACME, zmiana infrastruktury).</li>
  <li>Zapomniane subdomeny: np. api., app., cdn., status.</li>
  <li>Błędny cert na load balancerze po deployu (rollout/rollback).</li>
</ul>

<p>Jeśli chcesz szybko podnieść niezawodność bez przebudowy systemu, monitoring SSL/TLS to najlepszy „quick win”.</p>',
  'problem' => 'Wygasły certyfikat powoduje ostrzeżenia przeglądarki, błędy API i problemy z integracjami. Zespoły często dowiadują się o tym od klientów, bo nie mają prostego alertu 30/14/7 dni.',
  'approach' => 
  array (
    0 => 'Dodaj checki SSL dla domen produkcyjnych i kluczowych subdomen.',
    1 => 'Alertuj 30/14/7 dni przed wygaśnięciem.',
    2 => 'Eskaluj do ownera, jeśli 14 dni minęło bez reakcji.',
  ),
  'proof' => 
  array (
    0 => 'Brak niespodzianek z SSL/TLS.',
    1 => 'Jasna eskalacja i ślad decyzji.',
    2 => 'Mniej incydentów „po godzinach” z powodu odnowień.',
  ),
  'checklist' => 
  array (
    0 => 'Wszystkie domeny publiczne są pokryte (apex + subdomeny).',
    1 => 'Alerty 30/14/7 dni są ustawione.',
    2 => 'Owner i eskalacja są przypisane.',
    3 => 'Lista hostów zawiera API/app/status i inne krytyczne subdomeny.',
  ),
  'pitfalls' => 
  array (
    0 => 'Monitoring tylko apex domeny (pomijanie subdomen/SAN).',
    1 => 'Brak eskalacji: alerty wpadają do „szumu” i znikają.',
  ),
  'related_faq' => 
  array (
    0 => 'how-do-i-monitor-ssl-certificate-expiry',
    1 => 'what-causes-website-downtime',
  ),
);
