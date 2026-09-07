<?php

/**
 * @file
 * Gives the hero and info bar a fallback for when no meeting is scheduled.
 *
 * block_featured and block_infobar show the next upcoming meeting. Once it has
 * passed they match nothing and the front page loses its hero entirely, which
 * is why a hard-coded date had been put in the filter. Instead, each display
 * now falls back to the most recent past meeting via a Views empty area.
 *
 * Idempotent.
 *
 * ddev drush php:script build_hero_fallback --script-path=/var/www/html/scripts
 */

$view = \Drupal::entityTypeManager()->getStorage('view')->load('sfdug_meetings');
$display = $view->get('display');

$pairs = [
  'block_featured' => ['fallback' => 'block_featured_recent', 'mode' => 'featured'],
  'block_infobar'  => ['fallback' => 'block_infobar_recent',  'mode' => 'infobar'],
];

foreach ($pairs as $live => $info) {
  $fallback = $info['fallback'];
  $opts = $display[$live]['display_options'];

  // 1. The live display shows only genuinely upcoming meetings again.
  $opts['filters']['field_session_date_end_value']['value']['value'] = 'now';
  $opts['filters']['field_session_date_end_value']['value']['type'] = 'offset';

  // 2. Its empty area renders the fallback display.
  $opts['empty'] = [
    'area_view' => [
      'id' => 'area_view',
      'table' => 'views',
      'field' => 'view',
      'plugin_id' => 'view',
      'empty' => TRUE,
      'view_to_insert' => 'sfdug_meetings:' . $fallback,
      'inherit_arguments' => FALSE,
    ],
  ];
  $opts['defaults']['empty'] = FALSE;
  $display[$live]['display_options'] = $opts;

  // 3. The fallback: most recent meeting that has already happened.
  $fopts = $opts;
  unset($fopts['empty'], $fopts['block_description'], $fopts['display_extenders']);
  $fopts['defaults']['empty'] = FALSE;
  $fopts['filters']['field_session_date_end_value']['operator'] = '<';
  $fopts['filters']['field_session_date_end_value']['value']['value'] = 'now';
  $fopts['filters']['field_session_date_end_value']['value']['type'] = 'offset';
  $fopts['sorts']['field_session_date_value']['order'] = 'DESC';
  $fopts['row']['options']['view_mode'] = $info['mode'];
  $fopts['pager'] = ['type' => 'some', 'options' => ['items_per_page' => 1, 'offset' => 0]];

  $display[$fallback] = [
    'id' => $fallback,
    'display_title' => ucfirst($info['mode']) . ' (most recent, when nothing is scheduled)',
    'display_plugin' => 'block',
    'position' => count($display),
    'display_options' => $fopts,
  ];

  echo "  {$live}: filter back to now, empty area -> {$fallback}\n";
}

$view->set('display', $display);
$view->save();
echo "saved\n";
