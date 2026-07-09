<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class WorkerInstallScriptController extends Controller
{
    public function show(): Response
    {
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

PANEL_URL=""
REGISTRATION_TOKEN=""
WORKER_VERSION="latest"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --panel-url)
      PANEL_URL="$2"
      shift 2
      ;;
    --token)
      REGISTRATION_TOKEN="$2"
      shift 2
      ;;
    --version)
      WORKER_VERSION="$2"
      shift 2
      ;;
    *)
      echo "Unknown argument: $1"
      exit 1
      ;;
  esac
done

if [[ -z "$PANEL_URL" ]]; then
  echo "Missing --panel-url"
  exit 1
fi

if [[ -z "$REGISTRATION_TOKEN" ]]; then
  echo "Missing --token"
  exit 1
fi

if [[ "$EUID" -ne 0 ]]; then
  echo "Please run as root or with sudo."
  exit 1
fi

echo "Installing HivePanel Worker..."

mkdir -p /etc/hivepanel
mkdir -p /var/lib/hivepanel/cells
mkdir -p /var/lib/hivepanel/backups
mkdir -p /var/lib/hivepanel/data

cat > /etc/hivepanel/worker.yml <<YAML
panel:
  url: "${PANEL_URL}"

worker:
  registration_token: "${REGISTRATION_TOKEN}"
  token: ""
  listen: "0.0.0.0:8080"

node:
  id: ""

paths:
  data: "/var/lib/hivepanel/data"
  instances: "/var/lib/hivepanel/cells"
  backups: "/var/lib/hivepanel/backups"

runtime:
  type: "docker"

docker:
  network: "hivepanel"

allocations:
  ip: "0.0.0.0"
  port_start: 25565
  port_end: 25600
YAML

cat > /etc/systemd/system/hiveworker.service <<SERVICE
[Unit]
Description=HivePanel Worker
After=network.target docker.service
Wants=docker.service

[Service]
User=root
WorkingDirectory=/var/lib/hivepanel
ExecStart=/usr/local/bin/hiveworker --config /etc/hivepanel/worker.yml
Restart=always
RestartSec=5
LimitNOFILE=1048576

[Install]
WantedBy=multi-user.target
SERVICE

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker was not found. Install Docker before starting worker runtime containers."
fi

echo "Downloading HivePanel Worker binary..."

ARCH="$(uname -m)"

case "$ARCH" in
  x86_64|amd64)
    WORKER_ARCH="amd64"
    ;;
  aarch64|arm64)
    WORKER_ARCH="arm64"
    ;;
  *)
    echo "Unsupported architecture: $ARCH"
    exit 1
    ;;
esac

DOWNLOAD_URL="https://github.com/HiveOSSoftware/hiveworker/releases/latest/download/hiveworker_linux_${WORKER_ARCH}"

echo "Downloading HivePanel Worker from:"
echo "${DOWNLOAD_URL}"

curl -fsSL "${DOWNLOAD_URL}" -o /usr/local/bin/hiveworker
chmod +x /usr/local/bin/hiveworker

systemctl daemon-reload
systemctl enable hiveworker
systemctl restart hiveworker

echo "HivePanel Worker installed."
echo "Check status with:"
echo "systemctl status hiveworker"
BASH;

        return response($script, 200, [
            'Content-Type' => 'text/x-shellscript; charset=UTF-8',
        ]);
    }
}