#!/bin/bash
set -eo pipefail
rm -f /var/lib/mysql/mysql.sock

if [ "$#" -eq 0 ]; then
    set -- mysqld
fi

exec /usr/local/bin/docker-entrypoint.sh "$@"