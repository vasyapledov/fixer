<?php

namespace Drupal\fixer_api\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the Currency rate entity.
 *
 * @ContentEntityType(
 *   id = "fixer_currency_rate",
 *   label = @Translation("Currency rate"),
 *   base_table = "fixer_currency_rate",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "timestamp" = "timestamp",
 *     "base_currency" = "base_currency",
 *     "second_currency" = "second_currency",
 *   },
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 * )
 */
class CurrencyRate extends ContentEntityBase implements CurrencyRateInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['base_currency'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Base currency'))
      ->setDescription(t('Base currency for exchange operation.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setSetting('target_type', 'fixer_currency')
      ->setRequired(TRUE);

    $fields['second_currency'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Second currency'))
      ->setDescription(t('Second currency for exchange operation.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setSetting('target_type', 'fixer_currency')
      ->setRequired(TRUE);

    $fields['rate'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Exchange rate'))
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['timestamp'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Timestamp'))
      ->setDescription(t('Timestamp from last update.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return t('Currency rate');
  }

  /**
   * {@inheritdoc}
   */
  public function setRate($base_currency_id, $second_currency_id, $rate, $timestamp = NULL) {
    $time = $timestamp ?? time();
    // Update $this entity.
    $this->set('base_currency', $base_currency_id);
    $this->set('second_currency', $second_currency_id);
    $this->set('rate', $rate);
    $this->set('timestamp', $time);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRate() {
    return $this->get('rate')->value;
  }

}
