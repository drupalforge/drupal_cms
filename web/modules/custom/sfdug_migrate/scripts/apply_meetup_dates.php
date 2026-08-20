<?php

/**
 * @file
 * Sets session dates from the group's own Meetup.com event listings.
 *
 * migrations/sfdug_meetup_events.csv holds the name, start time and URL of 66
 * SFDUG events, scraped from the JSON-LD on each Meetup event page. That is the
 * authoritative record: it carries the real time of day, not just a date, and
 * it covers sessions whose titles never mentioned a date at all.
 *
 * Sessions are matched to events on title wording, the same way recordings were
 * matched to sessions. The `meetup_urls` column in the original migration CSV
 * is NOT used: it is offset by one row, so each node carries the previous
 * meetup's URL (verified — 40 mismatches against 1 agreement).
 *
 * Writes nothing unless --write is passed.
 *
 * Run:
 *   ddev drush php:script apply_meetup_dates \
 *     --script-path=/var/www/html/web/modules/custom/sfdug_migrate/scripts
 */

$write = in_array('--write', $_SERVER['argv'] ?? [], TRUE);

require_once __DIR__ . '/_keywords.inc';

$csv = __DIR__ . '/../migrations/sfdug_meetup_events.csv';
if (!is_readable($csv)) {
  throw new RuntimeException("Missing $csv");
}

$events = [];
$handle = fopen($csv, 'r');
fgetcsv($handle, 0, ',', '"', '\\');
while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
  [$url, $start, $name] = array_pad($row, 3, '');
  if ($start && $name && !str_starts_with($name, 'ERROR')) {
    // Score against the event's real start date, not any date written into its
    // name: the name is often undated, and the timestamp is exact.
    $when = new DateTime($start);
    $events[] = [
      'url' => $url,
      'start' => $start,
      'name' => $name,
      'keywords' => sfdug_keywords($name),
      'date' => [
        'month' => (int) $when->format('n'),
        'day' => (int) $when->format('j'),
        'year' => (int) $when->format('Y'),
      ],
    ];
  }
}
fclose($handle);
echo 'Meetup events loaded: ' . count($events) . "\n";

$storage = \Drupal::entityTypeManager()->getStorage('node');
$ids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'session')
  // The 2026 season was entered by hand from the promo art; leave it alone.
  ->condition('nid', 90, '<')
  ->sort('nid')
  ->execute();

$rows = [['nid', 'title', 'had_date', 'matched_event', 'score', 'new_date', 'verdict']];
$tally = ['set' => 0, 'changed' => 0, 'unmatched' => 0];

$nodes = $storage->loadMultiple($ids);

/**
 * Score every session against every event, then assign strongest pair first.
 *
 * Assigning in node order lets a mediocre pairing claim an event that a later
 * session matches far better — which is how "Speaker Workshop" and "Speaker
 * Diversity Workshop" ended up swapped.
 */
$pairs = [];
foreach ($nodes as $nid => $node) {
  $keywords = sfdug_keywords($node->label());
  $hints = sfdug_date_hints($node->label());
  foreach ($events as $index => $event) {
    $score = sfdug_score($keywords, $event['keywords']);
    if ($score <= 0) {
      continue;
    }
    $score += sfdug_date_adjustment($hints, $event['date']);
    if ($score >= 0.6) {
      $pairs[] = ['nid' => $nid, 'event' => $index, 'score' => $score];
    }
  }
}
usort($pairs, fn($a, $b) => $b['score'] <=> $a['score']);

$chosen = [];
$claimed = [];
foreach ($pairs as $pair) {
  if (isset($chosen[$pair['nid']]) || isset($claimed[$pair['event']])) {
    continue;
  }
  $chosen[$pair['nid']] = $pair;
  $claimed[$pair['event']] = TRUE;
}

foreach ($nodes as $nid => $node) {
  $best = $chosen[$nid]['event'] ?? NULL;
  $best_score = $chosen[$nid]['score'] ?? 0.0;

  $had = !$node->get('field_session_date')->isEmpty()
    ? \Drupal::service('date.formatter')->format((int) $node->get('field_session_date')->value, 'custom', 'Y-m-d')
    : '';

  if ($best === NULL || $best_score < 0.6) {
    $tally['unmatched']++;
    $rows[] = [$node->id(), $node->label(), $had, '', '', '', 'no confident match'];
    continue;
  }

  $claimed[$best] = TRUE;
  $event = $events[$best];
  $start = new DateTime($event['start']);
  $new = $start->format('Y-m-d H:i');

  $verdict = $had === '' ? 'set (was empty)' : ($had === $start->format('Y-m-d') ? 'confirmed' : 'corrected');
  $tally[$had === '' ? 'set' : 'changed']++;

  $rows[] = [
    $node->id(),
    $node->label(),
    $had,
    $event['name'],
    number_format($best_score, 2),
    $new,
    $verdict,
  ];

  if ($write) {
    $ts = $start->getTimestamp();
    $node->set('field_session_date', [
      'value' => $ts,
      'end_value' => $ts + 5400,
      'duration' => 90,
      'rrule' => NULL,
      'timezone' => 'America/Los_Angeles',
    ]);
    $node->setSyncing(TRUE);
    $node->save();
  }
}

$log = DRUPAL_ROOT . '/../logs/session-meetup-dates.csv';
$out = fopen($log, 'w');
foreach ($rows as $row) {
  fputcsv($out, $row, ',', '"', '\\');
}
fclose($out);

printf("newly dated: %d   updated: %d   unmatched: %d\n", $tally['set'], $tally['changed'], $tally['unmatched']);
echo $write ? "Written.\n" : "Dry run. Report: logs/session-meetup-dates.csv\n";
