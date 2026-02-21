#!/usr/bin/env bash
set -euo pipefail

cleanup() {
  wp-env stop || true
}
trap cleanup EXIT

wp-env start

wp-env run cli wp plugin activate trumonit-monitor
wp-env run cli wp eval-file wp-content/plugins/trumonit-monitor/tests/wp-flow-smoke.php
