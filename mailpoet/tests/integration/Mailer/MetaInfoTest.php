<?php declare(strict_types = 1);

namespace MailPoet\Test\Mailer;

use MailPoet\Entities\NewsletterEntity;
use MailPoet\Entities\SubscriberEntity;
use MailPoet\Mailer\MetaInfo;
use MailPoet\Test\DataFactories\Newsletter as NewsletterFactory;
use MailPoet\Test\DataFactories\Subscriber as SubscriberFactory;

class MetaInfoTest extends \MailPoetTest {
  /** @var MetaInfo */
  private $meta;

  /** @var SubscriberEntity */
  private $subscriber;

  /** @var NewsletterFactory */
  private $newsletterFactory;

  public function _before() {
    parent::_before();
    $this->meta = new MetaInfo;
    $this->subscriber = (new SubscriberFactory())
      ->withStatus(SubscriberEntity::STATUS_SUBSCRIBED)
      ->withSource('form')
      ->create();
    $this->newsletterFactory = new NewsletterFactory();
  }

  public function testItGetsMetaInfoForSendingTest() {
    expect($this->meta->getSendingTestMetaInfo())->equals([
      'email_type' => 'sending_test',
      'subscriber_status' => 'unknown',
      'subscriber_source' => 'administrator',
    ]);
  }

  public function testItGetsMetaInfoForPreview() {
    expect($this->meta->getPreviewMetaInfo())->equals([
      'email_type' => 'preview',
      'subscriber_status' => 'unknown',
      'subscriber_source' => 'administrator',
    ]);
  }

  public function testItGetsMetaInfoForStatsNotifications() {
    expect($this->meta->getStatsNotificationMetaInfo())->equals([
      'email_type' => 'email_stats_notification',
      'subscriber_status' => 'unknown',
      'subscriber_source' => 'administrator',
    ]);
  }

  public function testItGetsMetaInfoForWordPressTransactionalEmails() {
    expect($this->meta->getWordPressTransactionalMetaInfo())->equals([
      'email_type' => 'transactional',
      'subscriber_status' => 'unknown',
      'subscriber_source' => 'unknown',
    ]);

    $subscriber = $this->make(SubscriberEntity::class, [
      'getStatus' => 'subscribed',
      'getSource' => 'form',
    ]);
    expect($this->meta->getWordPressTransactionalMetaInfo($subscriber))->equals([
      'email_type' => 'transactional',
      'subscriber_status' => 'subscribed',
      'subscriber_source' => 'form',
    ]);
  }

  public function testItGetsMetaInfoForConfirmationEmails() {
    $subscriber = (new SubscriberFactory())
      ->withStatus(SubscriberEntity::STATUS_UNCONFIRMED)
      ->withSource('form')
      ->withEmail('test@metainfo.com')
      ->create();

    expect($this->meta->getConfirmationMetaInfo($subscriber))->equals([
      'email_type' => 'confirmation',
      'subscriber_status' => 'unconfirmed',
      'subscriber_source' => 'form',
    ]);
  }

  public function testItGetsMetaInfoForNewSubscriberNotifications() {
    expect($this->meta->getNewSubscriberNotificationMetaInfo())->equals([
      'email_type' => 'new_subscriber_notification',
      'subscriber_status' => 'unknown',
      'subscriber_source' => 'administrator',
    ]);
  }

  public function testItGetsMetaInfoForStandardNewsletter() {
    $subscriber = (new SubscriberFactory())
      ->withStatus(SubscriberEntity::STATUS_SUBSCRIBED)
      ->withSource('form')
      ->create();
    $newsletter = $this->newsletterFactory
      ->withType(NewsletterEntity::TYPE_STANDARD)
      ->create();

    expect($this->meta->getNewsletterMetaInfo($newsletter, $subscriber))->equals([
      'email_type' => 'newsletter',
      'subscriber_status' => 'subscribed',
      'subscriber_source' => 'form',
    ]);
  }

  public function testItGetsMetaInfoForWelcomeEmail() {
    $newsletter = $this->newsletterFactory
      ->withType(NewsletterEntity::TYPE_WELCOME)
      ->create();
    expect($this->meta->getNewsletterMetaInfo($newsletter, $this->subscriber))->equals([
      'email_type' => 'welcome',
      'subscriber_status' => 'subscribed',
      'subscriber_source' => 'form',
    ]);
  }

  public function testItGetsMetaInfoForPostNotification() {
    $newsletter1 = $this->newsletterFactory
      ->withType(NewsletterEntity::TYPE_NOTIFICATION)
      ->create();
    $newsletter2 = $this->newsletterFactory
      ->withType(NewsletterEntity::TYPE_NOTIFICATION_HISTORY)
      ->create();

    expect($this->meta->getNewsletterMetaInfo($newsletter1, $this->subscriber))->equals([
      'email_type' => 'post_notification',
      'subscriber_status' => 'subscribed',
      'subscriber_source' => 'form',
    ]);
    expect($this->meta->getNewsletterMetaInfo($newsletter2, $this->subscriber))->equals([
      'email_type' => 'post_notification',
      'subscriber_status' => 'subscribed',
      'subscriber_source' => 'form',
    ]);
  }

  public function testItGetsMetaInfoForAutomaticEmails() {
    $newsletter1 = $this->newsletterFactory
      ->withType(NewsletterEntity::TYPE_AUTOMATIC)
      ->withAutomaticTypeWooCommerceFirstPurchase()
      ->create();
    $newsletter2 = $this->newsletterFactory
      ->withType(NewsletterEntity::TYPE_AUTOMATIC)
      ->withAutomaticTypeWooCommerceProductInCategoryPurchased()
      ->create();

    expect($this->meta->getNewsletterMetaInfo($newsletter1, $this->subscriber))->equals([
      'email_type' => 'automatic_woocommerce_woocommerce_first_purchase',
      'subscriber_status' => 'subscribed',
      'subscriber_source' => 'form',
    ]);
    expect($this->meta->getNewsletterMetaInfo($newsletter2, $this->subscriber))->equals([
      'email_type' => 'automatic_woocommerce_woocommerce_product_purchased_in_category',
      'subscriber_status' => 'subscribed',
      'subscriber_source' => 'form',
    ]);
  }

  public function testItGetsMetaInfoForReEngagement() {
    $newsletter1 = $this->newsletterFactory
      ->withType(NewsletterEntity::TYPE_RE_ENGAGEMENT)
      ->create();
    $newsletter2 = $this->newsletterFactory
      ->withType(NewsletterEntity::TYPE_RE_ENGAGEMENT)
      ->create();
    expect($this->meta->getNewsletterMetaInfo($newsletter1, $this->subscriber))->equals([
      'email_type' => 're_engagement',
      'subscriber_status' => 'subscribed',
      'subscriber_source' => 'form',
    ]);
    expect($this->meta->getNewsletterMetaInfo($newsletter2, $this->subscriber))->equals([
      'email_type' => 're_engagement',
      'subscriber_status' => 'subscribed',
      'subscriber_source' => 'form',
    ]);
  }

  public function testItGetsMetaInfoForRandomType() {
    $newsletter1 = $this->newsletterFactory
      ->withType('random')
      ->create();

    expect($this->meta->getNewsletterMetaInfo($newsletter1, $this->subscriber))->equals([
      'email_type' => 'random',
      'subscriber_status' => 'subscribed',
      'subscriber_source' => 'form',
    ]);
  }
}
