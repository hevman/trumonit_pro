=== TruMonit Monitor ===
Contributors: trumonit
Tags: uptime monitoring, website monitoring, synthetic monitoring, api monitoring, incident alerts, status page
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress with TruMonit for uptime monitoring, website monitoring, synthetic monitoring, incident alerts, API monitoring, and status page health in wp-admin.

== Description ==

TruMonit Monitor provides a lightweight admin panel inside WordPress:

* Connect using TruMonit API token
* Login via API (email + password) to fetch token
* Validate connection against API
* Uptime monitoring snapshot (monitor count + incident totals)
* Review recent monitors and incidents in wp-admin
* Trigger "Run scan" for selected monitor
* Monitor/incident tab filters with pagination
* Open pricing and billing links from Plans tab
* Open TruMonit dashboard and incidents directly
* Translation template + Polish language pack included (`languages/`)

This plugin is intentionally simple (read-only snapshot + quick links) for fast operations workflow.

== External services ==

This plugin connects to the TruMonit API service to fetch monitoring data and run selected actions.

* Service provider: TruMonit
* Service website: https://trumonit.pro
* Privacy policy: https://trumonit.pro/privacy
* Terms: https://trumonit.pro/terms

What data is sent:

* API token (Bearer token) for authenticated API requests
* API login email/password only when you use the "Login via API" form
* Query params for pagination/filtering in monitor and incident requests

When data is sent:

* After admin submits Connect/Login/Refresh/Run scan actions
* While loading Monitors/Incidents tabs in wp-admin
* During automatic summary refresh on Overview when cached snapshot is stale

What endpoints are used:

* `POST /api/plugin/auth/login`
* `POST /api/plugin/auth/logout` (called when Disconnect is used in plugin UI)
* `GET /api/plugin/me`
* `GET /api/plugin/monitored-urls`
* `GET /api/plugin/incidents`
* `POST /api/plugin/monitored-urls/{id}/scan`

The plugin API contract is kept on stable unversioned `/api/plugin/*` routes so WordPress integration is not coupled to backend API version switches.

== Installation ==

1. Upload plugin folder to `/wp-content/plugins/`
2. Activate **TruMonit Monitor**
3. Open **TruMonit** in wp-admin
4. Paste service URL and API token, then click **Connect**

== Screenshots ==

1. Overview tab with connection status and health summary.
2. Monitors tab with pagination and quick "Run scan" action.
3. Incidents tab with recent incidents list.
4. Plans tab with pricing and billing shortcuts.

== Frequently Asked Questions ==

= Where do I get the API token? =

Generate a token in your TruMonit workspace user settings.

= What data is stored in WordPress? =

Service URL, encrypted API token, and last fetched health summary.

= Is this plugin writing monitors/incidents? =

Partly. It is mostly read-only, but it can trigger manual scan dispatch for a selected monitor.

== Changelog ==

= 0.1.0 =
* Connection by API token.
* API login (email/password) to obtain token.
* Health snapshot with monitor and incident counts.
* Recent monitors and incidents list in wp-admin.
* Manual "Run scan" action for a selected monitor.
* Plans tab with pricing and billing links.
