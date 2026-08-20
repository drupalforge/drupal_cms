<?php

/**
 * @file
 * Proposes correct session ↔ recording pairings.
 *
 * The Pantheon migration attached videos to sessions almost at random: only 4
 * of the 25 pairings are right, so cards on the homepage show one talk's title
 * over another talk's promo art.
 *
 * Media names are descriptive ("SFDUG - Nov. 10 2022 - An Update on WCAG 3.0"),
 * so they can be matched back to session titles on wording. This scores every
 * media/session pair and reports the best fit for each recording.
 *
 * Writes nothing unless --write is passed.
 *
 * Run:
 *   ddev drush php:script rematch_session_videos \
 *     --script-path=/var/www/html/web/modules/custom/sfdug_migrate/scripts
 */

$write = in_array('--write', $_SERVER['argv'] ?? [], TRUE);

require_once __DIR__ . '/_keywords.inc';

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

$session_ids = $node_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'session')
  ->sort('nid')
  ->execute();
$sessions = $node_storage->loadMultiple($session_ids);

$media_ids = $media_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('bundle', 'remote_video')
  ->sort('mid')
  ->execute();
$videos = $media_storage->loadMultiple($media_ids);

// Which session currently points at which media.
$current = [];
foreach ($sessions as $node) {
  if (!$node->get('field_session_video')->isEmpty()) {
    $current[(int) $node->get('field_session_video')->target_id] = $node;
  }
}

$session_keywords = [];
$session_dates = [];
foreach ($sessions as $nid => $node) {
  $session_keywords[$nid] = sfdug_keywords($node->label());
  $session_dates[$nid] = sfdug_date_hints($node->label());
}

$rows = [['mid', 'video_name', 'current_nid', 'current_title', 'proposed_nid', 'proposed_title', 'score', 'verdict']];
$counts = ['already correct' => 0, 'remap' => 0, 'attach' => 0, 'no confident match' => 0];
$assignments = [];

foreach ($videos as $mid => $media) {
  $keywords = sfdug_keywords($media->label());
  $media_date = sfdug_date_hints($media->label());

  $scores = [];
  foreach ($session_keywords as $nid => $words) {
    $score = sfdug_score($keywords, $words);
    if ($score > 0) {
      $scores[$nid] = $score + sfdug_date_adjustment($media_date, $session_dates[$nid]);
    }
  }
  arsort($scores);
  $best_nid = $scores ? array_key_first($scores) : NULL;
  $best_score = $best_nid ? $scores[$best_nid] : 0.0;

  $current_node = $current[$mid] ?? NULL;
  $current_nid = $current_node ? (int) $current_node->id() : NULL;

  // Never break a pairing that already scores as well as the alternative.
  if ($current_nid !== NULL && isset($scores[$current_nid]) && $scores[$current_nid] >= $best_score - 0.15) {
    $best_nid = $current_nid;
    $best_score = $scores[$current_nid];
  }

  if ($best_score < 0.6 || !$best_nid) {
    $verdict = 'no confident match';
    $best_nid = NULL;
  }
  elseif ($current_nid === $best_nid) {
    $verdict = 'already correct';
  }
  elseif ($current_nid === NULL) {
    $verdict = 'attach';
  }
  else {
    $verdict = 'remap';
  }

  $counts[$verdict]++;
  // Collect every confident pairing, including the ones already correct: the
  // write step clears all pairings first, so anything omitted here would be
  // detached rather than left alone.
  if ($best_nid) {
    $assignments[$best_nid][] = $mid;
  }

  $rows[] = [
    $mid,
    $media->label(),
    $current_nid ?? '',
    $current_node ? $current_node->label() : '',
    $best_nid ?? '',
    $best_nid ? $sessions[$best_nid]->label() : '',
    number_format($best_score, 2),
    $verdict,
  ];
}

$log_dir = DRUPAL_ROOT . '/../logs';
if (!is_dir($log_dir)) {
  mkdir($log_dir, 0775, TRUE);
}
$path = "$log_dir/session-video-rematch.csv";
$handle = fopen($path, 'w');
foreach ($rows as $row) {
  fputcsv($handle, $row, ',', '"', '\\');
}
fclose($handle);

foreach ($counts as $verdict => $n) {
  printf("%-20s %d\n", $verdict, $n);
}

if ($write) {
  // Clear every current pairing first, so a video that moves does not stay
  // attached in two places.
  foreach ($sessions as $node) {
    if (!$node->get('field_session_video')->isEmpty()) {
      $node->set('field_session_video', NULL);
      $node->setSyncing(TRUE);
      $node->save();
    }
  }
  $applied = 0;
  foreach ($assignments as $nid => $mids) {
    // One video per session; if two claim the same session, take the first.
    $sessions[$nid]->set('field_session_video', ['target_id' => reset($mids)]);
    $sessions[$nid]->setSyncing(TRUE);
    $sessions[$nid]->save();
    $applied++;
  }
  echo "\nApplied $applied pairings.\n";
}
else {
  echo "\nDry run. Report: logs/session-video-rematch.csv\n";
}
