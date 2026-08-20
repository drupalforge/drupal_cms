<?php

/**
 * @file
 * Imports the 2026 content the redesigned homepage is built from.
 *
 * Creates, if they are not already there:
 *  - the "Online — Zoom" venue and "Automation & Site Building" category terms
 *  - person nodes for the 2026 speakers
 *  - the upcoming ECA session and the four 2026 Season sessions
 *  - a "Home" page node carrying the evergreen closing sections
 *
 * Idempotent: everything is matched by title first, so re-running is safe and
 * will not duplicate. Existing nodes are left untouched.
 *
 * Run:
 *   ddev drush php:script import_2026_content \
 *     --script-path=/var/www/html/web/modules/custom/sfdug_migrate/scripts
 */

use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

$entity_type_manager = \Drupal::entityTypeManager();
$node_storage = $entity_type_manager->getStorage('node');
$term_storage = $entity_type_manager->getStorage('taxonomy_term');
$media_storage = $entity_type_manager->getStorage('media');
$file_repository = \Drupal::service('file.repository');

/** Source images shipped with the static prototype. */
$image_source = DRUPAL_ROOT . '/HTML/img';

$meetup_url = 'https://www.meetup.com/sfdug-san-francisco-drupal-users-group/events/316123460/?utm_medium=referral&utm_campaign=share-btn_savedevents_share_modal&utm_source=link&utm_version=v2&member_id=10134746';

/**
 * Loads a node of the given type by exact title, or NULL.
 */
$find_node = function (string $type, string $title) use ($node_storage): ?Node {
  $ids = $node_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', $type)
    ->condition('title', $title)
    ->range(0, 1)
    ->execute();
  return $ids ? $node_storage->load(reset($ids)) : NULL;
};

/**
 * Loads or creates a taxonomy term.
 */
$term = function (string $vid, string $name) use ($term_storage): Term {
  $existing = $term_storage->loadByProperties(['vid' => $vid, 'name' => $name]);
  if ($existing) {
    return reset($existing);
  }
  $t = Term::create(['vid' => $vid, 'name' => $name]);
  $t->save();
  echo "  term created: $vid / $name\n";
  return $t;
};

/**
 * Copies one of the prototype images into Drupal and returns the File entity.
 */
$image_file = function (string $filename) use ($image_source, $file_repository) {
  $destination = 'public://sfdug/' . $filename;
  $existing = \Drupal::entityTypeManager()->getStorage('file')
    ->loadByProperties(['uri' => $destination]);
  if ($existing) {
    return reset($existing);
  }

  $source = "$image_source/$filename";
  if (!is_readable($source)) {
    echo "  !! image not found: $source\n";
    return NULL;
  }

  $dir = 'public://sfdug';
  \Drupal::service('file_system')->prepareDirectory(
    $dir,
    FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
  );
  $file = $file_repository->writeData(file_get_contents($source), $destination);
  echo "  file created: $destination\n";
  return $file;
};

/**
 * Loads or creates an image media entity wrapping one of those files.
 */
$image_media = function (string $filename, string $name, string $alt) use ($image_file, $media_storage): ?Media {
  $existing = $media_storage->loadByProperties(['bundle' => 'image', 'name' => $name]);
  if ($existing) {
    return reset($existing);
  }
  $file = $image_file($filename);
  if (!$file) {
    return NULL;
  }
  $media = Media::create([
    'bundle' => 'image',
    'name' => $name,
    'status' => 1,
    'field_media_image' => ['target_id' => $file->id(), 'alt' => $alt],
  ]);
  $media->save();
  echo "  media created: $name\n";
  return $media;
};

/**
 * Loads or creates a remote_video media entity for a YouTube id.
 */
