<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace MailPoet\Cron\Workers;

use MailPoet\Cron\CronHelper;
use MailPoet\Cron\CronWorkerScheduler;
use MailPoet\Entities\NewsletterEntity;
use MailPoet\Entities\NewsletterSegmentEntity;
use MailPoet\Entities\ScheduledTaskEntity;
use MailPoet\Entities\SegmentEntity;
use MailPoet\Entities\SubscriberEntity;
use MailPoet\Logging\LoggerFactory;
use MailPoet\Models\ScheduledTask;
use MailPoet\Newsletter\NewslettersRepository;
use MailPoet\Newsletter\Scheduler\PostNotificationScheduler;
use MailPoet\Newsletter\Scheduler\Scheduler as NewsletterScheduler;
use MailPoet\Newsletter\Scheduler\WelcomeScheduler;
use MailPoet\Newsletter\Segment\NewsletterSegmentRepository;
use MailPoet\Newsletter\Sending\ScheduledTasksRepository;
use MailPoet\Segments\SegmentsRepository;
use MailPoet\Segments\SubscribersFinder;
use MailPoet\Subscribers\SubscriberSegmentRepository;
use MailPoet\Subscribers\SubscribersRepository;
use MailPoet\Tasks\Sending as SendingTask;
use MailPoet\Util\Security;
use MailPoet\WP\Functions as WPFunctions;
use MailPoetVendor\Carbon\Carbon;
use MailPoetVendor\Doctrine\ORM\EntityNotFoundException;

class Scheduler {
  const TASK_BATCH_SIZE = 5;

  /** @var SubscribersFinder */
  private $subscribersFinder;

  /** @var LoggerFactory */
  private $loggerFactory;

  /** @var CronHelper */
  private $cronHelper;

  /** @var CronWorkerScheduler */
  private $cronWorkerScheduler;

  /** @var ScheduledTasksRepository */
  private $scheduledTasksRepository;

  /** @var NewslettersRepository */
  private $newslettersRepository;

  /** @var SegmentsRepository */
  private $segmentsRepository;

  /** @var NewsletterSegmentRepository */
  private $newsletterSegmentRepository;

  /** @var WPFunctions */
  private $wp;

  /** @var Security */
  private $security;

  /** @var NewsletterScheduler */
  private $scheduler;

  /** @var SubscriberSegmentRepository */
  private $subscriberSegmentRepository;

  /** @var SubscribersRepository */
  private $subscribersRepository;

  public function __construct(
    SubscribersFinder $subscribersFinder,
    LoggerFactory $loggerFactory,
    CronHelper $cronHelper,
    CronWorkerScheduler $cronWorkerScheduler,
    ScheduledTasksRepository $scheduledTasksRepository,
    NewslettersRepository $newslettersRepository,
    SegmentsRepository $segmentsRepository,
    NewsletterSegmentRepository $newsletterSegmentRepository,
    WPFunctions $wp,
    Security $security,
    NewsletterScheduler $scheduler,
    SubscriberSegmentRepository $subscriberSegmentRepository,
    SubscribersRepository $subscribersRepository
  ) {
    $this->cronHelper = $cronHelper;
    $this->subscribersFinder = $subscribersFinder;
    $this->loggerFactory = $loggerFactory;
    $this->cronWorkerScheduler = $cronWorkerScheduler;
    $this->scheduledTasksRepository = $scheduledTasksRepository;
    $this->newslettersRepository = $newslettersRepository;
    $this->segmentsRepository = $segmentsRepository;
    $this->newsletterSegmentRepository = $newsletterSegmentRepository;
    $this->wp = $wp;
    $this->security = $security;
    $this->scheduler = $scheduler;
    $this->subscriberSegmentRepository = $subscriberSegmentRepository;
    $this->subscribersRepository = $subscribersRepository;
  }

