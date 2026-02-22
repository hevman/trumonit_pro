<?php

return array (
  'order' => 7,
  'title' => 'Monitoring logowania (synthetic / Playwright)',
  'meta_title' => 'Monitoring logowania (synthetic, Playwright) | TruMonit',
  'meta_description' => 'Synthetic monitoring logowania: monitoruj ścieżkę użytkownika w przeglądarce (Playwright), wykrywaj błędy JS, redirect-loop i awarie UI zanim zobaczą je klienci.',
  'summary' => 'Synthetic checks (Playwright) weryfikują realne flow (login/checkout) i wykrywają awarie UX/JS, których nie złapie zwykły HTTP check.',
  'body' => '<p><strong>HTTP check</strong> potwierdza dostępność endpointu. <strong>Synthetic monitoring</strong> potwierdza <em>ścieżkę użytkownika</em>: czy da się zalogować, przejść redirecty i zobaczyć kluczowy element po zalogowaniu.</p>

<h3>Kiedy warto użyć synthetic checks?</h3>
<ul>
  <li>Login działa losowo (race condition, błąd JS, problem z CSP), a HTTP nadal zwraca 200.</li>
  <li>Masz krytyczne flow: logowanie, reset hasła, checkout, onboardowanie.</li>
  <li>Chcesz wykrywać regresje UI po deployu szybciej niż zgłoszenia od klientów.</li>
</ul>

<h3>Co dokładnie walidować w flow logowania</h3>
<ul>
  <li>Redirecty: czy finalnie lądujesz na właściwej domenie i ścieżce.</li>
  <li>Element po zalogowaniu: np. nagłówek panelu, avatar, menu konta.</li>
  <li>Brak błędów krytycznych: komunikat o błędzie, pętla redirectów, blokada przez CSP.</li>
  <li>Czas: ustaw realistyczne timeouty (sieć, render, API).</li>
</ul>

<h3>Jak ograniczyć flaki (fałszywe alarmy)</h3>
<ul>
  <li>Używaj stabilnych selektorów (np. <code>data-testid</code>), nie klas z CSS.</li>
  <li>Waliduj jeden-dwa elementy krytyczne, nie cały layout.</li>
  <li>Dodaj retry i cooldown w alertach, aby nie spamować zespołu.</li>
</ul>

<h3>Bezpieczeństwo</h3>
<ul>
  <li>Dedykowane konto testowe z minimalnymi uprawnieniami (bez admina).</li>
  <li>Rotuj hasła przez secrets management i ogranicz dostęp do sekretów.</li>
  <li>Unikaj flow z CAPTCHA/MFA na środowisku produkcyjnym; jeśli musisz, twórz test-only bypass.</li>
</ul>

<p>W skrócie: jeśli chcesz monitorować to, co widzi użytkownik, wybierz <strong>monitoring user journey</strong> (Playwright) zamiast samego HTTP.</p>',
  'problem' => 'Logowanie i checkout potrafią wyglądać na „up” (HTTP 200), ale być zepsute dla użytkownika (błąd JS, pętla redirectów, problem z CSP, niedziałający przycisk). Synthetic checks wykrywają te awarie.',
  'approach' => 
  array (
    0 => 'Zdefiniuj flow i kryterium sukcesu (konkretny element po zalogowaniu).',
    1 => 'Użyj testowego użytkownika i stabilnych selektorów (data-testid).',
    2 => 'Ustaw timeouty, retry i cooldown, aby ograniczyć flaki.',
  ),
  'proof' => 
  array (
    0 => 'Wykrywa regresje UI/JS szybciej niż HTTP checks.',
    1 => 'Daje lepszy sygnał dla flow krytycznych dla klienta.',
    2 => 'Zmniejsza ryzyko „cichych awarii” po deployu.',
  ),
  'checklist' => 
  array (
    0 => 'Konto testowe istnieje i ma minimalne uprawnienia.',
    1 => 'Selektory są stabilne (data-testid), bez zależności od CSS.',
    2 => 'Sekrety są przechowywane i rotowane bezpiecznie.',
    3 => 'Alerty mają retry/cooldown (bez spamu).',
  ),
  'pitfalls' => 
  array (
    0 => 'Używanie realnych kont (ryzyko bezpieczeństwa).',
    1 => 'Brittle selektory i flaki po drobnych zmianach UI.',
    2 => 'CAPTCHA/MFA bez obejścia testowego (złamie monitor).',
  ),
  'related_faq' => 
  array (
    0 => 'how-to-monitor-login-flow-synthetically',
    1 => 'synthetic-checks-vs-http-checks',
    2 => 'can-i-monitor-private-internal-services',
  ),
);
