<?php

namespace Drush\Commands;

use Drupal\Core\Recipe\ConsoleInputCollector;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\Utility\CallableResolver;
use Symfony\Component\Console\Style\SymfonyStyle;
use Drupal\drupal_cms_installer\OperationTracker;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;

/**
 * Drush commands used during installation.
 */
final class DrupalForgeInstallerCommands extends DrushCommands {

  /**
   * Prepare Drush to be used while installation is in progress.
   */
  public function __construct() {
    // Tell Drush that we are in the process of installing so config validation
    // behaves properly.
    // @see \Drupal\Core\Recipe\RecipeConfigInstaller::installRecipeConfig()
    // @see \Drupal\Core\Installer\InstallerKernel::installationAttempted()
    $GLOBALS['install_state']['theme'] = 'drupal_cms_installer_theme';
  }

  /**
   * Applies a recipe with installer operation tracking.
   *
   * Records completed operations so they are skipped when installation resumes.
   *
   * @see \Drupal\drupal_cms_installer\OperationTracker
   */
  #[CLI\Command(name: 'drupalforge:recipe')]
  #[CLI\Bootstrap(level: DrupalBootLevels::MAX)]
  #[CLI\Argument(name: 'path', description: 'The recipe directory to apply, absolute or relative to the Drupal web root.')]
  #[CLI\Usage(name: 'drush drupalforge:recipe path/to/recipe', description: 'Apply a recipe to the site.')]
  public function applyRecipe(string $path, $options = ['input|i' => []]): int {
    if (!\Drupal::hasService(OperationTracker::class)) {
      $this->logger()->error(dt('This command requires the drupal_cms_installer OperationTracker service and can only be used during installation.'));
      return self::EXIT_FAILURE;
    }

    if (!is_dir($path)) {
      $this->logger()->error(dt('The supplied path @path is not a directory.', ['@path' => $path]));
      return self::EXIT_FAILURE;
    }

    $recipe = Recipe::createFromDirectory($path);

    // Collect --input / -i values and pass them to the recipe's input system.
    $io = new SymfonyStyle($this->input(), $this->output());
    $recipe->input->collectAll(new ConsoleInputCollector($this->input(), $io));

    try {
      $operations = \Drupal::service(OperationTracker::class)
        ->prepare(RecipeRunner::toBatchOperations($recipe));
      $this->logger()->notice(dt('Applying recipe @name (@count steps).', [
        '@name' => $recipe->name,
        '@count' => count($operations),
      ]));

      foreach ($operations as [$callable, $arguments]) {
        $callable = \Drupal::service(CallableResolver::class)
          ->getCallableFromDefinition($callable);
        $context = ['sandbox' => [], 'results' => [], 'finished' => 0];
        while ($context['finished'] < 1) {
          $context['finished'] = 1;
          $callable(...[...$arguments, &$context]);
        }
      }

      $this->logger()->success(dt('Recipe @name applied successfully.', ['@name' => $recipe->name]));
      return self::EXIT_SUCCESS;
    }
    catch (\Throwable $e) {
      $this->logger()->error(dt('Recipe failed: @message', ['@message' => $e->getMessage()]));
      throw $e;
    }
  }

}
