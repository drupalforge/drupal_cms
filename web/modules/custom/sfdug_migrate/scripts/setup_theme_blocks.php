<?php

/**
 * @file
 * Places the blocks, menus and menu links the SFDUG theme expects, and points
 * the front page at the Home node.
 *
 * Idempotent: existing blocks and menu links are updated in place.
 *
 * Run:
 *   ddev drush php:script setup_theme_blocks \
 *     --script-path=/var/www/html/web/modules/custom/sfdug_migrate/scripts
 */

use Drupal\block\Entity\Block;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

const SFDUG_THEME = 'sfdug';

/**
 * Blocks to place, keyed by block config id (without the theme prefix).
 *
 * `front_only` adds a request-path condition so the homepage furniture does not
 * leak onto interior pages.
 */
$blocks = [
  'branding' => [
    'plugin' => 'system_branding_block',
    'region' => 'header',
    'weight' => 0,
    'settings' => ['use_site_logo' => TRUE, 'use_site_name' => FALSE, 'use_site_slogan' => FALSE],
  ],
  'main_menu' => [
    'plugin' => 'system_menu_block:main',
    'region' => 'primary_menu',
    'weight' => 0,
    'settings' => ['level' => 1, 'depth' => 1],
  ],
  'infobar' => [
    'plugin' => 'views_block:sfdug_meetings-block_infobar',
    'region' => 'infobar',
    'weight' => 0,
    'front_only' => TRUE,
  ],
  'featured_session' => [
    'plugin' => 'views_block:sfdug_meetings-block_featured',
    'region' => 'hero',
    'weight' => 0,
    'front_only' => TRUE,
  ],
  'season' => [
    'plugin' => 'views_block:sfdug_meetings-block_season',
    'region' => 'recordings',
    'weight' => 0,
    'front_only' => TRUE,
  ],
  'archive' => [
    'plugin' => 'views_block:sfdug_meetings-block_archive',
    'region' => 'recordings',
    'weight' => 1,
    'front_only' => TRUE,
  ],
  'content' => [
    'plugin' => 'system_main_block',
    'region' => 'content',
    'weight' => 0,
  ],
  'page_title' => [
    'plugin' => 'page_title_block',
    'region' => 'highlighted',
    'weight' => -10,
    // The front page leads with the featured session, not an H1 saying "Home".
    'front_only' => FALSE,
    'not_front' => TRUE,
  ],
  'breadcrumbs' => [
    'plugin' => 'system_breadcrumb_block',
    'region' => 'breadcrumb',
    'weight' => 0,
  ],
  'messages' => [
    'plugin' => 'system_messages_block',
    'region' => 'highlighted',
    'weight' => 0,
  ],
  'local_tasks' => [
    'plugin' => 'local_tasks_block',
    'region' => 'highlighted',
    'weight' => 1,
    'settings' => ['primary' => TRUE, 'secondary' => TRUE],
  ],
  'local_actions' => [
    'plugin' => 'local_actions_block',
    'region' => 'highlighted',
    'weight' => 2,
  ],
  'footer_meetings' => [
    'plugin' => 'system_menu_block:footer-meetings',
    'region' => 'footer_second',
    'weight' => 0,
    'label' => 'Meetings',
    'label_display' => 'visible',
  ],
  'footer_community' => [
    'plugin' => 'system_menu_block:footer-community',
    'region' => 'footer_third',
    'weight' => 0,
    'label' => 'Community',
    'label_display' => 'visible',
  ],
];

echo "== menus ==\n";

$menus = [
  'footer-meetings' => 'Footer — Meetings',
  'footer-community' => 'Footer — Community',
];
foreach ($menus as $id => $label) {
  if (!Menu::load($id)) {
    Menu::create(['id' => $id, 'label' => $label, 'description' => 'SFDUG footer links.'])->save();
    echo "  created menu: $id\n";
  }
  else {
    echo "  exists: $id\n";
  }
}

echo "== menu links ==\n";

/**
 * The site's links. Existing links in these menus are removed first so the menu
 * ends up exactly as described here.
 */
$links = [
  'main' => [
    ['title' => 'Upcoming', 'uri' => 'internal:/upcoming', 'weight' => 0],
    ['title' => 'Videos', 'uri' => 'internal:/meetings', 'weight' => 1],
    ['title' => 'About', 'uri' => 'internal:/#about', 'weight' => 2],
    ['title' => 'Join', 'uri' => 'internal:/#join', 'weight' => 3],
    ['title' => 'Join the list', 'uri' => 'internal:/#join', 'weight' => 4, 'class' => 'btn-purple'],
  ],
  'footer-meetings' => [
    ['title' => 'Upcoming', 'uri' => 'internal:/upcoming', 'weight' => 0],
    ['title' => 'Video archive', 'uri' => 'internal:/meetings', 'weight' => 1],
    ['title' => 'Speakers', 'uri' => 'internal:/people', 'weight' => 2],
  ],
  'footer-community' => [
    ['title' => 'About SFDUG', 'uri' => 'internal:/#about', 'weight' => 0],
    ['title' => 'BADCamp', 'uri' => 'https://www.badcamp.org', 'weight' => 1],
    ['title' => 'Join the list', 'uri' => 'internal:/#join', 'weight' => 2],
  ],
];

