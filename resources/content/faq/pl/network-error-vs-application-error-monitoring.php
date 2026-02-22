<?php

return array (
  'order' => 21,
  'related' => 
  array (
    0 => 'multi-region-monitoring-why-important',
    1 => 'how-do-i-reduce-false-alerts',
    2 => 'how-do-i-monitor-api-endpoints',
  ),
  'related_solutions' => 
  array (
    0 => 'api-endpoint-monitoring',
    1 => 'uptime-monitoring-for-saas',
  ),
  'question' => 'Błąd sieci vs błąd aplikacji: jak to rozróżnić w monitoringu?',
  'answer' => '<p>Rozdzielanie <strong>błędów sieciowych</strong> i <strong>błędów aplikacyjnych</strong> to jedna z najważniejszych zasad monitoringu. Oba typy dają sygnał "usługa nie działa", ale wymagają innego ownera i innej ścieżki naprawy.</p>

<p>Do błędów sieciowych zaliczysz zwykle DNS, TLS handshake, timeout połączenia, problemy routingu i niedostępność portu. Do błędów aplikacyjnych: HTTP 5xx, błędną logikę biznesową, wadliwy payload, degradację odpowiedzi pod obciążeniem. Jeśli wrzucisz wszystko do jednego worka alertów, triage będzie wolniejszy i bardziej chaotyczny.</p>

<p>Praktyczny model: oznaczaj incydent domeną awarii (<em>network/transport</em> albo <em>application/runtime</em>) i mapuj eskalację do właściwego zespołu. Dla transportu: infrastruktura lub sieć. Dla runtime: backend albo właściciel usługi. Taki podział zwykle skraca czas pierwszej decyzji i ogranicza "ping-pong" między zespołami.</p>

<p>W TruMonit warto utrzymywać osobne checki i progi dla obu klas, a potem korelować je na osi czasu. Przykład: jednoczesny błąd DNS + port sugeruje warstwę sieciową; zdrowa sieć + wzrost 5xx sugeruje problem aplikacji. To prosty sposób na szybszą i bardziej precyzyjną reakcję.</p>

<p>Raz w miesiącu zrób przegląd incydentów: ile było awarii transportowych, ile aplikacyjnych, gdzie pojawiły się błędne eskalacje i które progi wymagają korekty. Taki rytm utrzymuje monitoring blisko realnych problemów produkcyjnych.</p>

<p>Dodatkowo ustal prostą matrycę decyzji dla dyżuru: jakie objawy oznaczają natychmiastowe przekazanie do zespołu infrastruktury, a jakie do backendu. Dzięki temu nawet nowa osoba na dyżurze podejmie pierwszą decyzję szybko i zgodnie z runbookiem.</p>',
  'meta_title' => 'Błąd sieci vs błąd aplikacji: jak to rozróżnić w monitoringu? | TruMonit',
  'meta_description' => 'Rozdziel błędy sieciowe i aplikacyjne, kieruj incydenty do właściwych ownerów i skracaj triage dzięki praktycznemu modelowi monitoringu produkcyjnego.',
);
