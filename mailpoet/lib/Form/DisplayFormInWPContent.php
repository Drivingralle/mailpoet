<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace MailPoet\Form;

use MailPoet\API\JSON\API;
use MailPoet\Config\Renderer as TemplateRenderer;
use MailPoet\Entities\FormEntity;
use MailPoet\Subscribers\SubscribersRepository;
use MailPoet\Subscribers\SubscriberSubscribeController;
use MailPoet\WooCommerce\Helper as WCHelper;
use MailPoet\WP\Functions as WPFunctions;

class DisplayFormInWPContent {

  const NO_FORM_TRANSIENT_KEY = 'no_forms_displayed_bellow_content';

  const TYPES = [
    FormEntity::DISPLAY_TYPE_BELOW_POST,
    FormEntity::DISPLAY_TYPE_POPUP,
    FormEntity::DISPLAY_TYPE_FIXED_BAR,
    FormEntity::DISPLAY_TYPE_SLIDE_IN,
  ];

  const WITH_COOKIE_TYPES = [
    FormEntity::DISPLAY_TYPE_POPUP,
    FormEntity::DISPLAY_TYPE_FIXED_BAR,
    FormEntity::DISPLAY_TYPE_SLIDE_IN,
  ];

  const SUPPORTED_POST_TYPES = [
    'post',
    'product',
    'job_listing',
  ];

  /** @var WPFunctions */
  private $wp;

  /** @var FormsRepository */
  private $formsRepository;

  /** @var Renderer */
  private $formRenderer;

  /** @var AssetsController */
  private $assetsController;

  /** @var TemplateRenderer */
  private $templateRenderer;

  /** @var SubscribersRepository */
  private $subscribersRepository;

  /** @var SubscriberSubscribeController */
  private $subscriberSubscribeController;

  /** @var WCHelper */
  private $woocommerceHelper;

  private $wooShopPageId = null;

  private $inWooProductLoop = false;

  public function __construct(
    WPFunctions $wp,
    FormsRepository $formsRepository,
    Renderer $formRenderer,
    AssetsController $assetsController,
    TemplateRenderer $templateRenderer,
    SubscriberSubscribeController $subscriberSubscribeController,
    SubscribersRepository $subscribersRepository,
    WCHelper $woocommerceHelper
  ) {
    $this->wp = $wp;
    $this->formsRepository = $formsRepository;
    $this->formRenderer = $formRenderer;
    $this->assetsController = $assetsController;
    $this->templateRenderer = $templateRenderer;
    $this->subscriberSubscribeController = $subscriberSubscribeController;
    $this->subscribersRepository = $subscribersRepository;
    $this->woocommerceHelper = $woocommerceHelper;
  }

  /**
   * This takes input from an action and any plugin or theme can pass anything.
   * We return string for regular content otherwise we just pass thru what comes.
   * @param mixed $content
   * @return string|mixed
   */
  private function display($content = null) {
    if (!is_string($content) || !$this->shouldDisplay()) return $content;

    $forms = $this->getForms();
    if (count($forms) === 0) {
      return $content;
    }

    $this->assetsController->setupFrontEndDependencies();
    $result = $content;
    foreach ($forms as $displayType => $form) {
      $result .= $this->getContentBellow($form, $displayType);
    }
    return $result;
  }

  public function contentDisplay($content = null) {
    $this->inWooProductLoop = false;
    return $this->display($content);
  }

  public function wooProductListDisplay($content = null) {
    $this->inWooProductLoop = true;
    return $this->display($content);
  }

