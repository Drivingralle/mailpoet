<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace MailPoet\Util\Notices;

use MailPoet\Config\Menu;
use MailPoet\Mailer\MailerFactory;
use MailPoet\Settings\SettingsController;
use MailPoet\Settings\TrackingConfig;
use MailPoet\Subscribers\SubscribersRepository;
use MailPoet\Util\License\Features\Subscribers as SubscribersFeature;
use MailPoet\WP\Functions as WPFunctions;

class PermanentNotices {

  /** @var WPFunctions */
  private $wp;

  /** @var PHPVersionWarnings */
  private $phpVersionWarnings;

  /** @var AfterMigrationNotice */
  private $afterMigrationNotice;

  /** @var UnauthorizedEmailNotice */
  private $unauthorizedEmailsNotice;

  /** @var UnauthorizedEmailInNewslettersNotice */
  private $unauthorizedEmailsInNewslettersNotice;

  /** @var InactiveSubscribersNotice */
  private $inactiveSubscribersNotice;

  /** @var BlackFridayNotice */
  private $blackFridayNotice;

  /** @var HeadersAlreadySentNotice */
  private $headersAlreadySentNotice;

  /** @var EmailWithInvalidSegmentNotice */
  private $emailWithInvalidListNotice;

  /** @var ChangedTrackingNotice */
  private $changedTrackingNotice;

  /** @var DeprecatedFilterNotice */
  private $deprecatedFilterNotice;

  /** @var DisabledMailFunctionNotice */
  private $disabledMailFunctionNotice;

  /** @var PendingApprovalNotice */
  private $pendingApprovalNotice;

  /** @var WooCommerceVersionWarning */
  private $woocommerceVersionWarning;

  public function __construct(
    WPFunctions $wp,
    TrackingConfig $trackingConfig,
    SubscribersRepository $subscribersRepository,
    SettingsController $settings,
    SubscribersFeature $subscribersFeature,
    MailerFactory $mailerFactory
  ) {
    $this->wp = $wp;
    $this->phpVersionWarnings = new PHPVersionWarnings();
    $this->afterMigrationNotice = new AfterMigrationNotice();
    $this->unauthorizedEmailsNotice = new UnauthorizedEmailNotice($wp, $settings);
    $this->unauthorizedEmailsInNewslettersNotice = new UnauthorizedEmailInNewslettersNotice($settings, $wp);
    $this->inactiveSubscribersNotice = new InactiveSubscribersNotice($settings, $subscribersRepository, $wp);
    $this->blackFridayNotice = new BlackFridayNotice($subscribersRepository);
    $this->headersAlreadySentNotice = new HeadersAlreadySentNotice($settings, $trackingConfig, $wp);
    $this->emailWithInvalidListNotice = new EmailWithInvalidSegmentNotice($wp);
    $this->changedTrackingNotice = new ChangedTrackingNotice($wp);
    $this->deprecatedFilterNotice = new DeprecatedFilterNotice($wp);
    $this->disabledMailFunctionNotice = new DisabledMailFunctionNotice($wp, $settings, $subscribersFeature, $mailerFactory);
    $this->pendingApprovalNotice = new PendingApprovalNotice($settings);
    $this->woocommerceVersionWarning = new WooCommerceVersionWarning($wp);
  }

  public function init() {
    $excludeSetupWizard = [
      'mailpoet-welcome-wizard',
      'mailpoet-woocommerce-setup',
      'mailpoet-landingpage',
    ];
    $this->wp->addAction('wp_ajax_dismissed_notice_handler', [
      $this,
      'ajaxDismissNoticeHandler',
    ]);

    $this->phpVersionWarnings->init(
      phpversion(),
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
    $this->afterMigrationNotice->init(
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
    $this->unauthorizedEmailsNotice->init(
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
    $this->unauthorizedEmailsInNewslettersNotice->init(
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
    $this->inactiveSubscribersNotice->init(
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
    $this->blackFridayNotice->init(
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
    $this->headersAlreadySentNotice->init(
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
    $this->emailWithInvalidListNotice->init(
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
    $this->changedTrackingNotice->init(
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
    $this->deprecatedFilterNotice->init(
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
    $this->disabledMailFunctionNotice->init(
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
    $this->pendingApprovalNotice->init(
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
    $this->woocommerceVersionWarning->init(
      Menu::isOnMailPoetAdminPage($excludeSetupWizard)
    );
  }

  public function ajaxDismissNoticeHandler() {
    if (!isset($_POST['type'])) return;
    switch ($_POST['type']) {
      case (PHPVersionWarnings::OPTION_NAME):
        $this->phpVersionWarnings->disable();
        break;
      case (AfterMigrationNotice::OPTION_NAME):
        $this->afterMigrationNotice->disable();
        break;
      case (BlackFridayNotice::OPTION_NAME):
        $this->blackFridayNotice->disable();
        break;
      case (HeadersAlreadySentNotice::OPTION_NAME):
        $this->headersAlreadySentNotice->disable();
        break;
      case (InactiveSubscribersNotice::OPTION_NAME):
        $this->inactiveSubscribersNotice->disable();
        break;
      case (EmailWithInvalidSegmentNotice::OPTION_NAME):
        $this->emailWithInvalidListNotice->disable();
        break;
      case (ChangedTrackingNotice::OPTION_NAME):
        $this->changedTrackingNotice->disable();
        break;
      case (DeprecatedFilterNotice::OPTION_NAME):
        $this->deprecatedFilterNotice->disable();
        break;
      case (WooCommerceVersionWarning::OPTION_NAME):
        $this->woocommerceVersionWarning->disable();
        break;
    }
  }
}
