<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace MailPoet\Cron;

use MailPoet\Cron\Workers\WorkersFactory;
use MailPoet\Logging\LoggerFactory;

class Daemon {
  public $timer;

  /** @var CronHelper */
  private $cronHelper;

  /** @var CronWorkerRunner */
  private $cronWorkerRunner;

  /** @var WorkersFactory */
  private $workersFactory;

  /** @var LoggerFactory  */
  private $loggerFactory;

  public function __construct(
    CronHelper $cronHelper,
    CronWorkerRunner $cronWorkerRunner,
    WorkersFactory $workersFactory,
    LoggerFactory $loggerFactory
  ) {
    $this->timer = microtime(true);
    $this->workersFactory = $workersFactory;
    $this->cronWorkerRunner = $cronWorkerRunner;
    $this->cronHelper = $cronHelper;
    $this->loggerFactory = $loggerFactory;
  }

  public function run($settingsDaemonData) {
    $settingsDaemonData['run_started_at'] = time();
    $this->cronHelper->saveDaemon($settingsDaemonData);

    $errors = [];
    foreach ($this->getWorkers() as $worker) {
      try {
        if ($worker instanceof CronWorkerInterface) {
          $this->cronWorkerRunner->run($worker);
        } else {
          $worker->process($this->timer); // BC for workers not implementing CronWorkerInterface
        }
      } catch (\Exception $e) {
        $workerClassNameParts = explode('\\', get_class($worker));
        $workerName = end($workerClassNameParts);
        $errors[] = [
          'worker' => $workerName,
          'message' => $e->getMessage(),
        ];

        if ($e->getCode() === CronHelper::DAEMON_EXECUTION_LIMIT_REACHED) {
          break;
        }

        $this->loggerFactory->getLogger(LoggerFactory::TOPIC_CRON)->error($e->getMessage(), ['error' => $e, 'worker' => $workerName]);
      }
    }

    if (!empty($errors)) {
      $this->cronHelper->saveDaemonLastError($errors);
    }

    // Log successful execution
    $this->cronHelper->saveDaemonRunCompleted(time());
  }

  private function getWorkers() {
    yield $this->workersFactory->createStatsNotificationsWorker(); // not CronWorkerInterface compatible
    yield $this->workersFactory->createScheduleWorker(); // not CronWorkerInterface compatible
    yield $this->workersFactory->createQueueWorker(); // not CronWorkerInterface compatible
    yield $this->workersFactory->createSendingServiceKeyCheckWorker();
    yield $this->workersFactory->createPremiumKeyCheckWorker();
    yield $this->workersFactory->createSubscribersStatsReportWorker();
    yield $this->workersFactory->createBounceWorker();
    yield $this->workersFactory->createExportFilesCleanupWorker();
    yield $this->workersFactory->createBeamerkWorker();
    yield $this->workersFactory->createSubscribersEmailCountsWorker();
    yield $this->workersFactory->createInactiveSubscribersWorker();
    yield $this->workersFactory->createUnsubscribeTokensWorker();
    yield $this->workersFactory->createWooCommerceSyncWorker();
    yield $this->workersFactory->createAuthorizedSendingEmailsCheckWorker();
    yield $this->workersFactory->createWooCommercePastOrdersWorker();
    yield $this->workersFactory->createStatsNotificationsWorkerForAutomatedEmails();
    yield $this->workersFactory->createSubscriberLinkTokensWorker();
    yield $this->workersFactory->createSubscribersEngagementScoreWorker();
    yield $this->workersFactory->createSubscribersLastEngagementWorker();
    yield $this->workersFactory->createSubscribersCountCacheRecalculationWorker();
    yield $this->workersFactory->createReEngagementEmailsSchedulerWorker();
    yield $this->workersFactory->createNewsletterTemplateThumbnailsWorker();
    yield $this->workersFactory->createAbandonedCartWorker();
  }
}
