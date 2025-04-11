#!/usr/bin/env bash
set -eu -o pipefail

cd $APP_ROOT

LOG_FILE="logs/init-$(date +%F-%T).log"
exec > >(tee $LOG_FILE) 2>&1

TIMEFORMAT=%lR
DDEV_DOCROOT=${WEB_ROOT##*/}
SETTINGS_FILE_PATH=$WEB_ROOT/sites/default/settings.php
# For faster performance, don't audit dependencies automatically.
export COMPOSER_NO_AUDIT=1
# For faster performance, don't install dev dependencies.
export COMPOSER_NO_DEV=1

#== Remove root-owned files.
echo
echo Remove root-owned files.
time sudo rm -rf lost+found

#== Composer install.
if [ ! -f composer.json ]; then
  echo
  echo 'Generate composer.json.'
  time source .devpanel/composer_setup.sh

  echo
  echo 'Apply patch for issue #3497485.'
  time patch -Np1 -r /dev/null < patches/drupal/drupal_cms/373.patch || :
fi
echo
time composer -n update --no-dev --no-progress

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

#== Generate hash salt.
if [ ! -f .devpanel/salt.txt ]; then
  echo
  echo 'Generate hash salt.'
  time openssl rand -hex 32 > .devpanel/salt.txt
fi

#== Pre-install starter recipe.
if [ -z "$(mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p$DB_PASSWORD $DB_NAME -e 'show tables')" ]; then
  echo
  echo 'Install Drupal base system.'
  while [ -z "$(drush sget recipe_installer_kit.profile_modules_installed 2> /dev/null)" ]; do
    time .devpanel/install > /dev/null
  done
  drush sdel recipe_installer_kit.profile_modules_installed

  echo
  echo 'Apply required recipes.'
  RECIPES=$(drush sget --format=yaml recipe_installer_kit.required_recipes | grep '^  - .\+/.\+' | cut -f 4 -d ' ')
  RECIPES_PATH=$(drush --include=.devpanel/drush crp)
  RECIPES_APPLIED=''
  for RECIPE in $RECIPES; do
    RECIPE_PATH=$RECIPES_PATH/${RECIPE##*/}
    if [ -d $RECIPE_PATH ]; then
      until time drush --include=.devpanel/drush -q recipe $RECIPE_PATH; do
        time drush cr
      done

      if [ -n "$RECIPES_APPLIED" ]; then
        RECIPES_APPLIED="$RECIPES_APPLIED,\"$RECIPE\""
      else
        RECIPES_APPLIED="\"$RECIPE\""
      fi
    fi
  done
  drush sdel recipe_installer_kit.required_recipes
  # Early versions of Drupal CMS subscribe to the recipe applied event. For
  # later versions we have to set the applied recipes manually.
  if [ -z "$(drush sget installer.applied_recipes 2> /dev/null)" ]; then
    drush sset --input-format=yaml installer.applied_recipes "[$RECIPES_APPLIED]"
  fi

  echo
  echo 'Tell Automatic Updates about patches.'
  time drush -n cset --input-format=yaml package_manager.settings additional_known_files_in_project_root '["patches.json", "patches.lock.json"]'

  echo
  time drush -n pmu drupal_cms_installer

  echo
  time drush cr
else
  drush -n updb
fi

INIT_DURATION=$SECONDS
INIT_HOURS=$(($INIT_DURATION / 3600))
INIT_MINUTES=$(($INIT_DURATION % 3600 / 60))
INIT_SECONDS=$(($INIT_DURATION % 60))
printf "\nTotal elapsed time: %d:%02d:%02d\n" $INIT_HOURS $INIT_MINUTES $INIT_SECONDS
