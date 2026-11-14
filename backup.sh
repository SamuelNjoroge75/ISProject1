#!/bin/bash
# Daily database backup (creative feature). Run as root so mysqldump can use the
# local socket login, with no password stored in any file.
set -euo pipefail
DIR="/var/backups/isproject1"
mkdir -p "$DIR"
chmod 700 "$DIR"
mysqldump --single-transaction ISProject1 | gzip > "$DIR/isproject1_$(date +%F_%H%M).sql.gz"
# keep the last 7 days only
find "$DIR" -name 'isproject1_*.sql.gz' -mtime +7 -delete
