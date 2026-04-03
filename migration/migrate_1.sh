#!/bin/bash
set -e

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"; }

log "=== STEP 1: Docker install ==="

if ! command -v docker &> /dev/null; then
  log "Installing Docker..."
  curl -fsSL https://get.docker.com | sudo bash
  sudo usermod -aG docker $USER
  log "Docker installed"
  log "RELOGIN REQUIRED — run: exit, then ssh back in, then ./migrate_2.sh"
else
  log "Docker already installed — run migrate_2.sh"
fi