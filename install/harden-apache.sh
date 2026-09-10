#!/usr/bin/env bash

set -euo pipefail
SRC=/var/www/okovision/install/099-okovision.conf
DST=/etc/apache2/sites-available/099-okovision.conf
[ -f "$DST" ] && cp -a "$DST" "$DST.bak-$(date +%Y%m%d-%H%M%S)"
cp "$SRC" "$DST"
apache2ctl configtest
systemctl reload apache2
echo "[OKV] vhost durci applique."