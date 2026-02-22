<?php

return array (
  'order' => 15,
  'related' => 
  array (
    0 => 'how-do-i-monitor-ssl-certificate-expiry',
    1 => 'what-causes-website-downtime',
    2 => 'what-is-a-status-page',
  ),
  'related_solutions' => 
  array (
    0 => 'ssl-certificate-expiry-monitoring',
    1 => 'status-page-for-saas',
    2 => 'incident-response-playbook',
    3 => 'domain-expiration-and-dns-monitoring',
  ),
  'question' => 'Jak monitorować wygasanie domeny?',
  'answer' => '<p>Wygasanie domeny to jedna z najbardziej kosztownych i jednocześnie najłatwiejszych do uniknięcia awarii. Traktuj to jak “płatność krytyczną” i ustaw kilka warstw zabezpieczeń.</p>

<h3>Minimalny setup (który realnie działa)</h3>
<ul>
  <li><strong>Autorenew</strong> u rejestratora + aktualna metoda płatności.</li>
  <li><strong>Alerty z wyprzedzeniem</strong>: 90/60/30/14/7 dni (nie tylko 1 przypomnienie).</li>
  <li><strong>2 ownerów</strong>: techniczny i biznesowy (żeby nie było single point of failure).</li>
  <li><strong>Inwentarz domen</strong>: lista domen, dostęp do konta rejestratora, gdzie jest DNS.</li>
</ul>

<h3>Co jeszcze warto dodać</h3>
<ul>
  <li>Włącz 2FA na koncie rejestratora i ustaw blokadę domeny (domain lock).</li>
  <li>Monitoruj DNS i krytyczne hosty (WWW/API) niezależnie, bo “domena opłacona” nie znaczy “serwis działa”.</li>
  <li>W razie awarii komunikuj status przez <a href=\"/pl/solutions/status-page-for-saas\">status page</a>.</li>
</ul>

<p>Powiązane: <a href=\"/pl/solutions/ssl-certificate-expiry-monitoring\">monitoring wygasania SSL</a> oraz <a href=\"/pl/solutions/incident-response-playbook\">playbook reakcji na incydent</a>.</p>',
  'meta_title' => 'Jak monitorować wygasanie domeny? | TruMonit',
  'meta_description' => 'Monitoring wygasania domeny: alerty 90/60/30/14/7 dni, autorenew, 2FA i routing powiadomień do 2 ownerów. Checklisty, żeby uniknąć kosztownej awarii.',
);
