<?php declare(strict_types = 1);

namespace MailPoet\Segments\DynamicSegments\Filters;

use MailPoet\Entities\DynamicSegmentFilterData;
use MailPoet\Entities\DynamicSegmentFilterEntity;
use MailPoet\Entities\SubscriberEntity;
use MailPoet\Util\Security;
use MailPoetVendor\Doctrine\DBAL\Connection;
use MailPoetVendor\Doctrine\DBAL\Query\QueryBuilder;
use MailPoetVendor\Doctrine\ORM\EntityManager;

class WooCommerceProduct implements Filter {
  const ACTION_PRODUCT = 'purchasedProduct';

  /** @var EntityManager */
  private $entityManager;

  /** @var WooFilterHelper */
  private $wooFilterHelper;

  /** @var FilterHelper */
  private $filterHelper;

  public function __construct(
    EntityManager $entityManager,
    FilterHelper $filterHelper,
    WooFilterHelper $wooFilterHelper
  ) {
    $this->entityManager = $entityManager;
    $this->wooFilterHelper = $wooFilterHelper;
    $this->filterHelper = $filterHelper;
  }

  public function apply(QueryBuilder $queryBuilder, DynamicSegmentFilterEntity $filter): QueryBuilder {
    $filterData = $filter->getFilterData();
    $operator = $filterData->getOperator();
    $productIds = $filterData->getParam('product_ids');
    $subscribersTable = $this->entityManager->getClassMetadata(SubscriberEntity::class)->getTableName();
    $parameterSuffix = $filter->getId() ?? Security::generateRandomString();

    if ($operator === DynamicSegmentFilterData::OPERATOR_ANY) {
      $orderStatsAlias = $this->wooFilterHelper->applyOrderStatusFilter($queryBuilder);
      $this->applyProductJoin($queryBuilder, $orderStatsAlias);
      $queryBuilder->andWhere("product.product_id IN (:products_{$parameterSuffix})");
    } elseif ($operator === DynamicSegmentFilterData::OPERATOR_ALL) {
      $orderStatsAlias = $this->wooFilterHelper->applyOrderStatusFilter($queryBuilder);
      $this->applyProductJoin($queryBuilder, $orderStatsAlias);
      $queryBuilder->andWhere("product.product_id IN (:products_{$parameterSuffix})")
        ->groupBy("{$subscribersTable}.id, $orderStatsAlias.order_id")
        ->having("COUNT($orderStatsAlias.order_id) = :count" . $parameterSuffix)
        ->setParameter('count' . $parameterSuffix, count($productIds));

    } elseif ($operator === DynamicSegmentFilterData::OPERATOR_NONE) {
      // subQuery with subscriber ids that bought products
      $subQuery = $this->createQueryBuilder($subscribersTable);
      $subQuery->select("DISTINCT $subscribersTable.id");
      $orderStatsAlias = $this->wooFilterHelper->applyOrderStatusFilter($subQuery);
      $subQuery = $this->applyProductJoin($subQuery, $orderStatsAlias);
      $subQuery->andWhere("product.product_id IN (:products_{$parameterSuffix})");
      // application subQuery for negation
      $queryBuilder->where("{$subscribersTable}.id NOT IN ({$this->filterHelper->getInterpolatedSQL($subQuery)})");
    }
    return $queryBuilder
      ->setParameter("products_{$parameterSuffix}", $productIds, Connection::PARAM_STR_ARRAY);
  }

  private function applyProductJoin(QueryBuilder $queryBuilder, string $orderStatsAlias): QueryBuilder {
    global $wpdb;
    return $queryBuilder->innerJoin(
      $orderStatsAlias,
      $wpdb->prefix . 'wc_order_product_lookup',
      'product',
      "$orderStatsAlias.order_id = product.order_id"
    );
  }

  private function createQueryBuilder(string $table): QueryBuilder {
    return $this->entityManager->getConnection()
      ->createQueryBuilder()
      ->from($table);
  }
}
