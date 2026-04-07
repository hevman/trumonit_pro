<?php

return array (
  'order' => 16,
  'related' => 
  array (
    0 => 'how-do-i-reduce-false-alerts',
    1 => 'what-is-an-incident-response-playbook',
    2 => 'what-is-mttr-and-how-to-improve-it',
  ),
  'related_solutions' => 
  array (
    0 => 'maintenance-windows-during-deploys',
    1 => 'reduce-false-alerts',
    2 => 'incident-response-playbook',
  ),
  'question' => 'Czym jest okno serwisowe w monitoringu?',
  'answer' => '<p><strong>Maintenance window</strong> (okno serwisowe) to zaplanowany okres, w którym alerty są czasowo wyciszone podczas kontrolowanych zmian: deployów, migracji, zmian infrastruktury.</p>

<h3>Po co to jest?</h3>
<ul>
  <li>Żeby nie spamować zespołu alertami, gdy <em>wiesz</em>, że przez kilka minut mogą wystąpić błędy.</li>
  <li>Żeby zachować czytelną historię incydentów (mniej fałszywych alarmów).</li>
</ul>

<h3>Jak używać tego bez ryzyka ukrywania realnej awarii</h3>
<ul>
  <li>Okno ma <strong>start i koniec</strong> (krótkie, konkretne), a nie “włączone na wieczność”.</li>
  <li>Okno ma <strong>owner</strong> (kto je kończy, jeśli deploy zakończy się wcześniej).</li>
  <li>Po deployu zawsze uruchom <strong>checklistę weryfikacji</strong> krytycznych stron i API.</li>
</ul>

<p>Więcej: <a href=\"/pl/solutions/maintenance-windows-during-deploys\">okna serwisowe podczas deployów (praktyka)</a>.</p>',
  'meta_title' => 'Czym jest okno serwisowe w monitoringu? | TruMonit',
  'meta_description' => 'Okno serwisowe w monitoringu: po co jest, jak ustawić start i koniec oraz odpowiedzialność, a także jak nie ukryć realnej awarii. Praktyczna lista kontrolna po wdrożeniu.',
);
