<?php declare(strict_types = 1);

namespace MailPoet\Segments\DynamicSegments\Filters;

use MailPoet\Entities\DynamicSegmentFilterData;
use MailPoet\WooCommerce\Helper;

/**
 * @group woo
 */
class WooCommerceSubscriptionTest extends \MailPoetTest {

  /**
   * The email address defines also the post_status of the subscription.
   */
  private const ACTIVE_EMAILS = [
    'active_subscriber1@example.com',
    'active_subscriber2@example.com',
    'pending-cancel_subscriber1@example.com',
  ];
  private const INACTIVE_EMAILS = [
    'cancelled_subscriber1@example.com',
  ];
  private const SUBSCRIBER_EMAILS = self::ACTIVE_EMAILS + self::INACTIVE_EMAILS;

  /** @var array */
  private $subscriptions = [];
  /** @var array */
  private $products = [];

  /** @var WooCommerceSubscription */
  private $wooCommerceSubscriptionFilter;

  public function _before(): void {
    $wooCommerceHelper = $this->diContainer->get(Helper::class);
    $this->wooCommerceSubscriptionFilter = $this->diContainer->get(WooCommerceSubscription::class);

    if ($wooCommerceHelper->isWooCommerceCustomOrdersTableEnabled()) {
      $this->markTestSkipped('WooCommerce Subscriptions does not work with WooCommerce Custom Orders Table.');
    }

    $productId = $this->createProduct('Premium Newsletter');
    foreach (self::SUBSCRIBER_EMAILS as $email) {
      $userId = $this->tester->createWordPressUser($email, 'subscriber');
      $status = 'wc-' . explode('_', $email)[0];
      $this->createSubscription(
        [
          'post_status' => $status,
        ],
        $userId,
        $productId
      );
    }
  }

  public function _after() {
    parent::_after();
    $this->cleanUp();
  }

  public function testAllSubscribersFoundWithOperatorAny(): void {
    $filterData = $this->getSegmentFilterData(
      DynamicSegmentFilterData::OPERATOR_ANY
    );
    $emails = $this->tester->getSubscriberEmailsMatchingDynamicFilter($filterData, $this->wooCommerceSubscriptionFilter);
    $this->assertEqualsCanonicalizing(self::ACTIVE_EMAILS, $emails);
  }

  public function testAllSubscribersFoundWithOperatorNoneOf(): void {
    $product = $this->createProduct("Another newsletter");
    $notToBeFoundEmail = "not-to-be-found@example.com";
    $subscriberId = $this->tester->createWordPressUser($notToBeFoundEmail, "subscriber");
    $this->assertTrue(!is_wp_error($subscriberId), "User could not be created $notToBeFoundEmail");

    $this->createSubscription(
      [],
      (int)$subscriberId,
      $product
    );
    $filterData = $this->getSegmentFilterData(
      DynamicSegmentFilterData::OPERATOR_NONE,
      [$product]
    );
    $emails = $this->tester->getSubscriberEmailsMatchingDynamicFilter($filterData, $this->wooCommerceSubscriptionFilter);
    expect($emails)->count(3);
    expect(in_array($notToBeFoundEmail, $emails))->false();
    $this->tester->deleteWordPressUser($notToBeFoundEmail);
  }

  public function testAllSubscribersFoundWithOperatorAllOf(): void {
    $this->createProduct("Another newsletter");
    $notToBeFoundEmail = "not-to-be-found@example.com";
    $toBeFoundEmail = "find-me@example.com";
    $this->tester->deleteWordPressUser($toBeFoundEmail);
    $this->tester->deleteWordPressUser($notToBeFoundEmail);
    $notToBeFoundSubscriberId = $this->tester->createWordPressUser($notToBeFoundEmail, "subscriber");
    $toBeFoundSubscriberId = $this->tester->createWordPressUser($toBeFoundEmail, "subscriber");
    $this->assertTrue(!is_wp_error($toBeFoundSubscriberId), "Could not create user $toBeFoundEmail");
    $this->assertTrue(!is_wp_error($notToBeFoundSubscriberId), "Could not create user $notToBeFoundEmail");

    $this->createSubscription(
      [],
      (int)$toBeFoundSubscriberId,
      ...$this->products
    );
    $filterData = $this->getSegmentFilterData(
      DynamicSegmentFilterData::OPERATOR_ALL,
      $this->products
    );
    $emails = $this->tester->getSubscriberEmailsMatchingDynamicFilter($filterData, $this->wooCommerceSubscriptionFilter);
    $this->assertEqualsCanonicalizing([$toBeFoundEmail], $emails);
    $this->tester->deleteWordPressUser($notToBeFoundEmail);
    $this->tester->deleteWordPressUser($toBeFoundEmail);
  }

  private function createProduct(string $name): int {
    $productData = [
      'post_type' => 'product',
      'post_status' => 'publish',
      'post_title' => $name,
    ];
    $productId = wp_insert_post($productData);
    $this->products[] = (int)$productId;
    return (int)$productId;
  }

  private function createSubscription(array $args, int $user, int ...$productIds): int {
    global $wpdb;
    $defaults = [
      'post_status' => 'wc-active',
      'post_type' => 'shop_subscription',
      'post_author' => 1,
    ];

    $args = wp_parse_args($args, $defaults);
    $orderId = wp_insert_post($args);
    $orderId = (int)$orderId;
    update_post_meta( $orderId, '_customer_user', $user );

    foreach ($productIds as $productId) {
      $sql = "insert into " . $wpdb->prefix . "woocommerce_order_items (order_id,order_item_type) values (" . $orderId . ", 'line_item')";
      $wpdb->query($sql);
      $sql = 'select LAST_INSERT_ID() as id';
      $lineItemId = $wpdb->get_col($sql)[0];
      $sql = "insert into " . $wpdb->prefix . "woocommerce_order_itemmeta (order_item_id, meta_key, meta_value) values (" . $lineItemId . ", '_product_id', '" . $productId . "')";
      $wpdb->query($sql);
    }

    $this->subscriptions[] = $orderId;
    return $orderId;
  }

  private function getSegmentFilterData(string $operator, array $productIds = null): DynamicSegmentFilterData {
    $filterData = [
      'operator' => $operator,
      'product_ids' => $productIds ?: $this->products,
    ];

    return new DynamicSegmentFilterData(
      DynamicSegmentFilterData::TYPE_WOOCOMMERCE_SUBSCRIPTION,
      WooCommerceSubscription::ACTION_HAS_ACTIVE,
      $filterData
    );
  }

  public function cleanUp(): void {
    foreach ($this->products as $productId) {
      wp_delete_post($productId);
    }
    foreach ($this->subscriptions as $productId) {
      wp_delete_post($productId);
    }
  }
}