  private function shouldDisplay(): bool {
    $result = true;
    // This is a fix Yoast plugin and Shapely theme compatibility
    // This is to make sure we only display once for each page
    // Yast plugin calls `get_the_excerpt` which also triggers hook `the_content` we don't want to include our form in that
    // Shapely calls the hook `the_content` multiple times on the page as well and we would display popup multiple times - not ideal
    if (!$this->wp->inTheLoop() || !$this->wp->isMainQuery()) {
      $result = $this->wp->applyFilters('mailpoet_display_form_is_main_loop', false);
    }
    // this code ensures that we display the form only on a page which is related to single post
    if (!$this->wp->isSingle() && !$this->wp->isPage()) $result = $this->wp->applyFilters('mailpoet_display_form_is_single', false);

    // Ensure form does not show up multiple times when called from the woocommerce_product_loop_end filter
    if ($this->inWooProductLoop) $result = $this->displayFormInProductListPage();

    $noFormsCache = $this->wp->getTransient(DisplayFormInWPContent::NO_FORM_TRANSIENT_KEY);
    if ($noFormsCache === '1') $result = false;
    return $result;
  }

  private function displayFormInProductListPage(): bool {
    $displayCheck = $this->wp->applyFilters('mailpoet_display_form_in_product_listing', true);

    $shopPageId = $this->woocommerceHelper->wcGetPageId('shop');
    $this->wooShopPageId = $shopPageId && $shopPageId > 0 ? $shopPageId : null;

    if ($displayCheck && !is_null($this->wooShopPageId) && $this->wp->isPage($this->wooShopPageId)) {
      return true;
    }

    return $displayCheck && $this->wp->isArchive() && $this->wp->isPostTypeArchive('product');
  }

  private function saveNoForms() {
    $this->wp->setTransient(DisplayFormInWPContent::NO_FORM_TRANSIENT_KEY, '1');
  }

  /**
   * @return array<string, FormEntity>
   */
  private function getForms(): array {
    $forms = $this->formsRepository->findBy([
      'deletedAt' => null,
      'status' => FormEntity::STATUS_ENABLED,
    ], ['updatedAt' => 'ASC']);
    if (count($forms) === 0) {
      $this->saveNoForms();
    }
    $forms = $this->filterOneFormInEachDisplayType($forms);
    return $forms;
  }

  /**
   * @param FormEntity[] $forms
   * @return array<string, FormEntity>
   */
  private function filterOneFormInEachDisplayType($forms): array {
    $formsFiltered = [];
    foreach ($forms as $form) {
      foreach (self::TYPES as $displayType) {
        if ($this->shouldDisplayFormType($form, $displayType)) {
          $formsFiltered[$displayType] = $form;
        }
      }
    }
    return $formsFiltered;
  }

  private function getContentBellow(FormEntity $form, string $displayType): string {
    if (!$this->shouldDisplayFormType($form, $displayType)) return '';

    $formSettings = $form->getSettings();
    if (!is_array($formSettings)) return '';
    $htmlId = 'mp_form_' . $displayType . $form->getId();
    $templateData = [
      'form_html_id' => $htmlId,
      'form_id' => $form->getId(),
      'form_success_message' => $formSettings['success_message'] ?? null,
      'form_type' => $displayType,
      'styles' => $this->formRenderer->renderStyles($form, '#' . $htmlId, $displayType),
      'html' => $this->formRenderer->renderHTML($form),
      'close_button_icon' => $formSettings['close_button'] ?? 'round_white',
    ];

    // (POST) non ajax success/error variables
    $templateData['success'] = (
      (isset($_GET['mailpoet_success']))
      &&
      ((int)$_GET['mailpoet_success'] === $form->getId())
    );
    $templateData['error'] = (
      (isset($_GET['mailpoet_error']))
      &&
      ((int)$_GET['mailpoet_error'] === $form->getId())
    );

    $templateData['delay'] = $formSettings['form_placement'][$displayType]['delay'] ?? 0;
    $templateData['position'] = $formSettings['form_placement'][$displayType]['position'] ?? '';
    $templateData['animation'] = $formSettings['form_placement'][$displayType]['animation'] ?? '';
    $templateData['fontFamily'] = $formSettings['font_family'] ?? '';
    $templateData['enableExitIntent'] = false;
    // Set default value for cookie expiration for backward compatibility with forms without this value
    $templateData['cookieFormExpirationTime'] = $formSettings['form_placement'][$displayType]['cookieExpiration'] ?? 7;
    if (
      isset($formSettings['form_placement'][$displayType]['exit_intent_enabled'])
      && ($formSettings['form_placement'][$displayType]['exit_intent_enabled'] === '1')
    ) {
      $templateData['enableExitIntent'] = true;
    }

    // generate security token
    $templateData['token'] = $this->wp->wpCreateNonce('mailpoet_token');

    // add API version
    $templateData['api_version'] = API::CURRENT_VERSION;
    return $this->templateRenderer->render('form/front_end_form.html', $templateData);
  }

