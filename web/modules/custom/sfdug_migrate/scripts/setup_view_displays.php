<?php

/**
 * @file
 * Configures how sessions render in each view mode.
 *
 * The card, archive-card and info-bar templates build their markup entirely
 * from preprocessed values, so those displays hide every field. Only the
 * featured and full displays render anything through `content`.
 */

use Drupal\Core\Entity\Entity\EntityViewDisplay;

/**
 * Fields each view mode renders. Everything not listed is hidden.
 */
$displays = [
  'infobar' => [],
  'card' => [],
  'card_archive' => [],
  'teaser' => [],
  'featured' => [
    'field_description' => [
      'type' => 'text_default',
      'label' => 'hidden',
      'weight' => 0,
    ],
  ],
  'full' => [
    'field_description' => [
      'type' => 'text_default',
      'label' => 'hidden',
      'weight' => 0,
    ],
    'field_session_video' => [
      'type' => 'entity_reference_entity_view',
      'label' => 'hidden',
      'weight' => 1,
      'settings' => ['view_mode' => 'default'],
    ],
    'field_slides' => [
      'type' => 'file_default',
      'label' => 'hidden',
      'weight' => 2,
    ],
  ],
];

$all_fields = array_keys(
  \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'session')
);

foreach ($displays as $mode => $components) {
  $display = EntityViewDisplay::load("node.session.$mode");
  if (!$display) {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'session',
      'mode' => $mode,
      'status' => TRUE,
    ]);
  }
  $display->setStatus(TRUE);

  // Start from a clean slate so re-running converges on the same result.
  foreach ($all_fields as $field) {
    $display->removeComponent($field);
  }
  $display->removeComponent('links');

  foreach ($components as $field => $options) {
    $display->setComponent($field, $options);
  }

  $display->save();
  echo "display: node.session.$mode (" . (count($components) ?: 'no') . " visible fields)\n";
}

echo "\nDone.\n";
