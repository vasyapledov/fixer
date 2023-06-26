<?php

namespace Drupal\fixer_api\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the advertiser entity.
 *
 * @ingroup advertiser
 *
 * @ContentEntityType(
 *   id = "fixer_currency",
 *   label = @Translation("Currency"),
 *   base_table = "fixer_currency",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "name" = "name",
 *     "full_name" = "full_name",
 *   },
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 * )
 */
class Currency extends ContentEntityBase implements CurrencyInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Currency name.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['full_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Full currency name.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('Currency');
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFullName() {
    return $this->get('full_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    return $this->set('name', $name);
  }

  /**
   * {@inheritdoc}
   */
  public function setFullName($full_name) {
    return $this->set('full_name', $full_name);
  }

}
