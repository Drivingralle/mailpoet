<?php declare(strict_types = 1);

namespace MailPoet\Migrations;

use MailPoet\Entities\LogEntity;
use MailPoet\Migrator\Migration;

class Migration_20230221_200520 extends Migration {
  public function run(): void {
    $this->addRawMessagesToLogs();
    $this->addContextToLogs();
  }

  private function addRawMessagesToLogs() {
    $logsTable = $this->getTableName(LogEntity::class);
    $columnName = 'raw_message';

    if ($this->columnExists($logsTable, $columnName)) {
      return;
    }

    $this->connection->executeStatement("
      ALTER TABLE {$logsTable}
      ADD {$columnName} longtext DEFAULT NULL
    ");
  }

  private function addContextToLogs() {
    $logsTable = $this->getTableName(LogEntity::class);
    $columnName = 'context';

    if ($this->columnExists($logsTable, $columnName)) {
      return;
    }

    $this->connection->executeStatement("
      ALTER TABLE {$logsTable}
      ADD {$columnName} longtext DEFAULT NULL
    ");
  }
}
