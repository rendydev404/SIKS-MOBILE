#!/bin/bash
set -e

VPS_HOST="${1:-76.13.193.138}"
VPS_USER="${2:-root}"
VPS_DIR="/opt/siks-wa-gateway"

echo "🚀 Deploying siks-wa-gateway to $VPS_USER@$VPS_HOST..."

ssh -o BatchMode=yes "$VPS_USER@$VPS_HOST" "mkdir -p $VPS_DIR/src/services $VPS_DIR/src/templates $VPS_DIR/data/sessions"

scp package.json Dockerfile docker-compose.yml .env.example "$VPS_USER@$VPS_HOST:$VPS_DIR/"
scp -r src/* "$VPS_USER@$VPS_HOST:$VPS_DIR/src/"

if [ -f "../assets/img/logo_sekolah.png" ]; then
    scp ../assets/img/logo_sekolah.png "$VPS_USER@$VPS_HOST:$VPS_DIR/src/templates/logo_sekolah.png"
fi

ssh -o BatchMode=yes "$VPS_USER@$VPS_HOST" "cd $VPS_DIR && cp -n .env.example .env || true && docker compose up -d --build"

sleep 5
ssh -o BatchMode=yes "$VPS_USER@$VPS_HOST" "docker ps --filter name=siks-wa-gateway && curl -s http://localhost:3050/health || true"

echo "✅ Deployment finished successfully!"
