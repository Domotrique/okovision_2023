#!/bin/sh
set -e

APP_DIR=/var/www/okovision
cd "$APP_DIR"

# Dossiers écrits à l'exécution par l'application
for d in _tmp _logs dumps var; do
    mkdir -p "$d"
    chown www-data:www-data "$d"
done

# Prod : config.php / config.json (générés par setup.php à la racine de l'app)
# sont redirigés vers un volume pour survivre à la recréation du conteneur.
# Tant que la cible n'existe pas, file_exists() renvoie false et setup.php s'affiche ;
# son file_put_contents() suit le lien et crée le fichier dans le volume.
if [ -n "$OKV_CONFIG_DIR" ]; then
    mkdir -p "$OKV_CONFIG_DIR"
    chown www-data:www-data "$OKV_CONFIG_DIR"
    for f in config.php config.json; do
        if [ -f "$f" ] && [ ! -L "$f" ]; then
            # Fichier présent dans l'image : on le migre dans le volume s'il n'y est pas déjà
            [ -e "$OKV_CONFIG_DIR/$f" ] || mv "$f" "$OKV_CONFIG_DIR/$f"
            rm -f "$f"
        fi
        [ -L "$f" ] || ln -s "$OKV_CONFIG_DIR/$f" "$f"
    done
fi

if [ "$OKV_CRON" = "1" ]; then
    echo "[OKV] Démarrage de cron (cron.php à hh:22)"
    cron
fi

exec "$@"
