<?php

return array (
  'order' => 30,
  'related' => 
  array (
    0 => 'how-to-monitor-login-flow-synthetically',
    1 => 'how-to-monitor-third-party-dependencies',
    2 => 'how-to-choose-a-website-monitoring-plan',
  ),
  'related_solutions' => 
  array (
    0 => 'synthetic-monitoring-login-flow',
    1 => 'api-endpoint-monitoring',
    2 => 'monitoring-third-party-dependencies',
  ),
  'question' => 'Czy mogę monitorować usługi prywatne lub wewnętrzne?',
  'answer' => '<p>Tak, ale nie da się “magicznie” monitorować prywatnych adresów z Internetu. Dla usług za VPN, w VPC albo w sieci firmowej potrzebujesz <strong>wykonania checków wewnątrz Twojej sieci</strong> (tzw. private probe / agent) albo bezpiecznego sposobu wystawienia health checka.</p>

<h3>Najczęstsze podejścia</h3>
<ul>
  <li><strong>Private agent</strong> uruchomiony w Twojej sieci (VM, Docker, Kubernetes), który wykonuje checki i raportuje wynik.</li>
  <li><strong>Allowlist IP</strong>: jeśli możesz, dopuszczasz ruch z konkretnych adresów (mniej elastyczne, ale proste).</li>
  <li><strong>Wewnętrzny endpoint health</strong>: osobny URL tylko do monitoringu, bez danych wrażliwych.</li>
</ul>

<h3>Bezpieczeństwo (ważne)</h3>
<ul>
  <li>Nie używaj “admin” tokenów do monitoringu. Dawaj minimalne uprawnienia (least privilege).</li>
  <li>Segmentuj sekrety: agent do monitoringu nie powinien mieć dostępu do całego środowiska.</li>
  <li>Loguj i limituj requesty: monitoring ma wykrywać awarie, a nie tworzyć nowe ryzyka.</li>
</ul>

<p>Jeśli monitorujesz procesy, nie tylko HTTP, zobacz <a href=\"/pl/solutions/synthetic-monitoring-login-flow\">synthetic monitoring login flow</a>. A jeśli dużo awarii wynika z dostawców zewnętrznych, przyda się <a href=\"/pl/solutions/monitoring-third-party-dependencies\">monitoring zależności third-party</a>.</p>',
  'meta_title' => 'Czy mogę monitorować usługi prywatne lub wewnętrzne? | TruMonit',
  'meta_description' => 'Tak: przez private agent/probe w Twojej sieci, allowlist IP lub osobny endpoint health. Najważniejsze zasady bezpieczeństwa i minimalne uprawnienia dla monitoringu.',
);
