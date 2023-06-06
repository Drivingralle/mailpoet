<?php declare(strict_types = 1);

namespace MailPoet\Automation\Integrations\WooCommerce;

use Automattic\WooCommerce\Utilities\OrderUtil;
use stdClass;
use WC_Order;
use WC_Order_Refund;

class WooCommerce {
  public function isWooCommerceActive(): bool {
    return class_exists('WooCommerce');
  }

  public function wcGetIsPaidStatuses(): array {
    return wc_get_is_paid_statuses();
  }

  public function isWooCommerceCustomOrdersTableEnabled(): bool {
    return $this->isWooCommerceActive()
      && method_exists(OrderUtil::class, 'custom_orders_table_usage_is_enabled')
      && OrderUtil::custom_orders_table_usage_is_enabled();
  }

  /**
   * @param mixed $theOrder
   * @return bool|WC_Order|WC_Order_Refund
   */
  public function wcGetOrder($theOrder = false) {
    return wc_get_order($theOrder);
  }

  /** @return WC_Order[]|stdClass */
  public function wcGetOrders(array $args = []) {
    return wc_get_orders($args);
  }

  public function wcGetOrderStatuses(): array {
    return wc_get_order_statuses();
  }
}
