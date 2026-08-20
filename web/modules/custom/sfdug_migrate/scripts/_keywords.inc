<?php

/**
 * @file
 * Shared title-matching helpers for the SFDUG content repair scripts.
 *
 * Used by rematch_session_videos.php and apply_meetup_dates.php, both of which
 * match one list of human-written titles against another.
 */

/**
 * Reduces a title to its meaningful words.
 *
 * Drops the SFDUG prefix, dates, and filler so that scoring compares topics
 * rather than the boilerplate every title shares.
 */
function sfdug_keywords(string $text): array {
  $text = mb_strtolower($text);
  $text = str_replace(['’', '‘', '“', '”', '–', '—'], ["'", "'", '"', '"', '-', '-'], $text);
  // Strip dates and the group name.
  $text = preg_replace('/\b(jan|january|feb|february|mar|march|apr|april|may|jun|june|jul|july|aug|august|sep|sept|september|oct|october|nov|november|dec|december)\b\.?/u', ' ', $text);
  $text = preg_replace('/\b(sfdug|san francisco drupal users? group|sf drupal users? group|meetup|meeting|presentation)\b/u', ' ', $text);
  $text = preg_replace('/\d+/', ' ', $text);
  $text = preg_replace('/[^a-z0-9 ]+/u', ' ', $text);

  $stop = array_flip([
    'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with', 'your',
    'you', 'is', 'are', 'be', 'at', 'by', 'from', 'it', 'that', 'this', 'as',
    'we', 'our', 'my', 'how', 'what', 'new', 'up', 'out', 'not', 'can', 'will',
    'drupal', 'st', 'nd', 'rd', 'th',
  ]);

  $words = array_filter(preg_split('/\s+/', trim($text)));
  $words = array_filter($words, fn($w) => mb_strlen($w) > 2 && !isset($stop[$w]));
  return array_values(array_unique($words));
}

/**
 * Overlap weighted towards covering the shorter title.
 *
 * A descriptive video title must share at least two words with a session
 * before it counts. Single-word overlaps like "theming" score a perfect ratio
 * against a stub title such as "SFDUG - Drupal 8 Theming" and were the source
 * of every false positive. The exemption is for videos whose own title reduces
 * to one distinctive word ("Freelancing"), where one shared word is all there
 * is to go on.
 *
 * @param array $a
 *   Keywords from the video title.
 * @param array $b
 *   Keywords from the session title.
 */
function sfdug_score(array $a, array $b): float {
  if (!$a || !$b) {
    return 0.0;
  }
  $shared = count(array_intersect($a, $b));
  if ($shared < 2 && count($a) > 1) {
    return 0.0;
  }
  return $shared === 0 ? 0.0 : $shared / min(count($a), count($b));
}

/**
 * Extracts (month, day) and (month, year) pairs from a title.
 */
function sfdug_date_hints(string $text): array {
  $months = 'jan|january|feb|february|mar|march|apr|april|may|jun|june|jul|july|aug|august|sep|sept|september|oct|october|nov|november|dec|december';
  $map = [
    'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
    'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
  ];
  $hints = ['month' => NULL, 'day' => NULL, 'year' => NULL];
  if (preg_match('/\b(' . $months . ')\.?\s+(\d{1,2})(?!\d)(?:st|nd|rd|th)?(?:\s*,)?(?:\s+(\d{4}))?/i', $text, $m)) {
    $hints['month'] = $map[substr(strtolower($m[1]), 0, 3)] ?? NULL;
    $hints['day'] = (int) $m[2];
    $hints['year'] = isset($m[3]) ? (int) $m[3] : NULL;
  }
  elseif (preg_match('/\b(' . $months . ')\.?\s+(\d{4})/i', $text, $m)) {
    $hints['month'] = $map[substr(strtolower($m[1]), 0, 3)] ?? NULL;
    $hints['year'] = (int) $m[2];
  }
  return $hints;
}

/**
 * Nudges a score up when two titles agree on a date, down when they disagree.
 *
 * This is what separates "Nov 12 - Responsive Images" from "July 2023 -
 * Simplifying Responsive Images", which share every meaningful word.
 */
function sfdug_date_adjustment(array $a, array $b): float {
  if ($a['month'] === NULL || $b['month'] === NULL) {
    return 0.0;
  }
  if ($a['month'] !== $b['month']) {
    return -0.5;
  }
  if ($a['day'] && $b['day']) {
    return $a['day'] === $b['day'] ? 0.5 : -0.3;
  }
  if ($a['year'] && $b['year'] && $a['year'] !== $b['year']) {
    return -0.3;
  }
  return 0.25;
}
