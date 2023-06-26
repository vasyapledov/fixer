<?php

namespace Drupal\fixer_api\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\fixer_api\FixerHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Fixer settings.
 */
class FixerSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'fixer_api.settings';

  /**
   * A FixerHandler instance.
   *
   * @var \Drupal\fixer_api\FixerHandler
   */
  protected $fixerHandler;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\fixer_api\FixerHandler $fixer_handler
   *   The factory for configuration objects.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    FixerHandler $fixer_handler,
    RendererInterface $renderer,
    Connection $connection) {
    parent::__construct($config_factory);
    $this->fixerHandler = $fixer_handler;
    $this->renderer = $renderer;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('fixer_api.handler'),
      $container->get('renderer'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'fixer_api_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'fixer_api.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['fixer_test_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('TEST mode'),
      '#description' => $this->t('Functionality for free account ONLY!'),
      '#default_value' => $config->get('fixer_test_mode') ?? 1,
    ];

    $form['fixer_api_link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fixer API link'),
      '#default_value' => $config->get('fixer_api_link'),
    ];

    $form['fixer_api_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fixer API code'),
      '#default_value' => $config->get('fixer_api_code'),
      '#required' => TRUE,
    ];

    $selected_currencies = $this->selectedIntercect(
      $this->fixerHandler->getCurrenciesList(),
      $config->get('fixer_currencies_list') ?? ['EUR' => 'EUR']);
    $form['fixer_api_list_selection'] = [
      '#type' => 'item',
      '#title' => $this->t('Selected currencies'),
      '#markup' => $selected_currencies,
    ];

    $form['fixer_currencies_list'] = [
      '#type' => 'select',
      '#title' => $this->t("Fixer currencies list"),
      '#description' => $this->t("List of the currencies."),
      '#default_value' => $config->get('fixer_currencies_list') ?? ['EUR' => 'EUR'],
      '#options' => $this->fixerHandler->getCurrenciesList(),
      '#wrapper_attributes' => [
        'class' => ['fixer-currencies-list-outside'],
      ],
      '#required' => TRUE,
      '#size' => 10,
      '#multiple' => TRUE,
      '#ajax' => [
        'callback' => '::selectedCurrenciesAjax',
        'disable-refocus' => TRUE,
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
        ],
      ],
    ];

    $form['exchange_test'] = [
      '#type' => 'link',
      '#title' => $this->t('Exchange test'),
      '#url' => Url::fromUserInput('/admin/config/fixer/exchange-test'),
      '#attributes' => [
        'class' => [
          'button',
        ],
        'target' => '_blank',
      ],
    ];

    $form['fixer_local_rates'] = [
      '#type' => 'link',
      '#title' => $this->t('Current rate`s list'),
      '#url' => Url::fromUserInput('/fixer-currency-rates'),
      '#attributes' => [
        'class' => [
          'button',
        ],
        'target' => '_blank',
      ],
    ];

    $form['cron_settings'] = [
      '#type' => 'link',
      '#title' => $this->t('Cron settings'),
      '#url' => Url::fromUserInput('/admin/config/system/cron/jobs/manage/fixer_api_cron'),
      '#attributes' => [
        'class' => [
          'button',
        ],
        'target' => '_blank',
      ],
    ];

    $form['force_update_list'] = [
      '#type' => 'button',
      '#value' => $this->t('Update currencies list from the server'),
      "#name" => 'force_update_list_push',
      '#ajax' => [
        'callback' => '::updateCurrenciesListAjax',
      ],
    ];

    $form['clean_local_data'] = [
      '#type' => 'button',
      '#value' => $this->t('DELETE all local data!'),
      "#name" => 'clean_local_data_push',
      '#ajax' => [
        'callback' => '::cleanLocalDataAjax',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Return list of selected currencies.
   *
   * Using for `fixer_currencies_list` form element.
   *
   * @param array|null $currencies_list
   *   Full list of currencies.
   * @param array|null $selected_currencies_list
   *   Selected currencies.
   *
   * @return string|null
   *   String with selected currencies.
   */
  protected function selectedIntercect($currencies_list, $selected_currencies_list) {
    $selected_currencies_array = array_intersect_key($currencies_list, $selected_currencies_list);
    $selected_currencies = '<div class="fixer-currency-list-selection">';
    foreach ($selected_currencies_array as $item) {
      $selected_currencies .= "&nbsp;&nbsp;-&nbsp;&nbsp;$item<br>";
    }
    $selected_currencies .= '</div>';

    return $selected_currencies;
  }

  /**
   * Ajax callback.
   *
   * Update list of currencies for 'fixer_currencies_list` form element.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response.
   */
  public function updateCurrenciesListAjax(array &$form, FormStateInterface $form_state) {
    $full_list = $this->fixerHandler->getCurrenciesList(TRUE);
    $form['fixer_currencies_list']['#options'] = $full_list;
    $selected_currencies = $this->selectedIntercect(
      $full_list,
      $form_state->getValue('fixer_currencies_list'));
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('.fixer-currency-list-selection', $selected_currencies));
    $response->addCommand(new ReplaceCommand('.fixer-currencies-list-outside', $form['fixer_currencies_list']));

    return $response;
  }

  /**
   * Ajax callback.
   *
   * Return list of selected currencies
   * for `fixer_currencies_list` form element.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response.
   */
  public function selectedCurrenciesAjax(array &$form, FormStateInterface $form_state) {
    $selected_currencies = $this->selectedIntercect(
      $this->fixerHandler->getCurrenciesList(),
      $form_state->getValue('fixer_currencies_list'));
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('.fixer-currency-list-selection', $selected_currencies));

    return $response;
  }

  /**
   * Ajax callback.
   *
   * Delete all local data.
   */
  public function cleanLocalDataAjax(array &$form, FormStateInterface $form_state) {
    $this->connection
      ->delete('fixer_currency_rate')
      ->condition('id', 0, '>')
      ->execute();
    $this->connection
      ->delete('fixer_currency')
      ->condition('id', 0, '>')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::SETTINGS)
      ->set('fixer_api_link', trim($form_state->getValue('fixer_api_link'), '/'))
      ->set('fixer_test_mode', $form_state->getValue('fixer_test_mode'))
      ->set('fixer_api_code', $form_state->getValue('fixer_api_code'))
      ->set('fixer_currencies_list', $form_state->getValue('fixer_currencies_list'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