$video_media = function (string $video_id, string $name) use ($media_storage): Media {
  $url = "https://www.youtube.com/watch?v=$video_id";
  $existing = $media_storage->loadByProperties([
    'bundle' => 'remote_video',
    'field_media_oembed_video' => $url,
  ]);
  if ($existing) {
    return reset($existing);
  }
  $media = Media::create([
    'bundle' => 'remote_video',
    'name' => $name,
    'status' => 1,
    'field_media_oembed_video' => $url,
  ]);
  $media->save();
  echo "  video media created: $name\n";
  return $media;
};

/**
 * Turns a local wall-clock time into a UNIX timestamp.
 */
$ts = function (string $datetime): int {
  return (new DateTime($datetime, new DateTimeZone('America/Los_Angeles')))->getTimestamp();
};

echo "== taxonomy ==\n";
$venue_online = $term('venue', 'Online — Zoom');
$category_automation = $term('session_category', 'Automation & Site Building');

echo "== people ==\n";

$people = [
  'Jürgen Haas' => [
    'organization' => 'Creator and maintainer of ECA · Drupal core subsystem maintainer',
    'bio' => 'Jürgen Haas created ECA and maintains it, and is a Drupal core subsystem maintainer for the new admin theme. He last spoke to SFDUG in January 2022, when ECA was six months old and had about thirty installs.',
    'photo' => ['jurgen-avatar.jpg', 'Jürgen Haas avatar', 'Jürgen Haas'],
  ],
  'Ashraf Abed' => ['organization' => 'Entrepreneur & Drupal Business Strategist', 'bio' => '', 'photo' => NULL],
  'Kristen Pol' => ['organization' => '', 'bio' => '', 'photo' => NULL],
  'Mike Herchel' => ['organization' => 'Drupal Core Maintainer, Creator of Olivero', 'bio' => '', 'photo' => NULL],
  'Matt Glaman' => ['organization' => '', 'bio' => '', 'photo' => NULL],
];

$person_nodes = [];
foreach ($people as $name => $spec) {
  if ($existing = $find_node('person', $name)) {
    $person_nodes[$name] = $existing;
    echo "  exists: $name\n";
    continue;
  }

  $values = ['type' => 'person', 'title' => $name, 'status' => 1, 'uid' => 1];
  if ($spec['bio']) {
    $values['field_bio'] = ['value' => '<p>' . $spec['bio'] . '</p>', 'format' => 'basic_html'];
  }
  if ($spec['organization']) {
    $values['field_organization'] = $spec['organization'];
  }
  if ($spec['photo'] && ($media = $image_media(...$spec['photo']))) {
    $values['field_photo'] = ['target_id' => $media->id()];
  }

  $person = Node::create($values);
  $person->save();
  $person_nodes[$name] = $person;
  echo "  created: $name (nid {$person->id()})\n";
}

echo "== sessions ==\n";

/**
 * The ECA description, verbatim from the prototype.
 */
$eca_body = <<<'HTML'
<p>ECA lets you automate a Drupal site without writing code: pick an event, add a condition, choose an action. Around 16,000 sites run it today, and its visual modeler — the drag-and-drop flowchart — is the part most people picture when they hear the name. It got a significant upgrade this year: a new modeler with color, dark mode, and full keyboard accessibility.</p>

<p>But the more consequential change is what happens when you never open the modeler. ECA now offers automation in place: click a field on a form, choose a template, and the model is built for you in the background — no canvas, no tokens, no need to know ECA is involved. For everyone else, there’s a debugger that replays what your site actually did, step by step, with every variable visible along the way.</p>

<div class="homework">
<h3 class="hw-title">New! Homework! Everybody likes homework, right?</h3>

<p class="hw-lede">If you want to get the most out of this session, take five minutes and run through these steps:</p>

<p class="hw-step-label">From a terminal:</p>

<pre class="hw-code"><code>composer require drupal/eca_starterkit
drush recipe ../recipes/eca_starterkit</code></pre>

<p class="hw-step-label">Then, as an administrator:</p>

<ol class="hw-list">
	<li>Go to your site’s user list and click <b>Add user</b></li>
	<li>Focus any text field — a light blue bolt appears beside it</li>
	<li>Click it, choose <b>Change label</b>, set a new label, and save</li>
	<li>Refresh the page</li>
