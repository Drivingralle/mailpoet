<?php declare(strict_types = 1);

namespace MailPoet\TestsSupport;

use Codeception\Event\SuiteEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Extension;
use MailPoet\DI\ContainerWrapper;
use MailPoetVendor\Doctrine\DBAL\Connection;
use MailPoetVendor\Doctrine\ORM\EntityManager;

class IntegrationCleanupExtension extends Extension {

  /**
   * @var String[]
   */
  private $tables;

  /**
   * @var Connection
   */
  private $connection;

  /**
   * @var EntityManager
   */
  private $entityManager;

  public static $events = [
    Events::TEST_BEFORE => 'beforeTest',
    Events::SUITE_BEFORE => 'beforeSuite',
  ];
  /** @var string */
  private $deleteStatement;

  public function beforeSuite(SuiteEvent $event) {
    $this->connection = ContainerWrapper::getInstance()->get(Connection::class);
    $this->entityManager = ContainerWrapper::getInstance()->get(EntityManager::class);
    $entitiesMeta = $this->entityManager->getMetadataFactory()->getAllMetadata();
    $this->tables = array_map(function($entityMeta) {
      return $entityMeta->getTableName();
    }, $entitiesMeta);
    $this->deleteStatement = 'SET FOREIGN_KEY_CHECKS=0;';
    foreach ($this->tables as $table) {
      $this->deleteStatement .= "DELETE FROM $table;";
    }
    $this->deleteStatement .= 'SET FOREIGN_KEY_CHECKS=1';
  }

  public function beforeTest(TestEvent $event) {
    $this->connection->executeStatement($this->deleteStatement);
    sleep(1);
  }
}
