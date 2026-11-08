#!/bin/sh
# =============================================================================
# NetSendo MTA (Haraka) entrypoint
# =============================================================================
# NetSendo decides the name each message is sent under (login file, netsendo_outbound
# plugin). config/me is only the name in the greeting and in the Received header: the
# same default, NMI_HOSTNAME or else the host of APP_URL (both from .env).
#
# NMI_MTA_LISTEN replaces the addresses Haraka listens on (smtp.ini listen=), for the
# MTA on the server's own network (network_mode: host), e.g. 172.17.0.1:2525.

valid_name() {
    printf '%s' "$1" | grep -Eq '^([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z][A-Za-z0-9-]{0,61}[A-Za-z0-9]$'
}

name="${NMI_HOSTNAME:-}"
if [ -z "$name" ] && [ -n "${APP_URL:-}" ]; then
    name=$(printf '%s' "$APP_URL" | sed -E 's|^[A-Za-z][A-Za-z0-9+.-]*://||; s|^[^@/]*@||; s|[/:?#].*$||')
fi

if [ -n "$name" ] && valid_name "$name"; then
    if ! printf '%s\n' "$name" > /opt/haraka/config/me 2>/dev/null; then
        echo "netsendo-mta: cannot write config/me, the greeting names $(hostname)" >&2
    fi
fi

if [ -n "${NMI_MTA_LISTEN:-}" ]; then
    if printf '%s' "$NMI_MTA_LISTEN" | grep -Eq '^[][0-9A-Fa-f.:,]+$'; then
        sed -i "s|^listen=.*|listen=${NMI_MTA_LISTEN}|" /opt/haraka/config/smtp.ini
    else
        echo "netsendo-mta: NMI_MTA_LISTEN='${NMI_MTA_LISTEN}' is not a list of address:port, ignored" >&2
    fi
fi

exec "$@"