</ol>

<p>Your form is now altered. No custom module, no <code class="hw-inline">hook_form_alter</code>, and nothing that required you to know ECA was involved. <span class="hw-note">(Recipe path may vary with your project layout.)</span></p>

<p class="hw-close">Come without doing it and you’ll follow along fine — but if you do it, you’ll have your own questions ready. Won’t that be fun?</p>
</div>

<p>Jürgen Haas created ECA and maintains it, and is a Drupal core subsystem maintainer for the new admin theme. He last spoke to SFDUG in January 2022, when ECA was six months old and had about thirty installs.</p>
HTML;

/**
 * The five 2026 sessions.
 *
 * DATES: taken from the dates printed on each talk's YouTube promo thumbnail
 * and cross-checked against the video titles — not from js/app.js, whose month
 * labels are shuffled (it calls Matt Glaman's talk April and Ashraf Abed's
 * January; they are actually January and May). The 2026 season meets on the
 * third Thursday, which is also what the ECA date, Thursday Aug 20, follows.
 * Matt Glaman's thumbnail carries no date; Jan 15 is that month's third
 * Thursday and fits an upload on Feb 2.
 */
$sessions = [
  [
    'title' => 'ECA: Drupal’s Automation Platform',
    'hook' => 'Automate Drupal without writing code — visually on the canvas, or right inside the form you’re editing.',
    'body' => $eca_body,
    'start' => '2026-08-20 12:00:00',
    'end' => '2026-08-20 13:00:00',
    'speakers' => ['Jürgen Haas'],
    'accent' => '#F26522',
    'badge' => 'New afternoon meeting!',
    'time_note' => 'New afternoon meeting time',
    'venue_note' => 'Link emailed after you RSVP',
    'rsvp' => $meetup_url,
    'promo' => 'eca-promo.jpg',
    'video' => NULL,
  ],
  [
    'title' => 'New Revenue Streams with Drupal: SMB, SaaS, Marketplace, Drupito & more',
    'start' => '2026-05-21 18:00:00',
    'end' => '2026-05-21 19:00:00',
    'speakers' => ['Ashraf Abed'],
    'video' => ['i4iIHlpgdIE', 'SFDUG - January 2026 - New Revenue Streams with Drupal'],
  ],
  [
    'title' => 'What’s Next for Drupal AI? A Look at the 2026 Roadmap',
    'start' => '2026-02-19 18:00:00',
    'end' => '2026-02-19 19:00:00',
    'speakers' => ['Kristen Pol'],
    'video' => ['hrvvWWAUps4', 'SFDUG - February 2026 - What’s Next for Drupal AI?'],
  ],
  [
    'title' => 'Modern Drupal Theming with Dripyard',
    'start' => '2026-03-19 18:00:00',
    'end' => '2026-03-19 19:00:00',
    'speakers' => ['Mike Herchel'],
    'video' => ['ZKseCW9VoYw', 'SFDUG - March 2026 - Modern Drupal Theming with Dripyard'],
  ],
  [
    'title' => 'Drupal Canvas Has Landed: Site Building in Drupal CMS 2.0',
    'start' => '2026-01-15 18:00:00',
    'end' => '2026-01-15 19:00:00',
    'speakers' => ['Matt Glaman'],
    'video' => ['t4_avk-i1RA', 'SFDUG - April 2026 - Drupal Canvas Has Landed'],
  ],
];

