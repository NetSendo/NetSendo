#!/usr/bin/env bash
# =============================================================================
# NetSendo MTA, end to end
# =============================================================================
# Starts the MTA built from docker/haraka with three addresses and a receiving mail
# server (sink.py), then runs the two tests of src/tests/Feature/NmiMailboxTest.php
# that are skipped in the normal suite:
#
#   - a mailbox saved through the form logs in to the MTA and its mail is accepted;
#   - mail leaves from the dedicated IP, says the IP's host name in HELO, and arrives
#     with a DKIM signature the receiver can verify.
#
# Needs Docker and PHP with the application's dependencies installed (src/vendor).
# Nothing leaves the test network: the recipient domain is the receiving server.
#
#   docker/haraka/test/e2e.sh
#   NMI_E2E_SUBNET=10.202.57 docker/haraka/test/e2e.sh    # when 10.201.57.0/24 is taken
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
NET="${NMI_E2E_SUBNET:-10.201.57}"
PORT="${NMI_E2E_PORT:-12525}"
WORK="$(mktemp -d)"
NETWORK=netsendo-mta-e2e
MTA=netsendo-mta-e2e
SINK=netsendo-mta-e2e-sink
IMAGE=netsendo-mta-e2e

cleanup() {
    docker rm -f "$MTA" "$SINK" >/dev/null 2>&1 || true
    docker network rm "$NETWORK" >/dev/null 2>&1 || true
    rm -rf "$WORK"
}
trap cleanup EXIT
cleanup
mkdir -p "$WORK/auth" "$WORK/sink"
chmod 777 "$WORK/sink"

echo "Building the MTA image..."
docker build -q -t "$IMAGE" "$ROOT/docker/haraka" >/dev/null
docker network create --subnet "$NET.0/24" "$NETWORK" >/dev/null

# The receiving server: the recipient domain sink.test resolves to it
docker run -d --name "$SINK" --network "$NETWORK" --ip "$NET.20" --network-alias sink.test \
    -v "$ROOT/docker/haraka/test:/tool:ro" -v "$WORK/sink:/data" python:3.12-alpine \
    sh -c "pip install -q --root-user-action=ignore aiosmtpd dkimpy dnspython && python /tool/sink.py" >/dev/null

# The MTA: .10 is its own address, .11 a dedicated IP it owns, .12 the local side of a
# translated public address (NMI_BIND_MAP)
docker run -d --name "$MTA" --network "$NETWORK" --ip "$NET.10" --cap-add NET_ADMIN \
    -p "127.0.0.1:$PORT:25" \
    -e "NMI_BIND_MAP=198.51.100.7=$NET.12" -e NMI_HELO_HOST=mta.example.com \
    -v "$ROOT/docker/haraka/config:/opt/haraka/config:ro" \
    -v "$ROOT/docker/haraka/plugins:/opt/haraka/plugins:ro" \
    -v "$WORK/auth:/opt/haraka/netsendo:ro" "$IMAGE" >/dev/null
docker exec -u root "$MTA" sh -c "ip addr add $NET.11/24 dev eth0 && ip addr add $NET.12/24 dev eth0"

echo "Waiting for the MTA and the receiving server..."
for _ in $(seq 1 60); do
    if docker logs "$SINK" 2>&1 | grep -q "sink listening" \
        && (exec 3<>"/dev/tcp/127.0.0.1/$PORT") 2>/dev/null; then
        break
    fi
    sleep 1
done
docker logs "$SINK" 2>&1 | grep -q "sink listening" || { echo "The receiving server did not start:"; docker logs "$SINK"; exit 1; }

cd "$ROOT/src"
status=0
NMI_E2E_MTA_HOST=127.0.0.1 NMI_E2E_MTA_PORT="$PORT" NMI_E2E_AUTH_FILE="$WORK/auth/auth.json" \
NMI_E2E_SINK_DIR="$WORK/sink" NMI_E2E_RCPT=nobody@sink.test \
NMI_E2E_DEFAULT_IP="$NET.10" NMI_E2E_HELO=mta.example.com NMI_E2E_BIND_IP="$NET.11" \
NMI_E2E_MAPPED_IP=198.51.100.7 NMI_E2E_MAPPED_LOCAL="$NET.12" \
APP_CONFIG_CACHE="$WORK/no-config.php" APP_ROUTES_CACHE="$WORK/no-routes.php" \
APP_PACKAGES_CACHE="$WORK/packages.php" APP_SERVICES_CACHE="$WORK/services.php" \
    php artisan test tests/Feature/NmiMailboxTest.php \
        --filter='test_a_saved_nmi_mailbox_is_accepted_by_a_running_mta|test_mail_leaves_from_the_dedicated_ip_with_a_valid_dkim_signature' \
    || status=$?

if [ "$status" -ne 0 ]; then
    echo "--- MTA log (last lines)"
    docker logs "$MTA" 2>&1 | grep -v "iconv\|TLD\|Suffix" | tail -40
    echo "--- what the receiving server saw"
    cat "$WORK/sink/sink.jsonl" 2>/dev/null || true
fi

exit "$status"
