#!/usr/bin/env bash
if [ -n "${DEBUG_SCRIPT:-}" ]; then
  set -x
fi
set -eu -o pipefail
cd $APP_ROOT

LOG_FILE="logs/init-$(date +%F-%T).log"
exec > >(tee $LOG_FILE) 2>&1

TIMEFORMAT=%lR
# Install regardless of security audit.
export COMPOSER_NO_AUDIT=1
export COMPOSER_NO_BLOCKING=1
# Keep deprecated var for compatibility with Composer versions where
# COMPOSER_NO_BLOCKING/--no-blocking is not supported yet.
export COMPOSER_NO_SECURITY_BLOCKING=1

#== Remove root-owned files.
echo
echo Remove root-owned files.
time sudo rm -rf lost+found

#== Composer install.
echo
if [ -f composer.json ]; then
  if composer show --locked cweagans/composer-patches ^2 &> /dev/null; then
    echo 'Update patches.lock.json.'
    time composer prl
    echo
  fi
else
  echo 'Generate composer.json.'
  time source .devpanel/composer_setup.sh
  echo
fi
time composer -n update --no-progress

#== Create the private files directory.
if [ ! -d private ]; then
  echo
  echo 'Create the private files directory.'
  time mkdir private
fi

#== Create the config sync directory.
if [ ! -d config/sync ]; then
  echo
  echo 'Create the config sync directory.'
  time mkdir -p config/sync
fi

#== Install Drupal.
echo
if [ -z "$(drush status --field=db-status)" ]; then
  echo 'Install Drupal base system.'
  until [ "$(drush sget install_task 2> /dev/null)" = 'install_configure_form' ]; do
    time .devpanel/install
  done
  drush -n cset system.site name 'Drupal CMS'

  echo
  echo 'Apply base recipes.'
  RECIPES_PATH=$(drush --include=.devpanel/drush crp)
  i=0
  while RECIPE=$(drush y:get:value recipes/drupal_cms_site_template_base/recipe.yml "recipes.$i"); do
    ((++i))
    echo "$RECIPE"
    if [[ ! $RECIPE =~ ^core/ ]]; then
      RECIPE="$RECIPES_PATH/$RECIPE"
    fi
    until time drush --include=.devpanel/drush -q recipe "$RECIPE"; do
      time drush cr
    done
  done

  echo
  echo 'Pre-seed completed operation hashes for applied recipes.'
  time drush --include=.devpanel/drush rrh

  echo
  echo 'Tell Automatic Updates about Composer plugins.'
  drush -n cset --input-format=yaml package_manager.settings additional_trusted_composer_plugins '["cweagans/composer-patches","drupal/site_template_helper"]'
  time drush ev '\Drupal::moduleHandler()->invoke("automatic_updates", "modules_installed", [[], FALSE])'

  echo
  time drush cr
else
  echo 'Update database.'
  time drush -n updb
fi

#== Warm up caches.
echo
echo 'Run cron.'
time drush cron
echo
echo 'Populate caches.'
time drush cache:warm &> /dev/null || :

#== Finish measuring script time.
INIT_DURATION=$SECONDS
INIT_HOURS=$(($INIT_DURATION / 3600))
INIT_MINUTES=$(($INIT_DURATION % 3600 / 60))
INIT_SECONDS=$(($INIT_DURATION % 60))
printf "\nTotal elapsed time: %d:%02d:%02d\n" $INIT_HOURS $INIT_MINUTES $INIT_SECONDS
