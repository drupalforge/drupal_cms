<?php

/**
 * @file
 * Builds the SFDUG content model: 3 content types, 18 fields, 3 view modes.
 *
 * Idempotent -- re-running converges rather than duplicating.
 *
 * ddev drush php:script build_content_model \
 *   --script-path=/var/www/html/scripts
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Entity\Entity\EntityViewMode;

/** Content types. `page` ships with Drupal CMS and is left alone. */
$types = [
  'session' => [
    'name' => 'Meeting',
    'description' => 'A monthly SFDUG meeting: the talk, its speakers and the recording. Machine name stays `session` so the 2026 theme templates keep working.',
  ],
  'person' => [
    'name' => 'Person',
    'description' => 'Speakers, organizers and contributors. No Drupal user account required.',
  ],
];

/** View modes the sfdug theme templates require. `card`, `full` and `teaser` already exist. */
$view_modes = [
  'card_archive' => 'Card (archive)',
  'featured' => 'Featured',
  'infobar' => 'Info bar',
];

/**
 * The 18 fields. Storage is keyed by field name; instances list their bundles.
 */
$fields = [
  // ---- Meeting -------------------------------------------------------------
  // NOT field_description: Drupal CMS already defines that as a string_long
  // SEO summary on `page`, and field storage is shared across bundles. Reusing
  // it would store 75 HTML descriptions as plain text with no format.
  'body' => [
    'type' => 'text_with_summary',
    'bundles' => ['session' => 'Description'],
  ],
  'field_short_description' => [
    'type' => 'string_long',
    'bundles' => ['session' => 'Short description'],
    'description' => 'The one-line hook shown on cards and in social previews.',
  ],
  'field_session_date' => [
    'type' => 'smartdate',
    'bundles' => ['session' => 'Date'],
  ],
  'field_session_video' => [
    'type' => 'entity_reference',
    'storage_settings' => ['target_type' => 'media'],
    'bundles' => ['session' => 'Recording'],
    'instance_settings' => [
      'handler' => 'default:media',
      'handler_settings' => ['target_bundles' => ['remote_video' => 'remote_video']],
    ],
  ],
  'field_person_speakers' => [
    'type' => 'entity_reference',
    'cardinality' => -1,
    'storage_settings' => ['target_type' => 'node'],
    'bundles' => ['session' => 'Speakers'],
    'instance_settings' => [
      'handler' => 'default:node',
      'handler_settings' => ['target_bundles' => ['person' => 'person']],
    ],
  ],
  'field_rsvp_url' => [
    'type' => 'link',
    'bundles' => ['session' => 'RSVP link'],
    'instance_settings' => ['title' => 1, 'link_type' => 17],
  ],
  'field_social_media_card' => [
    'type' => 'image',
    'bundles' => ['session' => 'Social media card'],
    'instance_settings' => [
      'file_directory' => '[date:custom:Y]-[date:custom:m]',
      'file_extensions' => 'png jpg jpeg webp',
      'alt_field' => FALSE,
      'alt_field_required' => FALSE,
    ],
  ],
  'field_accent_color' => [
    'type' => 'string',
    'storage_settings' => ['max_length' => 9],
    'bundles' => ['session' => 'Accent colour'],
    'description' => 'Hex value, e.g. #6c5cc4. Tints the meeting card and hero.',
  ],
  'field_slides' => [
    'type' => 'file',
    'cardinality' => -1,
    'bundles' => ['session' => 'Slides'],
    'instance_settings' => [
      'file_directory' => '[date:custom:Y]-[date:custom:m]',
      // The old site had `txt` here, an unchanged default on an empty field.
      'file_extensions' => 'pdf ppt pptx odp key',
      'description_field' => TRUE,
    ],
  ],
  'field_event_mode' => [
    'type' => 'list_string',
    'storage_settings' => [
      // This site rejects the indexed [{value,label}] shape with
      // "settings.allowed_values.0.label.0 doesn't exist"; the associative
      // form is what validates here.
      'allowed_values' => [
        'online' => 'Online',
        'in_person' => 'In person',
        'hybrid' => 'Hybrid',
      ],
    ],
    'bundles' => ['session' => 'Attendance mode'],
    'default_value' => [['value' => 'online']],
    'description' => 'Drives schema.org eventAttendanceMode. Replaces the old venue-name string match.',
  ],
  'field_location' => [
    'type' => 'string',
    'storage_settings' => ['max_length' => 255],
    'bundles' => ['session' => 'Location'],
    'description' => 'Where an in-person meeting happens. Leave empty for online meetings.',
  ],

  // ---- Person --------------------------------------------------------------
  'field_bio' => [
    'type' => 'text_long',
    'bundles' => ['person' => 'Bio'],
  ],
  'field_photo' => [
    'type' => 'entity_reference',
    'storage_settings' => ['target_type' => 'media'],
    'bundles' => ['person' => 'Photo'],
    'instance_settings' => [
      'handler' => 'default:media',
      'handler_settings' => ['target_bundles' => ['image' => 'image']],
    ],
  ],
  'field_organization' => [
    'type' => 'string',
    'storage_settings' => ['max_length' => 255],
    'bundles' => ['person' => 'Organization'],
  ],
  'field_linkedin' => [
    'type' => 'link',
    'bundles' => ['person' => 'LinkedIn'],
    'instance_settings' => ['title' => 1, 'link_type' => 17],
  ],
  'field_drupal_org_profile' => [
    'type' => 'link',
    'bundles' => ['person' => 'Drupal.org profile'],
    'instance_settings' => ['title' => 1, 'link_type' => 17],
  ],
  'field_website' => [
    'type' => 'link',
    'bundles' => ['person' => 'Website'],
    'instance_settings' => ['title' => 1, 'link_type' => 17],
  ],
];

