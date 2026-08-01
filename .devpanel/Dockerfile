ENV COMPOSER_HOME="${HOME}/.config/composer"

# Bakes the site template name into the image as an ENV variable so init scripts
# know which template to install. Populated at build time via the
# `drupal_cms_site_template` build-arg input of drupalforge/docker_publish_action.
ARG DRUPAL_CMS_SITE_TEMPLATE
ENV DRUPAL_CMS_SITE_TEMPLATE=${DRUPAL_CMS_SITE_TEMPLATE}

USER root

# Install the PHP PostgreSQL extension.
RUN docker-php-ext-install pgsql

USER ${USER}
