<?php

return array (
  'order' => 3,
  'related' => 
  array (
    0 => 'synthetic-checks-vs-http-checks',
    1 => 'how-do-i-monitor-ssl-certificate-expiry',
    2 => 'how-do-i-reduce-false-alerts',
  ),
  'related_solutions' => 
  array (
    0 => 'api-endpoint-monitoring',
  ),
  'question' => 'Jak monitorować endpointy API (REST/JSON)?',
  'answer' => '<p>Dobry <strong>monitoring API</strong> nie kończy się na statusie 200. Celem jest wykrycie awarii, które odczuwają klienci: timeoutów, 5xx, rate limit (429) oraz błędnych payloadów.</p>

<h3>Minimalny zestaw monitorów API</h3>
<ul>
  <li><strong>/health</strong> (techniczny): szybki sygnał, czy infrastruktura odpowiada.</li>
  <li><strong>Endpoint biznesowy</strong>: realny request używany przez klientów (np. lista faktur).</li>
  <li><strong>Webhook/ingest</strong> (jeśli masz): osobny monitor dla integracji.</li>
</ul>

<h3>Co sprawdzać w monitorze</h3>
<ul>
  <li>Status: akceptowalne kody (zwykle 2xx; czasem 3xx).</li>
  <li>Latency: progi zgodne z Twoim SLO (warning/critical).</li>
  <li>Payload: stabilny marker (klucz JSON, stała wartość, keyword) zamiast dynamicznego tekstu.</li>
  <li>Autoryzacja: testowy token/konto o minimalnych uprawnieniach.</li>
</ul>

<h3>Najczęstsze błędy</h3>
<ul>
  <li>Monitoring tylko /health i brak monitorów biznesowych.</li>
  <li>Zbyt ciasne timeouty (szum zamiast sygnału).</li>
  <li>Brittle asercje na polach, które zmieniają się naturalnie (np. timestampy).</li>
</ul>

<p>Jeśli chcesz szybciej przejść do działania, zacznij od 2 monitorów: /health + 1 endpoint biznesowy. Potem dodaj 429/rate limit i webhooki.</p>',
  'meta_title' => 'Jak monitorować endpointy API (REST/JSON)? | TruMonit',
  'meta_description' => 'Praktyczny monitoring API: /health + endpoint biznesowy, progi opóźnienia, stabilne asercje JSON/słów kluczowych oraz rozdzielenie 429 (limitów) od 5xx.',
);
