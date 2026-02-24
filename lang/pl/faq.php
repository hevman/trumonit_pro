<?php

return [
    'nav_faq' => 'FAQ',
    'page_title' => 'FAQ monitoringu stron i API',
    'page_description' => 'Praktyczne odpowiedzi o uptime, monitoringu endpointów API, alertach SSL, stronach statusu i reakcji na incydenty.',
    'search_label' => 'Szukaj w FAQ',
    'search_placeholder' => 'Szukaj pytań...',
    'read_answer' => 'Zobacz odpowiedź',
    'no_results' => 'Brak wyników. Spróbuj innego hasła.',
    'back_to_index' => 'Powrót do FAQ',
    'related_questions' => 'Powiązane pytania',
    'cta_title' => 'Chcesz alerty bez szumu?',
    'cta_body' => 'Utwórz przestrzeń roboczą, dodaj monitory i kieruj incydenty do zespołu w kilka minut.',
    'cta_primary' => 'Utwórz konto',
    'cta_secondary' => 'Zaloguj się',

    'what_is_uptime_monitoring_question' => 'Czym jest monitoring uptime?',
    'what_is_uptime_monitoring_answer' => '<p><strong>Monitoring uptime</strong> regularnie sprawdza, czy Twoja strona lub API są dostępne i działają poprawnie. Zwykle weryfikuje status HTTP, czas odpowiedzi oraz opcjonalne reguły treści.</p><p>Celem jest szybkie wykrycie awarii, natychmiastowy alert dla zespołu i kontrola trendu dostępności w czasie.</p>',

    'how_often_should_i_check_my_website_question' => 'Jak często sprawdzać stronę?',
    'how_often_should_i_check_my_website_answer' => '<p>Większość zespołów zaczyna od interwału <strong>1-5 minut</strong>. Dla krytycznych flow, takich jak checkout, logowanie i kluczowe API, zwykle wybiera się 1 minutę.</p><p>Krótszy interwał ustaw dla endpointów krytycznych biznesowo, a dłuższy dla stron mniej istotnych, aby zachować balans między szybkością i kosztem.</p>',

    'how_do_i_monitor_api_endpoints_question' => 'Jak monitorować endpointy API?',
    'how_do_i_monitor_api_endpoints_answer' => '<p>Monitoruj endpointy API przez oczekiwany zakres statusów (np. 2xx), limity timeout oraz opcjonalne reguły treści w odpowiedzi.</p><p>Dla pewności monitoruj jednocześnie szybki endpoint health oraz jeden endpoint biznesowo krytyczny używany przez klientów.</p>',

    'how_do_i_monitor_ssl_certificate_expiry_question' => 'Jak monitorować wygasanie certyfikatu SSL?',
    'how_do_i_monitor_ssl_certificate_expiry_answer' => '<p>Skonfiguruj alerty SSL przed datą wygaśnięcia, np. 30, 14 i 7 dni wcześniej.</p><p>Wczesne alerty SSL chronią przed prostymi awariami, ostrzeżeniami przeglądarki i problemami z integracjami API.</p>',

    'synthetic_checks_vs_http_checks_question' => 'Sprawdzenia syntetyczne vs sprawdzenia HTTP: jaka jest różnica?',
    'synthetic_checks_vs_http_checks_answer' => '<p><strong>Sprawdzenia HTTP</strong> są lekkie i szybkie: sprawdzają status, czas odpowiedzi i proste reguły treści.</p><p><strong>Sprawdzenia syntetyczne</strong> symulują zachowanie przeglądarki lub całą ścieżkę użytkownika, dzięki czemu wykrywają problemy front-end i procesy, których zwykły test HTTP nie zobaczy.</p>',

    'how_do_i_reduce_false_alerts_question' => 'Jak ograniczyć fałszywe alerty?',
    'how_do_i_reduce_false_alerts_answer' => '<p>Stosuj kilka zabezpieczeń naraz: alert po kilku kolejnych błędach, czas wyciszenia oraz okna serwisowe podczas planowanych prac.</p><p>Połączenie wzorca i deduplikacji zmniejsza szum, a jednocześnie nie ukrywa realnych incydentów.</p>',

    'what_is_sla_vs_slo_question' => 'SLA vs SLO: jaka jest różnica w monitoringu?',
    'what_is_sla_vs_slo_answer' => '<p><strong>SLO</strong> to wewnętrzny cel niezawodności (np. 99.9% uptime). <strong>SLA</strong> to formalna obietnica dla klienta, często z konsekwencjami finansowymi.</p><p>Dobry monitoring raportuje oba poziomy, aby zespół mógł reagować zanim dojdzie do naruszenia umowy.</p>',

    'what_is_a_status_page_question' => 'Czym jest strona statusu i dlaczego jest ważna?',
    'what_is_a_status_page_answer' => '<p>Strona statusu to publiczny kanał komunikacji o bieżącym stanie usługi oraz historii incydentów.</p><p>W czasie awarii odciąża wsparcie i buduje zaufanie dzięki transparentnym aktualizacjom.</p>',

    'what_is_an_incident_response_playbook_question' => 'Czym jest playbook reakcji na incydent?',
    'what_is_an_incident_response_playbook_answer' => '<p>Playbook incydentowy definiuje role i kroki po alercie: triage, komunikację, mitygację i postmortem.</p><p>Dobrze opisany playbook skraca czas rozwiązania problemu i podnosi powtarzalność reakcji między zmianami.</p>',

    'how_to_choose_a_website_monitoring_plan_question' => 'Jak wybrać plan monitoringu strony?',
    'how_to_choose_a_website_monitoring_plan_answer' => '<p>Dobieraj plan według liczby monitorów, częstotliwości skanów, liczby kanałów powiadomień oraz potrzeb strony statusu i pracy zespołowej.</p><p>Zacznij od mniejszego planu i skaluj, gdy rosną wolumen monitorów, wymagania reakcji i potrzeby ładu operacyjnego.</p>',

    'what_causes_website_downtime_question' => 'Jakie są najczęstsze przyczyny niedostępności strony?',
    'what_causes_website_downtime_answer' => '<p>Najczęstsze awarie wynikają z błędów wdrożeń, przeciążenia bazy danych, wygasniętych certyfikatów TLS, problemów DNS i awarii dostawców zewnętrznych.</p><p>Klasyfikowanie incydentów według root cause pomaga szybciej usuwać źródła powtarzalnych problemów.</p>',

    'multi_region_monitoring_why_important_question' => 'Dlaczego monitoring z wielu regionów jest ważny?',
    'multi_region_monitoring_why_important_answer' => '<p>Check z jednego regionu może dawać fałszywe alarmy, gdy problem dotyczy tylko jednej trasy sieciowej. Multi-region potwierdza, czy problem jest globalny czy lokalny.</p><p>To poprawia jakość sygnału i przyspiesza przekazanie incydentu do właściwego zespołu.</p>',

    'best_alert_channels_for_uptime_monitoring_question' => 'Jakie kanały alertów są najlepsze dla monitoringu uptime?',
    'best_alert_channels_for_uptime_monitoring_answer' => '<p>W praktyce warto mieć co najmniej dwa kanały: szybki (PagerDuty, telefon, push) i współpracy (Slack, Teams, e-mail).</p><p>Kanały powinny być mapowane do poziomu krytyczności, aby tylko najważniejsze incydenty budziły dyżur.</p>',

    'how_to_monitor_cron_jobs_question' => 'Jak monitorować cron jobs i zadania cykliczne?',
    'how_to_monitor_cron_jobs_answer' => '<p>Najprościej przez heartbeat: po każdym poprawnym wykonaniu job wysyła sygnał. Brak sygnału w czasie oznacza incydent.</p><p>Warto monitorować nie tylko brak wykonania, ale też zbyt długi czas trwania zadania.</p>',

    'how_to_monitor_domain_expiration_question' => 'Jak monitorować wygasanie domeny?',
    'how_to_monitor_domain_expiration_answer' => '<p>Ustaw alerty dla terminu wygasania domeny z dużym wyprzedzeniem, np. 60, 30 i 14 dni.</p><p>Powiadomienia powinny trafić do technicznego i biznesowego ownera, aby uniknąć punktu pojedynczej awarii.</p>',

    'what_is_maintenance_window_in_monitoring_question' => 'Czym jest maintenance window w monitoringu?',
    'what_is_maintenance_window_in_monitoring_answer' => '<p>Maintenance window to zaplanowany okres, w którym alerty są czasowo wyciszone podczas kontrolowanych zmian.</p><p>Okna powinny mieć jasny czas startu/końca i ownera, aby nie ukrywać realnych incydentów poza zakresem prac.</p>',

    'what_is_mttr_and_how_to_improve_it_question' => 'Czym jest MTTR i jak go poprawić?',
    'what_is_mttr_and_how_to_improve_it_answer' => '<p><strong>MTTR</strong> to średni czas rozwiązania incydentu. Poprawia się go przez szybszą detekcję, triage, mitygację i komunikację.</p><p>Regularne drill tests, aktualne playbooki i standardowe procedury rollbacku skracają czas przywrócenia usługi.</p>',

    'how_to_build_on_call_escalation_policy_question' => 'Jak zbudować politykę eskalacji on-call?',
    'how_to_build_on_call_escalation_policy_answer' => '<p>Określ poziomy krytyczności, czasy reakcji i timer eskalacji. Dla każdego obszaru przypisz primary i secondary ownera.</p><p>Eskalacja powinna być oparta o zasady i czas, a nie improwizację pod presją.</p>',

    'how_to_monitor_login_flow_synthetically_question' => 'Jak monitorować flow logowania przez synthetic checks?',
    'how_to_monitor_login_flow_synthetically_answer' => '<p>Przygotuj scenariusz: otwarcie strony logowania, wysłanie danych testowego konta i walidacja elementu po zalogowaniu.</p><p>Używaj dedykowanych kont testowych z ograniczonymi uprawnieniami i bezpiecznie rotuj sekrety.</p>',

    'how_to_monitor_keyword_on_page_question' => 'Jak monitorować słowo kluczowe lub marker treści na stronie?',
    'how_to_monitor_keyword_on_page_answer' => '<p>Dodaj assertion treści, np. oczekiwany tekst, klucz JSON lub marker HTML, który musi być obecny.</p><p>Wybieraj stabilne markery techniczne zamiast dynamicznego copy marketingowego, aby ograniczyć fałszywe alarmy.</p>',

    'network_error_vs_application_error_monitoring_question' => 'Błąd sieci vs błąd aplikacji: jak to rozróżnić w monitoringu?',
    'network_error_vs_application_error_monitoring_answer' => '<p>Rozdzielaj błędy transportu (DNS, TLS, timeout, połączenie) od błędów aplikacyjnych (5xx, błędna logika, zła treść żądania).</p><p>Obie klasy zwykle wymagają innych odpowiedzialnych osób i innej ścieżki mitygacji.</p>',

    'what_is_error_budget_for_slo_question' => 'Czym jest error budget w monitoringu SLO?',
    'what_is_error_budget_for_slo_answer' => '<p>Error budget to dopuszczalny poziom niedostępności wynikający z SLO, np. 0.1% dla celu 99.9% uptime.</p><p>Tempo spalania budżetu pomaga decydować, kiedy priorytetem jest stabilność zamiast nowych funkcji.</p>',

    'how_to_monitor_webhook_endpoints_question' => 'Jak monitorować endpointy webhook?',
    'how_to_monitor_webhook_endpoints_answer' => '<p>Monitoruj skuteczność odpowiedzi HTTP, opóźnienie i powodzenie przetwarzania webhooka po stronie aplikacji.</p><p>Warto dodać syntetyczne wywołania webhooka i bezpieczne treści testowe, aby sprawdzać cały przepływ.</p>',

    'how_to_monitor_third_party_dependencies_question' => 'Jak monitorować zależności zewnętrzne?',
    'how_to_monitor_third_party_dependencies_answer' => '<p>Wydziel krytycznych dostawców (płatności, auth, DNS, CDN, email) i oznacz ich w monitorach jako dependencies.</p><p>To przyspiesza ocenę, czy incydent jest wewnętrzny czy zewnętrzny oraz ułatwia komunikację z klientami.</p>',

    'how_to_use_status_page_during_incident_question' => 'Jak używać strony statusu podczas incydentu?',
    'how_to_use_status_page_during_incident_answer' => '<p>Opublikuj szybkie potwierdzenie incydentu, a potem aktualizacje w stałych odstępach, nawet jeśli rozwiązanie nie jest jeszcze gotowe.</p><p>Podawaj zakres wpływu i czas kolejnej aktualizacji, aby ograniczyć liczbę zapytań do wsparcia.</p>',

    'what_should_incident_postmortem_include_question' => 'Co powinno być w postmortem incydentu?',
    'what_should_incident_postmortem_include_answer' => '<p>Dobry postmortem zawiera timeline, wpływ na klienta, root cause, czynniki współtowarzyszące i stałe akcje naprawcze z ownerem i terminem.</p><p>Celem jest poprawa systemu i procesu, a nie wskazywanie winnych.</p>',

    'how_to_monitor_api_rate_limits_question' => 'Jak monitorować limity rate limit w API?',
    'how_to_monitor_api_rate_limits_answer' => '<p>Monitoruj nagłówki usage, odsetek odpowiedzi 429, retry behavior i presję kolejek dla endpointów o dużym wolumenie.</p><p>Alertuj przed osiągnięciem limitu twardego, aby mieć czas na throttling lub podniesienie planu.</p>',

    'how_many_monitors_do_i_need_question' => 'Ile monitorów potrzebuję na start?',
    'how_many_monitors_do_i_need_answer' => '<p>Zacznij od krytycznych user journey i kluczowych API, a potem rozszerz o zależności i przypadki brzegowe.</p><p>Lepsza zasada to jeden monitor na krytyczny flow, a nie jeden monitor na każdą podstronę.</p>',

    'how_to_monitor_redirect_chains_question' => 'Jak monitorować łańcuchy przekierowań?',
    'how_to_monitor_redirect_chains_answer' => '<p>Włącz podążanie za redirectami i weryfikuj URL końcowy, kod odpowiedzi, poprawny TLS oraz liczbę hopów.</p><p>Alarmuj przy nieoczekiwanym wydłużeniu łańcucha, bo rośnie latency i spada jakość crawl dla SEO.</p>',

    'can_i_monitor_private_internal_services_question' => 'Czy mogę monitorować usługi prywatne lub wewnętrzne?',
    'can_i_monitor_private_internal_services_answer' => '<p>Tak, przez sondy prywatne, agenty za VPN albo bezpieczny przekaźnik w granicach Twojej sieci.</p><p>Segmentuj dostępy i sekrety, aby monitoring wewnętrzny nie zwiększał skali skutków incydentu.</p>',
];
