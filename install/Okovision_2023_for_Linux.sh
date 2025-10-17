#!/usr/bin/env bash
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

# --- helpers ---------------------------------------------------------------
log(){ echo -e "[OKV] $*"; }

need_cmd(){ command -v "$1" >/dev/null 2>&1 || { echo "Missing: $1"; exit 1; }; }

# --- OS detection ----------------------------------------------------------
source /etc/os-release
ID="${ID:-debian}"
VERSION_CODENAME="${VERSION_CODENAME:-}"
log "Detected: $PRETTY_NAME"

# --- base tools ------------------------------------------------------------
sudo apt-get update -y
sudo apt-get install -y ca-certificates curl wget gnupg lsb-release unzip

# --- PHP repo (Debian 11 needs sury for PHP 8.2) ---------------------------
if [[ "$ID" = "debian" && "$VERSION_CODENAME" = "bullseye" ]]; then
  log "Adding Sury PHP repo for Debian 11 (bullseye)"
  curl -fsSL https://packages.sury.org/php/apt.gpg | sudo gpg --dearmor -o /etc/apt/trusted.gpg.d/sury.gpg
  echo "deb https://packages.sury.org/php/ bullseye main" | sudo tee /etc/apt/sources.list.d/sury-php.list >/dev/null
  sudo apt-get update -y
fi

# --- MariaDB ---------------------------------------------------------------
log "Installing MariaDB server"
sudo apt-get install -y mariadb-server
sudo systemctl enable --now mariadb

# Create DB and user (idempotent & scoped)
DB_NAME="okovision"
DB_USER="okouser"
DB_PASS="okopass"
sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

# --- Apache + PHP >= 8.2 ---------------------------------------------------
log "Installing Apache"
sudo apt-get install -y apache2
sudo a2enmod rewrite
sudo systemctl enable --now apache2

# Try to install PHP 8.2 explicitly, fallback to distro 'php' meta if 8.2 unavailable
PHP_PKGS_COMMON="mysql mbstring xml curl gd intl zip"
if apt-cache policy php8.2 >/dev/null 2>&1; then
  log "Installing PHP 8.2"
  sudo apt-get install -y php8.2 php8.2-cli php8.2-common libapache2-mod-php8.2
  sudo apt-get install -y $(printf "php8.2-%s " $PHP_PKGS_COMMON)
else
  log "PHP 8.2 not found in repos; installing distro default PHP"
  sudo apt-get install -y php php-cli php-common libapache2-mod-php
  sudo apt-get install -y $(printf "php-%s " $PHP_PKGS_COMMON)
fi

# Ensure Apache uses our PHP
sudo systemctl restart apache2

# --- Deploy Okovision ------------------------------------------------------
log "Deploying Okovision to /var/www/okovision"
cd /var/www/
ZIPFILE="okovision.zip"

# Try master.zip then fallback to main.zip
if wget -q --spider https://github.com/domotrique/okovision_2023/archive/refs/heads/master.zip; then
  wget -q -O "$ZIPFILE" https://github.com/domotrique/okovision_2023/archive/refs/heads/master.zip
  SRC_DIR="okovision_2023-master"
else
  wget -q -O "$ZIPFILE" https://github.com/domotrique/okovision_2023/archive/refs/heads/main.zip
  SRC_DIR="okovision_2023-main"
fi

unzip -q "$ZIPFILE"
rm -f "$ZIPFILE"

# Backup existing install if present
if [[ -d "okovision" ]]; then
  mv okovision "$(date +"%y-%m-%d")_okovision"
fi
mv "$SRC_DIR" okovision
sudo chown -R www-data:www-data okovision

# --- Apache vhost ----------------------------------------------------------
if [[ -f /var/www/okovision/install/099-okovision.conf ]]; then
  sudo cp /var/www/okovision/install/099-okovision.conf /etc/apache2/sites-available/
  sudo a2ensite 099-okovision.conf
  sudo a2dissite 000-default || true
  sudo systemctl reload apache2
else
  log "WARNING: vhost file not found: /var/www/okovision/install/099-okovision.conf"
fi

# --- Cron (idempotent) -----------------------------------------------------
log "Setting up cron"
PHP_BIN="$(command -v php)"
CRONLINE="22 */1 * * * cd /var/www/okovision; ${PHP_BIN} -f cron.php"
( sudo crontab -l 2>/dev/null | grep -v "okovision; .*cron.php" || true; echo "$CRONLINE" ) | sudo crontab -

log "Install done! Open http://localhost/ in your browser."
