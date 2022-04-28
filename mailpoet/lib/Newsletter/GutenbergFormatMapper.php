<?php declare(strict_types=1);

namespace MailPoet\Newsletter;

use MailPoet\Newsletter\GutenbergFormat\Divider;
use MailPoet\Newsletter\GutenbergFormat\Spacer;
use MailPoet\Util\pQuery\pQuery;

class GutenbergFormatMapper {
  const NEWSLETTER_WIDTH = 600;

  public function map(array $body): string {
    $blocks = $body['content']['blocks'] ?? [];
    $xxx = $this->mapBlocks($blocks);
    return $xxx;
  }

  private function mapBlocks(array $blocks): string {
    $result = '';
    foreach ($blocks as $block) {
      switch ($block['type']) {
        case 'container':
          if ($block['orientation'] === 'horizontal') {
            $result .= '<!-- wp:columns --><div class="wp-block-columns">' . $this->mapBlocks($block['blocks']) . '</div><!-- /wp:columns -->';
            break;
          }
          $result .= '<!-- wp:column --><div class="wp-block-column">' . $this->mapBlocks($block['blocks']) . '</div><!-- /wp:column -->';
          break;
        case 'footer':
          $result .= $this->mapFooter($block);
          break;
        case 'header':
          $result .= '<!-- wp:mailpoet/header --><p class="wp-block-mailpoet-header">' . str_replace(["</p>\n<p>", "\n", '<p>', '</p>'], ['<br/>', '<br/>', '', ''], $block['text']) . '</p><!-- /wp:mailpoet/header -->';
          break;
        case 'button':
          $result .= $this->mapButton($block);
          break;
        case 'text':
          $result .= $this->mapText($block);
          break;
        case 'image':
          $result .= $this->mapImage($block);
          break;
        case 'divider':
          $result .= $this->mapDivider($block);
          break;
        case 'spacer':
          $result .= $this->mapSpacer($block);
          break;
        case 'social':
          $result .= $this->mapSocialIcons($block);
          break;
        default:
          $result .= '<!-- wp:mailpoet/todo {"originalBlock":"' . $block['type'] . '"} /-->';
      }
    }
    return $result;
  }

  private function mapFooter(array $block): string {
    return '<!-- wp:mailpoet/footer {"className":"wp-block-mailpoet-footer"} -->' . $this->mapText($block) . '<!-- /wp:mailpoet/footer -->';
  }

  private function mapText(array $block): string {
    $parsed = pQuery::parseStr($block['text']);
    $result = '';
    $childCount = $parsed->childCount(true);
    for ($i = 0; $i < $childCount; $i++) {
      $child = $parsed->getChild($i, true);
      switch ($child->getTag()) {
        case 'p':
          $result .= '<!-- wp:paragraph -->' . $child->toString() . '<!-- /wp:paragraph -->';
          break;
        case 'h1':
        case 'h2':
        case 'h3':
          $result .= '<!-- wp:heading -->' . $child->toString() . '<!-- /wp:heading -->';
          break;
        case 'ol':
          $result .= '<!-- wp:list {"ordered": true} -->' . $child->toString() . '<!-- /wp:list -->';
          break;
        case 'ul':
          $result .= '<!-- wp:list -->' . $child->toString() . '<!-- /wp:list -->';
          break;
        case 'blockquote':
          $result .= '<!-- wp:quote --><blockquote class="wp-block-quote">' . $child->getInnerText() . '</blockquote><!-- /wp:quote -->';
          break;
        default:
          $result .= '<!-- wp:html -->' . $child->toString() . '<!-- /wp:html -->';
          break;
      }
    }
    return $result;
  }