  /**
   * Checks if the form should be displayed for current WordPress user
   *
   * @param FormEntity $form The form to check
   * @param string $formType Display type of the current form, from self::TYPES
   * @return bool False if form can be dismissed and user is subscribed to any of the form's lists
   */
  private function shouldDisplayFormForWPUser(FormEntity $form, string $formType): bool {
    if (!in_array($formType, self::WITH_COOKIE_TYPES, true)) return true;

    $subscriber = $this->subscribersRepository->getCurrentWPUser();
    if (!$subscriber) return true;

    if ($this->subscriberSubscribeController->isSubscribedToAnyFormSegments($form, $subscriber)) {
      return false;
    }
    return true;
  }

  private function shouldDisplayFormType(FormEntity $form, string $formType): bool {
    $settings = $form->getSettings();
    // check the structure just to be sure

    if (
      !is_array($settings)
      || !isset($settings['form_placement'][$formType])
      || !is_array($settings['form_placement'][$formType])
    ) return false;

    $setup = $settings['form_placement'][$formType];
    if ($setup['enabled'] !== '1') {
      return false;
    }

    if (!$this->shouldDisplayFormForWPUser($form, $formType)) return false;

    if ($this->wp->isSingular($this->wp->applyFilters('mailpoet_display_form_supported_post_types', self::SUPPORTED_POST_TYPES))) {
      if ($this->shouldDisplayFormOnPost($setup, 'posts')) return true;
      if ($this->shouldDisplayFormOnCategory($setup)) return true;
      if ($this->shouldDisplayFormOnTag($setup)) return true;
      return false;
    }
    if ($this->wp->isPage() && $this->shouldDisplayFormOnPost($setup, 'pages')) {
      return true;
    }

    if ($this->displayFormInProductListPage()) {
      // Allow form display on Woo Shop listing page
      if (is_null($this->wooShopPageId)) return false;
      if ($this->shouldDisplayFormOnPost($setup, 'pages', $this->wooShopPageId)) return true;
    }

    return false;
  }

  private function shouldDisplayFormOnPost(array $setup, string $postsKey, $postId = null): bool {
    if (!isset($setup[$postsKey])) {
      return false;
    }
    if (isset($setup[$postsKey]['all']) && $setup[$postsKey]['all'] === '1') {
      return true;
    }
    $post = $this->wp->getPost($postId, ARRAY_A);
    if (isset($setup[$postsKey]['selected']) && in_array($post['ID'], $setup[$postsKey]['selected'])) {
      return true;
    }
    return false;
  }

  private function shouldDisplayFormOnCategory(array $setup): bool {
    if (!isset($setup['categories'])) return false;
    if ($this->wp->hasCategory($setup['categories'])) return true;
    if ($this->wp->hasTerm($setup['categories'], 'product_cat')) return true;
    return false;
  }

  private function shouldDisplayFormOnTag(array $setup): bool {
    if (!isset($setup['tags'])) return false;
    if ($this->wp->hasTag($setup['tags'])) return true;
    if ($this->wp->hasTerm($setup['tags'], 'product_tag')) return true;
    return false;
  }
}
