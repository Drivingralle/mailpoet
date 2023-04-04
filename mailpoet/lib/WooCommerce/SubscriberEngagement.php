<?php declare(strict_types = 1);

namespace MailPoet\WooCommerce;

use MailPoet\Entities\SubscriberEntity;
use MailPoet\Subscribers\SubscribersRepository;
use WC_Order;

class SubscriberEngagement {

  /** @var SubscribersRepository */
  private $subscribersRepository;

  public function __construct(
    SubscribersRepository $subscribersRepository
  ) {
    $this->subscribersRepository = $subscribersRepository;
  }

  public function updateSubscriberEngagement($order): void {
    if (!$order instanceof WC_Order) {
      return;
    }

    $subscriber = $this->subscribersRepository->findOneBy(['email' => $order->get_billing_email()]);
    if (!$subscriber instanceof SubscriberEntity) {
      return;
    }

    $this->subscribersRepository->maybeUpdateLastEngagement($subscriber);
  }
}
