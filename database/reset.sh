#!/usr/bin/env bash
# Drop + create the CampusIQ database, import the schema, run the seed. One command:
#   bash database/reset.sh
# Reads DB_* from .env (values are never printed). Override binaries with MYSQL=... PHP=...
set -euo pipefail

cd "$(dirname "$0")/.."

MYSQL="${MYSQL:-/c/xampp/mysql/bin/mysql.exe}"
PHP="${PHP:-/c/xampp/php/php.exe}"

env_value() {
  # Last KEY=value line in .env, quotes and CR stripped. Empty if missing.
  local line
  line="$(grep -E "^[[:space:]]*$1[[:space:]]*=" .env 2>/dev/null | tail -n 1 || true)"
  line="${line#*=}"
  line="${line%$'\r'}"
  line="${line#\"}"; line="${line%\"}"
  printf '%s' "$line"
}

DB_HOST="$(env_value DB_HOST)"; DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="$(env_value DB_PORT)"; DB_PORT="${DB_PORT:-3306}"
DB_NAME="$(env_value DB_NAME)"; DB_NAME="${DB_NAME:-campusiq}"
DB_USER="$(env_value DB_USER)"; DB_USER="${DB_USER:-root}"
export MYSQL_PWD="$(env_value DB_PASS)"

if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
  echo "DB_NAME must be letters, numbers and underscores only." >&2
  exit 1
fi

mysql_cmd=("$MYSQL" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER")

echo "Resetting database '$DB_NAME'..."
"${mysql_cmd[@]}" -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "Importing database/schema.sql..."
"${mysql_cmd[@]}" "$DB_NAME" < database/schema.sql

echo "Clearing generated reports in storage/reports..."
find storage/reports -type f ! -name '.gitkeep' -delete

echo "Seeding..."
"$PHP" database/seed.php

echo "Done."
