#!/bin/bash

echo "🔧 Naprawianie zależności frontendowych..."

# Wymuszenie instalacji mimo błędów peerDeps (Vite 7 vs plugin-vue)
docker compose exec -u dev app npm install --legacy-peer-deps

echo "🎨 Budowanie frontendu..."
docker compose exec -u dev app npm run build

echo "✅ Frontend gotowy!"
