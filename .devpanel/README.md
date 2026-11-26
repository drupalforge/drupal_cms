Files in the `.devpanel` directory control DevPanel deployment for this app.


## Startup scripts

- [`custom_package_installer.sh`](custom_package_installer.sh): Installs
  extra system software. Runs as root. This is called by
  /scripts/apache-start.sh before Apache starts.
- [`init-container.sh`](init-container.sh): Startup for registry mode (a
  DevPanel template setting). Imports a database dump and copies files from the
  image’s app directory to an external volume.
- [`init.sh`](init.sh): Startup for non-registry mode and image build.
  Supporting files:
  - [`composer_setup.sh`](composer_setup.sh): Generates composer.json and
    composer.lock files. Not needed if you supply these files yourself.
  - [`settings.devpanel.php`](settings.devpanel.php): Settings for running
    Drupal as a DevPanel app.
  - [`drupal-settings.patch`](drupal-settings.patch): Patch for settings.php
    to include settings.devpanel.php. Installed by the post-drupal-scaffold-cmd
    script. Make sure this works with your Composer project.
  - [`install`](install): Runs interactive installer.
- [`Dockerfile`](Dockerfile): Provides the `COMPOSER_HOME` variable required by
  the Drupal Automatic Updates web UI.


## Git integration

- [`config.yml`](config.yml): Defines tasks to run when Git is configured to
  update the app automatically.


## Deployment

- [`re-config.sh`](re-config.sh): Runs when container configuration is
  changed in DevPanel or the app is deployed to a hosting provider.


## Creating a Docker image

- [`create_quickstart.sh`](create_quickstart.sh): Archives the database and
  files for the _Drupal Forge Docker Publish Workflow_ which can be added in
  [GitHub Actions](../../actions).


## Centralized Docker Hub image publishing

The GitHub Actions workflow
[`docker-publish-template`](../.github/workflows/docker-publish-template.yml)
in **this repository** (`drupalforge/drupal_cms`) is the **single source of
truth** for Docker Hub image generation across all Drupal CMS site templates.

### How it works

1. A `get-templates` job fetches the canonical template list from the
   Drupal CMS project:

   ```
   https://git.drupalcode.org/api/v4/projects/204857/repository/files/site-templates.yml/raw?ref=HEAD
   ```

   The job fails if the remote file is unreachable. Templates with a set
   `purchase` value in their metadata are excluded from the free-image build.

2. The `build-free-template-images` matrix job builds **one Docker image per
   free template**, publishing to Docker Hub as
   `drupalforge/<template-name>:main`.

   It uses
   [`drupalforge/docker_publish_action`](https://github.com/drupalforge/docker_publish_action).

3. Each image is published with the tag pattern:

   ```
   drupalforge/<template-name>:main
   ```

   where `<template-name>` exactly matches the name in `site-templates.yml`
   (e.g. `drupalforge/haven:main`, `drupalforge/convene:main`).

### Selecting the site template

During image builds, the publish action sets `DP_APP_ID` to the image
repository. For `drupalforge/<template-name>` (or
`ghcr.io/drupalforge/<template-name>`), `init.sh` uses the final path segment as
the template name. The generic `drupal_cms` image continues to use the
recipe-based base installation.

An explicit `DRUPAL_CMS_SITE_TEMPLATE` supplied through the action's generic
`init_env` configuration takes precedence.

Paid templates are not published by this workflow. The publish action makes
GHCR packages public for this public source repository, so paid images must
remain disabled until the action can reliably keep those packages private.

### Deprecating per-template repository workflows

Image generation in separate site-template repositories
(e.g. `drupalforge/haven`, `drupalforge/convene`) should be **disabled** so
that `drupalforge/drupal_cms` remains the single publishing source. Set the
`docker-publish-template.yml` workflow in each template repository to disabled
or remove it once the centralized workflow is validated.
