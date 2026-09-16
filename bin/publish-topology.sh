#!/usr/bin/env bash
set -euo pipefail

MAP_FILE="${1:-config/maps/example.conf}"
COMMIT_MESSAGE="${2:-design(topology): update flow layout and fix curves}"
LIBRELIVETOPOLOGY_CLI_PATH="${LIBRELIVETOPOLOGY_CLI:-}"

if [[ -n "$LIBRELIVETOPOLOGY_CLI_PATH" ]]; then
    [[ -f "$LIBRELIVETOPOLOGY_CLI_PATH" ]] || { echo "LIBRELIVETOPOLOGY_CLI not found: $LIBRELIVETOPOLOGY_CLI_PATH" >&2; exit 2; }
    php "$LIBRELIVETOPOLOGY_CLI_PATH" --lint "$MAP_FILE"
elif command -v librelivetopology >/dev/null 2>&1; then
    php "$(command -v librelivetopology)" --lint "$MAP_FILE"
else
    php bin/validate-map-config.php "$MAP_FILE"
fi

while IFS= read -r php_file; do
    php -l "$php_file" >/dev/null
done < <(git ls-files '*.php')

if [[ -x vendor/bin/phpunit ]]; then
    vendor/bin/phpunit --no-coverage
fi

REMOTE_URL="$(git remote get-url origin)"
if [[ "$REMOTE_URL" =~ ^https?://[^/]*:[^/]*@ ]]; then
    echo "Refusing a remote URL containing embedded credentials. Use SSH or a Git credential helper." >&2
    exit 3
fi

# Stage tracked topology/code changes only; untracked secrets stay untouched.
git add -u
if git diff --cached --quiet; then
    echo "Nothing to commit."
    exit 0
fi

git commit -m "$COMMIT_MESSAGE"
git push origin "$(git branch --show-current)"
