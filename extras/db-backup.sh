#!/bin/sh
# backup for brc sqlite database. run daily from the host's crontab, e.g.
#   5 4 * * * /home/ubuntu/source/bible-reading-challenge/extras/db-backup.sh
# usage: db-backup.sh [backup dir] [days to keep]
set -eu

DB="$(dirname "$0")/../brc.db"
BACKUP_DIR="${1:-/home/ubuntu/backup/brc}"
KEEP_DAYS="${2:-30}"

mkdir -p "$BACKUP_DIR"
# .backup takes a consistent snapshot while the app keeps writing (unlike copying the file)
sqlite3 "$DB" ".backup '$BACKUP_DIR/brc-$(date --utc +%Y%m%dT%H%M%SZ).db'"
find "$BACKUP_DIR" -name 'brc-*.db' -mtime +"$KEEP_DAYS" -delete
