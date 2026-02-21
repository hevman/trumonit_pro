# TruMonit Monitor test flow

## 1) WP smoke check

Run from WordPress root:

```bash
wp eval-file wp-content/plugins/trumonit-monitor/tests/wp-flow-smoke.php
```

This validates:
- plugin class is loaded
- all admin-post handlers are registered
- core options keep expected types

## 2) Automated wp-env integration smoke

Run from plugin directory:

```bash
bash tests/wp-env-smoke.sh
```

This bootstraps temporary WordPress via `@wordpress/env`, activates plugin, and runs `tests/wp-flow-smoke.php`.

## 3) Manual integration flow

1. Open `wp-admin -> TruMonit -> Overview`.
2. Connect with valid `Service URL + API token` and confirm success notice.
3. Open `Monitors`, run `Run scan`, and verify redirect stays on Monitors tab.
4. Open `Incidents`, apply status filter + pagination, verify rows update.
5. Disconnect and confirm snapshot and token are removed.