// -----------------------------------------------------------------------------

$made = ['types' => 0, 'modes' => 0, 'storages' => 0, 'instances' => 0];

foreach ($types as $id => $info) {
  if (!NodeType::load($id)) {
    NodeType::create([
      'type' => $id,
      'name' => $info['name'],
      'description' => $info['description'],
      'new_revision' => TRUE,
      'display_submitted' => FALSE,
      'preview_mode' => 1,
    ])->save();
    $made['types']++;
    echo "content type: {$id} ({$info['name']})\n";
  }
}

// Drupal CMS 2's `page` ships with field_content (Canvas) but no body. The
// Home node carries the front page's About and Join sections as hand-authored
// HTML whose classes the sfdug stylesheet targets, so body has to exist.
// Drupal CMS 2 ships no body storage at all, so node_add_body_field() has
// nothing to attach to. Create both halves.
if (NodeType::load('page') && !FieldConfig::loadByName('node', 'page', 'body')) {
  $body_storage = FieldStorageConfig::loadByName('node', 'body');
  if (!$body_storage) {
    $body_storage = FieldStorageConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => 'text_with_summary',
      'cardinality' => 1,
    ]);
    $body_storage->save();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    echo "storage: body (text_with_summary)\n";
    $made['storages']++;
  }
  FieldConfig::create([
    'field_storage' => $body_storage,
    'bundle' => 'page',
    'label' => 'Body',
    'required' => FALSE,
  ])->save();
  echo "  field: page.body\n";
  $made['instances']++;
}

foreach ($view_modes as $id => $label) {
  if (!EntityViewMode::load("node.{$id}")) {
    EntityViewMode::create([
      'id' => "node.{$id}",
      'targetEntityType' => 'node',
      'label' => $label,
    ])->save();
    $made['modes']++;
    echo "view mode: node.{$id}\n";
  }
}

foreach ($fields as $name => $spec) {
  $storage = FieldStorageConfig::loadByName('node', $name);
  if ($storage && $storage->getType() !== $spec['type']) {
    throw new \RuntimeException(sprintf(
      'Field storage node.%s already exists as %s, but this script wants %s. '
      . 'Reusing it would silently store the wrong shape -- pick another name.',
      $name, $storage->getType(), $spec['type']
    ));
  }
  if (!$storage) {
    $storage = FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $spec['type'],
      'cardinality' => $spec['cardinality'] ?? 1,
      'settings' => $spec['storage_settings'] ?? [],
    ]);
    $storage->save();
    // The new storage is not yet visible to FieldConfig's validation.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    $made['storages']++;
    echo "storage: {$name} ({$spec['type']})\n";
  }
  foreach ($spec['bundles'] as $bundle => $label) {
    if (!FieldConfig::loadByName('node', $bundle, $name)) {
      $values = [
        'field_storage' => $storage,
        'bundle' => $bundle,
        'label' => $label,
        'required' => FALSE,
        'description' => $spec['description'] ?? '',
        'settings' => $spec['instance_settings'] ?? [],
      ];
      if (isset($spec['default_value'])) {
        $values['default_value'] = $spec['default_value'];
      }
      FieldConfig::create($values)->save();
      $made['instances']++;
      echo "  field: {$bundle}.{$name}\n";
    }
  }
}

echo "\ncreated: {$made['types']} types, {$made['modes']} view modes, "
  . "{$made['storages']} storages, {$made['instances']} field instances\n";
