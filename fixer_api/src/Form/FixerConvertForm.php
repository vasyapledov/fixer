<?php

namespace Drupal\fixer_api\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\fixer_api\FixerHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fixer currencies convert - test form.
 */
class FixerConvertForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'fixer_api.convert';

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
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\fixer_api\FixerHandler $fixer_handler
   *   The factory for configuration objects.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    FixerHandler $fixer_handler,
    RendererInterface $renderer) {
    parent::__construct($config_factory);
    $this->fixerHandler = $fixer_handler;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('fixer_api.handler'),
      $container->get('renderer')
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

    $form['base_currency'] = [
      '#type' => 'select',
      '#title' => $this->t("Base currency"),
      '#default_value' => ['EUR' => 'EUR'],
      '#options' => array_intersect_key($this->fixerHandler->getCurrenciesList(),
          $config->get('fixer_currencies_list') ?? ['EUR' => 'EUR']),
      '#size' => 10,
      '#ajax' => [
        'callback' => '::exchangeAjax',
        'disable-refocus' => TRUE,
        'event' => 'change',
        'progress' => [
          'type' => 'none',
        ],
      ],
    ];

    $form['second_currency'] = [
      '#type' => 'select',
      '#title' => $this->t("Second currency"),
      '#description' => $this->t("List of the currencies."),
      '#default_value' => ['USD' => 'USD'],
      '#options' => $this->fixerHandler->getCurrenciesList(),
      '#size' => 10,
      '#ajax' => [
        'callback' => '::exchangeAjax',
        'disable-refocus' => TRUE,
        'event' => 'change',
        'progress' => [
          'type' => 'none',
        ],
      ],
    ];

    $form['amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Amount"),
      '#default_value' => 1,
      '#ajax' => [
        'callback' => '::exchangeAjax',
        'disable-refocus' => TRUE,
        'event' => 'change',
        'progress' => [
          'type' => 'none',
        ],
      ],
    ];

    $form['exchange_result'] = [
      '#type' => 'item',
      '#title' => $this->t('Result'),
      '#markup' => '<div class="fixer-exchange-result">' .
      $this->fixerHandler->convert(
        $form['base_currency']['#default_value'],
        $form['second_currency']['#default_value'],
        $form['amount']['#default_value']) . '</div>',

    ];

    $form['exchange_test'] = [
      '#type' => 'button',
      '#value' => $this->t('Exchange'),
      "#name" => 'exhcnage_test__push',
      '#ajax' => [
        'callback' => '::exchangeAjax',
      ],
    ];

    $form = parent::buildForm($form, $form_state);
    unset($form['actions']['submit']);

    return $form;
  }

  /**
   * Ajax callback.
   *
   * Return result of exchange.
   */
  public function exchangeAjax(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $result = '<div class="fixer-exchange-result">' .
      $this->fixerHandler->convert(
      $input['base_currency'],
      $input['second_currency'],
      $input['amount']) . '</div>';
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('.fixer-exchange-result', $result));

    return $response;
  }

}
