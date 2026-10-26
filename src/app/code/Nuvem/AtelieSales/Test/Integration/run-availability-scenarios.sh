#!/usr/bin/env bash
set -euo pipefail

cd "$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel)"

test_database="f25_validation_$(date +%s)_$$"
database_created=false
server_started=false

cleanup() {
    if [[ "$server_started" == true ]]; then
        bin/docker-compose exec -T phpfpm sh -ec '
            if [ -f "$1/server.pid" ]; then
                kill "$(cat "$1/server.pid")" 2>/dev/null || true
            fi
        ' -- "/tmp/$test_database"
    fi
    if [[ "$database_created" == true ]]; then
        bin/docker-compose exec -T db sh -ec '
            mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE \`$1\`; REVOKE ALL PRIVILEGES ON \`$1\`.* FROM '\''$MYSQL_USER'\''@'\''%'\'';"
        ' -- "$test_database"
    fi
    bin/docker-compose exec -T phpfpm rm -rf -- "/tmp/$test_database"
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

bin/docker-compose exec -T -e "F25_TEST_DATABASE=$test_database" phpfpm sh -ec '
    mkdir -p "/tmp/$F25_TEST_DATABASE"
    php -S 127.0.0.1:18025 -t pub app/code/Nuvem/AtelieSales/Test/Integration/availability-router.php \
        > "/tmp/$F25_TEST_DATABASE/server.log" 2>&1 &
    echo $! > "/tmp/$F25_TEST_DATABASE/server.pid"
'
server_started=true
bin/docker-compose exec -T phpfpm sh -ec '
    for attempt in $(seq 1 20); do
        if ! kill -0 "$(cat "$1/server.pid")" 2>/dev/null; then
            cat "$1/server.log"
            exit 1
        fi
        if curl -s --max-time 2 http://127.0.0.1:18025/rest/default/V1/atelie/disponibilidade/NUVEM-RUNNER > /dev/null; then
            exit 0
        fi
        sleep 1
    done
    cat "$1/server.log"
    exit 1
' -- "/tmp/$test_database"
if ! bin/docker-compose exec -T -e "F25_TEST_DATABASE=$test_database" phpfpm php \
    app/code/Nuvem/AtelieSales/Test/Integration/AvailabilityScenarios.php http://127.0.0.1:18025/rest/default/V1; then
    bin/docker-compose exec -T phpfpm cat "/tmp/$test_database/server.log"
    exit 1
fi
