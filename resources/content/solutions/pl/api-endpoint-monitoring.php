<?php

return array (
  'order' => 2,
  'title' => 'Monitoring endpointów API (REST/JSON)',
  'meta_title' => 'Monitoring endpointów API (REST/JSON) | TruMonit',
  'meta_description' => 'Monitoring API: status, latency, timeout i asercje treści. Wykrywaj timeouty, 5xx, błędne payloady i regresje rate limit zanim zobaczą to użytkownicy.',
  'summary' => 'Dobry monitoring API łączy status, czas odpowiedzi, walidację payloadu (JSON/keyword) i obserwację 429/rate limit, bez nadmiaru fałszywych alarmów.',
  'body' => '<p>Monitoring endpointów API bywa zdradliwy: możesz mieć <strong>HTTP 200</strong>, a jednocześnie zepsuty payload, zbyt wolne odpowiedzi albo błędną logikę biznesową. Celem jest szybkie wykrycie problemów, które realnie psują produkt.</p>

<h3>Minimalny zestaw monitorów API (który działa)</h3>
<ul>
  <li><strong>Health</strong>: szybki endpoint techniczny (np. /health) dla dostępności infrastruktury.</li>
  <li><strong>Endpoint biznesowy</strong>: realny request, z którego korzystają klienci (np. /api/v1/invoices).</li>
  <li><strong>Webhook/ingest</strong> (jeśli masz): osobny monitor dla krytycznych integracji.</li>
</ul>

<h3>Jakie warunki (assertions) stosować</h3>
<ul>
  <li>Status: akceptowalne kody (zwykle 2xx; czasem 3xx dla redirectów).</li>
  <li>Latency: próg ostrzegawczy (warning) i krytyczny (critical) zgodny z Twoim SLO.</li>
  <li>Payload: stabilny marker (klucz JSON, stała wartość, keyword). Unikaj dynamicznego copy.</li>
  <li>Autoryzacja: jeśli endpoint wymaga tokena, używaj konta/klucza testowego z minimalnym zakresem.</li>
</ul>

<h3>Rate limit i 429: jak to monitorować</h3>
<ul>
  <li>Wykrywaj skoki 429 i koreluj z latency oraz 5xx (często idą razem podczas przeciążenia).</li>
  <li>Zwracaj uwagę na „retry storm”: zbyt agresywne retry po stronie klienta potrafi pogorszyć incydent.</li>
  <li>Rozdziel alerty: osobno „rate limit” i osobno „błąd aplikacji”, żeby szybciej trafić do właściwego ownera.</li>
</ul>

<h3>Typowe wzorce incydentów API</h3>
<ul>
  <li>Timeouty przez wolny upstream, DB saturation, locki lub zbyt ciężkie zapytania.</li>
  <li>5xx po wdrożeniu (błąd serializacji, migracje, config).</li>
  <li>200 z błędnym payloadem (zmiana kontraktu, null w polu, zła walidacja).</li>
</ul>

<p>Praktyczna zasada: <strong>monitoruj to, co kupuje klient</strong>. Jeśli jedyny monitor to /health, dowiesz się o awarii dopiero z supportu.</p>',
  'problem' => 'API może zwracać 200, a mimo to psuć flow biznesowy (wolne odpowiedzi, błędny payload, problemy autoryzacji, rate limit). Potrzebujesz monitoringu, który mierzy dostępność i jakość odpowiedzi.',
  'approach' => 
  array (
    0 => 'Dodaj monitory dla health + jednego endpointu biznesowego.',
    1 => 'Ustaw timeouty i progi latency pod realne SLO.',
    2 => 'Dodaj stabilne asercje (keyword/JSON) dla poprawności payloadu.',
    3 => 'Monitoruj 429/rate limit i odseparuj alerty od 5xx.',
    4 => 'Zmapuj alerty do ownerów (escalation) i zdefiniuj cooldown.',
  ),
  'proof' => 
  array (
    0 => 'Wczesne wykrycie timeoutów, 5xx i problemów rate limit.',
    1 => 'Lepsze rozróżnienie błędów sieci vs aplikacja vs przeciążenie.',
    2 => 'Historia incydentów pod analizę MTTR i postmortem.',
  ),
  'checklist' => 
  array (
    0 => 'Health + endpoint biznesowy są monitorowane.',
    1 => 'Timeout i progi latency są ustawione.',
    2 => 'Asercje payloadu są stabilne i nie flakują.',
    3 => 'Alerting 429/rate limit działa i nie miesza się z 5xx.',
  ),
  'pitfalls' => 
  array (
    0 => 'Monitoring tylko /health (brak monitorów biznesowych).',
    1 => 'Brittle asercje na dynamicznym tekście lub przypadkowych polach JSON.',
    2 => 'Brak testowych danych / konta testowego dla endpointów wymagających autoryzacji.',
  ),
  'related_faq' => 
  array (
    0 => 'how-do-i-monitor-api-endpoints',
    1 => 'network-error-vs-application-error-monitoring',
    2 => 'how-to-monitor-api-rate-limits',
    3 => 'how-to-monitor-webhook-endpoints',
  ),
);
