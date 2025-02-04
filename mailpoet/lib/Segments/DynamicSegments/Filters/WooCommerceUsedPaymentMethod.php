<?php declare(strict_types = 1);

namespace MailPoet\Segments\DynamicSegments\Filters;

use MailPoet\Entities\DynamicSegmentFilterData;
use MailPoet\Entities\DynamicSegmentFilterEntity;
use MailPoet\Segments\DynamicSegments\Exceptions\InvalidFilterException;
use MailPoet\WooCommerce\Helper;
use MailPoetVendor\Carbon\Carbon;
use MailPoetVendor\Doctrine\DBAL\Connection;
use MailPoetVendor\Doctrine\DBAL\Query\QueryBuilder;

class WooCommerceUsedPaymentMethod implements Filter {
  const ACTION = 'usedPaymentMethod';

  const VALID_OPERATORS = [
    DynamicSegmentFilterData::OPERATOR_NONE,
    DynamicSegmentFilterData::OPERATOR_ANY,
    DynamicSegmentFilterData::OPERATOR_ALL,
  ];

  /** @var WooFilterHelper */
  private $wooFilterHelper;

  /** @var Helper */
  private $wooHelper;

  /** @var FilterHelper */
  private $filterHelper;

  public function __construct(
    FilterHelper $filterHelper,
    WooFilterHelper $wooFilterHelper,
    Helper $wooHelper
  ) {
    $this->wooFilterHelper = $wooFilterHelper;
    $this->wooHelper = $wooHelper;
    $this->filterHelper = $filterHelper;
  }

  public function apply(QueryBuilder $queryBuilder, DynamicSegmentFilterEntity $filter): QueryBuilder {
    $filterData = $filter->getFilterData();
    $operator = $filterData->getParam('operator');
    $paymentMethods = $filterData->getParam('payment_methods');
    $days = $filterData->getParam('used_payment_method_days');

    if (!is_string($operator) || !in_array($operator, self::VALID_OPERATORS, true)) {
      throw new InvalidFilterException('Invalid operator', InvalidFilterException::MISSING_OPERATOR);
    }

    if (!is_array($paymentMethods) || count($paymentMethods) < 1) {
      throw new InvalidFilterException('Missing payment methods', InvalidFilterException::MISSING_VALUE);
    }

    if (!is_int($days) || $days < 1) {
      throw new InvalidFilterException('Missing days', InvalidFilterException::MISSING_VALUE);
    }

    $includedStatuses = array_keys($this->wooHelper->getOrderStatuses());
    $failedKey = array_search('wc-failed', $includedStatuses, true);
    if ($failedKey !== false) {
      unset($includedStatuses[$failedKey]);
    }
    $date = Carbon::now()->subDays($days);

    switch ($operator) {
      case DynamicSegmentFilterData::OPERATOR_ANY:
        $this->applyForAnyOperator($queryBuilder, $includedStatuses, $paymentMethods, $date);
        break;
      case DynamicSegmentFilterData::OPERATOR_ALL:
        $this->applyForAllOperator($queryBuilder, $includedStatuses, $paymentMethods, $date);
        break;
      case DynamicSegmentFilterData::OPERATOR_NONE:
        $subQuery = $this->filterHelper->getNewSubscribersQueryBuilder();
        $this->applyForAnyOperator($subQuery, $includedStatuses, $paymentMethods, $date);
        $subscribersTable = $this->filterHelper->getSubscribersTable();
        $queryBuilder->andWhere($queryBuilder->expr()->notIn("$subscribersTable.id", $this->filterHelper->getInterpolatedSQL($subQuery)));
        break;
    }

    return $queryBuilder;
  }

  private function applyForAnyOperator(QueryBuilder $queryBuilder, array $includedStatuses, array $paymentMethods, Carbon $date): void {
    if ($this->wooHelper->isWooCommerceCustomOrdersTableEnabled()) {
      $this->applyCustomOrderTableJoin($queryBuilder, $includedStatuses, $paymentMethods, $date);
    } else {
      $this->applyPostmetaOrderJoin($queryBuilder, $includedStatuses, $paymentMethods, $date);
    }
  }

  private function applyForAllOperator(QueryBuilder $queryBuilder, array $includedStatuses, array $paymentMethods, Carbon $date): void {
    if ($this->wooHelper->isWooCommerceCustomOrdersTableEnabled()) {
      $ordersAlias = $this->applyCustomOrderTableJoin($queryBuilder, $includedStatuses, $paymentMethods, $date);
      $queryBuilder->groupBy('inner_subscriber_id')
        ->having("COUNT(DISTINCT $ordersAlias.payment_method) = " . count($paymentMethods));
    } else {
      $postmetaAlias = $this->applyPostmetaOrderJoin($queryBuilder, $includedStatuses, $paymentMethods, $date);
      $queryBuilder->groupBy('inner_subscriber_id')->having("COUNT(DISTINCT $postmetaAlias.meta_value) = " . count($paymentMethods));
    }
  }

  private function applyPostmetaOrderJoin(QueryBuilder $queryBuilder, array $includedStatuses, array $paymentMethods, Carbon $date, string $postmetaAlias = 'postmeta'): string {
    $dateParam = $this->filterHelper->getUniqueParameterName('date');
    $paymentMethodParam = $this->filterHelper->getUniqueParameterName('paymentMethod');
    $paymentMethodMetaKeyParam = $this->filterHelper->getUniqueParameterName('paymentMethod');

    $postMetaTable = $this->filterHelper->getPrefixedTable('postmeta');
    $orderStatsAlias = $this->wooFilterHelper->applyOrderStatusFilter($queryBuilder, $includedStatuses);
    $queryBuilder
      ->innerJoin($orderStatsAlias, $postMetaTable, $postmetaAlias, "$orderStatsAlias.order_id = $postmetaAlias.post_id")
      ->andWhere("$orderStatsAlias.date_created >= :$dateParam")
      ->andWhere("postmeta.meta_key = :$paymentMethodMetaKeyParam")
      ->andWhere("postmeta.meta_value IN (:$paymentMethodParam)")
      ->setParameter($paymentMethodMetaKeyParam, '_payment_method')
      ->setParameter($dateParam, $date->toDateTimeString())
      ->setParameter($paymentMethodParam, $paymentMethods, Connection::PARAM_STR_ARRAY);
    return $postmetaAlias;
  }

  private function applyCustomOrderTableJoin(QueryBuilder $queryBuilder, array $includedStatuses, array $paymentMethods, Carbon $date, string $ordersAlias = 'orders'): string {
    $dateParam = $this->filterHelper->getUniqueParameterName('date');
    $paymentMethodParam = $this->filterHelper->getUniqueParameterName('paymentMethod');
    $ordersTable = $this->wooHelper->getOrdersTableName();
    $orderStatsAlias = $this->wooFilterHelper->applyOrderStatusFilter($queryBuilder, $includedStatuses);
    $queryBuilder
      ->innerJoin($orderStatsAlias, $ordersTable, 'orders', "$orderStatsAlias.order_id = orders.id")
      ->andWhere("$orderStatsAlias.date_created >= :$dateParam")
      ->andWhere("$ordersAlias.payment_method IN (:$paymentMethodParam)")
      ->setParameter($dateParam, $date->toDateTimeString())
      ->setParameter($paymentMethodParam, $paymentMethods, Connection::PARAM_STR_ARRAY);
    return $ordersAlias;
  }
}
