<?php declare(strict_types = 1);

namespace MailPoet\Automation\Engine\Integration;

use MailPoet\Automation\Engine\Data\Subject;

interface SubjectTransformer {
  public function transform(Subject $data): Subject;

  public function returns(): string;

  public function accepts(): string;
}
