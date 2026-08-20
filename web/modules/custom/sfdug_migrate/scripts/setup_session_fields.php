<?php

/**
 * @file
 * Adds the fields and view modes the 2026 SFDUG design needs to `session`.
 *
 * Idempotent: existing fields and view modes are left alone.
 *
 * Run: ddev drush php:script setup_session_fields --script-path=web/modules/custom/sfdug_migrate/scripts
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Field definitions keyed by machine name.
 *
 * Everything here is single-value; the design has one slot for each.
 */
$fields = [
  'field_session_date' => [
    'type' => 'smartdate',
    'label' => 'Session date & time',
    'description' => 'When the meetup happens. Drives upcoming vs. past everywhere on the site.',
    'required' => FALSE,
    'settings' => [],
    'storage_settings' => [],
  ],
  'field_rsvp_url' => [
    'type' => 'link',
    'label' => 'RSVP link',
    'description' => 'Meetup.com (or other) registration URL. The link title becomes the button label.',
    'required' => FALSE,
    'settings' => [
      'title' => DRUPAL_OPTIONAL,
      'link_type' => 17,
    ],
    'storage_settings' => [],
  ],
  'field_accent_color' => [
    'type' => 'string',
    'label' => 'Accent colour',
    'description' => 'Hex colour for this session’s accent, e.g. #F26522. Leave empty to use the site default.',
    'required' => FALSE,
    'settings' => [],
    'storage_settings' => ['max_length' => 9],
  ],
  'field_badge' => [
    'type' => 'string',
    'label' => 'Badge',
    'description' => 'Short highlight pill shown in the info bar, e.g. “New afternoon meeting!”.',
    'required' => FALSE,
    'settings' => [],
    'storage_settings' => ['max_length' => 80],
  ],
  'field_venue' => [
    'type' => 'entity_reference',
    'label' => 'Venue',
    'description' => 'Where the meetup takes place.',
    'required' => FALSE,
    'settings' => [
      'handler' => 'default:taxonomy_term',
      'handler_settings' => [
        'target_bundles' => ['venue' => 'venue'],
        'auto_create' => TRUE,
      ],
    ],
    'storage_settings' => ['target_type' => 'taxonomy_term'],
  ],
  'field_venue_note' => [
    'type' => 'string',
    'label' => 'Venue note',
    'description' => 'Small print under the venue, e.g. “Link emailed after you RSVP”.',
    'required' => FALSE,
    'settings' => [],
    'storage_settings' => ['max_length' => 255],
  ],
  'field_time_note' => [
    'type' => 'string',
    'label' => 'Time note',
    'description' => 'Small print under the time, e.g. “New afternoon meeting time”.',
    'required' => FALSE,
    'settings' => [],
    'storage_settings' => ['max_length' => 255],
  ],
];

foreach ($fields as $name => $spec) {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $spec['type'],
      'cardinality' => 1,
      'settings' => $spec['storage_settings'],
    ])->save();
    echo "storage created: $name\n";
  }
  else {
    echo "storage exists:  $name\n";
  }

  if (!FieldConfig::loadByName('node', 'session', $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => 'session',
      'label' => $spec['label'],
      'description' => $spec['description'],
      'required' => $spec['required'],
      'settings' => $spec['settings'],
    ])->save();
    echo "field created:   session.$name\n";
  }
  else {
    echo "field exists:    session.$name\n";
  }
}

/**
 * View modes the homepage and listings render sessions through.
 */
$view_modes = [
  'infobar' => 'Info bar',
  'featured' => 'Featured (homepage hero)',
  'card' => 'Card (video grid)',
  'card_archive' => 'Card — archive',
];

$storage = \Drupal::entityTypeManager()->getStorage('entity_view_mode');
foreach ($view_modes as $id => $label) {
  if (!$storage->load("node.$id")) {
    $storage->create([
      'id' => "node.$id",
      'label' => $label,
      'targetEntityType' => 'node',
    ])->save();
    echo "view mode created: node.$id\n";
  }
  else {
    echo "view mode exists:  node.$id\n";
  }
}

echo "\nDone.\n";
