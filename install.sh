#!/bin/bash
# =============================================================================
# NetSendo - Quick Installation Script
# =============================================================================
# Downloads the production configuration and starts NetSendo using the
# pre-built Docker images from ghcr.io/netsendo/netsendo.
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/NetSendo/NetSendo/main/install.sh | bash
#
# Options (environment variables for bash, after the pipe):
#   ... | VERSION=2.7.1 bash               # Install a specific version (default: latest)
#   ... | INSTALL_DIR=/opt/netsendo bash   # Install folder (default: ./netsendo)
#   ... | HTTP_PORT=8080 bash              # Port of the web app (default: 5029)
# =============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
NETSENDO_VERSION="${VERSION:-latest}"
NETSENDO_VERSION="${NETSENDO_VERSION#v}"   # image tags are 2.6.0, git tags v2.6.0
INSTALL_DIR="${INSTALL_DIR:-./netsendo}"
HTTP_PORT="${HTTP_PORT:-5029}"
GITHUB_REPO="NetSendo/NetSendo"

# Configuration files come from the same release as the image
if [ "$NETSENDO_VERSION" = "latest" ]; then
    GIT_REF="main"
else
    GIT_REF="v${NETSENDO_VERSION}"
fi
RAW_URL="https://raw.githubusercontent.com/${GITHUB_REPO}/${GIT_REF}"

echo -e "${BLUE}"
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                                                               ║"
echo "║              NetSendo Installation Script                     ║"
echo "║                                                               ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo -e "${YELLOW}📦 Installing NetSendo version: ${NETSENDO_VERSION}${NC}"
echo ""

# Check for Docker
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker is not installed. Please install Docker first.${NC}"
    echo "   Visit: https://docs.docker.com/get-docker/"
    exit 1
fi

# Check for Docker Compose
if ! docker compose version &> /dev/null; then
    echo -e "${RED}❌ Docker Compose is not available. Please update Docker.${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Docker and Docker Compose found${NC}"

# An existing installation keeps its .env: a new APP_KEY or database password
# would lock it out of its own data.
if [ -f "$INSTALL_DIR/.env" ]; then
    echo -e "${YELLOW}⚠️  NetSendo is already installed in $INSTALL_DIR${NC}"
    echo "   To update it, run:"
    echo "   cd $INSTALL_DIR && docker compose pull && docker compose up -d"
    exit 1
fi

mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

echo -e "${BLUE}📥 Downloading configuration files (${GIT_REF})...${NC}"

download() {
    mkdir -p "$(dirname "$2")"
    if ! curl -fsSL "${RAW_URL}/$1" -o "$2"; then
        echo -e "${RED}❌ Could not download $1 from ${GIT_REF}. Check the version number.${NC}"
        exit 1
    fi
}

# Every file docker-compose.yml mounts must exist, or Docker creates a folder in its place
download docker-compose.yml docker-compose.yml
download docker/nginx/default.conf docker/nginx/default.conf
download docker/php/docker-entrypoint.sh docker/php/docker-entrypoint.sh
download docker/php/uploads.ini docker/php/uploads.ini
download .env.example .env
chmod +x docker/php/docker-entrypoint.sh
mkdir -p backups/db

# Set KEY=VALUE in .env, replacing the line if it exists
set_env() {
    local tmp
    tmp="$(mktemp)"
    awk -v key="$1" -v value="$2" '
        BEGIN { done = 0 }
        index($0, key "=") == 1 { print key "=" value; done = 1; next }
        { print }
        END { if (!done) print key "=" value }
    ' .env > "$tmp"
    cat "$tmp" > .env
    rm -f "$tmp"
}

random_base64() {
    if command -v openssl &> /dev/null; then
        openssl rand -base64 "$1"
    else
        head -c "$1" /dev/urandom | base64
    fi
}

# The containers mount .env read-only, so the key has to be in it before the first start
set_env APP_KEY "base64:$(random_base64 32)"
set_env APP_URL "http://localhost:${HTTP_PORT}"
set_env HTTP_PORT "${HTTP_PORT}"
set_env DB_PASSWORD "$(random_base64 32 | tr -dc 'a-zA-Z0-9' | head -c 24)"
# Reverb (Live Visitors): the example key and secret are public, and the secret signs
# access to the private channels, so every installation gets its own
set_env REVERB_APP_KEY "$(random_base64 32 | tr -dc 'a-zA-Z0-9' | head -c 20)"
set_env REVERB_APP_SECRET "$(random_base64 48 | tr -dc 'a-zA-Z0-9' | head -c 32)"
set_env NETSENDO_VERSION "${NETSENDO_VERSION}"

echo -e "${GREEN}✅ Configuration files created${NC}"

echo -e "${BLUE}🚀 Starting NetSendo...${NC}"

docker compose pull
docker compose up -d

echo ""
echo -e "${BLUE}⏳ Waiting for NetSendo to answer (first start runs the migrations)...${NC}"
ready=0
for _ in $(seq 1 60); do
    code="$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:${HTTP_PORT}/" || true)"
    if [ "$code" != "000" ] && [ "$code" -lt 500 ]; then
        ready=1
        break
    fi
    sleep 5
done

echo ""
if [ "$ready" = "1" ]; then
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                               ║${NC}"
    echo -e "${GREEN}║              ✅ NetSendo installed successfully!              ║${NC}"
    echo -e "${GREEN}║                                                               ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
else
    echo -e "${YELLOW}⚠️  NetSendo is still starting. Follow it with: docker compose logs -f app${NC}"
fi
echo ""
echo -e "${BLUE}📍 Access your application:${NC}"
echo "   • Web App:  http://localhost:${HTTP_PORT}"
echo "   • Mailpit:  http://localhost:5031 (test inbox)"
echo ""
echo -e "${BLUE}📋 Useful commands:${NC}"
echo "   • View logs:  docker compose logs -f"
echo "   • Stop:       docker compose down"
echo "   • Start:      docker compose up -d"
echo ""
echo -e "${YELLOW}🌐 Before going public:${NC}"
echo "   Set APP_URL in .env to your https:// address, put a reverse proxy in front"
echo "   of port ${HTTP_PORT} and run: docker compose up -d"
echo ""
echo -e "${YELLOW}🔄 To update to a new version:${NC}"
echo "   1. Edit .env and change NETSENDO_VERSION (or keep 'latest')"
echo "   2. Run: docker compose pull && docker compose up -d"
echo ""
