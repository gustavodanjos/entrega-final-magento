#!/usr/bin/env bash
set -euo pipefail

cd "$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel)"
command -v jq >/dev/null

test_database="f24_validation_$(date +%s)_$$"
test_output=$(mktemp -d /tmp/f24-returns.XXXXXX)
database_created=false
race_pid=

cleanup() {
    if [[ -n "$race_pid" ]]; then
        wait "$race_pid" || true
    fi
    if [[ "$database_created" == true ]]; then
        bin/docker-compose exec -T db sh -ec '
            mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE \`$1\`; REVOKE ALL PRIVILEGES ON \`$1\`.* FROM '\''$MYSQL_USER'\''@'\''%'\'';"
        ' -- "$test_database"
    fi
    bin/docker-compose exec -T phpfpm rm -rf -- "/tmp/$test_database"
    rm -rf -- "$test_output"
}
trap cleanup EXIT

bin/docker-compose exec -T db sh -ec '
    mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE \`$1\`; GRANT ALL PRIVILEGES ON \`$1\`.* TO '\''$MYSQL_USER'\''@'\''%'\'';"
' -- "$test_database"
database_created=true
bin/docker-compose exec -T db bash -euo pipefail -c '
    mariadb-dump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --skip-lock-tables --no-tablespaces "$MYSQL_DATABASE" |
        mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "$1"
' -- "$test_database"

scenario=(bin/docker-compose exec -T -e "F24_TEST_DATABASE=$test_database" phpfpm php
    app/code/Nuvem/AtelieSales/Test/Integration/ReturnStockScenarios.php)
"${scenario[@]}" scenarios

for kind in same distinct; do
    fixture=$("${scenario[@]}" create-race "$kind")
    order_id=$(jq -er '.order_id' <<< "$fixture")
    creditmemo_id=$(jq -er '.creditmemo_id' <<< "$fixture")
    second_id=$(jq -er '.second_id' <<< "$fixture")
    initial_stock=$(jq -er '.stock' <<< "$fixture")
    "${scenario[@]}" refund-held "$creditmemo_id" > "$test_output/held.log" 2>&1 &
    race_pid=$!
    for ((attempt = 0; attempt < 100; attempt++)); do
        if grep -q '^LOCKED$' "$test_output/held.log"; then
            break
        fi
        if ! kill -0 "$race_pid" 2>/dev/null; then
            cat "$test_output/held.log"
            exit 1
        fi
        sleep 0.1
    done
    grep -q '^LOCKED$' "$test_output/held.log"
    "${scenario[@]}" refund-snapshot "$second_id"
    wait "$race_pid"
    race_pid=
    expected=1
    if [[ "$kind" == distinct ]]; then
        expected=2
    fi
    "${scenario[@]}" verify-race "$order_id" "$initial_stock" "$expected"
done
