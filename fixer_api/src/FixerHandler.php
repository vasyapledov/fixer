<?php

namespace Drupal\fixer_api;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\fixer_api\Entity\Currency;
use Drupal\fixer_api\Entity\CurrencyRate;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Main fixer handler class.
 */
class FixerHandler {

  use StringTranslationTrait;

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'fixer_api.settings';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The setting's config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configSettings;

  /**
   * The api url.
   *
   * @var string
   */
  protected $apiUri;

  /**
   * Guzzle Http Client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * A logger instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Constructor method.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->configSettings = $this->configFactory->getEditable(static::SETTINGS);
    $this->apiUri = trim(($this->configSettings->get('fixer_api_link') ?? ''), '/');
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }

  /**
   * Create currency list from local settings or from external server.
   *
   * @param bool $force_update
   *   Force update from the external server.
   *
   * @return array|null
   *   List of currencies with full names.
   */
  public function getCurrenciesList(bool $force_update = FALSE) {
    $currencies_list = [];
    // Force update from server
    // or first local call for currencies list.
    if ($force_update || empty($this->configSettings->get('full_currencies_list'))) {

      $response = $this->getCurrenciesListFromServer();
      if (isset($response['success'])
        && $response['success'] == 'true'
        && isset($response['symbols'])) {
        foreach ($response['symbols'] as $name => $full_name) {
          $currencies_list[$name] = "$name ($full_name)";
          // Create or update Currency entity.
          $this->setCurrency($name, "$name ($full_name)");
        }
        // Save list for #options.
        $this->configSettings
          ->set('full_currencies_list', $currencies_list)
          ->save();
        $this->logger->get('Fixer')->info('Currencies list has been updated');

      }
      // Response error.
      else {
        $this->logger->get('Fixer')->error('Error. No updates for Currencies list.');
      }

    }
    // Take from local config.
    else {
      $currencies_list = $this->configSettings->get('full_currencies_list');
    }

    return $currencies_list;
  }

  /**
   * Create or update `Currency` entity.
   *
   * For pair - $base_currency-$second_currency.
   *
   * @param string $name
   *   Currency name.
   * @param string $full_name
   *   Currency full name.
   *
   * @return \Drupal\fixer_api\Entity\Currency|mixed
   *   Return Currency entity.
   */
  public function setCurrency($name, $full_name) {
    /** @var \Drupal\fixer_api\Entity\Currency $entity */
    $entities = $this->entityTypeManager->getStorage('fixer_currency')->loadByProperties([
      'name' => $name,
    ]);
    $entity = reset($entities);

    // Update existing entity.
    if ($entity) {
      $entity->setName($name);
      $entity->setFullName($full_name);
    }
    // Create new entity.
    else {
      $entity = Currency::create([
        'name' => $name,
        'full_name' => $full_name,
      ]);
    }

    $entity->save();

    return $entity;
  }

  /**
   * Get rate from local settings or from external server.
   *
   * For pair - $base_currency-$second_currency.
   *
   * @param string $base_currency
   *   Base currency.
   * @param string $second_currency
   *   Second currency.
   *
   * @return float
   *   Exchange rate.
   */
  public function getRate($base_currency, $second_currency) {

    $base_currency_refs = $this->entityTypeManager->getStorage('fixer_currency')->loadByProperties([
      'name' => $base_currency,
    ]);
    $base_currency_ref = reset($base_currency_refs);
    $base_currency_id = ($base_currency_ref) ? $base_currency_ref->id() : NULL;
    $second_currency_refs = $this->entityTypeManager->getStorage('fixer_currency')->loadByProperties([
      'name' => $second_currency,
    ]);
    $second_currency_ref = reset($second_currency_refs);
    $second_currency_id = ($second_currency_ref) ? $second_currency_ref->id() : NULL;

    $entities = $this->entityTypeManager->getStorage('fixer_currency_rate')->loadByProperties([
      'base_currency' => $base_currency_id,
      'second_currency' => $second_currency_id,
    ]);
    if ($entities) {
      /** @var \Drupal\fixer_api\Entity\CurrencyRate $entity */
      $entity = reset($entities);

      return $entity->get('rate')->value;
    }
    else {
      return 0;
    }
  }

