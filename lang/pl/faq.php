<?php

return [
    'nav_faq' => 'FAQ',
    'page_title' => 'FAQ monitoringu stron i API',
    'page_description' => 'Praktyczne odpowiedzi o uptime, monitoringu endpointow API, alertach SSL, status page i reakcji na incydenty.',
    'search_label' => 'Szukaj w FAQ',
    'search_placeholder' => 'Szukaj pytan...',
    'read_answer' => 'Zobacz odpowiedz',
    'no_results' => 'Brak wynikow. Sprobuj innego hasla.',
    'back_to_index' => 'Powrot do FAQ',
    'related_questions' => 'Powiazane pytania',
    'cta_title' => 'Chcesz alerty bez szumu?',
    'cta_body' => 'Utworz workspace, dodaj monitory i kieruj incydenty do zespolu w kilka minut.',
    'cta_primary' => 'Utworz konto',
    'cta_secondary' => 'Zaloguj sie',

    'what_is_uptime_monitoring_question' => 'Czym jest monitoring uptime?',
    'what_is_uptime_monitoring_answer' => '<p><strong>Monitoring uptime</strong> regularnie sprawdza, czy Twoja strona lub API sa dostepne i dzialaja poprawnie. Zwykle weryfikuje status HTTP, czas odpowiedzi oraz opcjonalnie reguly tresci.</p><p>Celem jest szybkie wykrycie awarii, natychmiastowy alert dla zespolu i kontrola trendu dostepnosci w czasie.</p>',

    'how_often_should_i_check_my_website_question' => 'Jak czesto sprawdzac strone?',
    'how_often_should_i_check_my_website_answer' => '<p>Wiekszosc zespolow zaczyna od interwalu <strong>1-5 minut</strong>. Dla krytycznych flow, takich jak checkout, logowanie i kluczowe API, zwykle wybiera sie 1 minute.</p><p>Krotszy interwal daj dla endpointow krytycznych biznesowo, a dluzszy dla stron mniej istotnych, aby zachowac balans miedzy szybkoscia i kosztem.</p>',

    'how_do_i_monitor_api_endpoints_question' => 'Jak monitorowac endpointy API?',
    'how_do_i_monitor_api_endpoints_answer' => '<p>Monitoruj endpointy API przez oczekiwany zakres statusow (np. 2xx), limity timeout oraz opcjonalne reguly tresci w odpowiedzi.</p><p>Dla pewnosci monitoruj jednoczesnie szybki endpoint health oraz jeden endpoint biznesowo krytyczny uzywany przez klientow.</p>',

    'how_do_i_monitor_ssl_certificate_expiry_question' => 'Jak monitorowac wygasanie certyfikatu SSL?',
    'how_do_i_monitor_ssl_certificate_expiry_answer' => '<p>Skonfiguruj alerty SSL przed data wygasniecia, np. 30, 14 i 7 dni wczesniej.</p><p>Wczesne alerty SSL chronia przed prostymi awariami, ostrzezeniami przegladarki i problemami z integracjami API.</p>',

    'synthetic_checks_vs_http_checks_question' => 'Synthetic checks vs HTTP checks: jaka jest roznica?',
    'synthetic_checks_vs_http_checks_answer' => '<p><strong>HTTP checks</strong> sa lekkie i szybkie: sprawdzaja status, czas odpowiedzi i proste reguly tresci.</p><p><strong>Synthetic checks</strong> symuluja zachowanie przegladarki lub cala sciezke uzytkownika, dzieki czemu wykrywaja problemy front-end i workflow, ktorych zwykly HTTP check nie zobaczy.</p>',

    'how_do_i_reduce_false_alerts_question' => 'Jak ograniczyc falszywe alerty?',
    'how_do_i_reduce_false_alerts_answer' => '<p>Stosuj kilka zabezpieczen naraz: alert po kilku kolejnych bledach, cooldown oraz maintenance windows podczas planowanych prac.</p><p>Polaczenie baseline i deduplikacji zmniejsza szum, a jednoczesnie nie ukrywa realnych incydentow.</p>',

    'what_is_sla_vs_slo_question' => 'SLA vs SLO: jaka jest roznica w monitoringu?',
    'what_is_sla_vs_slo_answer' => '<p><strong>SLO</strong> to wewnetrzny cel niezawodnosci (np. 99.9% uptime). <strong>SLA</strong> to formalna obietnica dla klienta, czesto z konsekwencjami finansowymi.</p><p>Dobry monitoring raportuje oba poziomy, aby zespol mogl reagowac zanim dojdzie do naruszenia umowy.</p>',

    'what_is_a_status_page_question' => 'Czym jest status page i dlaczego jest wazny?',
    'what_is_a_status_page_answer' => '<p>Status page to publiczny kanal komunikacji o biezacym stanie uslugi oraz historii incydentow.</p><p>W czasie awarii odciaza support i buduje zaufanie dzieki transparentnym aktualizacjom.</p>',

    'what_is_an_incident_response_playbook_question' => 'Czym jest playbook reakcji na incydent?',
    'what_is_an_incident_response_playbook_answer' => '<p>Playbook incydentowy definiuje role i kroki po alercie: triage, komunikacje, mitygacje i postmortem.</p><p>Dobrze opisany playbook skraca czas rozwiazania problemu i podnosi powtarzalnosc reakcji miedzy zmianami.</p>',

    'how_to_choose_a_website_monitoring_plan_question' => 'Jak wybrac plan monitoringu strony?',
    'how_to_choose_a_website_monitoring_plan_answer' => '<p>Dobieraj plan wedlug liczby monitorow, czestotliwosci skanow, liczby kanalow powiadomien oraz potrzeb status page i workflow zespolowego.</p><p>Zacznij od mniejszego planu i skaluj, gdy rosna wolumen monitorow, wymagania reakcji i potrzeby governance.</p>',

    'what_causes_website_downtime_question' => 'Jakie sa najczestsze przyczyny niedostepnosci strony?',
    'what_causes_website_downtime_answer' => '<p>Najczestsze awarie wynikaja z bledow wdrozen, przeciarenia bazy danych, wygasnietych certyfikatow TLS, problemow DNS i awarii dostawcow zewnetrznych.</p><p>Klasyfikowanie incydentow wedlug root cause pomaga szybciej usuwac zrodla powtarzalnych problemow.</p>',

    'multi_region_monitoring_why_important_question' => 'Dlaczego monitoring z wielu regionow jest wazny?',
    'multi_region_monitoring_why_important_answer' => '<p>Check z jednego regionu moze dawac falszywe alarmy, gdy problem dotyczy tylko jednej trasy sieciowej. Multi-region potwierdza, czy problem jest globalny czy lokalny.</p><p>To poprawia jakosc sygnalu i przyspiesza przekazanie incydentu do wlasciwego zespolu.</p>',

    'best_alert_channels_for_uptime_monitoring_question' => 'Jakie kanaly alertow sa najlepsze dla monitoringu uptime?',
    'best_alert_channels_for_uptime_monitoring_answer' => '<p>W praktyce warto miec co najmniej dwa kanaly: szybki (PagerDuty, telefon, push) i wspolpracy (Slack, Teams, email).</p><p>Kanaly powinny byc mapowane do poziomu krytycznosci, aby tylko najwazniejsze incydenty budzily dyzur.</p>',

    'how_to_monitor_cron_jobs_question' => 'Jak monitorowac cron jobs i zadania cykliczne?',
    'how_to_monitor_cron_jobs_answer' => '<p>Najprosciej przez heartbeat: po kazdym poprawnym wykonaniu job wysyla sygnal. Brak sygnalu w czasie oznacza incydent.</p><p>Warto monitorowac nie tylko brak wykonania, ale tez zbyt dlugi czas trwania zadania.</p>',

    'how_to_monitor_domain_expiration_question' => 'Jak monitorowac wygasanie domeny?',
    'how_to_monitor_domain_expiration_answer' => '<p>Ustaw alerty dla terminu wygasania domeny z duzym wyprzedzeniem, np. 60, 30 i 14 dni.</p><p>Powiadomienia powinny trafic do technicznego i biznesowego ownera, aby uniknac punktu pojedynczej awarii.</p>',

    'what_is_maintenance_window_in_monitoring_question' => 'Czym jest maintenance window w monitoringu?',
    'what_is_maintenance_window_in_monitoring_answer' => '<p>Maintenance window to zaplanowany okres, w ktorym alerty sa czasowo wyciszone podczas kontrolowanych zmian.</p><p>Okna powinny miec jasny czas startu/konca i ownera, aby nie ukrywac realnych incydentow poza zakresem prac.</p>',

    'what_is_mttr_and_how_to_improve_it_question' => 'Czym jest MTTR i jak go poprawic?',
    'what_is_mttr_and_how_to_improve_it_answer' => '<p><strong>MTTR</strong> to sredni czas rozwiazania incydentu. Poprawia sie go przez szybsza detekcje, triage, mitygacje i komunikacje.</p><p>Regularne drill tests, aktualne playbooki i standardowe procedury rollbacku skracaja czas przywrocenia uslugi.</p>',

    'how_to_build_on_call_escalation_policy_question' => 'Jak zbudowac polityke eskalacji on-call?',
    'how_to_build_on_call_escalation_policy_answer' => '<p>Okresl poziomy krytycznosci, czasy reakcji i timer eskalacji. Dla kazdego obszaru przypisz primary i secondary ownera.</p><p>Eskalacja powinna byc oparta o zasady i czas, a nie improwizacje pod presja.</p>',

    'how_to_monitor_login_flow_synthetically_question' => 'Jak monitorowac flow logowania przez synthetic checks?',
    'how_to_monitor_login_flow_synthetically_answer' => '<p>Przygotuj scenariusz: otwarcie strony logowania, wyslanie danych testowego konta i walidacja elementu po zalogowaniu.</p><p>Uzywaj dedykowanych kont testowych z ograniczonymi uprawnieniami i bezpiecznie rotuj sekrety.</p>',

    'how_to_monitor_keyword_on_page_question' => 'Jak monitorowac slowo kluczowe lub marker tresci na stronie?',
    'how_to_monitor_keyword_on_page_answer' => '<p>Dodaj assertion tresci, np. oczekiwany tekst, klucz JSON lub marker HTML, ktory musi byc obecny.</p><p>Wybieraj stabilne markery techniczne zamiast dynamicznego copy marketingowego, aby ograniczyc falszywe alarmy.</p>',

    'network_error_vs_application_error_monitoring_question' => 'Blad sieci vs blad aplikacji: jak to rozroznic w monitoringu?',
    'network_error_vs_application_error_monitoring_answer' => '<p>Rozdzielaj bledy transportu (DNS, TLS, timeout, connect) od bledow aplikacyjnych (5xx, bledna logika, zly payload).</p><p>Obie klasy zwykle wymagaja innych ownerow i innej sciezki mitygacji.</p>',

    'what_is_error_budget_for_slo_question' => 'Czym jest error budget w monitoringu SLO?',
    'what_is_error_budget_for_slo_answer' => '<p>Error budget to dopuszczalny poziom niedostepnosci wynikajacy z SLO, np. 0.1% dla celu 99.9% uptime.</p><p>Tempo spalania budzetu pomaga decydowac, kiedy priorytetem jest stabilnosc zamiast nowych funkcji.</p>',

    'how_to_monitor_webhook_endpoints_question' => 'Jak monitorowac endpointy webhook?',
    'how_to_monitor_webhook_endpoints_answer' => '<p>Monitoruj skutecznosc odpowiedzi HTTP, opoznienie i powodzenie przetwarzania webhooka po stronie aplikacji.</p><p>Warto dodac synthetic webhook requests i bezpieczne payloady testowe, aby sprawdzac caly pipeline.</p>',

    'how_to_monitor_third_party_dependencies_question' => 'Jak monitorowac zaleznosci zewnetrzne?',
    'how_to_monitor_third_party_dependencies_answer' => '<p>Wydziel krytycznych dostawcow (platnosci, auth, DNS, CDN, email) i oznacz ich w monitorach jako dependencies.</p><p>To przyspiesza ocene, czy incydent jest wewnetrzny czy zewnetrzny oraz ulatwia komunikacje z klientami.</p>',

    'how_to_use_status_page_during_incident_question' => 'Jak uzywac status page podczas incydentu?',
    'how_to_use_status_page_during_incident_answer' => '<p>Opublikuj szybkie potwierdzenie incydentu, a potem aktualizacje w stalych odstepach, nawet jesli fix nie jest jeszcze gotowy.</p><p>Podawaj zakres wplywu i czas kolejnej aktualizacji, aby ograniczyc liczbe zapytan do supportu.</p>',

    'what_should_incident_postmortem_include_question' => 'Co powinno byc w postmortem incydentu?',
    'what_should_incident_postmortem_include_answer' => '<p>Dobry postmortem zawiera timeline, wplyw na klienta, root cause, czynniki wspoltowarzyszace i stale akcje naprawcze z ownerem i terminem.</p><p>Celem jest poprawa systemu i procesu, a nie wskazywanie winnych.</p>',

    'how_to_monitor_api_rate_limits_question' => 'Jak monitorowac limity rate limit w API?',
    'how_to_monitor_api_rate_limits_answer' => '<p>Monitoruj naglowki usage, odsetek odpowiedzi 429, retry behavior i presje kolejek dla endpointow o duzym wolumenie.</p><p>Alertuj przed osiagnieciem limitu twardego, aby miec czas na throttling lub podniesienie planu.</p>',

    'how_many_monitors_do_i_need_question' => 'Ile monitorow potrzebuje na start?',
    'how_many_monitors_do_i_need_answer' => '<p>Zacznij od krytycznych user journey i kluczowych API, a potem rozszerz o zaleznosci i przypadki brzegowe.</p><p>Lepsza zasada to jeden monitor na krytyczny flow, a nie jeden monitor na kazda podstrone.</p>',

    'how_to_monitor_redirect_chains_question' => 'Jak monitorowac lancuchy przekierowan?',
    'how_to_monitor_redirect_chains_answer' => '<p>Wlacz podazanie za redirectami i weryfikuj URL koncowy, kod odpowiedzi, poprawny TLS oraz liczbe hopow.</p><p>Alarmuj przy nieoczekiwanym wydluzeniu lancucha, bo rosnie latency i spada jakosc crawl dla SEO.</p>',

    'can_i_monitor_private_internal_services_question' => 'Czy moge monitorowac uslugi prywatne lub wewnetrzne?',
    'can_i_monitor_private_internal_services_answer' => '<p>Tak, przez private probes, agenty za VPN albo bezpieczny relay w granicach Twojej sieci.</p><p>Segmentuj dostepy i sekrety, aby monitoring wewnetrzny nie zwiekszal blast radius przy incydencie.</p>',
];
