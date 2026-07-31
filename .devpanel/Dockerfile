ENV COMPOSER_HOME="${HOME}/.config/composer"

# Allow callers to bake the site template name into the image so init scripts
# can identify which template was installed without needing DP_APP_ID at runtime.
ARG DRUPAL_CMS_SITE_TEMPLATE
ENV DRUPAL_CMS_SITE_TEMPLATE=${DRUPAL_CMS_SITE_TEMPLATE}

USER root

# Install the PHP PostgreSQL extension.
RUN docker-php-ext-install pgsql

USER ${USER}
