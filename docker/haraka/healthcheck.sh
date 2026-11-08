#!/bin/sh
# NetSendo MTA healthcheck: the first address Haraka listens on (smtp.ini listen=,
# NMI_MTA_LISTEN) accepts connections
listen=$(sed -n 's/^listen=//p' /opt/haraka/config/smtp.ini | head -n 1 | cut -d, -f1)
port=${listen##*:}
host=$(printf '%s' "${listen%:*}" | tr -d '[]')
case "$host" in
    ''|'::'|'0.0.0.0') host=127.0.0.1 ;;
esac
exec nc -z -w 5 "$host" "${port:-25}"
