<?php declare(strict_types = 1);

use Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore;
use Codeception\Actor;
use Codeception\Scenario;
use MailPoet\DI\ContainerWrapper;
use MailPoet\Entities\DynamicSegmentFilterData;
use MailPoet\Entities\DynamicSegmentFilterEntity;
use MailPoet\Entities\SegmentEntity;
use MailPoet\Entities\SubscriberEntity;
use MailPoet\Segments\DynamicSegments\Filters\Filter;
use MailPoet\Util\Security;
use MailPoet\WooCommerce\Helper;
use MailPoetVendor\Doctrine\DBAL\Driver\Statement;
use MailPoetVendor\Doctrine\DBAL\Query\QueryBuilder;
use MailPoetVendor\Doctrine\ORM\EntityManager;

require_once(ABSPATH . 'wp-admin/includes/user.php');
require_once(ABSPATH . 'wp-admin/includes/ms.php');

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
 *
 * @SuppressWarnings(PHPMD)
*/
// phpcs:ignore PSR1.Classes.ClassDeclaration
class IntegrationTester extends Actor {

  /** @var EntityManager */
  private $entityManager;

  private $wooOrderIds = [];

  private $createdUserEmails = [];

  use _generated\IntegrationTesterActions;

  public function __construct(
    Scenario $scenario
  ) {
    parent::__construct($scenario);
    $this->entityManager = ContainerWrapper::getInstance()->get(EntityManager::class);
  }

  public function createWordPressUser(string $email, string $role) {
    $userId = wp_insert_user([
      'user_login' => explode('@', $email)[0],
      'user_email' => $email,
      'role' => $role,
      'user_pass' => '12123154',
    ]);
    if ($userId instanceof \WP_Error) {
      throw new \MailPoet\RuntimeException('Could not create WordPress user: ' . $userId->get_error_message());
    }
    $this->createdUserEmails[] = $email;
    return $userId;
  }

  public function deleteWordPressUser(string $email) {
    $user = get_user_by('email', $email);
    if (!$user) {
      return;
    }
    if (is_multisite()) {
      wpmu_delete_user($user->ID);
    } else {
      wp_delete_user($user->ID);
    }
  }

  public function deleteCreatedWordpressUsers(): void {
    foreach ($this->createdUserEmails as $email) {
      $this->deleteWordPressUser($email);
    }
  }

  public function createWooCommerceOrder(array $data = []): \WC_Order {
    $helper = ContainerWrapper::getInstance()->get(Helper::class);
    $order = $helper->wcCreateOrder([]);

    if (isset($data['date_created'])) {
      $order->set_date_created($data['date_created']);
    }

    if (isset($data['billing_email'])) {
      $order->set_billing_email($data['billing_email']);
    }

    $order->save();

    $this->wooOrderIds[] = $order->get_id();

    return $order;
  }

  public function updateWooOrderStats(int $orderId): void {
    if (!class_exists('Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore')) {
      return;
    }
    DataStore::sync_order($orderId);
  }

  public function deleteTestWooOrder(int $wooOrderId) {
    $helper = ContainerWrapper::getInstance()->get(Helper::class);
    $order = $helper->wcGetOrder($wooOrderId);
    if ($order instanceof \WC_Order) {
      $order->delete(true);
    }
  }

  public function deleteTestWooOrders() {
    $helper = ContainerWrapper::getInstance()->get(Helper::class);
    foreach ($this->wooOrderIds as $wooOrderId) {
      $order = $helper->wcGetOrder($wooOrderId);
      if ($order instanceof \WC_Order) {
        $order->delete(true);
      }
    }
    $this->wooOrderIds = [];
  }

  public function uniqueId($length = 10): string {
    return Security::generateRandomString($length);
  }

  /**
   * Compares two DateTimeInterface objects by comparing timestamp values.
   * $delta parameter specifies tolerated difference
   */
  public function assertEqualDateTimes(?DateTimeInterface $date1, ?DateTimeInterface $date2, int $delta = 0) {
    if (!$date1 instanceof DateTimeInterface) {
      throw new \Exception('$date1 is not DateTimeInterface');
    }
    if (!$date2 instanceof DateTimeInterface) {
      throw new \Exception('$date2 is not DateTimeInterface');
    }
    expect($date1->getTimestamp())->equals($date2->getTimestamp(), $delta);
  }

  public function getSubscriberEmailsMatchingDynamicFilter(DynamicSegmentFilterData $data, Filter $filter): array {
    $segment = new SegmentEntity('temporary segment', SegmentEntity::TYPE_DYNAMIC, 'description');
    $this->entityManager->persist($segment);
    $filterEntity = new DynamicSegmentFilterEntity($segment, $data);
    $this->entityManager->persist($filterEntity);
    $segment->addDynamicFilter($filterEntity);

    $queryBuilder = $filter->apply($this->getSubscribersQueryBuilder(), $filterEntity);
    $statement = $queryBuilder->execute();
    $results = $statement instanceof Statement ? $statement->fetchAllAssociative() : [];
    $emails = array_map(function($row) {
      $subscriber = $this->entityManager->find(SubscriberEntity::class, $row['inner_subscriber_id']);
      if (!$subscriber instanceof SubscriberEntity) {
        throw new \Exception('this is for PhpStan');
      }
      return $subscriber->getEmail();
    }, $results);

    return $emails;
  }

  public function getSubscribersQueryBuilder(): QueryBuilder {
    $subscribersTable = $this->entityManager->getClassMetadata(SubscriberEntity::class)->getTableName();
    return $this->entityManager
      ->getConnection()
      ->createQueryBuilder()
      ->select("DISTINCT $subscribersTable.id as inner_subscriber_id")
      ->from($subscribersTable);
  }

  public function deleteTestData(): void {
    $this->deleteTestWooOrders();
    $this->deleteCreatedWordpressUsers();
  }
}
