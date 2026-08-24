#!/usr/bin/env bash
# Forge/manual deploy for coopos.ng (Docker). Touches only coopos-* containers.
set -euo pipefail
ROOT="${FORGE_SITE_PATH:-$(cd "$(dirname "$0")/.." && pwd)}"
cd "$ROOT"
TAG=$(git rev-parse --short HEAD)
docker build -t coopos-app:"$TAG" -t coopos-app:latest .
export IMAGE_TAG="$TAG"
if [ -f docker-compose.prod.yml ]; then
  docker compose -p coopos -f docker-compose.yml -f docker-compose.prod.yml up -d --remove-orphans --force-recreate
else
  docker compose -p coopos up -d --remove-orphans
fi
s=none
for i in $(seq 1 40); do
  s=$(docker inspect -f '{{.State.Health.Status}}' coopos-app 2>/dev/null || echo none)
  [ "$s" = healthy ] && break
  # host-network may report starting longer; also accept running + /up
  if curl -fsS -H 'Host: coopos.ng' http://127.0.0.1:8088/up >/dev/null 2>&1; then s=healthy; break; fi
  sleep 3
done
[ "$s" = healthy ] || { echo "coopos-app not healthy ($s)"; docker logs --tail 80 coopos-app || true; exit 1; }
docker restart coopos-queue 2>/dev/null || true
echo "Deployed coopos-app:$TAG"
