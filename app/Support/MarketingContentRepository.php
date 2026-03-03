<?php

namespace App\Support;

class MarketingContentRepository
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $solutionsCache = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $faqCache = [];

    /** @var array<string, array<string, string>> */
    private const FAQ_EXPANSION_HINTS = [
        'en' => [
            'how-to-monitor-webhook-endpoints' => 'api uptime monitoring and website downtime alerts for webhook pipelines',
            'how-to-monitor-third-party-dependencies' => 'website monitoring tool strategy for third-party providers',
            'synthetic-checks-vs-http-checks' => 'uptime checker selection between synthetic and HTTP checks',
            'what-is-mttr-and-how-to-improve-it' => 'website downtime alerts and incident response speed',
            'how-to-monitor-redirect-chains' => 'website monitoring tool checks for SEO and redirect health',
            'how-to-monitor-login-flow-synthetically' => 'synthetic browser checks for business-critical login flows',
            'how-to-monitor-domain-expiration' => 'domain and DNS monitoring to avoid unplanned downtime',
            'what-is-uptime-monitoring' => 'uptime checker fundamentals for teams choosing monitoring software',
            'multi-region-monitoring-why-important' => 'multi-location confirmation for reliable downtime detection',
            'how-do-i-reduce-false-alerts' => 'signal quality and alert routing to cut noisy incidents',
            'how-to-monitor-api-rate-limits' => 'api uptime monitoring with quota and throttling controls',
            'what-is-an-incident-response-playbook' => 'repeatable incident workflows and status page communication',
        ],
        'pl' => [
            'how-to-monitor-webhook-endpoints' => 'monitoring API i alerty przestojów dla pipeline webhooków',
            'how-to-monitor-third-party-dependencies' => 'strategia monitoringu zależności zewnętrznych',
            'synthetic-checks-vs-http-checks' => 'dobór między checkami synthetic i HTTP uptime',
            'what-is-mttr-and-how-to-improve-it' => 'skrót czasu reakcji i sensowne alerty przestojów',
            'how-to-monitor-redirect-chains' => 'kontrola przekierowań pod SEO i stabilność ścieżek',
            'how-to-monitor-login-flow-synthetically' => 'synthetic monitoring dla krytycznego logowania',
            'how-to-monitor-domain-expiration' => 'monitoring domeny i DNS, aby uniknąć awarii',
            'what-is-uptime-monitoring' => 'podstawy monitoringu uptime przy wyborze narzędzia',
            'multi-region-monitoring-why-important' => 'weryfikacja multi-region dla pewnej detekcji awarii',
            'how-do-i-reduce-false-alerts' => 'jakość sygnału i routing alertów zamiast szumu',
            'how-to-monitor-api-rate-limits' => 'monitoring limitów API i bezpieczne progi',
            'what-is-an-incident-response-playbook' => 'powtarzalny proces incydentowy i komunikacja status page',
        ],
    ];

    /** @var array<string, array<string, array<string, mixed>>> */
    private const BOFU_SOLUTION_OVERRIDES = [
        'en' => [
            'uptimerobot-alternative-for-smb' => [
                'summary' => 'UptimeRobot alternative for SMB teams that need cleaner alerting, incident workflow, and better status communication.',
                'problem' => 'Teams outgrow simple uptime checks when they need clear ownership, incident timeline, and lower alert noise.',
                'meta_description' => 'UptimeRobot alternative for SMB teams: compare alert quality, incident workflow, and status communication before you migrate monitoring.',
                'body' => '<p>If you are searching for an <strong>UptimeRobot alternative</strong>, the real decision is operational: how fast you detect issues, how cleanly you escalate, and how clearly you communicate to customers.</p><p>Use a practical migration scorecard: check interval, false positive rate, alert routing, and incident timeline quality. Then run both tools in parallel for one week on the same critical URLs.</p><p>In TruMonit you can combine monitor checks, incident tracking, and status page updates in one workflow, so SMB teams avoid context switching during outages.</p>',
            ],
            'statuscake-alternative-with-status-pages' => [
                'summary' => 'StatusCake alternative with stronger status page workflow for teams that need clear external communication during incidents.',
                'problem' => 'Monitoring alone is not enough when customers expect fast and consistent public status updates.',
                'meta_description' => 'StatusCake alternative with status page workflow: compare downtime detection, customer updates, and incident communication quality.',
                'body' => '<p>A good <strong>StatusCake alternative</strong> should improve not only checks, but also communication. During incidents, customers need one reliable status page with frequent and consistent updates.</p><p>Evaluate three areas: detection speed, update cadence, and post-incident transparency. If your team has alerts but no clear update process, trust drops even when uptime improves.</p><p>TruMonit connects incident events with status updates so your public communication follows operations, not manual copy and paste.</p>',
            ],
            'website-monitoring-tool-for-agencies' => [
                'summary' => 'Website monitoring tool for agencies that manage many client sites and need fast triage plus shareable reporting.',
                'problem' => 'Agencies lose margin when one operator manually checks many dashboards and sends client updates by hand.',
                'meta_description' => 'Website monitoring tool for agencies: monitor many client sites, route alerts, and deliver clear incident reporting without manual chaos.',
                'body' => '<p>A <strong>website monitoring tool</strong> for agencies must handle scale: many domains, many priorities, and many stakeholders. The winning setup is tags, ownership, and repeatable escalation rules.</p><p>Build one agency workflow: monitor groups by client, severity-based routing, and a simple report rhythm. That reduces reaction time and protects account retention.</p><p>With TruMonit, agencies can move from ad hoc checks to a repeatable incident process with better handoff between technical and account teams.</p>',
            ],
            'incident-management-software-for-smb-saas' => [
                'summary' => 'Incident management software for SMB SaaS teams that need clear escalation, accountability, and faster recovery.',
                'problem' => 'Without a defined incident process, teams detect issues but still lose time on handoffs and unclear ownership.',
                'meta_description' => 'Incident management software for SMB SaaS: structure escalation, improve accountability, and reduce time to recover from outages.',
                'body' => '<p>For SMB SaaS, <strong>incident management software</strong> should make response predictable. You need one place for signal, owner, timeline, and customer communication.</p><p>Start with simple rules: severity levels, on-call owner, and update cadence. Then review each incident to remove recurring bottlenecks from your process.</p><p>TruMonit supports this model by linking monitors, incidents, and public updates so response is faster and less stressful for small teams.</p>',
            ],
            'status-page-software-for-b2b-saas' => [
                'summary' => 'Status page software for B2B SaaS that turns outage updates into consistent customer communication.',
                'problem' => 'B2B customers expect timely status updates; delays create support load and trust loss.',
                'meta_description' => 'Status page software for B2B SaaS: publish consistent incident updates, reduce support pressure, and protect customer trust.',
                'body' => '<p><strong>Status page software</strong> for B2B SaaS is a communication product, not only a branding widget. Enterprise buyers look for clarity, consistency, and time-stamped updates.</p><p>Define your incident communication format before the next outage: what gets published, how often, and who approves updates. This alone reduces inbound support pressure.</p><p>TruMonit helps teams keep status communication aligned with real incident progress from detection through resolution.</p>',
            ],
            'website-downtime-alerts-setup' => [
                'summary' => 'Website downtime alerts setup guide for teams that need fewer false alarms and faster real incident response.',
                'problem' => 'Many setups alert too often and too early, which creates noise and slower response to real outages.',
                'meta_description' => 'Website downtime alerts setup: configure thresholds, routing, and cooldown to reduce noise and react faster to real outages.',
                'body' => '<p>A good <strong>website downtime alerts</strong> setup balances speed and confidence. If you alert on every short spike, teams mute notifications and miss critical events.</p><p>Use failure confirmation, cooldown, and severity routing. Then test escalation with a controlled outage simulation, not only in production chaos.</p><p>TruMonit gives you a practical alerting baseline that can be tuned per service criticality.</p>',
            ],
            'uptime-checker-for-multi-site-teams' => [
                'summary' => 'Uptime checker for multi-site teams that need one view across many domains, priorities, and response owners.',
                'problem' => 'Managing many websites without prioritization causes alert overload and delayed response on critical properties.',
                'meta_description' => 'Uptime checker for multi-site teams: monitor many domains with priority routing and clear ownership for faster response.',
                'body' => '<p>An <strong>uptime checker</strong> for multi-site operations should prioritize what matters first. Not every domain has the same business impact, so alerts must follow service criticality.</p><p>Create tiers, assign owners, and route by severity. This turns a long monitor list into an operational queue your team can actually process.</p><p>TruMonit supports multi-site monitoring with grouped workflows and practical escalation rules.</p>',
            ],
            'api-uptime-monitoring-for-critical-endpoints' => [
                'summary' => 'API uptime monitoring for critical endpoints with latency thresholds, status validation, and incident-ready alerting.',
                'problem' => 'API outages often start as partial failures, high latency, or auth errors that basic uptime checks miss.',
                'meta_description' => 'API uptime monitoring for critical endpoints: track status, latency, and error patterns to detect failures before customers complain.',
                'body' => '<p><strong>API uptime monitoring</strong> should include status, latency, and error-class visibility. A green 200 can still hide degraded user experience when latency explodes.</p><p>Track p95 latency, auth failures, and retry spikes for the endpoints that drive revenue or core product actions.</p><p>TruMonit helps teams detect API degradation earlier and escalate with clearer technical context.</p>',
            ],
            'ping-monitoring-vs-http-monitoring' => [
                'summary' => 'Ping monitoring vs HTTP monitoring explained for teams choosing the right check type for each service.',
                'problem' => 'Teams often choose one check type for everything and miss incidents that need application-level validation.',
                'meta_description' => 'Ping monitoring vs HTTP monitoring: choose the right check type by risk, service role, and required incident signal quality.',
                'body' => '<p><strong>Ping monitoring</strong> answers network reachability, while HTTP checks validate application response. You need both, but not in the same way for every service.</p><p>Use ping for infrastructure heartbeat and HTTP for user-facing reliability. Then route alerts differently so infrastructure and app teams get the right signal.</p><p>TruMonit supports this split so your monitoring model reflects real incident ownership.</p>',
            ],
            'port-monitoring-for-infrastructure-services' => [
                'summary' => 'Port monitoring for infrastructure services where TCP availability is a key early signal of service health.',
                'problem' => 'Service failures can start with closed or unstable ports before full HTTP checks show visible errors.',
                'meta_description' => 'Port monitoring for infrastructure services: detect TCP availability issues early and route incidents to the right owners.',
                'body' => '<p><strong>Port monitoring</strong> is useful when service health depends on TCP reachability: databases, mail relays, custom services, and internal gateways.</p><p>Use port checks as an early-warning layer, then correlate with higher-level checks to avoid false conclusions.</p><p>TruMonit helps infrastructure-focused teams add this signal into one incident workflow with clear escalation.</p>',
            ],
            'website-downtime-alerts-for-ecommerce' => [
                'summary' => 'Website downtime alerts for ecommerce stores where checkout and payment paths directly impact revenue.',
                'problem' => 'Generic uptime alerts miss conversion-critical failures like checkout degradation and payment disruptions.',
                'meta_description' => 'Website downtime alerts for ecommerce: protect checkout, payment, and key conversion paths with revenue-focused alerting.',
                'body' => '<p>In ecommerce, <strong>website downtime alerts</strong> must focus on revenue paths: product pages, cart, checkout, and payment callback flow.</p><p>Prioritize alerts by conversion impact, not only by technical error count. This helps teams respond where each minute has direct revenue cost.</p><p>TruMonit supports ecommerce monitoring by aligning alert severity with business-critical customer journeys.</p>',
            ],
            'free-uptime-monitoring-vs-paid-plans' => [
                'summary' => 'Free uptime monitoring vs paid plans: where free is enough and where paid features protect business continuity.',
                'problem' => 'Teams stay on free plans too long and discover limits only during real incidents.',
                'meta_description' => 'Free uptime monitoring vs paid plans: compare alerting limits, workflow depth, and reliability trade-offs before scaling.',
                'body' => '<p><strong>Free uptime monitoring</strong> is useful for basic visibility, but paid plans usually unlock cleaner alerting, incident workflow, and reporting depth.</p><p>The right decision depends on risk: number of critical services, required response time, and customer communication expectations.</p><p>Use a simple rule: if outages require coordinated response and external updates, paid workflow features quickly pay for themselves.</p>',
            ],
        ],
        'pl' => [
            'uptimerobot-alternative-for-smb' => [
                'title' => 'Alternatywa dla UptimeRobot dla zespołów SMB',
                'meta_title' => 'Alternatywa dla UptimeRobot dla zespołów SMB | TruMonit',
                'summary' => 'Alternatywa dla UptimeRobot dla małych zespołów, które potrzebują lepszych alertów, incydentów i komunikacji statusowej.',
                'problem' => 'Prosty uptime przestaje wystarczać, gdy potrzebujesz odpowiedzialności, eskalacji i mniejszego szumu alertowego.',
                'meta_description' => 'Alternatywa dla UptimeRobot dla SMB: porównaj jakość alertów, workflow incydentów i komunikację statusową przed migracją.',
                'body' => '<p>Jeśli szukasz <strong>alternatywy dla UptimeRobot</strong>, kluczowa jest operacyjność: jak szybko wykrywasz problem, jak dobrze eskalujesz i jak komunikujesz awarię klientom.</p><p>Porównaj narzędzia na wspólnym zestawie URL-i: szybkość detekcji, fałszywe alarmy, routing alertów i jakość osi czasu incydentu.</p><p>W TruMonit łączysz checki, incydenty i status page w jednym procesie, bez ręcznego przepinania między narzędziami.</p>',
            ],
            'statuscake-alternative-with-status-pages' => [
                'title' => 'Alternatywa dla StatusCake z lepszym status page',
                'meta_title' => 'Alternatywa dla StatusCake z lepszym status page | TruMonit',
                'summary' => 'Alternatywa dla StatusCake z mocniejszym procesem status page i lepszą komunikacją podczas incydentów.',
                'problem' => 'Sama detekcja awarii nie wystarcza, gdy klienci oczekują regularnych i spójnych aktualizacji statusu.',
                'meta_description' => 'Alternatywa dla StatusCake ze status page: porównaj detekcję awarii, aktualizacje dla klientów i jakość komunikacji incydentów.',
                'body' => '<p>Dobra <strong>alternatywa dla StatusCake</strong> powinna poprawić nie tylko monitorowanie, ale też komunikację do klientów. W praktyce to status page buduje zaufanie w trakcie awarii.</p><p>Oceń trzy obszary: tempo wykrycia, rytm aktualizacji i transparentność po incydencie. Bez tego support rośnie, a klienci tracą pewność.</p><p>TruMonit łączy zdarzenia incydentowe z aktualizacjami statusu, dzięki czemu komunikacja idzie razem z realnym postępem naprawy.</p>',
            ],
            'website-monitoring-tool-for-agencies' => [
                'title' => 'Website monitoring tool dla agencji',
                'meta_title' => 'Website monitoring tool dla agencji | TruMonit',
                'summary' => 'Website monitoring tool dla agencji, które obsługują wiele serwisów klientów i potrzebują szybkiego triage.',
                'problem' => 'Agencje tracą czas i marżę, gdy operator ręcznie sprawdza wiele paneli i wysyła aktualizacje ręcznie.',
                'meta_description' => 'Website monitoring tool dla agencji: monitoruj wiele serwisów klientów, routuj alerty i raportuj incydenty bez chaosu.',
                'body' => '<p><strong>Website monitoring tool</strong> dla agencji musi skalować się na wiele domen i wielu interesariuszy. Klucz to tagowanie, priorytety i odpowiedzialność.</p><p>Zbuduj jeden workflow: grupy monitorów per klient, routing alertów per krytyczność i stały rytm raportów.</p><p>TruMonit pomaga przejść z ręcznych kontroli na powtarzalny proces incydentowy, który łatwo pokazać klientowi.</p>',
            ],
            'incident-management-software-for-smb-saas' => [
                'title' => 'Incident management software dla SMB SaaS',
                'meta_title' => 'Incident management software dla SMB SaaS | TruMonit',
                'summary' => 'Incident management software dla zespołów SMB SaaS, które chcą szybszej eskalacji i krótszego czasu naprawy.',
                'problem' => 'Bez procesu incydentowego nawet dobra detekcja nie daje szybkiej reakcji i powoduje chaos w zespole.',
                'meta_description' => 'Incident management software dla SMB SaaS: uporządkuj eskalację, odpowiedzialność i czas naprawy po awariach.',
                'body' => '<p>Dla SMB SaaS <strong>incident management software</strong> ma dawać przewidywalny proces: sygnał, owner, oś czasu i komunikację do klientów.</p><p>Ustal prosty model: poziomy severity, osoba on-call i rytm aktualizacji podczas incydentu. Potem poprawiaj proces po każdym zdarzeniu.</p><p>TruMonit spina monitory, incydenty i status page, żeby mały zespół działał szybciej bez zbędnej złożoności.</p>',
            ],
            'status-page-software-for-b2b-saas' => [
                'title' => 'Status page software dla B2B SaaS',
                'meta_title' => 'Status page software dla B2B SaaS | TruMonit',
                'summary' => 'Status page software dla B2B SaaS, które chcą spójnej i wiarygodnej komunikacji awarii.',
                'problem' => 'Klienci B2B oczekują czytelnych aktualizacji statusu; opóźnienia podnoszą koszt supportu i psują zaufanie.',
                'meta_description' => 'Status page software dla B2B SaaS: publikuj spójne aktualizacje incydentów i zmniejsz presję na support.',
                'body' => '<p><strong>Status page software</strong> w B2B to narzędzie zaufania, nie tylko widget na stronie. Liczy się tempo i spójność komunikacji podczas incydentu.</p><p>Przed kolejną awarią zdefiniuj schemat publikacji: co komunikujesz, jak często i kto zatwierdza wpis.</p><p>TruMonit pomaga utrzymać zgodność między realnym stanem incydentu a publicznymi aktualizacjami statusu.</p>',
            ],
            'website-downtime-alerts-setup' => [
                'title' => 'Website downtime alerts - konfiguracja',
                'meta_title' => 'Website downtime alerts - konfiguracja | TruMonit',
                'summary' => 'Praktyczna konfiguracja website downtime alerts pod szybką reakcję i mniejszą liczbę fałszywych alarmów.',
                'problem' => 'Źle ustawione alerty generują szum i opóźniają reakcję na realne awarie.',
                'meta_description' => 'Website downtime alerts setup: ustaw progi, routing i cooldown, aby ograniczyć szum i szybciej reagować na awarie.',
                'body' => '<p>Dobrze ustawione <strong>website downtime alerts</strong> to balans między szybkością a pewnością sygnału. Zbyt agresywne progi dają spam i męczą zespół.</p><p>Ustaw potwierdzenie błędu, cooldown i routing per severity, a potem przetestuj eskalację na kontrolowanym scenariuszu.</p><p>TruMonit daje solidny punkt startowy, który można stroić pod krytyczność usług.</p>',
            ],
            'uptime-checker-for-multi-site-teams' => [
                'title' => 'Uptime checker dla zespołów multi-site',
                'meta_title' => 'Uptime checker dla zespołów multi-site | TruMonit',
                'summary' => 'Uptime checker dla zespołów obsługujących wiele domen, priorytetów i właścicieli reakcji.',
                'problem' => 'Bez priorytetów monitoring wielu stron powoduje przeciążenie alertami i wolniejszy triage.',
                'meta_description' => 'Uptime checker dla zespołów multi-site: monitoruj wiele domen z priorytetami i jasną odpowiedzialnością reakcji.',
                'body' => '<p><strong>Uptime checker</strong> dla wielu serwisów musi wspierać priorytety biznesowe. Nie każda domena ma taki sam wpływ na przychód i reputację.</p><p>Podziel monitory na poziomy krytyczności, przypisz ownerów i rozdziel alerty według wpływu.</p><p>TruMonit pozwala zbudować taki model, żeby lista monitorów była kolejką działań, a nie chaosem.</p>',
            ],
            'api-uptime-monitoring-for-critical-endpoints' => [
                'title' => 'API uptime monitoring dla krytycznych endpointów',
                'meta_title' => 'API uptime monitoring dla krytycznych endpointów | TruMonit',
                'summary' => 'API uptime monitoring dla endpointów krytycznych: status, opóźnienie i wzorce błędów pod realny triage.',
                'problem' => 'Problemy API często zaczynają się od degradacji i opóźnień, zanim pojawi się pełna niedostępność.',
                'meta_description' => 'API uptime monitoring dla krytycznych endpointów: kontroluj status, opóźnienie i błędy zanim ucierpią użytkownicy.',
                'body' => '<p><strong>API uptime monitoring</strong> powinien obejmować status, latency i klasy błędów. Sam kod 200 nie mówi, czy użytkownik ma dobrą jakość działania.</p><p>Monitoruj p95, błędy autoryzacji i skoki retry dla endpointów wpływających na kluczowe akcje produktu.</p><p>TruMonit pomaga wykryć degradację API wcześniej i eskalować z lepszym kontekstem technicznym.</p>',
            ],
            'ping-monitoring-vs-http-monitoring' => [
                'title' => 'Ping monitoring vs HTTP monitoring',
                'meta_title' => 'Ping monitoring vs HTTP monitoring | TruMonit',
                'summary' => 'Ping monitoring vs HTTP monitoring: jak dobrać typ checka do usługi i ograniczyć błędne wnioski.',
                'problem' => 'Jeden typ checka dla wszystkich usług nie pokazuje pełnego obrazu incydentu.',
                'meta_description' => 'Ping monitoring vs HTTP monitoring: dobierz typ checka do ryzyka usługi i oczekiwanej jakości sygnału incydentu.',
                'body' => '<p><strong>Ping monitoring</strong> odpowiada na pytanie o dostępność sieci, a HTTP monitoring sprawdza warstwę aplikacji. Oba są potrzebne, ale do innych celów.</p><p>Ping traktuj jako heartbeat infrastruktury, HTTP jako walidację ścieżki użytkownika.</p><p>TruMonit wspiera ten podział, dzięki czemu alert trafia do właściwego zespołu i szybciej kończy się naprawą.</p>',
            ],
            'port-monitoring-for-infrastructure-services' => [
                'title' => 'Port monitoring dla usług infrastrukturalnych',
                'meta_title' => 'Port monitoring dla usług infrastrukturalnych | TruMonit',
                'summary' => 'Port monitoring dla usług, gdzie dostępność TCP jest wczesnym sygnałem problemów operacyjnych.',
                'problem' => 'Awaria usługi bywa widoczna na poziomie portu wcześniej niż w checkach HTTP.',
                'meta_description' => 'Port monitoring dla usług infrastrukturalnych: wykrywaj problemy TCP wcześniej i eskaluj do właściwych ownerów.',
                'body' => '<p><strong>Port monitoring</strong> sprawdza się przy usługach opartych o TCP: bazy danych, bramy, serwisy wewnętrzne, relaye.</p><p>Używaj go jako warstwy wczesnego ostrzegania, a potem koreluj z checkami wyższego poziomu.</p><p>W TruMonit ten sygnał wpada do tego samego procesu incydentowego, więc reakcja jest szybsza i czytelniejsza.</p>',
            ],
            'website-downtime-alerts-for-ecommerce' => [
                'title' => 'Website downtime alerts dla e-commerce',
                'meta_title' => 'Website downtime alerts dla e-commerce | TruMonit',
                'summary' => 'Website downtime alerts dla e-commerce, gdzie checkout i płatności bezpośrednio wpływają na przychód.',
                'problem' => 'Ogólne alerty uptime pomijają awarie na ścieżkach konwersji i powodują straty przychodowe.',
                'meta_description' => 'Website downtime alerts dla e-commerce: chroń checkout, płatności i ścieżki konwersji alertami pod przychód.',
                'body' => '<p>W e-commerce <strong>website downtime alerts</strong> powinny być zorientowane na przychód: karta produktu, koszyk, checkout i płatność.</p><p>Priorytetyzuj alerty według wpływu na konwersję, a nie tylko według samego błędu technicznego.</p><p>TruMonit pomaga powiązać krytyczność alertu z wartością biznesową ścieżki użytkownika.</p>',
            ],
            'free-uptime-monitoring-vs-paid-plans' => [
                'title' => 'Free uptime monitoring vs plany płatne',
                'meta_title' => 'Free uptime monitoring vs plany płatne | TruMonit',
                'summary' => 'Free uptime monitoring vs paid: gdzie darmowy plan wystarcza, a gdzie płatne funkcje realnie obniżają ryzyko.',
                'problem' => 'Zespoły często za późno przechodzą na plan płatny i odkrywają limity dopiero przy incydencie.',
                'meta_description' => 'Free uptime monitoring vs paid plans: porównaj limity alertów, workflow incydentów i opłacalność skali.',
                'body' => '<p><strong>Free uptime monitoring</strong> to dobry start, ale wraz z krytycznością usług rośnie potrzeba lepszych alertów, workflow i raportowania.</p><p>Decyzję podejmuj na podstawie ryzyka: liczby usług krytycznych, wymaganego czasu reakcji i oczekiwań klientów.</p><p>Jeśli awaria wymaga koordynacji i komunikacji z klientem, plan płatny zwykle szybko się zwraca.</p>',
            ],
        ],
    ];

    /** @var array<string, array<string, string>> */
    private const ALTERNATIVE_PROFILES = [
        'uptimerobot-alternative-for-smb' => [
            'competitor' => 'UptimeRobot',
            'segment_en' => 'SMB teams that started with basic uptime checks',
            'segment_pl' => 'zespoly SMB, ktore zaczely od prostych checkow uptime',
            'competitor_best_en' => 'you only need basic checks and simple alerting',
            'competitor_best_pl' => 'wystarcza Ci podstawowy check i proste alerty',
            'switch_trigger_en' => 'alert noise grows and incidents need ownership plus status updates',
            'switch_trigger_pl' => 'rosnie szum alertow i incydenty wymagaja ownership oraz status update',
        ],
        'statuscake-alternative-with-status-pages' => [
            'competitor' => 'StatusCake',
            'segment_en' => 'teams focused on external uptime visibility',
            'segment_pl' => 'zespoly skupione na widocznosci uptime dla klientow',
            'competitor_best_en' => 'you mainly need straightforward checks and notifications',
            'competitor_best_pl' => 'potrzebujesz glownie prostych checkow i powiadomien',
            'switch_trigger_en' => 'you need stronger incident-to-status workflow and less manual communication',
            'switch_trigger_pl' => 'potrzebujesz mocniejszego workflow incydent-status i mniej recznej komunikacji',
        ],
        'pingdom-alternative-for-smb' => [
            'competitor' => 'Pingdom',
            'segment_en' => 'SMB teams with classic uptime-first setup',
            'segment_pl' => 'zespoly SMB z klasycznym podejsciem uptime-first',
            'competitor_best_en' => 'classic uptime reporting is enough for your operations',
            'competitor_best_pl' => 'klasyczne raporty uptime sa dla Ciebie wystarczajace',
            'switch_trigger_en' => 'you need faster triage and cleaner escalation policy',
            'switch_trigger_pl' => 'potrzebujesz szybszego triage i czystszej polityki eskalacji',
        ],
        'better-stack-alternative-for-smb' => [
            'competitor' => 'Better Stack',
            'segment_en' => 'lean teams that combine monitoring and ops quickly',
            'segment_pl' => 'zwinne zespoly laczace monitoring i ops w szybkim tempie',
            'competitor_best_en' => 'you prefer broad toolbox flexibility over strict process',
            'competitor_best_pl' => 'wolisz szeroka elastycznosc narzedzi ponad sztywny proces',
            'switch_trigger_en' => 'you want simpler ownership and predictable incident handoff',
            'switch_trigger_pl' => 'chcesz prostszego ownership i przewidywalnego handoff incydentow',
        ],
        'datadog-synthetics-alternative-for-smb' => [
            'competitor' => 'Datadog Synthetics',
            'segment_en' => 'product teams with advanced synthetic checks',
            'segment_pl' => 'zespoly produktowe z rozbudowanymi checkami synthetic',
            'competitor_best_en' => 'you already run broad Datadog stack and need deep integration',
            'competitor_best_pl' => 'masz juz szeroki stack Datadog i potrzebujesz glebokiej integracji',
            'switch_trigger_en' => 'you need lower operational overhead and clearer pricing for SMB',
            'switch_trigger_pl' => 'potrzebujesz mniejszego narzutu operacyjnego i czytelniejszego kosztu dla SMB',
        ],
        'new-relic-synthetics-alternative' => [
            'competitor' => 'New Relic Synthetics',
            'segment_en' => 'teams validating journeys with synthetic monitoring',
            'segment_pl' => 'zespoly walidujace sciezki uzytkownika przez synthetic monitoring',
            'competitor_best_en' => 'you prioritize deep New Relic ecosystem alignment',
            'competitor_best_pl' => 'priorytetem jest dla Ciebie zgodnosc z ekosystemem New Relic',
            'switch_trigger_en' => 'you want more focused uptime workflow for SMB operations',
            'switch_trigger_pl' => 'chcesz bardziej skupionego workflow uptime dla operacji SMB',
        ],
        'site24x7-alternative-for-teams' => [
            'competitor' => 'Site24x7',
            'segment_en' => 'teams managing mixed infrastructure and websites',
            'segment_pl' => 'zespoly monitorujace mieszana infrastrukture i strony',
            'competitor_best_en' => 'you need broad infrastructure-style monitoring breadth',
            'competitor_best_pl' => 'potrzebujesz szerokiego zakresu monitoringu infrastruktury',
            'switch_trigger_en' => 'you need simpler daily operations and faster incident ownership',
            'switch_trigger_pl' => 'potrzebujesz prostszej codziennej operacji i szybszego ownership incydentow',
        ],
        'uptrends-alternative-for-smb' => [
            'competitor' => 'Uptrends',
            'segment_en' => 'SMB teams tracking availability and user paths',
            'segment_pl' => 'zespoly SMB kontrolujace dostepnosc i sciezki uzytkownika',
            'competitor_best_en' => 'your process works and you mainly need stable checks',
            'competitor_best_pl' => 'Twoj proces dziala i potrzebujesz glownie stabilnych checkow',
            'switch_trigger_en' => 'you need tighter escalation and incident communication loop',
            'switch_trigger_pl' => 'potrzebujesz ciasniejszej petli eskalacji i komunikacji incydentowej',
        ],
        'freshping-alternative-for-small-teams' => [
            'competitor' => 'Freshping',
            'segment_en' => 'small teams with lightweight uptime stack',
            'segment_pl' => 'male zespoly z lekkim stackiem uptime',
            'competitor_best_en' => 'you only need a very simple free-style monitoring baseline',
            'competitor_best_pl' => 'wystarcza Ci bardzo prosty baseline monitoringu',
            'switch_trigger_en' => 'you need more mature incident process as team and risk grow',
            'switch_trigger_pl' => 'potrzebujesz dojrzalszego procesu incydentowego wraz ze wzrostem zespolu i ryzyka',
        ],
        'hetrixtools-alternative-for-agencies' => [
            'competitor' => 'HetrixTools',
            'segment_en' => 'agencies and multi-client operations teams',
            'segment_pl' => 'agencje i zespoly obslugujace wielu klientow',
            'competitor_best_en' => 'you mostly need status visibility per client',
            'competitor_best_pl' => 'potrzebujesz glownie widocznosci statusu per klient',
            'switch_trigger_en' => 'you need stronger owner routing and repeatable client communication',
            'switch_trigger_pl' => 'potrzebujesz mocniejszego routingu ownerow i powtarzalnej komunikacji z klientem',
        ],
        'hyperping-alternative-with-incident-workflow' => [
            'competitor' => 'Hyperping',
            'segment_en' => 'product teams with modern uptime UI expectations',
            'segment_pl' => 'zespoly produktowe oczekujace nowoczesnego UX monitoringu',
            'competitor_best_en' => 'design-first monitoring is your main priority',
            'competitor_best_pl' => 'priorytetem jest dla Ciebie design-first w monitoringu',
            'switch_trigger_en' => 'you need stricter incident workflow and operational governance',
            'switch_trigger_pl' => 'potrzebujesz bardziej rygorystycznego workflow incydentow i governance operacyjnego',
        ],
        'checkly-alternative-for-website-monitoring' => [
            'competitor' => 'Checkly',
            'segment_en' => 'dev-heavy teams monitoring journeys with tests',
            'segment_pl' => 'zespoly dev-heavy monitorujace sciezki testami',
            'competitor_best_en' => 'engineering-centered check-as-code is your core model',
            'competitor_best_pl' => 'core modelem jest u Ciebie engineering i check-as-code',
            'switch_trigger_en' => 'you need less engineering overhead for business-side monitoring',
            'switch_trigger_pl' => 'potrzebujesz mniejszego narzutu engineering przy monitoringu biznesowym',
        ],
        'sematext-alternative-for-uptime-monitoring' => [
            'competitor' => 'Sematext',
            'segment_en' => 'teams combining logs, metrics, and uptime in one suite',
            'segment_pl' => 'zespoly laczace logi, metryki i uptime w jednym pakiecie',
            'competitor_best_en' => 'you need broader observability depth beyond uptime workflow',
            'competitor_best_pl' => 'potrzebujesz szerszej observability wykraczajacej poza uptime workflow',
            'switch_trigger_en' => 'you want focused uptime and incident process without extra complexity',
            'switch_trigger_pl' => 'chcesz skupionego procesu uptime i incydentow bez dodatkowej zlozonosci',
        ],
        'appsignal-uptime-alternative' => [
            'competitor' => 'AppSignal Uptime',
            'segment_en' => 'teams close to application performance workflows',
            'segment_pl' => 'zespoly bliskie workflow application performance',
            'competitor_best_en' => 'APM-first context is more important than process standardization',
            'competitor_best_pl' => 'kontekst APM-first jest wazniejszy niz standaryzacja procesu',
            'switch_trigger_en' => 'you need clearer public incident communication with simple ownership',
            'switch_trigger_pl' => 'potrzebujesz czytelniejszej publicznej komunikacji incydentow i prostego ownership',
        ],
        'statuspage-alternative-for-saas' => [
            'competitor' => 'Statuspage',
            'segment_en' => 'SaaS teams where public status trust is critical',
            'segment_pl' => 'zespoly SaaS, gdzie kluczowe jest zaufanie do status page',
            'competitor_best_en' => 'status communication is your core need and monitoring lives elsewhere',
            'competitor_best_pl' => 'status communication jest core, a monitoring masz osobno',
            'switch_trigger_en' => 'you need detection, incident ownership, and status updates in one loop',
            'switch_trigger_pl' => 'potrzebujesz jednej petli: detekcja, ownership incydentu i status update',
        ],
        'cachet-alternative-with-hosted-status-page' => [
            'competitor' => 'Cachet',
            'segment_en' => 'teams considering self-hosted status solutions',
            'segment_pl' => 'zespoly rozwazajace self-hosted status page',
            'competitor_best_en' => 'you accept self-hosting overhead for full control',
            'competitor_best_pl' => 'akceptujesz narzut self-hostingu w zamian za pelna kontrole',
            'switch_trigger_en' => 'you need hosted reliability and less maintenance burden',
            'switch_trigger_pl' => 'potrzebujesz niezawodnosci hosted i mniejszego burden utrzymaniowego',
        ],
        'updown-io-alternative-for-growing-teams' => [
            'competitor' => 'Updown.io',
            'segment_en' => 'growing teams with cost-sensitive uptime checks',
            'segment_pl' => 'rosnace zespoly z naciskiem na koszt monitoringu uptime',
            'competitor_best_en' => 'you only need very lean check execution and billing simplicity',
            'competitor_best_pl' => 'potrzebujesz tylko lekkiego check execution i prostego rozliczenia',
            'switch_trigger_en' => 'you need stronger escalation model and team handoff as incidents grow',
            'switch_trigger_pl' => 'potrzebujesz mocniejszego modelu eskalacji i handoff przy rosnacej skali incydentow',
        ],
        'cronitor-alternative-for-cron-monitoring' => [
            'competitor' => 'Cronitor',
            'segment_en' => 'teams where cron and scheduled task reliability matters',
            'segment_pl' => 'zespoly, gdzie krytyczna jest niezawodnosc cron i taskow cyklicznych',
            'competitor_best_en' => 'cron-centric workflows are your main monitoring concern',
            'competitor_best_pl' => 'workflow cron-centric jest Twoim glownym obszarem monitoringu',
            'switch_trigger_en' => 'you need cron plus website and incident communication in one place',
            'switch_trigger_pl' => 'potrzebujesz cron plus website i komunikacji incydentowej w jednym miejscu',
        ],
        'ohdear-alternative-for-smb' => [
            'competitor' => 'Oh Dear',
            'segment_en' => 'SMB teams looking for all-in-one site health checks',
            'segment_pl' => 'zespoly SMB szukajace all-in-one dla health checkow strony',
            'competitor_best_en' => 'you prioritize broad website checks over incident workflow depth',
            'competitor_best_pl' => 'priorytetem sa szerokie checki strony, nie glebokosc workflow incydentow',
            'switch_trigger_en' => 'you need clearer owner model and communication process during downtime',
            'switch_trigger_pl' => 'potrzebujesz czytelniejszego modelu ownerow i procesu komunikacji podczas downtime',
        ],
        'supermonitoring-alternative-for-agencies' => [
            'competitor' => 'Super Monitoring',
            'segment_en' => 'agencies handling many clients and SLA expectations',
            'segment_pl' => 'agencje obslugujace wielu klientow i oczekiwania SLA',
            'competitor_best_en' => 'you need straightforward client monitoring baseline only',
            'competitor_best_pl' => 'potrzebujesz jedynie prostego baseline monitoringu klientow',
            'switch_trigger_en' => 'you need repeatable incident handling and better client-facing status updates',
            'switch_trigger_pl' => 'potrzebujesz powtarzalnego incident handling i lepszych status update dla klientow',
        ],
    ];

    public function normalizeLocale(string $locale): string
    {
        return $locale === 'pl' ? 'pl' : 'en';
    }

    /**
     * @return array<int, array{slug:string,title:string,meta_title:string,meta_description:string,summary:string,body:string,problem:string,approach:array<int,string>,proof:array<int,string>,checklist:array<int,string>,pitfalls:array<int,string>,related_faq:array<int,string>,order:int}>
     */
    public function solutionPages(string $locale): array
    {
        $locale = $this->normalizeLocale($locale);

        if (isset($this->solutionsCache[$locale])) {
            return $this->solutionsCache[$locale];
        }

        $fallback = $this->loadPhpMap(resource_path('content/solutions/en'));
        $localized = $locale === 'en'
            ? $fallback
            : $this->loadPhpMap(resource_path("content/solutions/{$locale}"));

        $slugs = array_values(array_unique(array_merge(array_keys($fallback), array_keys($localized))));
        $pages = [];

        foreach ($slugs as $slug) {
            $raw = $localized[$slug] ?? $fallback[$slug] ?? null;
            if (! is_array($raw)) {
                continue;
            }

            $raw = $this->applyBofuSolutionOverrides($slug, $raw, $locale);
            $comparison = $this->buildAlternativeComparisonData($slug, $locale);
            if (is_array($comparison)) {
                $raw = $this->enrichAlternativeRaw($raw, $comparison, $locale);
            }

            $title = trim((string) ($raw['title'] ?? ''));
            $summary = trim((string) ($raw['summary'] ?? ''));
            if ($title === '' || $summary === '') {
                continue;
            }

            $pages[] = [
                'slug' => $slug,
                'title' => $title,
                'meta_title' => trim((string) ($raw['meta_title'] ?? $title)),
                'meta_description' => $this->normalizeSolutionMetaDescription(
                    (string) ($raw['meta_description'] ?? ''),
                    $title,
                    $summary,
                    $locale
                ),
                'summary' => $summary,
                'body' => $this->sanitizePublicHtml(trim((string) ($raw['body'] ?? ''))),
                'problem' => trim((string) ($raw['problem'] ?? $summary)),
                'approach' => $this->normalizeStringList($raw['approach'] ?? []),
                'proof' => $this->normalizeStringList($raw['proof'] ?? []),
                'checklist' => $this->normalizeStringList($raw['checklist'] ?? []),
                'pitfalls' => $this->normalizeStringList($raw['pitfalls'] ?? []),
                'related_faq' => $this->normalizeStringList($raw['related_faq'] ?? []),
                'comparison' => $comparison ?? [],
                'order' => (int) ($raw['order'] ?? 9999),
            ];
        }

        usort($pages, static function (array $a, array $b): int {
            $orderCmp = $a['order'] <=> $b['order'];
            if ($orderCmp !== 0) {
                return $orderCmp;
            }

            return strcmp($a['slug'], $b['slug']);
        });

        $this->solutionsCache[$locale] = $pages;

        return $pages;
    }

    /**
     * @return array{slug:string,title:string,meta_title:string,meta_description:string,summary:string,body:string,problem:string,approach:array<int,string>,proof:array<int,string>,checklist:array<int,string>,pitfalls:array<int,string>,related_faq:array<int,string>,order:int}|null
     */
    public function findSolution(string $locale, string $slug): ?array
    {
        $normalizedSlug = trim(rawurldecode($slug));
        if ($normalizedSlug === '') {
            return null;
        }

        foreach ($this->solutionPages($locale) as $page) {
            if (strcasecmp($page['slug'], $normalizedSlug) === 0) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function solutionSlugs(): array
    {
        return array_values(array_map(
            static fn (array $page): string => $page['slug'],
            $this->solutionPages('en')
        ));
    }

    /**
     * @return array<int, array{slug:string,question:string,answer:string,meta_title:string,meta_description:string,related:array<int,string>,related_solutions:array<int,string>,order:int}>
     */
    public function faqArticles(string $locale): array
    {
        $locale = $this->normalizeLocale($locale);

        if (isset($this->faqCache[$locale])) {
            return $this->faqCache[$locale];
        }

        $fallback = $this->loadPhpMap(resource_path('content/faq/en'));
        $localized = $locale === 'en'
            ? $fallback
            : $this->loadPhpMap(resource_path("content/faq/{$locale}"));

        $slugs = array_values(array_unique(array_merge(array_keys($fallback), array_keys($localized))));
        $articles = [];

        foreach ($slugs as $slug) {
            $raw = $localized[$slug] ?? $fallback[$slug] ?? null;
            if (! is_array($raw)) {
                continue;
            }

            $question = trim((string) ($raw['question'] ?? ''));
            $answer = trim((string) ($raw['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }

            $related = $this->normalizeStringList($raw['related'] ?? []);
            $relatedSolutions = $this->normalizeStringList($raw['related_solutions'] ?? []);
            $answer = $this->expandFaqAnswerIfThin($slug, $locale, $question, $answer, $relatedSolutions);
            $metaDescription = $this->normalizeFaqMetaDescription(
                $slug,
                (string) ($raw['meta_description'] ?? ''),
                $question,
                strip_tags($answer),
                $locale
            );
            $answer = $this->appendFaqLeadSection($slug, $locale, $answer, $relatedSolutions);
            $answer = $this->sanitizePublicHtml($answer);

            $articles[] = [
                'slug' => $slug,
                'question' => $question,
                'answer' => $answer,
                'meta_title' => trim((string) ($raw['meta_title'] ?? $question)),
                'meta_description' => $metaDescription,
                'related' => $related,
                'related_solutions' => $relatedSolutions,
                'order' => (int) ($raw['order'] ?? 9999),
            ];
        }

        usort($articles, static function (array $a, array $b): int {
            $orderCmp = $a['order'] <=> $b['order'];
            if ($orderCmp !== 0) {
                return $orderCmp;
            }

            return strcmp($a['slug'], $b['slug']);
        });

        $knownSlugs = array_flip(array_map(static fn (array $a): string => $a['slug'], $articles));
        $knownSolutionSlugs = array_flip($this->solutionSlugs());

        $articles = array_map(static function (array $article) use ($knownSlugs, $knownSolutionSlugs): array {
            $article['related'] = array_values(array_filter($article['related'], static function (string $slug) use ($knownSlugs): bool {
                return isset($knownSlugs[$slug]);
            }));
            $article['related_solutions'] = array_values(array_filter($article['related_solutions'], static function (string $slug) use ($knownSolutionSlugs): bool {
                return isset($knownSolutionSlugs[$slug]);
            }));

            return $article;
        }, $articles);

        $this->faqCache[$locale] = $articles;

        return $articles;
    }

    /**
     * @return array{slug:string,question:string,answer:string,meta_title:string,meta_description:string,related:array<int,string>,related_solutions:array<int,string>,order:int}|null
     */
    public function findFaqArticle(string $locale, string $slug): ?array
    {
        $normalizedSlug = trim(rawurldecode($slug));
        if ($normalizedSlug === '') {
            return null;
        }

        foreach ($this->faqArticles($locale) as $article) {
            if (strcasecmp($article['slug'], $normalizedSlug) === 0) {
                return $article;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function faqSlugs(): array
    {
        return array_values(array_map(
            static fn (array $article): string => $article['slug'],
            $this->faqArticles('en')
        ));
    }

    /**
     * @param array{slug:string,related_faq?:array<int,string>} $solution
     * @return array<int, array{slug:string,question:string,answer:string,meta_title:string,meta_description:string,related:array<int,string>,related_solutions:array<int,string>,order:int}>
     */
    public function relatedFaqForSolution(string $locale, array $solution, int $limit = 4): array
    {
        $faq = $this->faqArticles($locale);
        if ($faq === []) {
            return [];
        }

        $faqBySlug = [];
        foreach ($faq as $article) {
            $faqBySlug[$article['slug']] = $article;
        }

        $manual = [];
        foreach (($solution['related_faq'] ?? []) as $slug) {
            if (isset($faqBySlug[$slug])) {
                $manual[] = $faqBySlug[$slug];
            }
        }

        if ($manual !== []) {
            return array_slice($manual, 0, max(1, $limit));
        }

        $tokens = $this->tokenize((string) ($solution['slug'] ?? ''));
        if ($tokens === []) {
            return array_slice($faq, 0, max(1, $limit));
        }

        $scored = [];
        foreach ($faq as $article) {
            $haystack = strtolower($article['slug'] . ' ' . ($article['question'] ?? ''));
            $score = 0;
            foreach ($tokens as $token) {
                if ($token !== '' && str_contains($haystack, $token)) {
                    $score++;
                }
            }

            if ($score > 0) {
                $article['_score'] = $score;
                $scored[] = $article;
            }
        }

        if ($scored === []) {
            return array_slice($faq, 0, max(1, $limit));
        }

        usort($scored, static fn (array $a, array $b): int => ($b['_score'] <=> $a['_score']) ?: (($a['order'] ?? 9999) <=> ($b['order'] ?? 9999)));

        return array_slice(array_map(static function (array $article): array {
            unset($article['_score']);

            return $article;
        }, $scored), 0, max(1, $limit));
    }

    /**
     * @param array{slug:string,title?:string,summary?:string,related_faq?:array<int,string>} $solution
     * @return array<int, array{slug:string,title:string,meta_title:string,meta_description:string,summary:string,body:string,problem:string,approach:array<int,string>,proof:array<int,string>,checklist:array<int,string>,pitfalls:array<int,string>,related_faq:array<int,string>,order:int}>
     */
    public function relatedSolutions(string $locale, array $solution, int $limit = 3): array
    {
        $pages = $this->solutionPages($locale);
        if ($pages === []) {
            return [];
        }

        $limit = max(1, $limit);
        $currentSlug = trim((string) ($solution['slug'] ?? ''));
        $tokens = $this->tokenize((string) ($solution['slug'] ?? '') . ' ' . (string) ($solution['title'] ?? '') . ' ' . (string) ($solution['summary'] ?? ''));
        $relatedFaq = array_flip($this->normalizeStringList($solution['related_faq'] ?? []));

        $fallback = [];
        $scored = [];

        foreach ($pages as $page) {
            if ($currentSlug !== '' && strcasecmp($page['slug'], $currentSlug) === 0) {
                continue;
            }

            $fallback[] = $page;
            $score = 0;

            if ($tokens !== []) {
                $candidateTokens = $this->tokenize($page['slug'] . ' ' . $page['title'] . ' ' . $page['summary']);
                $score += count(array_intersect($tokens, $candidateTokens)) * 3;
            }

            if ($relatedFaq !== [] && $page['related_faq'] !== []) {
                $candidateFaq = array_flip($page['related_faq']);
                $score += count(array_intersect_key($relatedFaq, $candidateFaq)) * 5;
            }

            if ($score > 0) {
                $page['_score'] = $score;
                $scored[] = $page;
            }
        }

        if ($scored === []) {
            return array_slice($fallback, 0, $limit);
        }

        usort($scored, static fn (array $a, array $b): int => ($b['_score'] <=> $a['_score']) ?: (($a['order'] ?? 9999) <=> ($b['order'] ?? 9999)));

        return array_slice(array_map(static function (array $page): array {
            unset($page['_score']);

            return $page;
        }, $scored), 0, $limit);
    }

    /**
     * @param array{slug:string,question?:string,related?:array<int,string>,related_solutions?:array<int,string>} $article
     * @return array<int, array{slug:string,title:string,meta_title:string,meta_description:string,summary:string,body:string,problem:string,approach:array<int,string>,proof:array<int,string>,checklist:array<int,string>,pitfalls:array<int,string>,related_faq:array<int,string>,order:int}>
     */
    public function relatedSolutionsForFaq(string $locale, array $article, int $limit = 3): array
    {
        $pages = $this->solutionPages($locale);
        if ($pages === []) {
            return [];
        }

        $limit = max(1, $limit);
        $pagesBySlug = [];
        foreach ($pages as $page) {
            $pagesBySlug[$page['slug']] = $page;
        }

        $manual = [];
        foreach (($article['related_solutions'] ?? []) as $slug) {
            if (isset($pagesBySlug[$slug])) {
                $manual[] = $pagesBySlug[$slug];
            }
        }

        if ($manual !== []) {
            return array_slice($manual, 0, $limit);
        }

        $tokens = $this->tokenize((string) ($article['slug'] ?? '') . ' ' . (string) ($article['question'] ?? ''));
        if ($tokens === []) {
            return array_slice($pages, 0, $limit);
        }

        $scored = [];
        foreach ($pages as $page) {
            $candidateTokens = $this->tokenize($page['slug'] . ' ' . $page['title'] . ' ' . $page['summary']);
            $score = count(array_intersect($tokens, $candidateTokens));
            if ($score > 0) {
                $page['_score'] = $score;
                $scored[] = $page;
            }
        }

        if ($scored === []) {
            return array_slice($pages, 0, $limit);
        }

        usort($scored, static fn (array $a, array $b): int => ($b['_score'] <=> $a['_score']) ?: (($a['order'] ?? 9999) <=> ($b['order'] ?? 9999)));

        return array_slice(array_map(static function (array $page): array {
            unset($page['_score']);

            return $page;
        }, $scored), 0, $limit);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadPhpMap(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        $result = [];
        foreach ($files as $file) {
            $slug = pathinfo($file, PATHINFO_FILENAME);
            if (! is_string($slug) || trim($slug) === '') {
                continue;
            }

            $data = require $file;
            if (! is_array($data)) {
                continue;
            }

            $result[$slug] = $data;
        }

        return $result;
    }

    /**
     * @param mixed $items
     * @return array<int, string>
     */
    private function normalizeStringList($items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($item): string {
            return trim((string) $item);
        }, $items), static function (string $value): bool {
            return $value !== '';
        }));
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function applyBofuSolutionOverrides(string $slug, array $raw, string $locale): array
    {
        $overrides = self::BOFU_SOLUTION_OVERRIDES[$locale][$slug] ?? null;
        if (! is_array($overrides)) {
            return $raw;
        }

        foreach ($overrides as $key => $value) {
            if (! array_key_exists($key, $raw)) {
                $raw[$key] = $value;
                continue;
            }

            $current = $raw[$key];

            if (is_string($current)) {
                if (trim($current) === '') {
                    $raw[$key] = $value;
                }
                continue;
            }

            if (is_array($current)) {
                if ($current === []) {
                    $raw[$key] = $value;
                }
                continue;
            }

            if ($current === null) {
                $raw[$key] = $value;
            }
        }

        return $raw;
    }

    /**
     * @return array<string, string>|null
     */
    private function buildAlternativeComparisonData(string $slug, string $locale): ?array
    {
        $profile = self::ALTERNATIVE_PROFILES[$slug] ?? null;
        if (! is_array($profile)) {
            return null;
        }

        $competitor = (string) ($profile['competitor'] ?? '');
        if ($competitor === '') {
            return null;
        }

        if ($locale === 'pl') {
            return [
                'competitor' => $competitor,
                'segment' => (string) ($profile['segment_pl'] ?? ''),
                'choose_competitor' => (string) ($profile['competitor_best_pl'] ?? ''),
                'switch_trigger' => (string) ($profile['switch_trigger_pl'] ?? ''),
                'choose_trumonit' => 'potrzebujesz jednego procesu: detekcja, eskalacja, ownership i status updates',
                'migration_hint' => 'zrob test rownolegly 7-14 dni na krytycznych URL i porownaj opoznienie detekcji oraz jakosc eskalacji',
            ];
        }

        return [
            'competitor' => $competitor,
            'segment' => (string) ($profile['segment_en'] ?? ''),
            'choose_competitor' => (string) ($profile['competitor_best_en'] ?? ''),
            'switch_trigger' => (string) ($profile['switch_trigger_en'] ?? ''),
            'choose_trumonit' => 'you need one process for detection, escalation, ownership, and status updates',
            'migration_hint' => 'run a 7-14 day parallel test on critical URLs and compare detection lag with escalation quality',
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, string> $comparison
     * @return array<string, mixed>
     */
    private function enrichAlternativeRaw(array $raw, array $comparison, string $locale): array
    {
        $competitor = $comparison['competitor'] ?? 'the current tool';
        $chooseCompetitor = $comparison['choose_competitor'] ?? '';
        $chooseTruMonit = $comparison['choose_trumonit'] ?? '';
        $switchTrigger = $comparison['switch_trigger'] ?? '';
        $migrationHint = $comparison['migration_hint'] ?? '';

        if ($locale === 'pl') {
            $raw['approach'] = [
                'Uruchom test rownolegly: TruMonit vs ' . $competitor . ' na tych samych uslugach krytycznych.',
                'Porownaj trzy metryki: opoznienie detekcji, falszywe alarmy i czas do pierwszej sensownej akcji.',
                'Zweryfikuj caly workflow: alert -> owner -> status update -> zamkniecie incydentu.',
            ];
            $raw['proof'] = [
                'Mniejszy szum alertowy i szybszy triage podczas awarii niz w scenariuszu bazowym ' . $competitor . '.',
                'Czytelny ownership oraz lepszy handoff miedzy osobami on-call w porownaniu z rozproszonym workflow.',
                'Bardziej spojna komunikacja statusowa do klientow i interesariuszy przy modelu TruMonit vs ' . $competitor . '.',
            ];
            $raw['checklist'] = [
                'Czy obecny proces na ' . $competitor . ' daje jasny owner dla kazdego alertu krytycznego?',
                'Czy status update da sie opublikowac bez recznego chaosu i bez dodatkowych narzedzi?',
                'Czy po deployu proces pozostaje stabilny i powtarzalny przez minimum 7 dni?',
                'Czy po tygodniu testu TruMonit vs ' . $competitor . ' widac roznice w czasie reakcji?',
            ];
            $raw['pitfalls'] = [
                'Decyzja tylko po cenie i liczbie monitorow bez oceny procesu incydentowego w ' . $competitor . '.',
                'Brak testu rownoleglego TruMonit i ' . $competitor . ' na tych samych URL oraz endpointach.',
                'Mieszanie alertow krytycznych i informacyjnych w jednym kanale, niezaleznie od narzedzia.',
            ];

            $appendix = '<h3>Szybka decyzja: TruMonit vs ' . htmlspecialchars($competitor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h3>'
                . '<p><strong>Kiedy zostac przy ' . htmlspecialchars($competitor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':</strong> ' . htmlspecialchars($chooseCompetitor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.</p>'
                . '<p><strong>Kiedy wybrac TruMonit:</strong> ' . htmlspecialchars($chooseTruMonit, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.</p>'
                . '<p><strong>Sygnal zmiany:</strong> ' . htmlspecialchars($switchTrigger, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.</p>'
                . '<p><strong>Jak migrowac bez ryzyka:</strong> ' . htmlspecialchars($migrationHint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.</p>';
        } else {
            $raw['approach'] = [
                'Run a parallel test: TruMonit vs ' . $competitor . ' on the same critical services.',
                'Compare three core metrics: detection lag, false positives, and time to first meaningful action.',
                'Validate full workflow: alert -> owner -> status update -> incident closure.',
            ];
            $raw['proof'] = [
                'Lower alert noise and faster triage during outages compared with baseline ' . $competitor . '.',
                'Clear ownership model with cleaner on-call handoff during incident pressure.',
                'More consistent status communication for customers and stakeholders across the full incident cycle.',
            ];
            $raw['checklist'] = [
                'Does your current ' . $competitor . ' setup assign a clear owner to every critical alert?',
                'Can your team publish status updates quickly without manual chaos or extra tools?',
                'Is the workflow still reliable after deploys and configuration changes for at least one week?',
                'After side-by-side testing, is response time measurably better with TruMonit?',
            ];
            $raw['pitfalls'] = [
                'Choosing by price and monitor count without evaluating incident workflow quality in ' . $competitor . '.',
                'Skipping side-by-side testing of TruMonit and ' . $competitor . ' on the same URLs and endpoints.',
                'Mixing critical and informational alerts in one noisy channel regardless of tool.',
            ];

            $appendix = '<h3>Quick decision: TruMonit vs ' . htmlspecialchars($competitor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h3>'
                . '<p><strong>When to stay with ' . htmlspecialchars($competitor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':</strong> ' . htmlspecialchars($chooseCompetitor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.</p>'
                . '<p><strong>When to choose TruMonit:</strong> ' . htmlspecialchars($chooseTruMonit, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.</p>'
                . '<p><strong>Switch trigger:</strong> ' . htmlspecialchars($switchTrigger, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.</p>'
                . '<p><strong>Low-risk migration hint:</strong> ' . htmlspecialchars($migrationHint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.</p>';
        }

        $body = trim((string) ($raw['body'] ?? ''));
        if ($body === '') {
            $body = $locale === 'pl'
                ? '<p>To porownanie pokazuje roznice operacyjne miedzy TruMonit i ' . htmlspecialchars($competitor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.</p>'
                : '<p>This comparison highlights operational differences between TruMonit and ' . htmlspecialchars($competitor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.</p>';
        }
        if (! str_contains($body, 'Quick decision: TruMonit vs') && ! str_contains($body, 'Szybka decyzja: TruMonit vs')) {
            $body .= $appendix;
        }
        $raw['body'] = $body;

        return $raw;
    }

    private function normalizeSolutionMetaDescription(string $rawMeta, string $title, string $summary, string $locale): string
    {
        $meta = trim($rawMeta);
        if ($meta === '') {
            $meta = $summary !== '' ? $summary : $title;
        }

        $meta = str_replace(['...', '…'], '', $meta);
        $meta = preg_replace('/\s+/u', ' ', $meta) ?? $meta;
        $meta = trim($meta);

        if ($meta === '') {
            $meta = $locale === 'pl'
                ? 'Praktyczny scenariusz wdrożenia monitoringu pod intencję zakupową i działanie zespołu.'
                : 'Practical implementation scenario focused on buying intent and operational team outcomes.';
        }

        if (mb_strlen($meta) < 140) {
            $suffix = $locale === 'pl'
                ? ' Zawiera konkretne kroki wdrożenia, checklistę i częste błędy.'
                : ' Includes concrete implementation steps, checklist, and common pitfalls.';
            $meta = trim($meta . $suffix);
        }

        if (mb_strlen($meta) > 160) {
            $meta = $this->truncateAtWordBoundary($meta, 160);
        }

        if (mb_strlen($meta) < 140) {
            $fallback = $locale === 'pl'
                ? $title . ' - praktyczne wdrożenie monitoringu, alertów i procesu incydentowego.'
                : $title . ' - practical rollout for monitoring, alerting, and incident workflow.';
            $meta = $this->truncateAtWordBoundary($fallback, 160);
        }

        return trim($meta);
    }

    private function normalizeFaqMetaDescription(string $slug, string $rawMeta, string $question, string $answerText, string $locale): string
    {
        $meta = trim($rawMeta);
        if ($meta === '') {
            $meta = $locale === 'pl'
                ? $question . ' - praktyczny poradnik wdrożenia, diagnostyki i checklisty monitoringu w TruMonit.'
                : $question . ' - practical implementation guide with diagnosis and checklist for monitoring in TruMonit.';
        }

        $meta = str_replace(['...', '…'], '', $meta);
        $meta = preg_replace('/\s+/u', ' ', $meta) ?? $meta;
        $meta = trim($meta);

        if ($meta === '') {
            $meta = $locale === 'pl'
                ? 'Praktyczny przewodnik monitoringu: kroki wdrożenia, checklista i najczęstsze błędy.'
                : 'Practical monitoring guide with implementation steps, checklist, and common pitfalls.';
        }

        if (mb_strlen($meta) < 140) {
            $suffix = $locale === 'pl'
                ? ' Zawiera konkretne kroki, diagnostykę i działania pod produkcję.'
                : ' Includes concrete steps, diagnostics, and production-ready actions.';
            $meta = trim($meta . $suffix);
        }

        if (mb_strlen($meta) > 160) {
            $meta = $this->truncateAtWordBoundary($meta, 160);
        }

        if (mb_strlen($meta) < 140) {
            $fallback = $locale === 'pl'
                ? $question . ' - praktyczna checklista wdrożenia i kontroli jakości monitoringu.'
                : $question . ' - practical checklist for implementation and monitoring quality control.';
            $meta = $this->truncateAtWordBoundary($fallback, 160);
        }

        return trim($meta);
    }

    /**
     * @param array<int, string> $relatedSolutions
     */
    private function expandFaqAnswerIfThin(string $slug, string $locale, string $question, string $answer, array $relatedSolutions): string
    {
        $hints = self::FAQ_EXPANSION_HINTS[$locale] ?? [];
        $hint = $hints[$slug] ?? null;
        if ($hint === null || $this->wordCount($answer) >= 220) {
            return $answer;
        }

        $primarySolution = $relatedSolutions[0] ?? null;
        $secondarySolution = $relatedSolutions[1] ?? null;
        $primaryLabel = $primarySolution ? $this->humanizeSlug($primarySolution) : null;
        $secondaryLabel = $secondarySolution ? $this->humanizeSlug($secondarySolution) : null;

        if ($locale === 'pl') {
            $safeQuestion = htmlspecialchars($question, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeHint = htmlspecialchars($hint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $appendix = '<h3>Szybka diagnoza i decyzja wdrożeniowa</h3>'
                . '<p>W praktyce temat <strong>' . $safeQuestion . '</strong> rzadko kończy się na jednej metryce. Połącz sygnał techniczny z wpływem biznesowym i ustal, kiedy alert wymaga realnej akcji zespołu.</p>'
                . '<p>Najlepszy efekt daje podejście krokowe: najpierw krytyczne ścieżki, potem progi per poziom ważności, a na końcu test eskalacji na żywym scenariuszu. To kluczowe zwłaszcza dla obszaru: <strong>' . $safeHint . '</strong>.</p>'
                . '<h3>Checklist operacyjny</h3>'
                . '<ul>'
                . '<li>Zdefiniuj ownera i maksymalny czas reakcji na incydent.</li>'
                . '<li>Rozdziel alerty krytyczne od informacyjnych, aby zmniejszyć szum.</li>'
                . '<li>Po wdrożeniu zmian (deploy, motyw, integracja API) zrób re-test.</li>'
                . '</ul>';
            if ($primarySolution !== null) {
                $safePrimary = htmlspecialchars((string) ($primaryLabel ?? $primarySolution), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $appendix .= '<p>Wdrożenie przyspieszysz w scenariuszu <a href="/' . $locale . '/solutions/' . $primarySolution . '"><strong>' . $safePrimary . '</strong></a>';
                if ($secondarySolution !== null) {
                    $safeSecondary = htmlspecialchars((string) ($secondaryLabel ?? $secondarySolution), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $appendix .= ' oraz uzupełniająco w <a href="/' . $locale . '/solutions/' . $secondarySolution . '"><strong>' . $safeSecondary . '</strong></a>';
                }
                $appendix .= '.</p>';
            }

            $result = $answer . "\n\n" . $appendix;
            if ($this->wordCount($result) < 220) {
                $result .= '<p>Dla stabilnego efektu ustaw stały rytm przeglądu: raz w tygodniu kontrola alertów i jakości danych, raz w miesiącu test eskalacji oraz po każdym większym wdrożeniu szybki audyt monitorów krytycznych. Dobrą praktyką jest też krótki raport po incydencie: co zadziałało, co było opóźnione i które progi wymagają korekty. Taki cykl daje realną poprawę, bo ogranicza szum i skraca czas decyzji przy następnym problemie.</p>';
            }
            while ($this->wordCount($result) < 220) {
                $result .= '<p>Warto też monitorować trend tygodniowy: liczba alertów, czas eskalacji i odsetek fałszywych alarmów. To ułatwia decyzje produktowe.</p>';
            }

            return $result;
        }

        $safeQuestion = htmlspecialchars($question, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeHint = htmlspecialchars($hint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $appendix = '<h3>Practical diagnosis and rollout plan</h3>'
            . '<p>For <strong>' . $safeQuestion . '</strong>, teams lose time when they track raw signals but do not map them to incident impact. The goal is not more alerts; the goal is faster and cleaner decisions.</p>'
            . '<p>Use this sequence: define business-critical paths, map thresholds to severity, then validate escalation with a realistic drill. This is especially relevant for <strong>' . $safeHint . '</strong>.</p>'
            . '<h3>Operational checklist</h3>'
            . '<ul>'
            . '<li>Assign ownership and response targets per incident level.</li>'
            . '<li>Separate critical alerts from informational signals to reduce noise.</li>'
            . '<li>Re-run validation after deploys, theme changes, or API updates.</li>'
            . '</ul>';
        if ($primarySolution !== null) {
            $safePrimary = htmlspecialchars((string) ($primaryLabel ?? $primarySolution), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $appendix .= '<p>To implement faster, continue with <a href="/' . $locale . '/solutions/' . $primarySolution . '"><strong>' . $safePrimary . '</strong></a>';
            if ($secondarySolution !== null) {
                $safeSecondary = htmlspecialchars((string) ($secondaryLabel ?? $secondarySolution), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $appendix .= ' and pair it with <a href="/' . $locale . '/solutions/' . $secondarySolution . '"><strong>' . $safeSecondary . '</strong></a>';
            }
            $appendix .= '.</p>';
        }

        $result = $answer . "\n\n" . $appendix;
        if ($this->wordCount($result) < 220) {
            $result .= '<p>For stable results, run a fixed review cycle: weekly alert hygiene and data-quality checks, monthly escalation drills, and a quick validation after each major release. Add a short post-incident review that captures what worked, where response slowed down, and which thresholds should be adjusted. This operating rhythm consistently improves monitoring outcomes because it reduces noise and speeds up decision-making in the next incident window.</p>';
        }
        while ($this->wordCount($result) < 220) {
            $result .= '<p>Track weekly trend metrics as well: alert volume, escalation lag, and false-positive ratio. This makes operational prioritization much clearer.</p>';
        }

        return $result;
    }

    /**
     * Keep FAQ rendering resilient even when lead-section enrichment is disabled.
     *
     * @param array<int, string> $relatedSolutions
     */
    private function appendFaqLeadSection(string $slug, string $locale, string $answer, array $relatedSolutions): string
    {
        return $answer;
    }

    private function truncateAtWordBoundary(string $text, int $maxLen): string
    {
        if (mb_strlen($text) <= $maxLen) {
            return trim($text);
        }

        $cut = mb_substr($text, 0, $maxLen);
        $cut = preg_replace('/\s+\S*$/u', '', $cut) ?? $cut;
        $cut = rtrim($cut, " ,;:-");

        if ($cut === '') {
            return trim(mb_substr($text, 0, $maxLen));
        }

        return $cut . '.';
    }

    private function wordCount(string $html): int
    {
        $plain = trim(strip_tags($html));
        if ($plain === '') {
            return 0;
        }

        $parts = preg_split('/[^\p{L}\p{N}\-]+/u', $plain, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? count($parts) : 0;
    }

    private function humanizeSlug(string $slug): string
    {
        return mb_convert_case(str_replace('-', ' ', trim($slug)), MB_CASE_TITLE, 'UTF-8');
    }

    private function sanitizePublicHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $wrapped = '<!DOCTYPE html><html><body>'.$html.'</body></html>';
        $previous = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadHTML(
                mb_convert_encoding($wrapped, 'HTML-ENTITIES', 'UTF-8'),
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            $body = $dom->getElementsByTagName('body')->item(0);
            if (! $body instanceof \DOMElement) {
                return strip_tags($html);
            }

            $this->sanitizeHtmlNode($body);

            $output = '';
            foreach (iterator_to_array($body->childNodes) as $child) {
                if ($child instanceof \DOMNode) {
                    $output .= $dom->saveHTML($child);
                }
            }

            return trim($output);
        } catch (\Throwable) {
            return strip_tags($html, '<p><a><strong><em><ul><ol><li><br><code><pre><h2><h3><blockquote>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function sanitizeHtmlNode(\DOMNode $node): void
    {
        if ($node instanceof \DOMElement) {
            $tag = strtolower($node->tagName);

            if (in_array($tag, ['html', 'body'], true)) {
                foreach (iterator_to_array($node->childNodes) as $child) {
                    if ($child instanceof \DOMNode) {
                        $this->sanitizeHtmlNode($child);
                    }
                }

                return;
            }

            $stripEntirely = ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta'];
            if (in_array($tag, $stripEntirely, true)) {
                $node->parentNode?->removeChild($node);

                return;
            }

            $allowedTags = ['p', 'a', 'strong', 'em', 'ul', 'ol', 'li', 'br', 'code', 'pre', 'h2', 'h3', 'blockquote'];

            if (! in_array($tag, $allowedTags, true)) {
                $parent = $node->parentNode;
                if (! $parent instanceof \DOMNode) {
                    return;
                }

                while ($node->firstChild instanceof \DOMNode) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                return;
            }

            $allowedAttributes = [
                'a' => ['href', 'title', 'target', 'rel'],
            ];

            if ($node->hasAttributes()) {
                $toRemove = [];
                foreach (iterator_to_array($node->attributes) as $attribute) {
                    if (! $attribute instanceof \DOMAttr) {
                        continue;
                    }

                    $name = strtolower($attribute->name);
                    $value = trim((string) $attribute->value);
                    $tagAllowedAttributes = $allowedAttributes[$tag] ?? [];

                    if (str_starts_with($name, 'on')) {
                        $toRemove[] = $name;
                        continue;
                    }

                    if (! in_array($name, $tagAllowedAttributes, true)) {
                        $toRemove[] = $name;
                        continue;
                    }

                    if ($tag === 'a' && $name === 'href' && ! $this->isSafeHref($value)) {
                        $toRemove[] = $name;
                        continue;
                    }

                    if ($tag === 'a' && $name === 'target' && ! in_array(strtolower($value), ['_self', '_blank'], true)) {
                        $toRemove[] = $name;
                    }
                }

                foreach ($toRemove as $attributeName) {
                    $node->removeAttribute($attributeName);
                }

                if ($tag === 'a' && strtolower((string) $node->getAttribute('target')) === '_blank') {
                    $rel = trim((string) $node->getAttribute('rel'));
                    $parts = preg_split('/\s+/', strtolower($rel), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $parts = array_values(array_unique(array_merge($parts, ['noopener', 'noreferrer'])));
                    $node->setAttribute('rel', implode(' ', $parts));
                }
            }
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMNode) {
                $this->sanitizeHtmlNode($child);
            }
        }
    }

    private function isSafeHref(string $href): bool
    {
        $href = trim($href);
        if ($href === '') {
            return false;
        }

        if (str_starts_with($href, '/') || str_starts_with($href, '#') || str_starts_with($href, '?')) {
            return true;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);
        if (! is_string($scheme) || $scheme === '') {
            return true;
        }

        return in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true);
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $value): array
    {
        $tokens = preg_split('/[^a-z0-9]+/i', strtolower($value)) ?: [];
        $tokens = array_values(array_filter(array_map(static fn (string $token): string => trim($token), $tokens), static fn (string $token): bool => strlen($token) > 2));

        return array_values(array_unique($tokens));
    }
}