  public function process($timer = false) {
    $timer = $timer ?: microtime(true);

    // abort if execution limit is reached
    $this->cronHelper->enforceExecutionLimit($timer);

    $scheduledTasks = $this->getScheduledSendingTasks();
    if (!count($scheduledTasks)) return false;

    // To prevent big changes we convert ScheduledTaskEntity to old model
    $scheduledQueues = [];
    foreach ($scheduledTasks as $scheduledTask) {
      $task = ScheduledTask::findOne($scheduledTask->getId());
      if (!$task) continue;
      $scheduledQueue = SendingTask::createFromScheduledTask($task);
      if (!$scheduledQueue) continue;
      $scheduledQueues[] = $scheduledQueue;
    }

    $this->updateTasks($scheduledTasks);
    foreach ($scheduledQueues as $i => $queue) {
      $newsletter = $this->newslettersRepository->findOneById($queue->newsletterId);
      try {
        if (!$newsletter instanceof NewsletterEntity || $newsletter->getDeletedAt() !== null) {
          $queue->delete();
        } elseif ($newsletter->getStatus() !== NewsletterEntity::STATUS_ACTIVE && $newsletter->getStatus() !== NewsletterEntity::STATUS_SCHEDULED) {
          continue;
        } elseif ($newsletter->getType() === NewsletterEntity::TYPE_WELCOME) {
          $this->processWelcomeNewsletter($newsletter, $queue);
        } elseif ($newsletter->getType() === NewsletterEntity::TYPE_NOTIFICATION) {
          $this->processPostNotificationNewsletter($newsletter, $queue);
        } elseif ($newsletter->getType() === NewsletterEntity::TYPE_STANDARD) {
          $this->processScheduledStandardNewsletter($newsletter, $queue);
        } elseif ($newsletter->getType() === NewsletterEntity::TYPE_AUTOMATIC) {
          $this->processScheduledAutomaticEmail($newsletter, $queue);
        } elseif ($newsletter->getType() === NewsletterEntity::TYPE_RE_ENGAGEMENT) {
          $this->processReEngagementEmail($queue);
        } elseif ($newsletter->getType() === NewsletterEntity::TYPE_AUTOMATION) {
          $this->processScheduledAutomationEmail($queue);
        } elseif ($newsletter->getType() === NewsletterEntity::TYPE_AUTOMATION_TRANSACTIONAL) {
          $this->processScheduledTransactionalEmail($queue);
        }
      } catch (EntityNotFoundException $e) {
        // Doctrine throws this exception when newsletter doesn't exist but is referenced in a scheduled task.
        // This was added while refactoring this method to use Doctrine instead of Paris. We have to handle this case
        // for the SchedulerTest::testItDeletesQueueDuringProcessingWhenNewsletterNotFound() test. I'm not sure
        // if this problem could happen in production or not.
        $queue->delete();
      }
      $this->cronHelper->enforceExecutionLimit($timer);
    }
  }

  public function processWelcomeNewsletter(NewsletterEntity $newsletter, $queue) {
    $subscribers = $queue->getSubscribers();
    if (empty($subscribers[0])) {
      $queue->delete();
      $this->updateScheduledTaskEntity($queue, true);
      return false;
    }
    $subscriberId = (int)$subscribers[0];
    if ($newsletter->getOptionValue('event') === 'segment') {
      if ($this->verifyMailpoetSubscriber($subscriberId, $newsletter, $queue) === false) {
        return false;
      }
    } else {
      if ($newsletter->getOptionValue('event') === 'user') {
        if ($this->verifyWPSubscriber($subscriberId, $newsletter, $queue) === false) {
          return false;
        }
      }
    }
    $queue->status = null;
    $queue->save();
    $this->updateScheduledTaskEntity($queue);
    return true;
  }

