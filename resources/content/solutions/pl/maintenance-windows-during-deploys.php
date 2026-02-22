<?php

return array(
    'order' => 10,
    'title' => 'Okna serwisowe (maintenance windows) podczas deployów',
    'meta_title' => 'Okna serwisowe podczas deployów | TruMonit',
    'meta_description' => 'Używaj maintenance windows, aby wyciszyć alerty podczas planowanych zmian, bez ukrywania realnych awarii. Dodaj checklistę weryfikacji po deployu.',
    'summary' => 'Wycisz alerty podczas planowanych zmian, ale zachowaj widoczność i weryfikację po deployu, żeby nie ukryć realnych incydentów.',
    'body' => '<p><strong>Okno serwisowe</strong> to narzędzie bezpieczeństwa: chroni zespół przed spamem alertowym podczas kontrolowanych zmian. Źle użyte potrafi jednak ukryć realną awarię.</p>

<h3>Kiedy używać maintenance window</h3>
<ul>
  <li>Migracje bazy danych i zmiany schematu.</li>
  <li>Zmiany infrastruktury (DNS, load balancer, CDN).</li>
  <li>Duże releasy, które chwilowo zwiększają error rate.</li>
</ul>

<h3>Dobre praktyki</h3>
<ul>
  <li>Okno ma być krótkie i jednoznaczne (start/koniec).</li>
  <li>Wyznacz ownera (kto kończy okno, gdy deploy skończy się wcześniej).</li>
  <li>Po deployu uruchom weryfikację krytycznych stron i API.</li>
  <li>Dla login/checkout użyj <a href=\"/pl/solutions/synthetic-monitoring-login-flow\">monitoringu user journey (Playwright)</a>.</li>
</ul>

<p>Zobacz też: <a href=\"/pl/solutions/reduce-false-alerts\">Jak ograniczyć fałszywe alerty</a>.</p>',
    'problem' => 'Deploy potrafi generować fałszywe alerty. Bez okien serwisowych zespół ignoruje monitoring; bez weryfikacji po deployu łatwo ukryć realny incydent.',
    'approach' => array(
        'Ustal zasady: kiedy wolno używać okien serwisowych i kto je tworzy.',
        'Trzymaj okna krótkie i “owned”; kończ wcześniej, jeśli można.',
        'Zawsze rób checklistę weryfikacji po deployu.',
    ),
    'proof' => array(
        'Mniej szumu alertowego podczas zmian.',
        'Lepszy sygnał dla realnych awarii.',
        'Szybsza pewność po deployu (weryfikacja jest częścią procesu).',
    ),
    'checklist' => array(
        'Okno ma ownera i czas końca.',
        'Krytyczne monitory są sprawdzone po deployu.',
        'Proces incydentowy opisuje, co robić, gdy deploy się nie uda.',
    ),
    'pitfalls' => array(
        'Okna włączone “na długo”.',
        'Okno jako “mute forever”.',
        'Brak weryfikacji po deployu (problemy wykrywają użytkownicy).',
    ),
    'related_faq' => array(
        'what-is-maintenance-window-in-monitoring',
        'what-causes-website-downtime',
        'how-to-use-status-page-during-incident',
    ),
);