  /**
  "type": "text",
  "text": "<h2>Heading+1</h2>\n<p>Paragraph+with+text.+<strong>Bold</strong>.+<em>Itallic</em>.+<span+style=\"color:+#eb1c1c\">Custom+colored</span>.+<span+style=\"color:+#2dc26b\">Colored+from+selection</span>.+<a+href=\"https://example.com\"+title=\"Title+link\"+target=\"_blank\">Link</a></p>\n<p+style=\"text-align:+left\">Text+align+left.</p>\n<p+style=\"text-align:+right\">Text+align+right.</p>\n<p+style=\"text-align:+center\">Text+align+middle.</p>\n<p+style=\"text-align:+justify\">Text+aligned+justify.</p>\n<blockquote>\n<p+style=\"text-align:+justify\">Quote+text</p>\n</blockquote>\n<ol>\n<li+style=\"text-align:+justify\">Numbered+list</li>\n<li+style=\"text-align:+justify\">Numbered+list</li>\n</ol>\n<ul>\n<li>bullet+list\n<ul>\n<li>nested+bullet+list</li>\n</ul>\n</li>\n</ul>\n<p></p>\n<p+style=\"text-align:+center\"><a+href=\"[link:subscription_unsubscribe_url]\"+target=\"_blank\">Unsubscribe</a></p>"
  },
   */

  private function mapButton(array $block): string {
    $blockStyles = $block['styles']['block'];
    $attributes = [];
    $width = intval(str_replace('px', '', $blockStyles['width'] ?? ''));
    $fontSize = intval(str_replace('px', '', $blockStyles['fontSize'] ?? ''));
    $lineHeight = intval(str_replace('px', '', $blockStyles['lineHeight'] ?? ''));
    // Approx pixel width to container width. So far only container considered is full width.
    // Todo: use width of nested container
    $attributes['width'] = ceil(($width / self::NEWSLETTER_WIDTH) * 4) * 25;
    $renderWidth = self::NEWSLETTER_WIDTH * ($attributes['width'] / 100);
    $styles = [];
    $styles['border'] = [
      'radius' => $blockStyles['borderRadius'],
      'style' => $blockStyles['borderStyle'],
      'width' => $blockStyles['borderWidth'],
      'color' => $blockStyles['borderColor'],
    ];
    $styles['spacing']['padding'] = [
      'left' => strval(ceil(($width - $renderWidth) / 2)) . 'px',
      'right' => strval(ceil(($width - $renderWidth) / 2)) . 'px',
      'top' => strval(ceil(($lineHeight - ($fontSize * 1.8)) / 2)) . 'px',
      'bottom' => strval(ceil(($lineHeight - ($fontSize * 1.8)) / 2)) . 'px',
    ];
    $styles['typography']['fontSize'] = $blockStyles['fontSize'];
    $styles['color'] = [
      'background' => $blockStyles['backgroundColor'],
      'text' => $blockStyles['fontColor'],
    ];
    $attributes['style'] = $styles;
    $linkStyles = [
      "border-radius:{$blockStyles['borderRadius']}",
      "border-color:{$blockStyles['borderColor']}",
      "border-style:{$blockStyles['borderStyle']}",
      "border-width:{$blockStyles['borderWidth']}",
      "background-color:{$blockStyles['backgroundColor']}",
      "color:{$blockStyles['fontColor']}",
      "padding-top:{$styles['spacing']['padding']['top']}",
      "padding-right:{$styles['spacing']['padding']['right']}",
      "padding-bottom:{$styles['spacing']['padding']['bottom']}",
      "padding-left:{$styles['spacing']['padding']['left']}",
    ];
    $buttonStyles = [
      "font-size:{$blockStyles['fontSize']}",
    ];
    $linkClasses = ['wp-block-button__link'];
    if (isset($blockStyles['fontColor'])) {
      $linkClasses[] = 'has-text-color';
    }
    if (isset($blockStyles['backgroundColor'])) {
      $linkClasses[] = 'has-background';
    }
    if (isset($blockStyles['borderColor'])) {
      $linkClasses[] = 'has-border-color';
    }
    $buttonClasses = ['wp-block-button'];
    $buttonClasses[] = 'has-custom-width';
    $buttonClasses[] = 'wp-block-button__width-' . $attributes['width'];
    if (isset($blockStyles['fontSize'])) {
      $buttonClasses[] = 'has-custom-font-size';
    }
    $result = '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button ' . json_encode($attributes) . ' --><div class="' . esc_attr(join(' ', $buttonClasses)) . '" style="' . esc_attr(join(';', $buttonStyles)) . '"><a class="' . esc_attr(join(' ', $linkClasses)) . '" style="' . esc_attr(join(';', $linkStyles)) . '">' . $block['text'] . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
    return $result;
  }

