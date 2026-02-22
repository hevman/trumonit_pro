<?php

return array (
  'order' => 4,
  'related' => 
  array (
    0 => 'what-is-uptime-monitoring',
    1 => 'how-do-i-monitor-api-endpoints',
    2 => 'what-is-a-status-page',
  ),
  'related_solutions' => 
  array (
    0 => 'ssl-certificate-expiry-monitoring',
  ),
  'question' => 'Jak monitorować wygaśnięcie certyfikatu SSL/TLS?',
  'answer' => '<p>Najprostszy i najbardziej opłacalny monitor to <strong>monitoring wygaśnięcia SSL/TLS</strong>. Ustaw alerty z wyprzedzeniem i przypisz ownera, żeby odnowienie nie „zginęło” w szumie.</p>

<h3>Rekomendowane okna alertów</h3>
<ul>
  <li><strong>30 dni</strong>: wczesne ostrzeżenie.</li>
  <li><strong>14 dni</strong>: eskalacja do ownera.</li>
  <li><strong>7 dni</strong>: krytyczne przypomnienia.</li>
</ul>

<h3>Co warto uwzględnić</h3>
<ul>
  <li>Subdomeny i SAN (api., app., status., cdn.).</li>
  <li>Wildcardy: czy naprawdę obejmują wszystkie hosty.</li>
  <li>Proces odnowienia (np. Let’s Encrypt) i kto jest za niego odpowiedzialny.</li>
</ul>

<p>Wczesne alerty SSL chronią przed awariami logowania, błędami API i utratą zaufania użytkowników.</p>',
  'meta_title' => 'Jak monitorować wygaśnięcie certyfikatu SSL/TLS? | TruMonit',
  'meta_description' => 'Monitoring SSL/TLS: alerty 30/14/7 dni, pokrycie subdomen (SAN) i eskalacja do ownera. Prosty sposób na uniknięcie awarii „z dnia na dzień”.',
);
