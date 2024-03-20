<?php

namespace Drupal\fixer_api\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\fixer_api\FixerHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process a queue to fetch CurrencyRate's items.
 *
 * @QueueWorker(
 *   id = "fixer_rates_queue_worker",
 *   title = @Translation("Fixer Rates Queue Worker"),
 *   cron = {"time" = 180}
 * )
 */
class FixerRatesQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * A FixerHandler instance.
   *
   * @var \Drupal\fixer_api\FixerHandler
   */
  protected $fixerHandler;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\fixer_api\FixerHandler $fixer_handler
   *   The factory for configuration objects.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FixerHandler $fixer_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fixerHandler = $fixer_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('fixer_api.handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Save all rates from server to the local DB.
    $this->fixerHandler->saveRates([$data]);
  }

}