$link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
foreach ($links as $menu_name => $items) {
  foreach ($link_storage->loadByProperties(['menu_name' => $menu_name]) as $existing) {
    $existing->delete();
  }
  foreach ($items as $item) {
    $values = [
      'title' => $item['title'],
      'menu_name' => $menu_name,
      'link' => ['uri' => $item['uri']],
      'weight' => $item['weight'],
      'expanded' => FALSE,
    ];
    if (!empty($item['class'])) {
      $values['link'] = [
        'uri' => $item['uri'],
        'options' => ['attributes' => ['class' => [$item['class']]]],
      ];
    }
    MenuLinkContent::create($values)->save();
  }
  echo "  $menu_name: " . count($items) . " links\n";
}

echo "== blocks ==\n";

foreach ($blocks as $key => $spec) {
  $id = SFDUG_THEME . '_' . $key;
  $block = Block::load($id);
  if (!$block) {
    $block = Block::create([
      'id' => $id,
      'theme' => SFDUG_THEME,
      'plugin' => $spec['plugin'],
    ]);
  }

  $block->setRegion($spec['region']);
  $block->setWeight($spec['weight']);
  $block->setStatus(TRUE);

  $settings = $block->get('settings') ?: [];
  $settings['id'] = str_replace(':', '_', $spec['plugin']);
  $settings['provider'] = explode(':', $spec['plugin'])[0] === 'views_block' ? 'views' : $settings['provider'] ?? 'system';
  $settings['label'] = $spec['label'] ?? ucfirst(str_replace('_', ' ', $key));
  $settings['label_display'] = $spec['label_display'] ?? '0';
  $settings += $spec['settings'] ?? [];
  if (!empty($spec['settings'])) {
    $settings = $spec['settings'] + $settings;
  }
  $block->set('settings', $settings);

  $visibility = [];
  if (!empty($spec['front_only'])) {
    $visibility['request_path'] = [
      'id' => 'request_path',
      'negate' => FALSE,
      'pages' => '<front>',
    ];
  }
  elseif (!empty($spec['not_front'])) {
    $visibility['request_path'] = [
      'id' => 'request_path',
      'negate' => TRUE,
      'pages' => '<front>',
    ];
  }
  $block->set('visibility', $visibility);

  $block->save();
  echo "  {$spec['region']}: $id\n";
}

echo "== removing auto-placed duplicates ==\n";

foreach ([
  'sfdug_primary_local_tasks',
  'sfdug_secondary_local_tasks',
  'sfdug_primary_admin_actions',
] as $stale) {
  if ($block = Block::load($stale)) {
    $block->delete();
    echo "  deleted: $stale\n";
  }
}

echo "== main menu: module-provided links ==\n";

// Event Platform and the job-listings view push their own links into the main
// menu. They belong to a conference site, not to this one.
$menu_link_manager = \Drupal::service('plugin.manager.menu.link');
foreach ([
  'smart_menu_links:news',
  'smart_menu_links:sponsors',
  'smart_menu_links:schedule',
  'smart_menu_links:sessions',
  'views_view:views.job_listings.page_1',
] as $plugin_id) {
  if ($menu_link_manager->hasDefinition($plugin_id)) {
    $menu_link_manager->updateDefinition($plugin_id, ['enabled' => FALSE]);
    echo "  disabled: $plugin_id\n";
  }
}

echo "== site settings ==\n";

$home_ids = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'page')
  ->condition('title', 'Home')
  ->range(0, 1)
  ->execute();

if ($home_ids) {
  // Point at the alias, not the node id: the Home node lands on a different
  // nid in every environment, and a hardcoded /node/N would break the front
  // page as soon as this config was imported somewhere else.
  \Drupal::configFactory()->getEditable('system.site')
    ->set('page.front', '/home')
    ->save();
  echo '  front page -> /home (node ' . reset($home_ids) . ")\n";
}
else {
  echo "  !! Home node not found; front page left as-is\n";
}

\Drupal::configFactory()->getEditable('system.theme')->set('default', SFDUG_THEME)->save();
echo '  default theme -> ' . SFDUG_THEME . "\n";

echo "\nDone.\n";
