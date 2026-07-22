#!/bin/bash
set -euo pipefail

echo "=== MIS Project Setup ==="
echo ""

# Check Docker access
if docker ps &>/dev/null; then
    echo "[OK] Docker is accessible"
else
    echo "[!] Docker not accessible — run this script in a NEW terminal after restarting WSL:"
    echo "    On Windows: wsl.exe --shutdown  (or 'wsl --shutdown' from PowerShell)"
    echo "    Then open a new terminal and run: bash setup.sh"
    exit 1
fi

# Build images (first time can be slow)
echo "[1/4] Building Docker images..."
make build
echo ""

# Start services
echo "[2/4] Starting services..."
make up
echo ""

# Backend setup
echo "[3/4] Setting up backend..."
make composer-install
make key
echo "Waiting for PostgreSQL..."
sleep 5
make migrate
make seed
make storage
echo ""

# Verify
echo "[4/4] Verification..."
echo "  - Backend:  http://localhost:8000/api/v1/health"
echo "  - Frontend: http://localhost:3000"
echo "  - Mailpit:  http://localhost:8025"
echo "  - Meili:    http://localhost:7700"

echo ""
echo "=== Setup complete! Run 'make logs' to see all services ==="
