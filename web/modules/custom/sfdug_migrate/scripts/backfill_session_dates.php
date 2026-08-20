<?php

/**
 * @file
 * Best-effort backfill of field_session_date on the legacy session archive.
 *
 * The 74 sessions imported from Pantheon carry no date at all. Dates are
 * recovered, in order of trustworthiness, from:
 *
 *   1. the node title, when it states an explicit year
 *      ("SFDUG November 10, 2022 - ...")                        -> high
 *   2. the node title's month and day plus the year from the node's created
 *      date ("SFDUG - Mar 10 - ...")                            -> medium
 *   3. a month and year with no day, resolved to the second Thursday, which
 *      is the pattern the dated titles follow                   -> medium
 *
 * The attached remote_video's name would be a better source — those names carry
 * full years — except that the Pantheon migration attached the wrong video to
 * most sessions (nid 1, "PHPStan", holds the D9 Localization recording, and
 * only 4 of the 25 pairings are correct). The media name is reported for
 * reference but is never used to derive a date.
 *
 * Anything else is left empty and listed in the report.
 *
 * Writes nothing unless --write is passed. Default is a dry run that only
 * produces logs/session-date-backfill.csv for review.
 *
 * Run:
 *   ddev drush php:script backfill_session_dates \
 *     --script-path=/var/www/html/web/modules/custom/sfdug_migrate/scripts
 *   ddev drush php:script backfill_session_dates \
 *     --script-path=/var/www/html/web/modules/custom/sfdug_migrate/scripts -- --write
 */

$write = in_array('--write', $extra ?? [], TRUE)
  || in_array('--write', $_SERVER['argv'] ?? [], TRUE);

$tz = new DateTimeZone('America/Los_Angeles');

/** Default start time for legacy meetups, which ran in the evening. */
const SFDUG_LEGACY_HOUR = '18:00';
const SFDUG_LEGACY_DURATION = 60;

/**
 * Maps a month word (any common spelling) to its number.
 */
function sfdug_month(string $word): ?int {
  $word = strtolower(rtrim(trim($word), '.'));
  static $map = [
    'jan' => 1, 'january' => 1,
    'feb' => 2, 'february' => 2,
    'mar' => 3, 'march' => 3,
    'apr' => 4, 'april' => 4,
    'may' => 5,
    'jun' => 6, 'june' => 6,
    'jul' => 7, 'july' => 7,
    'aug' => 8, 'august' => 8,
    'sep' => 9, 'sept' => 9, 'september' => 9,
    'oct' => 10, 'october' => 10,
    'nov' => 11, 'november' => 11,
    'dec' => 12, 'december' => 12,
  ];
  return $map[$word] ?? NULL;
}

/**
 * Pulls a month, an optional day and an optional year out of free text.
 *
 * Returns [month, day|null, year|null] or NULL when no month is present.
 */
function sfdug_parse_date(string $text): ?array {
  $months = 'jan|january|feb|february|mar|march|apr|april|may|jun|june|jul|july|aug|august|sep|sept|september|oct|october|nov|november|dec|december';

  // "November 10, 2022" / "Nov. 10 2022" / "Jan. 8th 2022" / "Mar 10"
  //
  // The (?!\d) guard stops the day from swallowing the first two digits of a
  // bare "June 2023", which would otherwise be read as June 20.
  if (preg_match(
    '/\b(' . $months . ')\.?\s+(\d{1,2})(?!\d)(?:st|nd|rd|th)?(?:\s*,)?(?:\s+(\d{4}))?/i',
    $text,
    $m
  )) {
    $month = sfdug_month($m[1]);
    if ($month) {
      return [$month, (int) $m[2], isset($m[3]) ? (int) $m[3] : NULL];
    }
  }

  // "July 2022" / "March 2023" — month and year, no day.
  if (preg_match('/\b(' . $months . ')\.?\s+(\d{4})/i', $text, $m)) {
    $month = sfdug_month($m[1]);
    if ($month) {
      return [$month, NULL, (int) $m[2]];
    }
  }

  return NULL;
}

/**
 * The second Thursday of a month — SFDUG's usual slot.
 */
function sfdug_second_thursday(int $year, int $month, DateTimeZone $tz): int {
  $d = new DateTime("$year-$month-01", $tz);
  $d->modify('first thursday of this month');
  $d->modify('+1 week');
  return (int) $d->format('j');
}

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$ids = $node_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'session')
  ->notExists('field_session_date')
  ->sort('nid')
  ->execute();

echo 'Sessions without a date: ' . count($ids) . "\n";
echo $write ? "MODE: writing\n\n" : "MODE: dry run (pass --write to save)\n\n";

$rows = [['nid', 'title', 'source', 'confidence', 'resolved_date', 'video_media_name']];
$tally = ['high' => 0, 'medium' => 0, 'none' => 0];

foreach ($node_storage->loadMultiple($ids) as $node) {
  $title = $node->label();
  $media_name = '';
  $parsed = NULL;
  $source = '';
  $confidence = 'none';

  // Recorded for the report only — see the file docblock on why the video's
  // name is not trusted as a date source.
  if ($node->hasField('field_session_video') && ($media = $node->get('field_session_video')->entity)) {
    $media_name = $media->label();
  }

  // 1. The node title, when it states a year.
  {
    $candidate = sfdug_parse_date($title);
    if ($candidate && $candidate[2]) {
      $parsed = $candidate;
      $source = 'title (explicit year)';
      $confidence = 'high';
    }
    // 3. Month and day, year borrowed from the created date.
    elseif ($candidate && $candidate[1]) {
      $created_year = (int) date('Y', $node->getCreatedTime());
      $month = $candidate[0];
      // A title month far ahead of the created month usually means the node was
      // made late in the previous year for a meetup early in the next one.
      $created_month = (int) date('n', $node->getCreatedTime());
      $year = ($month < $created_month - 6) ? $created_year + 1 : $created_year;
      $parsed = [$month, $candidate[1], $year];
      $source = 'title month/day + created year';
      $confidence = 'medium';
    }
  }

  $resolved = '';
  if ($parsed) {
    [$month, $day, $year] = $parsed;
    if (!$day) {
      $day = sfdug_second_thursday($year, $month, $tz);
      $source .= ' (day = 2nd Thursday)';
      $confidence = 'medium';
    }
    $start = new DateTime(sprintf('%04d-%02d-%02d %s', $year, $month, $day, SFDUG_LEGACY_HOUR), $tz);
    $resolved = $start->format('D, M j Y g:i A');

    if ($write) {
      $node->set('field_session_date', [
        'value' => $start->getTimestamp(),
        'end_value' => $start->getTimestamp() + SFDUG_LEGACY_DURATION * 60,
        'duration' => SFDUG_LEGACY_DURATION,
        'rrule' => NULL,
        'timezone' => 'America/Los_Angeles',
      ]);
      $node->setSyncing(TRUE);
      $node->save();
    }
  }

  $tally[$confidence]++;
  $rows[] = [$node->id(), $title, $source, $confidence, $resolved, $media_name];
}

// Write the review report.
$log_dir = DRUPAL_ROOT . '/../logs';
if (!is_dir($log_dir)) {
  mkdir($log_dir, 0775, TRUE);
}
$path = "$log_dir/session-date-backfill.csv";
$handle = fopen($path, 'w');
foreach ($rows as $row) {
  fputcsv($handle, $row, ',', '"', '\\');
}
fclose($handle);

printf("high: %d   medium: %d   unresolved: %d\n", $tally['high'], $tally['medium'], $tally['none']);
echo "Report: logs/session-date-backfill.csv\n";
