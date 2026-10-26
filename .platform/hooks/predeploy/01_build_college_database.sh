#!/bin/bash
#
# Builds the production database from database/data/colleges.json.
#
# The app only reads college data and never saves athlete answers, so the
# database is a read-only copy of a file in GitHub. Elastic Beanstalk runs
# predeploy hooks on every application deployment and whenever it provisions
# a new instance (for example, when AWS replaces a server), before the new
# version goes live. If any step fails, the deployment stops and the
# previous version keeps running.

set -euo pipefail

DATABASE_FILE="database/database.sqlite"
EXPECTED_COLLEGES=25

echo "Building college database in $(pwd)"

rm -f "$DATABASE_FILE"
touch "$DATABASE_FILE"

php artisan migrate --force --no-interaction
php artisan db:seed --class=CollegeSeeder --force --no-interaction

COLLEGE_COUNT=$(php -r '$db = new PDO("sqlite:database/database.sqlite"); echo $db->query("SELECT COUNT(*) FROM colleges")->fetchColumn();')

if [ "$COLLEGE_COUNT" -lt "$EXPECTED_COLLEGES" ]; then
    echo "Expected at least $EXPECTED_COLLEGES colleges but found $COLLEGE_COUNT. Stopping deployment."
    exit 1
fi

echo "Loaded $COLLEGE_COUNT colleges."

# The web server runs as the webapp user, so it needs to read the database and
# write sessions, logs, and cached views.
chown -R webapp:webapp database storage bootstrap/cache
