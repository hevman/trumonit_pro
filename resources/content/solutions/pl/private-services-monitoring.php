<?php

return array (
  'order' => 17,
  'title' => 'Monitoring usług prywatnych (VPC, VPN, intranet)',
  'meta_title' => 'Monitoring usług prywatnych | TruMonit',
  'meta_description' => 'Jak monitorować usługi za VPN/VPC: private agent/probe, zasady least privilege, osobne endpointy health i bezpieczny routing alertów.',
  'summary' => 'Prywatnych adresów nie da się monitorować “z Internetu”. Rozwiązaniem jest private agent w Twojej sieci i bezpieczne health checki.',
  'body' => '<p>Jeśli endpoint jest za VPN, w VPC albo w sieci firmowej, to publiczny monitoring nie ma do niego dostępu. Najbezpieczniejszy model to <strong>private agent</strong>, który wykonuje checki wewnątrz Twojej sieci i raportuje wynik.</p>

<h3>Architektura, która działa</h3>
<ul>
  <li>Agent działa w Twoim środowisku (VM/Docker/Kubernetes).</li>
  <li>Wykonuje checki do prywatnych usług (HTTP, TCP, synthetics).</li>
  <li>Wysyła do panelu tylko wynik i minimalne metadane.</li>
</ul>

<h3>Bezpieczeństwo (najważniejsze)</h3>
<ul>
  <li>Least privilege: agent nie ma uprawnień admina ani dostępu do wszystkiego.</li>
  <li>Osobny endpoint health bez danych wrażliwych.</li>
  <li>Segmentacja: monitoring nie może zwiększać blast radius.</li>
</ul>

<p>Powiązane: <a href="/pl/solutions/synthetic-monitoring-login-flow">synthetic monitoring flow</a> oraz <a href="/pl/solutions/monitoring-third-party-dependencies">monitoring zależności</a>. FAQ: <a href="/pl/faq/can-i-monitor-private-internal-services">czy mogę monitorować usługi prywatne</a>.</p>',
  'problem' => 'Najważniejsze usługi często są prywatne (baza, API wewnętrzne, panele). Jeśli monitorujesz tylko publiczny URL, możesz mieć “zielono”, gdy core jest niedostępny w prywatnej sieci.',
  'approach' => array (
    0 => 'Uruchom private agent w VPC/VPN (blisko monitorowanych usług).',
    1 => 'Zacznij od krytycznych endpointów i health checków.',
    2 => 'Zastosuj least privilege i segmentację sieci/secrets.',
    3 => 'Ustaw routing alertów pod krytyczność usług.',
  ),
  'proof' => array (
    0 => 'Masz realny sygnał o dostępności core usług, nie tylko frontu.',
    1 => 'Lepsza diagnoza: czy problem jest “w środku”, czy na edge.',
    2 => 'Bezpieczna architektura bez wystawiania prywatnych usług do Internetu.',
  ),
  'checklist' => array (
    0 => 'Agent jest uruchomiony w Twojej sieci i ma minimalne uprawnienia.',
    1 => 'Health endpointy nie ujawniają danych wrażliwych.',
    2 => 'Alert routing jest dopasowany do krytyczności.',
  ),
  'pitfalls' => array (
    0 => 'Wystawienie prywatnego endpointu publicznie “tylko do monitoringu”.',
    1 => 'Agent zbyt uprzywilejowany (ryzyko bezpieczeństwa).',
    2 => 'Monitoring wszystkiego naraz zamiast krytycznych usług.',
  ),
  'related_faq' => array (
    0 => 'can-i-monitor-private-internal-services',
    1 => 'how-to-monitor-login-flow-synthetically',
    2 => 'how-to-monitor-third-party-dependencies',
  ),
);