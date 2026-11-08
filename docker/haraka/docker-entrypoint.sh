#!/bin/sh
# =============================================================================
# NetSendo MTA (Haraka) entrypoint
# =============================================================================
# The name the MTA gives itself to the recipients' mail servers (HELO/EHLO).
# Set NMI_HOSTNAME to the name the PTR record of the sending IP points to,
# e.g. mail.example.com: a HELO that does not match the IP costs deliverability.

if [ -n "${NMI_HOSTNAME:-}" ]; then
    if ! printf '%s\n' "$NMI_HOSTNAME" > /opt/haraka/config/me 2>/dev/null; then
        echo "netsendo-mta: cannot write config/me, the MTA introduces itself as $(hostname)" >&2
    fi
else
    echo "netsendo-mta: NMI_HOSTNAME is not set, the MTA introduces itself as $(hostname)." >&2
    echo "netsendo-mta: set NMI_HOSTNAME in .env to the PTR name of the sending IP." >&2
fi

exec "$@"
