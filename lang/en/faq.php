<?php

return [
    'nav_faq' => 'FAQ',
    'page_title' => 'Monitoring FAQ for websites and APIs',
    'page_description' => 'Practical answers on uptime checks, API endpoint monitoring, SSL alerts, status pages, and incident response.',
    'search_label' => 'Search FAQ',
    'search_placeholder' => 'Search questions...',
    'read_answer' => 'Read answer',
    'no_results' => 'No results found. Try another keyword.',
    'back_to_index' => 'Back to FAQ',
    'related_questions' => 'Related questions',
    'cta_title' => 'Need alerting without noise?',
    'cta_body' => 'Create a workspace, add monitors, and route incidents to your team in minutes.',
    'cta_primary' => 'Create account',
    'cta_secondary' => 'Log in',

    'what_is_uptime_monitoring_question' => 'What is uptime monitoring?',
    'what_is_uptime_monitoring_answer' => '<p><strong>Uptime monitoring</strong> checks whether your website or API is reachable and healthy at regular intervals. It usually verifies HTTP status, response time, and optionally content rules.</p><p>The goal is to detect outages early, alert your team quickly, and track availability trends over time.</p>',

    'how_often_should_i_check_my_website_question' => 'How often should I check my website?',
    'how_often_should_i_check_my_website_answer' => '<p>Most teams start with checks every <strong>1 to 5 minutes</strong>. For critical checkout, login, or API flows, 1-minute checks are common.</p><p>Use shorter intervals for revenue-critical endpoints and longer intervals for low-risk pages to balance speed and cost.</p>',

    'how_do_i_monitor_api_endpoints_question' => 'How do I monitor API endpoints?',
    'how_do_i_monitor_api_endpoints_answer' => '<p>Monitor API endpoints with expected status ranges (for example 2xx), timeout limits, and optional keyword checks in response bodies.</p><p>For deeper confidence, monitor both a fast health endpoint and one business-critical API route used by customers.</p>',

    'how_do_i_monitor_ssl_certificate_expiry_question' => 'How do I monitor SSL certificate expiry?',
    'how_do_i_monitor_ssl_certificate_expiry_answer' => '<p>Set SSL checks to alert your team before certificate expiration, typically 30, 14, and 7 days in advance.</p><p>Early SSL alerts prevent avoidable downtime, trust warnings, and failed API integrations.</p>',

    'synthetic_checks_vs_http_checks_question' => 'Synthetic checks vs HTTP checks: what is the difference?',
    'synthetic_checks_vs_http_checks_answer' => '<p><strong>HTTP checks</strong> are lightweight and fast: they verify status codes, response time, and simple content rules.</p><p><strong>Synthetic checks</strong> simulate browser behavior or user journeys, which helps detect front-end and workflow issues that plain HTTP checks can miss.</p>',

    'how_do_i_reduce_false_alerts_question' => 'How do I reduce false alerts?',
    'how_do_i_reduce_false_alerts_answer' => '<p>Use multiple safeguards: alert after several consecutive failures, apply cooldowns, and use maintenance windows during planned work.</p><p>Combining baselines with deduplication reduces alert noise while keeping real incidents visible.</p>',

    'what_is_sla_vs_slo_question' => 'What is SLA vs SLO in monitoring?',
    'what_is_sla_vs_slo_answer' => '<p><strong>SLO</strong> is your internal reliability target (for example 99.9% uptime). <strong>SLA</strong> is a formal customer commitment, often with penalties.</p><p>Good monitoring tracks both so teams can manage risk before contractual breaches happen.</p>',

    'what_is_a_status_page_question' => 'What is a status page and why does it matter?',
    'what_is_a_status_page_answer' => '<p>A status page is a public communication channel that shows current service health and incident history.</p><p>It reduces support pressure during outages and improves trust through transparent updates.</p>',

    'what_is_an_incident_response_playbook_question' => 'What is an incident response playbook?',
    'what_is_an_incident_response_playbook_answer' => '<p>An incident playbook defines who does what when an alert fires: triage, communication, mitigation, and postmortem.</p><p>Documented playbooks shorten mean time to resolve and keep response quality consistent across shifts.</p>',

    'how_to_choose_a_website_monitoring_plan_question' => 'How should I choose a website monitoring plan?',
    'how_to_choose_a_website_monitoring_plan_answer' => '<p>Choose based on monitor count, check frequency, number of notification channels, and need for public status pages or team workflows.</p><p>Start with a lean plan, then upgrade when your monitor volume, response requirements, or governance needs increase.</p>',

    'what_causes_website_downtime_question' => 'What are the most common causes of website downtime?',
    'what_causes_website_downtime_answer' => '<p>Most outages come from deployment mistakes, database saturation, expired TLS certificates, DNS errors, and third-party dependency failures.</p><p>Tagging incidents by root cause helps you prioritize the fixes that reduce repeat downtime the fastest.</p>',

    'multi_region_monitoring_why_important_question' => 'Why is multi-region monitoring important?',
    'multi_region_monitoring_why_important_answer' => '<p>Single-region checks can produce false positives when one network path is unstable. Multi-region checks validate whether an issue is global or local.</p><p>This improves signal quality and helps your team route incidents to the right owner sooner.</p>',

    'best_alert_channels_for_uptime_monitoring_question' => 'What are the best alert channels for uptime monitoring?',
    'best_alert_channels_for_uptime_monitoring_answer' => '<p>Use at least two channels: one immediate channel (PagerDuty, phone, push) and one collaborative channel (Slack, Teams, email).</p><p>Tier channels by severity so critical incidents wake on-call, while low-priority alerts stay async.</p>',

    'how_to_monitor_cron_jobs_question' => 'How do I monitor cron jobs and scheduled tasks?',
    'how_to_monitor_cron_jobs_answer' => '<p>Use heartbeat monitoring: each successful run sends a ping. If no ping arrives before a deadline, trigger an incident.</p><p>Track both missed runs and excessive run duration to catch queue stalls and silent failures.</p>',

    'how_to_monitor_domain_expiration_question' => 'How do I monitor domain expiration?',
    'how_to_monitor_domain_expiration_answer' => '<p>Set domain expiry checks with long lead times, for example 60, 30, and 14 days before expiration.</p><p>Route alerts to both engineering and business owners so renewal does not depend on a single person.</p>',

    'what_is_maintenance_window_in_monitoring_question' => 'What is a maintenance window in monitoring?',
    'what_is_maintenance_window_in_monitoring_answer' => '<p>A maintenance window temporarily suppresses incident notifications for planned work, migrations, or infrastructure changes.</p><p>Use scheduled windows with start/end times and clear ownership to avoid masking real incidents by accident.</p>',

    'what_is_mttr_and_how_to_improve_it_question' => 'What is MTTR and how do I improve it?',
    'what_is_mttr_and_how_to_improve_it_answer' => '<p><strong>MTTR</strong> is mean time to resolve. Improve it by reducing time to detection, triage, mitigation, and communication.</p><p>Run drill exercises, keep playbooks current, and standardize rollback procedures for common failures.</p>',

    'how_to_build_on_call_escalation_policy_question' => 'How do I build an on-call escalation policy?',
    'how_to_build_on_call_escalation_policy_answer' => '<p>Define severity levels, response targets, and escalation timers. Assign primary and secondary responders for each service area.</p><p>Escalate by time and ownership, not by ad-hoc judgment, to avoid delays during high-pressure incidents.</p>',

    'how_to_monitor_login_flow_synthetically_question' => 'How do I monitor a login flow with synthetic checks?',
    'how_to_monitor_login_flow_synthetically_answer' => '<p>Create a script that opens the login page, submits credentials for a test account, and validates a post-login element.</p><p>Use dedicated test users with limited permissions and rotate credentials through secure secrets storage.</p>',

    'how_to_monitor_keyword_on_page_question' => 'How do I monitor a keyword or content marker on a page?',
    'how_to_monitor_keyword_on_page_answer' => '<p>Add a content assertion to your check, such as expected text, JSON field, or HTML marker that must be present.</p><p>Prefer stable markers over dynamic copy to reduce false alarms from minor UI text updates.</p>',

    'network_error_vs_application_error_monitoring_question' => 'Network error vs application error: how should I treat them?',
    'network_error_vs_application_error_monitoring_answer' => '<p>Separate transport failures (DNS, TLS, timeout, connect errors) from application failures (5xx, logic errors, invalid responses).</p><p>Different classes usually need different responders and different mitigation paths.</p>',

    'what_is_error_budget_for_slo_question' => 'What is an error budget for SLO monitoring?',
    'what_is_error_budget_for_slo_answer' => '<p>An error budget is the allowed unreliability derived from your SLO target, for example 0.1% downtime for 99.9% uptime.</p><p>Teams use budget burn rate to decide when to prioritize reliability work over feature delivery.</p>',

    'how_to_monitor_webhook_endpoints_question' => 'How do I monitor webhook endpoints?',
    'how_to_monitor_webhook_endpoints_answer' => '<p>Track HTTP success rate, response latency, and downstream processing success for webhook deliveries.</p><p>Use synthetic webhook requests and replay-safe payloads to verify the full ingestion path.</p>',

    'how_to_monitor_third_party_dependencies_question' => 'How do I monitor third-party dependencies?',
    'how_to_monitor_third_party_dependencies_answer' => '<p>Monitor critical providers separately (payments, auth, DNS, CDN, email) and link them to dependency tags in incident views.</p><p>This helps you quickly classify incidents as internal or external and update customers with confidence.</p>',

    'how_to_use_status_page_during_incident_question' => 'How should I use a status page during an incident?',
    'how_to_use_status_page_during_incident_answer' => '<p>Publish an initial acknowledgment quickly, then update status at regular intervals even when there is no full fix yet.</p><p>Use plain language, share impact scope, and provide next update time to reduce support load and uncertainty.</p>',

    'what_should_incident_postmortem_include_question' => 'What should an incident postmortem include?',
    'what_should_incident_postmortem_include_answer' => '<p>Include timeline, customer impact, root cause, contributing factors, and the permanent corrective actions with owners and due dates.</p><p>Postmortems should focus on system improvements, not individual blame.</p>',

    'how_to_monitor_api_rate_limits_question' => 'How do I monitor API rate limits?',
    'how_to_monitor_api_rate_limits_answer' => '<p>Track usage headers, 429 response rates, retry behavior, and queue backpressure on high-volume endpoints.</p><p>Alert before hard caps are reached so teams can throttle traffic or upgrade limits proactively.</p>',

    'how_many_monitors_do_i_need_question' => 'How many monitors do I need?',
    'how_many_monitors_do_i_need_answer' => '<p>Start from business-critical user journeys and core APIs, then add monitors for dependencies and edge cases.</p><p>A common baseline is one monitor per critical flow, not one monitor per page.</p>',

    'how_to_monitor_redirect_chains_question' => 'How do I monitor redirect chains?',
    'how_to_monitor_redirect_chains_answer' => '<p>Enable redirect-follow checks and assert final URL, status code, TLS validity, and total hop count.</p><p>Alert when chains grow unexpectedly because latency and SEO crawl quality can degrade.</p>',

    'can_i_monitor_private_internal_services_question' => 'Can I monitor private or internal services?',
    'can_i_monitor_private_internal_services_answer' => '<p>Yes, by using private probes, VPN-connected agents, or secure relay architecture within your network boundary.</p><p>Segment credentials and network access so internal monitoring does not expand blast radius during incidents.</p>',
];