  private function mapImage(array $block): string {
    $src = esc_url($block['src']);
    $attributes = [
      'linkDestination' => 'none',
      'alignment' => 'center',
      'id' => 15, // @Todo: fill with correct data
      'sizeSlug' => 'large', // @Todo fill with correct data
      'width' => false,
      'height' => false,
    ];

    // to be filled
    $size = 'size-large';// @Todo: fill with correct data

    $width = '';
    if (isset($block['width']) && $block['fullWidth'] === true) {
      $attributes['width'] = floatval(esc_attr($block['width']));
      $width = sprintf('width="%s"', $attributes['width']);
    }

    $height = '';
    if (isset($block['height'])) { // @Todo: figure out if height should be added ( if so, how to calculate correct height? )
      //$attributes['height'] = floatval(esc_attr($block['height']));
      //$height = sprintf('height="%s"', $attributes['height']);
    }

    $alt = "";
    if (!empty($block['alt'])) {
      $alt = esc_attr($block['alt']);
    }

    $image = strtr('<img src="%src" alt="%alt" %width %height class="wp-image-%imageId"/>', [
      '%src' => $src,
      '%alt' => $alt,
      '%width' => $width,
      '%imageId' => $attributes['id'],
    ]);

    if (!empty($block['link'])) {
      $link = esc_url($block['link']);
      $image = strtr('<a href="%link">%image</a>', [
        '%link' => $link,
        '%image' => $image,
      ]);
      $attributes['linkDestination'] = 'custom';
    }

    $alignmentCls = 'aligncenter';
    if (isset($block['styles'], $block['styles']['block'], $block['styles']['block']['textAlign'])) {
      $attributes['alignment'] = esc_attr($block['styles']['block']['textAlign']);
      $alignmentCls = "align{$attributes['alignment']}";
    }

    $attributes = array_filter($attributes);

    return strtr('<!-- wp:image %attributes -->
      <figure class="wp-block-image %size %extraCls %alignmentCls">%img</figure><!-- /wp:image -->',
      [
        '%attributes' => json_encode($attributes),
        '%img' => $image,
        '%size' => $size,
        '%alignmentCls' => $alignmentCls,
        '%extraCls' => $block['fullWidth'] === false ? 'is-resized' : '',
      ]);
  }

  /**
   * Receives divider block settings and converts them to core/separator settings
   * @param array $block
   * @return string
   */
  private function mapDivider(array $block): string {
    $divider = new Divider($block);
    return strtr('<!-- wp:separator %attributes --><hr class="wp-block-separator %classNames"/><!-- /wp:separator -->', [
        '%attributes' => \json_encode($divider->getAttributes()),
        '%classNames' => $divider->getClassNames(),
      ]
    );
  }

  private function mapSpacer(array $block): string {
    return (new Spacer($block))->getBlockMarkup();
  }

  private function mapSocialIcons(array $block): string {
    $result = '<!-- wp:social-links {"layout":{"type":"flex","justifyContent":"' . $block['styles']['block']['textAlign'] . '"}} --><ul class="wp-block-social-links">';
    foreach ($block['icons'] as $icon) {
      $linkAttributes = [
        'service' => $icon['iconType'], // Todo may not work for all icon types
      ];
      if (!empty($icon['link'])) {
        $linkAttributes['url'] = $icon['link'];
      }
      if (!empty($icon['text'])) {
        $linkAttributes['label'] = $icon['text'];
      }
      $result .= '<!-- wp:social-link ' . json_encode($linkAttributes) . ' /-->';
    }
    $result .= '</ul><!-- /wp:social-links -->';
    return $result;

  }
}
