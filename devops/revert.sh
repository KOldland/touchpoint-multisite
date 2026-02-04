#!/usr/bin/env bash
set -euo pipefail

cat <<'EOF'
# Revert PR (GitHub CLI)
# gh pr revert <PR_NUMBER> --repo KOldland/touchpoint-template

# Deactivate KH Events plugin
# wp plugin deactivate kh-events

# Stop aggregator worker (example if using systemd)
# sudo systemctl stop kh-smma-aggregator
EOF
