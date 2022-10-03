<?php declare(strict_types = 1);

namespace MailPoet\Automation\Engine\Validation;

use MailPoet\Automation\Engine\Data\Workflow;
use MailPoet\Automation\Engine\Validation\WorkflowGraph\WorkflowWalker;
use MailPoet\Automation\Engine\Validation\WorkflowRules\ConsistentStepMapRule;
use MailPoet\Automation\Engine\Validation\WorkflowRules\NoCycleRule;
use MailPoet\Automation\Engine\Validation\WorkflowRules\NoDuplicateEdgesRule;
use MailPoet\Automation\Engine\Validation\WorkflowRules\NoJoinRule;
use MailPoet\Automation\Engine\Validation\WorkflowRules\NoSplitRule;
use MailPoet\Automation\Engine\Validation\WorkflowRules\NoUnreachableStepsRule;
use MailPoet\Automation\Engine\Validation\WorkflowRules\TriggersUnderRootRule;
use MailPoet\Automation\Engine\Validation\WorkflowRules\UnknownStepRule;
use MailPoet\Automation\Engine\Validation\WorkflowRules\ValidStepArgsRule;

class WorkflowValidator {
  /** @var WorkflowWalker */
  private $workflowWalker;

  /** @var ValidStepArgsRule */
  private $validStepArgsRule;

  /** @var UnknownStepRule */
  private $unknownStepRule;

  public function __construct(
    UnknownStepRule $unknownStepRule,
    ValidStepArgsRule $validStepArgsRule,
    WorkflowWalker $workflowWalker
  ) {
    $this->unknownStepRule = $unknownStepRule;
    $this->validStepArgsRule = $validStepArgsRule;
    $this->workflowWalker = $workflowWalker;
  }

  public function validate(Workflow $workflow): void {
    $this->workflowWalker->walk($workflow, [
      new NoUnreachableStepsRule(),
      new ConsistentStepMapRule(),
      new NoDuplicateEdgesRule(),
      new TriggersUnderRootRule(),
      new NoCycleRule(),
      new NoJoinRule(),
      new NoSplitRule(),
      $this->unknownStepRule,
      $this->validStepArgsRule,
    ]);
  }
}
