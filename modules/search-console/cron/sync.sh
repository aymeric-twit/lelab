#!/usr/bin/env bash
# ============================================================
# Cron de synchronisation Search Console
#
# Installation dans crontab (exécution quotidienne à 6h00) :
#   crontab -e
#   0 6 * * * /chemin/vers/search-console/cron/sync.sh >> /chemin/vers/search-console/storage/sync.log 2>&1
#
# Ou avec un chemin absolu vers php :
#   0 6 * * * /usr/bin/php /chemin/vers/search-console/bin/sync.php >> /chemin/vers/search-console/storage/sync.log 2>&1
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Créer le dossier de logs s'il n'existe pas
mkdir -p "$PROJECT_DIR/storage"

# Exécuter la synchronisation
php "$PROJECT_DIR/bin/sync.php" >> "$PROJECT_DIR/storage/sync.log" 2>&1

EXIT_CODE=$?

if [ $EXIT_CODE -ne 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Sync terminée avec erreur (code: $EXIT_CODE)" >> "$PROJECT_DIR/storage/sync.log"
fi

exit $EXIT_CODE