  public function processPostNotificationNewsletter(NewsletterEntity $newsletter, SendingTask $queue) {
    $this->loggerFactory->getLogger(LoggerFactory::TOPIC_POST_NOTIFICATIONS)->info(
      'process post notification in scheduler',
      ['newsletter_id' => $newsletter->getId(), 'task_id' => $queue->taskId]
    );

    // ensure that segments exist
    $segments = $newsletter->getSegmentIds();
    if (empty($segments)) {
      $this->loggerFactory->getLogger(LoggerFactory::TOPIC_POST_NOTIFICATIONS)->info(
        'post notification no segments',
        ['newsletter_id' => $newsletter->getId(), 'task_id' => $queue->taskId]
      );
      return $this->deleteQueueOrUpdateNextRunDate($queue, $newsletter);
    }

    // ensure that subscribers are in segments
    $taskModel = $queue->task();
    $taskEntity = $this->scheduledTasksRepository->findOneById($taskModel->id);
    if ($taskEntity instanceof ScheduledTaskEntity) {
      $subscribersCount = $this->subscribersFinder->addSubscribersToTaskFromSegments($taskEntity, $segments);
    }

    if (empty($subscribersCount)) {
      $this->loggerFactory->getLogger(LoggerFactory::TOPIC_POST_NOTIFICATIONS)->info(
        'post notification no subscribers',
        ['newsletter_id' => $newsletter->getId(), 'task_id' => $queue->taskId, 'segment_ids' => $segments]
      );
      return $this->deleteQueueOrUpdateNextRunDate($queue, $newsletter);
    }

    // create a duplicate newsletter that acts as a history record
    try {
      $notificationHistory = $this->createPostNotificationHistory($newsletter);
    } catch (\Exception $exception) {
      $this->loggerFactory->getLogger(LoggerFactory::TOPIC_POST_NOTIFICATIONS)->error(
        'creating post notification history failed',
        ['newsletter_id' => $newsletter->getId(), 'task_id' => $queue->taskId, 'error' => $exception->getMessage()]
      );
      return false;
    }

    // queue newsletter for delivery
    $queue->newsletterId = (int)$notificationHistory->getId();
    $queue->updateCount();
    $queue->status = null;
    $queue->save();
    $this->updateScheduledTaskEntity($queue);

    // Because there is mixed usage of the old and new model, we want to be sure about the correct state
    $this->newslettersRepository->refresh($notificationHistory);
    $queue->getSendingQueueEntity(); // This call refreshes sending queue entity

    $this->loggerFactory->getLogger(LoggerFactory::TOPIC_POST_NOTIFICATIONS)->info(
      'post notification set status to sending',
      ['newsletter_id' => $newsletter->getId(), 'task_id' => $queue->taskId]
    );
    return true;
  }

  public function processScheduledAutomaticEmail(NewsletterEntity $newsletter, $queue) {
    if ($newsletter->getOptionValue('sendTo') === 'segment') {
      $segment = $this->segmentsRepository->findOneById($newsletter->getOptionValue('segment'));
      if ($segment instanceof SegmentEntity) {
        $taskModel = $queue->task();
        $taskEntity = $this->scheduledTasksRepository->findOneById($taskModel->id);
        if ($taskEntity instanceof ScheduledTaskEntity) {
          $result = $this->subscribersFinder->addSubscribersToTaskFromSegments($taskEntity, [(int)$segment->getId()]);
        }

        if (empty($result)) {
          $queue->delete();
          $this->updateScheduledTaskEntity($queue, true);
          return false;
        }
      }
    } else {
      $subscribers = $queue->getSubscribers();
      $subscriber = (!empty($subscribers) && is_array($subscribers)) ?
        $this->subscribersRepository->findOneById($subscribers[0]) :
        false;
      if (!$subscriber) {
        $queue->delete();
        $this->updateScheduledTaskEntity($queue, true);
        return false;
      }
      if ($this->verifySubscriber($subscriber, $queue) === false) {
        return false;
      }
    }

    $queue->status = null;
    $queue->save();
    $this->updateScheduledTaskEntity($queue);
    return true;
  }

