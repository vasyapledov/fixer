<?php

namespace Drupal\fixer_api\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines a common interface for CurrenсyRate entity.
 */
interface CurrencyRateInterface extends ContentEntityInterface {

  /**
   * Update rate for pair base-second.
   *
   * @param int $base_currency_id
   *   Base currency ID.
   * @param int $second_currency_id
   *   Second currency ID.
   * @param float $rate
   *   Second currency ID.
   * @param int $timestamp
   *   Timestamp.
   *
   * @return \Drupal\fixer_api\Entity\CurrencyRate
   *   Return CurrencyRate entity.
   */
  public function setRate($base_currency_id, $second_currency_id, $rate, $timestamp = NULL);

  /**
   * Get rate for pair base-second.
   *
   * @return float
   *   Rate.
   */
  public function getRate();

}
