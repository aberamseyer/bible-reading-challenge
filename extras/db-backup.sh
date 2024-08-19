#!/bin/sh
# backup for brc sqlite database

# the following line begin deletes the oldest backup. add comment to collect however many backups you want to retain first
cd /home/public/export/ && rm `ls -l | grep brc | awk '{print $9}' | head -n 1`

sqlite3 /home/bible-reading-challenge/brc.db .dump | gzip > /home/public/export/brc-$(date --utc +%Y%m%d_%H%M%SZ).sql.gz