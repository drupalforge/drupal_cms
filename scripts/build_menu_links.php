<?php

/**
 * @file
 * Rebuilds the SFDUG menu links. Idempotent -- matches on menu + title.
 *
 * ddev drush php:script build_menu_links --script-path=/var/www/html/scripts
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

$links = [
  ['main', 0, 'Upcoming', 'internal:/upcoming'],
  ['main', 1, 'Videos', 'internal:/meetings'],
  ['main', 2, 'About', 'internal:/#about'],
  ['main', 3, 'Join', 'internal:/#join'],
  ['main', 4, 'Join the list', 'internal:/#join'],
  ['footer-community', 0, 'About SFDUG', 'internal:/#about'],
  ['footer-community', 1, 'BADCamp', 'https://www.badcamp.org'],
  ['footer-community', 2, 'Join the list', 'internal:/#join'],
  ['footer-meetings', 0, 'Upcoming', 'internal:/upcoming'],
  ['footer-meetings', 1, 'Video archive', 'internal:/meetings'],
  ['footer-meetings', 2, 'Speakers', 'internal:/people'],
];

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$made = 0;

foreach ($links as [$menu, $weight, $title, $uri]) {
  $existing = $storage->loadByProperties(['menu_name' => $menu, 'title' => $title]);
  if ($existing) {
    continue;
  }
  MenuLinkContent::create([
    'title' => $title,
    'link' => ['uri' => $uri],
    'menu_name' => $menu,
    'weight' => $weight,
    'expanded' => FALSE,
    'enabled' => TRUE,
  ])->save();
  printf("  %-18s %-16s %s\n", $menu, $title, $uri);
  $made++;
}

printf("\ncreated %d menu links\n", $made);