  public function processScheduledAutomationEmail($queue): bool {
    $subscribers = $queue->getSubscribers();
    $subscriber = (!empty($subscribers) && is_array($subscribers)) ? $this->subscribersRepository->findOneById($subscribers[0]) : null;
    if (!$subscriber) {
      $queue->delete();
      $this->updateScheduledTaskEntity($queue, true);
      return false;
    }
    if (!$this->verifySubscriber($subscriber, $queue)) {
      return false;
    }

    $queue->status = null;
    $queue->save();
    $this->updateScheduledTaskEntity($queue);
    return true;
  }

  public function processScheduledTransactionalEmail($queue): bool {
    $subscribers = $queue->getSubscribers();
    $subscriber = (!empty($subscribers) && is_array($subscribers)) ? $this->subscribersRepository->findOneById($subscribers[0]) : null;
    if (!$subscriber) {
      $queue->delete();
      $this->updateScheduledTaskEntity($queue, true);
      return false;
    }
    if (!$this->verifySubscriber($subscriber, $queue)) {
      $queue->delete();
      $this->updateScheduledTaskEntity($queue, true);
      return false;
    }

    $queue->status = null;
    $queue->save();
    $this->updateScheduledTaskEntity($queue);
    return true;
  }

  public function processScheduledStandardNewsletter(NewsletterEntity $newsletter, SendingTask $task) {
    $segments = $newsletter->getSegmentIds();
    $taskModel = $task->task();
    $taskEntity = $this->scheduledTasksRepository->findOneById($taskModel->id);

    if ($taskEntity instanceof ScheduledTaskEntity) {
      $this->subscribersFinder->addSubscribersToTaskFromSegments($taskEntity, $segments);
    }

    // update current queue
    $task->updateCount();
    $task->status = null;
    $task->save();
    // update newsletter status
    $newsletter->setStatus(NewsletterEntity::STATUS_SENDING);
    $this->newslettersRepository->persist($newsletter);
    $this->newslettersRepository->flush();
    $this->updateScheduledTaskEntity($task);
    return true;
  }

  private function processReEngagementEmail($queue) {
    $queue->status = null;
    $queue->save();
    $this->updateScheduledTaskEntity($queue);
    return true;
  }

  public function verifyMailpoetSubscriber(int $subscriberId, NewsletterEntity $newsletter, $queue): bool {
    $subscriber = $this->subscribersRepository->findOneById($subscriberId);

    // check if subscriber is in proper segment
    $subscriberInSegment = $this->subscriberSegmentRepository->findOneBy(
      [
        'subscriber' => $subscriberId,
        'segment' => $newsletter->getOptionValue('segment'),
        'status' => SubscriberEntity::STATUS_SUBSCRIBED,
      ]
    );
    if (!$subscriber || !$subscriberInSegment) {
      $queue->delete();
      return false;
    }
    return $this->verifySubscriber($subscriber, $queue);
  }

  public function verifyWPSubscriber(int $subscriberId, NewsletterEntity $newsletter, $queue): bool {
    // check if user has the proper role
    $subscriber = $this->subscribersRepository->findOneById($subscriberId);
    if (!$subscriber || $subscriber->isWPUser() === false || is_null($subscriber->getWpUserId())) {
      $queue->delete();
      return false;
    }
    $wpUser = get_userdata($subscriber->getWpUserId());
    if ($wpUser === false) {
      $queue->delete();
      return false;
    }
    if (
      $newsletter->getOptionValue('role') !== WelcomeScheduler::WORDPRESS_ALL_ROLES
      && !in_array($newsletter->getOptionValue('role'), ((array)$wpUser)['roles'])
    ) {
      $queue->delete();
      return false;
    }
    return $this->verifySubscriber($subscriber, $queue);
  }