  /**
   * Update or create rate for pair base-second.
   *
   * @param string $base_currency
   *   Base currency.
   * @param string $second_currency
   *   Second currency.
   * @param float $rate
   *   Rate.
   * @param int $timestamp
   *   Timestamp.
   *
   * @return \Drupal\fixer_api\Entity\CurrencyRate|mixed
   *   Return CurrencyRate entity.
   */
  public function setRate(
    $base_currency,
    $second_currency,
    $rate,
    $timestamp = NULL) {
    $time = $timestamp ?? time();
    $base_currency_refs = $this->entityTypeManager->getStorage('fixer_currency')->loadByProperties([
      'name' => $base_currency,
    ]);
    $base_currency_ref = reset($base_currency_refs);
    $base_currency_id = ($base_currency_ref) ? $base_currency_ref->id() : NULL;
    $second_currency_refs = $this->entityTypeManager->getStorage('fixer_currency')->loadByProperties([
      'name' => $second_currency,
    ]);
    $second_currency_ref = reset($second_currency_refs);
    $second_currency_id = ($second_currency_ref) ? $second_currency_ref->id() : NULL;

    if ($base_currency_ref && $second_currency_ref) {
      /** @var \Drupal\fixer_api\Entity\CurrencyRate $entity */
      $entities = $this->entityTypeManager->getStorage('fixer_currency_rate')->loadByProperties([
        'base_currency' => $base_currency_id,
        'second_currency' => $second_currency_id,
      ]);
      $entity = reset($entities);

      // Create new rate.
      if (empty($entity)) {
        $entity = CurrencyRate::create([
          'base_currency' => $base_currency_id,
          'second_currency' => $second_currency_id,
          'rate' => $rate,
          'timestamp' => $time,
        ]);
      }
      else {
        $entity->setRate($base_currency_id, $second_currency_id, $rate, $time);

      }
    }
    $entity->save();

    return $entity;
  }

  /**
   * Save all rates for $base_currencies_ids from the external server.
   *
   * @param array $base_currencies_ids
   *   List of base_currencies for external server data request.
   */
  public function saveRates(array $base_currencies_ids = ['EUR']) {
    foreach ($base_currencies_ids as $base_currency) {
      $response = $this->getRatesFromServer($base_currency);

      if (isset($response['success'])
        && $response['success'] == 'true'
        && isset($response['rates'])) {
        $time = (isset($response['timestamp']))
          ? $response['timestamp']
          : time();
        $rates = (isset($response['rates'])) ? $response['rates'] : [];
        foreach ($rates as $currency => $rate) {
          $this->setRate($base_currency, $currency, $rate, $time);
        }

        $this->logger->get('Fixer')->info('Rates has been updated for %base.', ['%base' => $base_currency]);
      }
      // Response error.
      else {
        $this->logger->get('Fixer')->error('No updates for %base.', ['%base' => $base_currency]);
      }
    }
  }

  /**
   * Request to the external API.
   *
   * @return array|null
   *   Raw data from the sxternal server.
   */
  public function serverRequest(string $uri, string $query = '') {
    $apiUri = $this->apiUri . $uri;
    $access_key = $this->configSettings->get('fixer_api_code');
    $query = "access_key=$access_key" . $query;
    $result = [];

    try {
      $response = $this->httpClient->get(
        $apiUri,
        [
          'query' => $query,
        ]);
      $result = Json::decode($response->getBody());
    }
    catch (\Exception $e) {
      watchdog_exception('fixer', $e, "Error: URL - $access_key?$query");
    }

    return $result;
  }

  /**
   * Get currencies list from the external server.
   *
   * @return array|null
   *   List of the currencies from the external server.
   */
  public function getCurrenciesListFromServer() {
    $apiUri = '/symbols';
    return $this->serverRequest($apiUri);
  }

  /**
   * Get all rates for selected base currency from the server.
   *
   * @param string $base_currency
   *   Base currency.
   *
   * @return string|null
   *   Response with rates.
   */
  public function getRatesFromServer(string $base_currency = 'EUR') {
    $apiUri = '/latest';
    $query = '&base=$base_currency';

    if ($this->configSettings->get('fixer_test_mode')) {
      // EUR as base currency only.
      return $this->serverRequest($apiUri);
    }
    else {
      // This request work for not free accounts only.
      return $this->serverRequest($apiUri, $query);
    }
  }

  /**
   * Converting $base_currency into $second_currency with $rate.
   *
   * @param string $base_currency
   *   Base currency.
   * @param string $second_currency
   *   Second currency.
   * @param float $amount
   *   Amount of the $base_currency.
   * @param int $precision
   *   Convertion precision.
   *
   * @return float
   *   Converted value.
   */
  public function convert($base_currency, $second_currency, $amount, $precision = 6) {
    return round($this->getRate($base_currency, $second_currency) * $amount, $precision);
  }

}
