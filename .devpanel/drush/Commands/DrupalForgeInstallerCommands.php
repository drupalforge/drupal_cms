<?php

namespace Drush\Commands;

use Composer\InstalledVersions;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeConfigurator;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\drupal_cms_installer\RecipeHandler;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;

/**
 * Drush commands used during installation.
 */
final class DrupalForgeInstallerCommands extends DrushCommands {

  protected $recipeDirectory;

  /**
   * Prepare Drush to be used while installation is in progress.
   */
  public function __construct() {
    // Tell Drush that we are in the process of installing.
    $GLOBALS['install_state']['theme'] = 'drupal_cms_installer_theme';

    // Set the contrib recipes path.
    ['install_path' => $project_root] = InstalledVersions::getRootPackage();
    $project_root = realpath($project_root);
    assert(is_string($project_root));

    $file = $project_root . DIRECTORY_SEPARATOR . 'composer.json';
    $data = file_get_contents($file);
    $data = json_decode($data, TRUE, flags: JSON_THROW_ON_ERROR);

    $directory = array_find_key(
      $data['extra']['installer-paths'] ?? [],
      fn (array $criteria): bool => in_array('type:' . Recipe::COMPOSER_PROJECT_TYPE, $criteria, TRUE),
    );
    if ($directory) {
      $directory = $project_root . DIRECTORY_SEPARATOR . $directory;
      // The general recipe directory will not have package-specific placeholders,
      // because that makes no sense.
      $directory = str_replace(['{$name}', '{$vendor}'], '', $directory);
      $this->recipeDirectory = rtrim($directory, DIRECTORY_SEPARATOR);
    }
  }

  /**
   * Command to get the contrib recipes path.
   */
  #[CLI\Command(name: 'drupalforge:contrib-recipes-path', aliases: ['contrib-recipes-path', 'crp'])]
  #[CLI\Usage(name: 'drupalforge:contrib-recipes-path', description: 'Emit the path to contrib recipes.')]
  public function getContribRecipesPath() {
    return $this->recipeDirectory;
  }

  #[CLI\Command(name: 'drupalforge:record-recipe-hashes', aliases: ['record-recipe-hashes', 'rrh'])]
  #[CLI\Bootstrap(level: DrupalBootLevels::MAX)]
  #[CLI\Argument(name: 'locators', description: 'Recipe locators to process. Default: []')]
  #[CLI\Usage(name: 'drupalforge:record-recipe-hashes [locator1] [locator2] ...', description: 'Record completed operation hashes. Pass any number of locators; if none are provided, process RecipeHandler::listApplied().')]
  public function recordRecipeHashes($locators = [], $options = []): int {
    if (\Drupal::installProfile() !== 'drupal_cms_installer') {
      $this->logger()->error('This command can only be used with the drupal_cms_installer profile.');
      return self::EXIT_FAILURE;
    }

    $recipe_handler = \Drupal::service(RecipeHandler::class);
    $locators = $locators ?: $recipe_handler->listApplied();

    $completed_hashes = $recipe_handler->listCompletedOperationHashes();
    foreach ($locators as $locator) {
      $already_applied_path = is_dir($locator) ? $locator : $recipe_handler->getPath($locator);
      if (is_dir($already_applied_path)) {
        // When the recipe is included by another recipe, the path may differ
        // from the locator, so we need use both paths to prevent duplicate
        // operations.
        $name = strpos($already_applied_path, 'core/') === 0 ? $already_applied_path : basename($already_applied_path);
        $recipe = RecipeConfigurator::getIncludedRecipe(dirname($already_applied_path), $name);
        $recipes = [$recipe->path => $recipe];
        if ($recipe->path !== $already_applied_path) {
          $recipes[$already_applied_path] = Recipe::createFromDirectory($already_applied_path);
        }
        foreach ($recipes as $recipe) {
          foreach (RecipeRunner::toBatchOperations($recipe) as $operation) {
            $operation_hash = hash('sha256', serialize($operation));
            if (isset($completed_hashes[$operation_hash])) {
              continue;
            }
            [$callable, $arguments] = $operation;
            $completed_hashes[$operation_hash] = _drupal_cms_installer_operation_metadata($callable, $arguments);
            $recipe_handler->markOperationHashCompleted($operation_hash, $completed_hashes[$operation_hash]);
          }
        }
      }
    }

    return self::EXIT_SUCCESS;
  }

}
