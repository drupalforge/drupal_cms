#!/bin/bash
# ---------------------------------------------------------------------
# Copyright (C) 2025 DevPanel
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation version 3 of the
# License.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# For GNU Affero General Public License see <https://www.gnu.org/licenses/>.
# ----------------------------------------------------------------------

cd $APP_ROOT

#== Import database
if [ -z "$(drush status --field=db-status)" ]; then
  if [[ -f .devpanel/dumps/db.sql.gz ]]; then
    echo 'Import mysql file ...'
    drush sqlq --file=../.devpanel/dumps/db.sql.gz
  fi
fi

if [[ -n "$DB_SYNC_VOL" ]]; then
  if [[ ! -f "../build/.devpanel/init-container.sh" ]]; then
    echo 'Sync volume...'
    sudo rsync -a --ignore-existing --exclude .git ./* ../build/
  fi
fi

drush -n updb
echo
echo 'Run cron.'
drush cron
echo
echo 'Populate caches.'
drush cache:warm &> /dev/null || :