  public function verifySubscriber(SubscriberEntity $subscriber, $queue): bool {
    $newsletter = $queue->newsletterId ? $this->newslettersRepository->findOneById($queue->newsletterId) : null;
    if ($newsletter && $newsletter->getType() === NewsletterEntity::TYPE_AUTOMATION_TRANSACTIONAL) {
      return $subscriber->getStatus() !== SubscriberEntity::STATUS_BOUNCED;
    }
    if ($subscriber->getStatus() === SubscriberEntity::STATUS_UNCONFIRMED) {
      // reschedule delivery
      $task = $this->scheduledTasksRepository->findOneById($queue->task()->id);

      if ($task instanceof ScheduledTaskEntity) {
        $this->cronWorkerScheduler->rescheduleProgressively($task);
      }

      return false;
    } else if ($subscriber->getStatus() === SubscriberEntity::STATUS_UNSUBSCRIBED) {
      $queue->delete();
      return false;
    }
    return true;
  }

  public function deleteQueueOrUpdateNextRunDate($queue, NewsletterEntity $newsletter) {
    if ($newsletter->getOptionValue('intervalType') === PostNotificationScheduler::INTERVAL_IMMEDIATELY) {
      $queue->delete();
      $this->updateScheduledTaskEntity($queue, true);
      return;
    } else {
      $nextRunDate = $this->scheduler->getNextRunDate($newsletter->getOptionValue('schedule'));
      if (!$nextRunDate) {
        $queue->delete();
        $this->updateScheduledTaskEntity($queue, true);
        return;
      }
      $queue->scheduledAt = $nextRunDate;
      $queue->save();
      $this->updateScheduledTaskEntity($queue);
    }
  }

  private function updateScheduledTaskEntity(SendingTask $queue, bool $hasBeenDeleted = false) {
    $taskModel = $queue->task();
    if (!$taskModel instanceof ScheduledTask) {
      return;
    }
    $taskEntity = $this->scheduledTasksRepository->findOneById($taskModel->id);
    if (!$taskEntity instanceof ScheduledTaskEntity) {
      return;
    }
    $hasBeenDeleted ? $this->scheduledTasksRepository->detach($taskEntity) : $this->scheduledTasksRepository->refresh($taskEntity);
  }

  public function createPostNotificationHistory(NewsletterEntity $newsletter): NewsletterEntity {
    // clone newsletter
    $notificationHistory = clone $newsletter;
    $notificationHistory->setParent($newsletter);
    $notificationHistory->setType(NewsletterEntity::TYPE_NOTIFICATION_HISTORY);
    $notificationHistory->setStatus(NewsletterEntity::STATUS_SENDING);
    $notificationHistory->setUnsubscribeToken($this->security->generateUnsubscribeTokenByEntity($notificationHistory));

    // reset timestamps
    $createdAt = Carbon::createFromTimestamp($this->wp->currentTime('timestamp'));
    $notificationHistory->setCreatedAt($createdAt);
    $notificationHistory->setUpdatedAt($createdAt);
    $notificationHistory->setDeletedAt(null);

    // reset hash
    $notificationHistory->setHash(Security::generateHash());

    $this->newslettersRepository->persist($notificationHistory);
    $this->newslettersRepository->flush();

    // create relationships between notification history and segments
    foreach ($newsletter->getNewsletterSegments() as $newsletterSegment) {
      $segment = $newsletterSegment->getSegment();
      if (!$segment) {
        continue;
      }
      $duplicateSegment = new NewsletterSegmentEntity($notificationHistory, $segment);
      $notificationHistory->getNewsletterSegments()->add($duplicateSegment);
      $this->newsletterSegmentRepository->persist($duplicateSegment);
    }
    $this->newslettersRepository->flush();

    return $notificationHistory;
  }

  /**
   * @param ScheduledTaskEntity[] $scheduledTasks
   */
  private function updateTasks(array $scheduledTasks): void {
    $ids = array_map(function (ScheduledTaskEntity $scheduledTask): ?int {
      return $scheduledTask->getId();
    }, $scheduledTasks);
    $ids = array_filter($ids);
    $this->scheduledTasksRepository->touchAllByIds($ids);
  }

  /**
   * @return ScheduledTaskEntity[]
   */
  public function getScheduledSendingTasks(): array {
    return $this->scheduledTasksRepository->findScheduledSendingTasks(self::TASK_BATCH_SIZE);
  }
}
