<?php

namespace Drupal\fixer_api\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines a common interface for Currency entity.
 */
interface CurrencyInterface extends ContentEntityInterface {

  /**
   * Get name.
   *
   * @return string
   *   Name.
   */
  public function getName();

  /**
   * Get full name.
   *
   * @return string
   *   Full name.
   */
  public function getFullName();

  /**
   * Set name.
   *
   * @return \Drupal\fixer_api\Entity\Currency|mixed
   *   Return Currency entity.
   */
  public function setName($name);

  /**
   * Set full name.
   *
   * @return \Drupal\fixer_api\Entity\Currency|mixed
   *   Return Currency entity.
   */
  public function setFullName($full_name);

}
