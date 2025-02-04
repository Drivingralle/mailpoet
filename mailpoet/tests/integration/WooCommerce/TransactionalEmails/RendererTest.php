<?php declare(strict_types = 1);

namespace MailPoet\WooCommerce\TransactionalEmails;

use Codeception\Stub;
use MailPoet\Config\ServicesChecker;
use MailPoet\Entities\NewsletterEntity;
use MailPoet\Logging\LoggerFactory;
use MailPoet\Newsletter\Editor\LayoutHelper as L;
use MailPoet\Newsletter\NewslettersRepository;
use MailPoet\Newsletter\Renderer\Preprocessor;
use MailPoet\Newsletter\Renderer\Renderer as NewsletterRenderer;
use MailPoet\Newsletter\Sending\SendingQueuesRepository;
use MailPoet\Newsletter\Shortcodes\Shortcodes as NewsletterShortcodes;
use MailPoet\WP\Functions as WPFunctions;
use MailPoetVendor\csstidy;

/**
 * @group woo
 */
class RendererTest extends \MailPoetTest {
  /** @var NewsletterEntity */
  private $newsletter;

  /** @var NewslettersRepository */
  private $newslettersRepository;

  public function _before() {
    parent::_before();
    $this->newsletter = new NewsletterEntity();
    $this->newslettersRepository = $this->diContainer->get(NewslettersRepository::class);
    $this->newsletter->setSubject('WooCommerce Transactional Email');
    $this->newsletter->setType(NewsletterEntity::TYPE_WC_TRANSACTIONAL_EMAIL);
    $this->newsletter->setPreheader('');
    $this->newsletter->setBody([
      'content' => L::col([
        L::row([L::col([['type' => 'text', 'text' => 'Some text before heading']])]),
        ['type' => 'woocommerceHeading'],
        L::row([L::col([['type' => 'text', 'text' => 'Some text between heading and content']])]),
        ['type' => 'woocommerceContent'],
        L::row([L::col([['type' => 'text', 'text' => 'Some text after content']])]),
      ]),
    ]);
  }

  public function testGetHTMLBeforeContent() {
    $renderer = $this->getRenderer();
    $renderer->render($this->newsletter, 'Heading Text');
    $html = $renderer->getHTMLBeforeContent();
    expect($html)->stringContainsString('Some text before heading');
    expect($html)->stringContainsString('Heading Text');
    expect($html)->stringContainsString('Some text between heading and content');
    expect($html)->stringNotContainsString('Some text after content');
  }

  public function testGetHTMLAfterContent() {
    $renderer = $this->getRenderer();
    $renderer->render($this->newsletter, 'Heading Text');
    $html = $renderer->getHTMLAfterContent();
    expect($html)->stringNotContainsString('Some text before heading');
    expect($html)->stringNotContainsString('Heading Text');
    expect($html)->stringNotContainsString('Some text between heading and content');
    expect($html)->stringContainsString('Some text after content');
  }

  public function testRenderHeadingTextWhenHeadingBlockMovedToFooter() {
    $this->newsletter->setBody([
      'content' => L::col([
        L::row([L::col([['type' => 'text', 'text' => 'Some text before heading']])]),
        L::row([L::col([['type' => 'text', 'text' => 'Some text between heading and content']])]),
        ['type' => 'woocommerceContent'],
        ['type' => 'woocommerceHeading'],
        L::row([L::col([['type' => 'text', 'text' => 'Some text after content']])]),
      ]),
    ]);
    $this->newslettersRepository->persist($this->newsletter);
    $renderer = $this->getRenderer();
    $renderer->render($this->newsletter, 'Heading Text');
    $html = $renderer->getHTMLAfterContent();
    expect($html)->stringContainsString('Heading Text');
    expect($html)->stringContainsString('Some text after content');
  }

  public function testPrefixCss() {
    $renderer = $this->getRenderer(true);
    $css = $renderer->prefixCss('
      #some_id {color: black}
      .some-class {height: 50px; width: 30px}
      h1 {
        font-weight:bold;
      }
    ');
    expect($css)->stringContainsString("#mailpoet_woocommerce_container #some_id {\ncolor:black\n}");
    expect($css)->stringContainsString("#mailpoet_woocommerce_container .some-class {\nheight:50px;\nwidth:30px\n}");
    expect($css)->stringContainsString("#mailpoet_woocommerce_container h1 {\nfont-weight:700\n}");
  }

  public function testItReplaceShortcodes() {
    $this->newsletter->setBody([
      'content' => L::col([
        L::row([L::col([['type' => 'text', 'text' => 'Some text before heading']])]),
        L::row([L::col([['type' => 'text', 'text' => 'Some text between heading and content']])]),
        ['type' => 'woocommerceContent'],
        ['type' => 'woocommerceHeading'],
        L::row([L::col([['type' => 'text', 'text' => 'Some text after content']])]),
        L::row([L::col([
          ['type' => 'text', 'text' => '[site:title]'],
          ['type' => 'text', 'text' => '[site:homepage_url]'],
          ['type' => 'text', 'text' => '[date:mtext]'],
          ['type' => 'text', 'text' => '[date:y]'],
        ])]),
      ]),
    ]);
    $this->newslettersRepository->persist($this->newsletter);
    $renderer = $this->getRenderer();
    $renderer->render($this->newsletter, 'Heading Text');
    $html = $renderer->getHTMLAfterContent();

    $siteName = strval(get_option('blogname'));
    expect($html)->stringContainsString($siteName); // [site:title]

    $siteUrl = strval(get_option('home'));
    expect($html)->stringContainsString($siteUrl); // [site:homepage_url]

    expect($html)->stringContainsString(date_i18n('F', WPFunctions::get()->currentTime('timestamp'))); // [date:mtext]
    expect($html)->stringContainsString(date_i18n('Y', WPFunctions::get()->currentTime('timestamp'))); // [date:y]
  }

  private function getNewsletterRenderer(): NewsletterRenderer {
    $wooPreprocessor = new ContentPreprocessor(Stub::make(
      \MailPoet\WooCommerce\TransactionalEmails::class,
      [
        'getWCEmailSettings' => [
          'base_text_color' => '',
          'base_color' => '',
        ],
      ]
    ));
    return new NewsletterRenderer(
      $this->diContainer->get(\MailPoet\Newsletter\Renderer\Blocks\Renderer::class),
      $this->diContainer->get(\MailPoet\Newsletter\Renderer\Columns\Renderer::class),
      new Preprocessor(
        $this->diContainer->get(\MailPoet\Newsletter\Renderer\Blocks\AbandonedCartContent::class),
        $this->diContainer->get(\MailPoet\Newsletter\Renderer\Blocks\AutomatedLatestContentBlock::class),
        $wooPreprocessor,
        $this->diContainer->get(\MailPoet\WooCommerce\CouponPreProcessor::class)
      ),
      $this->diContainer->get(\MailPoetVendor\CSS::class),
      $this->diContainer->get(ServicesChecker::class),
      $this->diContainer->get(WPFunctions::class),
      $this->diContainer->get(LoggerFactory::class),
      $this->diContainer->get(NewslettersRepository::class),
      $this->diContainer->get(SendingQueuesRepository::class)
    );
  }

  private function getRenderer($useNewsletterDI = false) {
    $newsletterRenderer = $useNewsletterDI ? $this->diContainer->get(NewsletterRenderer::class) : $this->getNewsletterRenderer();
    return new Renderer(
      new csstidy,
      $newsletterRenderer,
      $this->diContainer->get(NewsletterShortcodes::class)
    );
  }
}
