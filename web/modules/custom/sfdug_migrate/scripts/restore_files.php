<?php

/**
 * @file
 * Restores the managed image files the site's content references.
 *
 * A database transfer — backup_migrate, a SQL dump, whatever — carries the
 * file_managed rows but not the files themselves, so the promo poster and the
 * speaker avatar 404 until the bytes are put back. This copies them out of the
 * module's assets directory into public:// wherever they are missing.
 *
 * Safe to run any time: existing files are left alone.
 *
 * Run:
 *   ddev drush php:script restore_files \
 *     --script-path=/var/www/html/web/modules/custom/sfdug_migrate/scripts
 */

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;

$file_system = \Drupal::service('file_system');
$source_dir = __DIR__ . '/../assets';

$restored = $present = $missing = 0;

foreach (\Drupal::entityTypeManager()->getStorage('file')->loadMultiple() as $file) {
  $uri = $file->getFileUri();

  // Only the files this module put there; oEmbed thumbnails regenerate on
  // their own and image-style derivatives are rebuilt on request.
  if (!str_starts_with($uri, 'public://sfdug/')) {
    continue;
  }

  if (is_readable($uri)) {
    $present++;
    continue;
  }

  $source = $source_dir . '/' . basename($uri);
  if (!is_readable($source)) {
    echo "  !! no source for $uri\n";
    $missing++;
    continue;
  }

  $directory = dirname($uri);
  $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
  $file_system->copy($source, $uri, FileExists::Replace);
  echo "  restored: $uri\n";
  $restored++;
}

printf("\nrestored %d, already present %d, missing source %d\n", $restored, $present, $missing);

if ($restored) {
  echo "Run `drush cr` so image styles pick them up.\n";
}