foreach ($sessions as $spec) {
  if ($find_node('session', $spec['title'])) {
    echo "  exists: {$spec['title']}\n";
    continue;
  }

  $start = $ts($spec['start']);
  $end = $ts($spec['end']);

  $values = [
    'type' => 'session',
    'title' => $spec['title'],
    'status' => 1,
    'uid' => 1,
    'field_session_date' => [
      'value' => $start,
      'end_value' => $end,
      'duration' => (int) (($end - $start) / 60),
      'rrule' => NULL,
      'timezone' => 'America/Los_Angeles',
    ],
    'field_venue' => ['target_id' => $venue_online->id()],
  ];

  foreach ([
    'hook' => 'field_short_description',
    'accent' => 'field_accent_color',
    'badge' => 'field_badge',
    'time_note' => 'field_time_note',
    'venue_note' => 'field_venue_note',
  ] as $key => $field) {
    if (!empty($spec[$key])) {
      $values[$field] = $spec[$key];
    }
  }

  if (!empty($spec['body'])) {
    $values['field_description'] = ['value' => $spec['body'], 'format' => 'full_html'];
  }
  if (!empty($spec['rsvp'])) {
    $values['field_rsvp_url'] = ['uri' => $spec['rsvp'], 'title' => 'RSVP for free'];
    $values['field_session_category'] = ['target_id' => $category_automation->id()];
  }
  if (!empty($spec['promo']) && ($file = $image_file($spec['promo']))) {
    $values['field_social_media_card'] = [
      'target_id' => $file->id(),
      'alt' => $spec['title'],
    ];
  }
  if (!empty($spec['video'])) {
    $media = $video_media($spec['video'][0], $spec['video'][1]);
    $values['field_session_video'] = ['target_id' => $media->id()];
  }

  $speakers = [];
  foreach ($spec['speakers'] as $name) {
    if (isset($person_nodes[$name])) {
      $speakers[] = ['target_id' => $person_nodes[$name]->id()];
    }
  }
  if ($speakers) {
    $values['field_person_speakers'] = $speakers;
  }

  $session = Node::create($values);
  $session->save();
  echo "  created: {$spec['title']} (nid {$session->id()})\n";
}

echo "== home page ==\n";

$home_body = <<<'HTML'
<section class="history" id="about">
<div class="wrap history-inner">
<div class="eyebrow">Est. April 2006</div>

<h2>Twenty Years of Bay Area Drupal</h2>

<p>In April 2006, Zack Rosen and Gregory Heller organized the first Drupal Camp SF — a two-day training at CivicSpace’s offices with Jeff Robbins from Lullabot. It sold out in five days; half the attendees flew in from out of state.</p>

<p class="closer">That was the beginning. We’re still here, still meeting every month.</p>
</div>
</section>

<section class="join" id="join">
<div class="wrap join-inner">
<div class="card-flat badcamp">
<div class="pill-tag">Part of the community</div>

<h3>SFDUG is part of <span>BADCamp</span></h3>

<p>We’re part of the wider Bay Area Drupal community behind the Bay Area Drupal Camp. Our monthly meetups may soon be hosted right alongside BADCamp — same friendly crowd, more ways to connect year-round.</p>

<a class="more" href="https://www.badcamp.org">Visit Bay Area Drupal Camp →</a></div>

<div class="card-flat newsletter">
<h3>Never miss a meetup</h3>

<p>Join the mailing list and we’ll send you the topic, speaker, and Zoom link before each month’s meeting. No spam — just Drupal.</p>

<form class="news-form" id="news-form"><input aria-label="Email address" name="email" placeholder="you@example.com" required="required" type="email" /> <button type="submit">Sign me up</button></form>

<div class="news-fine">Free · online every month · everyone welcome</div>

<div aria-live="polite" class="news-msg" id="news-msg" role="status">&nbsp;</div>
</div>
</div>
</section>
HTML;

$home = $find_node('page', 'Home');
if ($home) {
  echo "  exists: Home (nid {$home->id()})\n";
}
else {
  $home = Node::create([
    'type' => 'page',
    'title' => 'Home',
    'status' => 1,
    'uid' => 1,
    'body' => ['value' => $home_body, 'format' => 'full_html'],
    'path' => ['alias' => '/home', 'pathauto' => 0],
  ]);
  $home->save();
  echo "  created: Home (nid {$home->id()})\n";
}

echo "\nDone. Home node is nid {$home->id()} at /home\n";
