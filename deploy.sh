#!/bin/bash
# Deploy the gym site on the droplet.  Run as:  sudo bash deploy.sh
#
# Lives in the repo rather than only on the server, so a rebuilt droplet does
# not depend on anyone remembering what it used to contain.
set -euo pipefail
cd "$(dirname "$0")"

echo "Pulling latest code..."
git pull --ff-only

# Unlike the fightnights deploy, install dependencies every time. A composer.lock
# change that is pulled but not installed fails at runtime, not at deploy.
echo "Installing dependencies..."
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

echo "Fixing ownership..."
chown -R www-data:www-data .
# Uploads must be writable by Apache or the admin cannot save a photo.
mkdir -p public/uploads
chmod 775 public/uploads

echo "Deploy complete."
# There is no migration runner, and adding one that guesses which statements
# have already run is worse than a reminder. schema-changes.sql is in commit
# order, so the ones below the last block you applied are the ones outstanding.
echo
echo "Reminder: if this release added columns, apply them by hand —"
echo "  mysql -u <user> -p <database> < database/schema-changes.sql"
echo "Running a block twice is an error, not damage; skip what is already there."
