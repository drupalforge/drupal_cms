<?php

/**
 * @file
 * Exports SFDUG content from the CURRENT site into CSVs for the 2026 rebuild.
 *
 * Read-only. Run against sfdug_df_simplified, never the new site.
 *
 * The historical CSVs in sfdug_migrate/migrations are NOT a valid source: they
 * predate apply_meetup_dates.php, backfill_session_dates.php,
 * rematch_session_videos.php and import_2026_content.php. The database is the
 * only place those corrections exist.
 *
 * ddev drush php:script export_from_old_site --script-path=/var/www/html/scripts
 */

$dir = '/var/www/html/logs/export';
if (!is_dir($dir)) {
  mkdir($dir, 0777, TRUE);
}

$storage = \Drupal::entityTypeManager()->getStorage('node');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

/** Reads a field's first value, or '' when empty/absent. */
$val = function ($entity, string $field, string $prop = 'value') {
  if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
    return '';
  }
  return (string) ($entity->get($field)->first()->get($prop)->getValue() ?? '');
};

$rows = [];

// ---------------------------------------------------------------- videos ----
$fh = fopen("$dir/videos.csv", 'w');
fputcsv($fh, ['media_id', 'name', 'url', 'created']);
$count = 0;
foreach ($media_storage->loadByProperties(['bundle' => 'remote_video']) as $media) {
  fputcsv($fh, [
    $media->id(),
    $media->label(),
    $val($media, 'field_media_oembed_video'),
    $media->getCreatedTime(),
  ]);
  $count++;
}
fclose($fh);
$rows['videos.csv'] = $count;

// ---------------------------------------------------------------- people ----
$fh = fopen("$dir/people.csv", 'w');
fputcsv($fh, [
  'nid', 'title', 'status', 'created',
  'bio', 'bio_format', 'organization',
  'linkedin', 'drupal_org_profile', 'website',
  'photo_uri', 'photo_name', 'photo_alt',
]);
$count = 0;
foreach ($storage->loadByProperties(['type' => 'person']) as $node) {
  $photo_uri = $photo_name = $photo_alt = '';
  if ($node->hasField('field_photo') && ($media = $node->get('field_photo')->entity)) {
    $photo_name = $media->label();
    if (!$media->get('field_media_image')->isEmpty()) {
      $item = $media->get('field_media_image')->first();
      $photo_alt = (string) $item->get('alt')->getValue();
      if ($file = $item->get('entity')->getValue()) {
        $photo_uri = $file->getFileUri();
      }
    }
  }
  fputcsv($fh, [
    $node->id(),
    $node->label(),
    (int) $node->isPublished(),
    $node->getCreatedTime(),
    $val($node, 'field_bio'),
    $val($node, 'field_bio', 'format'),
    $val($node, 'field_organization'),
    $val($node, 'field_linkedin', 'uri'),
    $val($node, 'field_drupal_org_profile', 'uri'),
    $val($node, 'field_website', 'uri'),
    $photo_uri,
    $photo_name,
    $photo_alt,
  ]);
  $count++;
}
fclose($fh);
$rows['people.csv'] = $count;

// -------------------------------------------------------------- meetings ----
$fh = fopen("$dir/meetings.csv", 'w');
fputcsv($fh, [
  'nid', 'title', 'status', 'created', 'changed',
  'description', 'description_format', 'short_description',
  'date_value', 'date_end_value', 'date_duration', 'date_rrule',
  'video_media_id', 'speaker_nids',
  'rsvp_uri', 'rsvp_title', 'accent_color',
  'social_card_uri', 'event_mode', 'location',
]);
$count = 0;
$undated = 0;
foreach ($storage->loadByProperties(['type' => 'session']) as $node) {
  // Old venue taxonomy -> explicit attendance mode + plain text location.
  $mode = 'online';
  $location = '';
  if ($node->hasField('field_venue') && ($term = $node->get('field_venue')->entity)) {
    $name = $term->label();
    $is_online = (bool) preg_match('/online|zoom|virtual|remote/i', $name);
    $mode = $is_online ? 'online' : 'in_person';
    $location = $is_online ? '' : $name;
  }

  $speakers = [];
  if ($node->hasField('field_person_speakers')) {
    foreach ($node->get('field_person_speakers')->referencedEntities() as $person) {
      $speakers[] = $person->id();
    }
  }

  $video_id = '';
  if ($node->hasField('field_session_video') && ($media = $node->get('field_session_video')->entity)) {
    $video_id = $media->id();
  }

  $card_uri = '';
  if ($node->hasField('field_social_media_card') && ($file = $node->get('field_social_media_card')->entity)) {
    $card_uri = $file->getFileUri();
  }

  $date_value = $val($node, 'field_session_date');
  if ($date_value === '') {
    $undated++;
  }

  fputcsv($fh, [
    $node->id(),
    $node->label(),
    (int) $node->isPublished(),
    $node->getCreatedTime(),
    $node->getChangedTime(),
    $val($node, 'field_description'),
    $val($node, 'field_description', 'format'),
    $val($node, 'field_short_description'),
    $date_value,
    $val($node, 'field_session_date', 'end_value'),
    $val($node, 'field_session_date', 'duration'),
    $val($node, 'field_session_date', 'rrule'),
    $video_id,
    implode('|', $speakers),
    $val($node, 'field_rsvp_url', 'uri'),
    $val($node, 'field_rsvp_url', 'title'),
    $val($node, 'field_accent_color'),
    $card_uri,
    $mode,
    $location,
  ]);
  $count++;
}
fclose($fh);
$rows['meetings.csv'] = $count;

// ----------------------------------------------------------------- pages ----
$alias_repo = \Drupal::service('path_alias.repository');
$fh = fopen("$dir/pages.csv", 'w');
fputcsv($fh, ['nid', 'title', 'status', 'created', 'body', 'body_format', 'alias']);
$count = 0;
foreach ($storage->loadByProperties(['type' => 'page']) as $node) {
  $alias = $alias_repo->lookupBySystemPath('/node/' . $node->id(), 'en');
  fputcsv($fh, [
    $node->id(),
    $node->label(),
    (int) $node->isPublished(),
    $node->getCreatedTime(),
    $val($node, 'body'),
    $val($node, 'body', 'format'),
    $alias ? $alias['alias'] : '',
  ]);
  $count++;
}
fclose($fh);
$rows['pages.csv'] = $count;

echo "exported to {$dir}\n";
foreach ($rows as $file => $n) {
  printf("  %-14s %3d rows\n", $file, $n);
}
printf("  (%d meetings have no date)\n", $undated);
