<?php

namespace App\Services;

use App\Models\TemplateBlock;

class MjmlService
{
    /**
     * Convert JSON block structure to MJML markup
     */
    public function jsonToMjml(array $structure, array $settings = []): string
    {
        $blocks = $structure['blocks'] ?? [];
        $templateSettings = array_merge([
            'width' => 600,
            'background_color' => '#f4f4f4',
            'content_background' => '#ffffff',
            'font_family' => 'Arial, Helvetica, sans-serif',
            'primary_color' => '#6366f1',
            'text_color' => '#1e293b',
        ], $settings);

        $mjml = $this->generateMjmlWrapper($templateSettings, function() use ($blocks, $templateSettings) {
            $content = '';
            foreach ($blocks as $block) {
                $content .= $this->blockToMjml($block, $templateSettings);
            }
            return $content;
        });

        return $mjml;
    }

    /**
     * Generate MJML wrapper with head and body
     */
    protected function generateMjmlWrapper(array $settings, callable $bodyContent): string
    {
        $fontFamily = $settings['font_family'];
        $backgroundColor = $settings['background_color'];
        $contentBackground = $settings['content_background'];
        $textColor = $settings['text_color'];
        $width = $settings['width'];

        $darkMode = $settings['dark_mode'] ?? [];
        $darkBg = $darkMode['background_color'] ?? '#1e293b';
        $darkContentBg = $darkMode['content_background'] ?? '#0f172a';
        $darkText = $darkMode['text_color'] ?? '#f1f5f9';

        $body = $bodyContent();

        return <<<MJML
<mjml>
  <mj-head>
    <mj-attributes>
      <mj-all font-family="{$fontFamily}" />
      <mj-text font-size="16px" color="{$textColor}" line-height="1.6" />
      <mj-button font-size="16px" />
    </mj-attributes>
    <mj-style>
      .dark-mode { display: none; }
      @media (prefers-color-scheme: dark) {
        .light-mode { display: none !important; }
        .dark-mode { display: block !important; }
        .body-wrapper { background-color: {$darkBg} !important; }
        .content-wrapper { background-color: {$darkContentBg} !important; }
        .dark-text { color: {$darkText} !important; }
      }
    </mj-style>
    <mj-style inline="inline">
      a { color: inherit; }
    </mj-style>
  </mj-head>
  <mj-body background-color="{$backgroundColor}" width="{$width}px" css-class="body-wrapper">
    <mj-wrapper background-color="{$contentBackground}" css-class="content-wrapper">
      {$body}
    </mj-wrapper>
  </mj-body>
</mjml>
MJML;
    }

    /**
     * Convert a single block to MJML
     */
    protected function blockToMjml(array $block, array $settings): string
    {
        $type = $block['type'] ?? 'text';
        $content = $block['content'] ?? [];
        $blockSettings = $block['settings'] ?? [];

        return match ($type) {
            'header' => $this->headerToMjml($content, $blockSettings, $settings),
            'text' => $this->textToMjml($content, $blockSettings),
            'image' => $this->imageToMjml($content, $blockSettings),
            'button' => $this->buttonToMjml($content, $blockSettings),
            'divider' => $this->dividerToMjml($content, $blockSettings),
            'spacer' => $this->spacerToMjml($content, $blockSettings),
            'columns' => $this->columnsToMjml($content, $blockSettings, $settings),
            'product' => $this->productToMjml($content, $blockSettings, $settings),
            'product_grid' => $this->productGridToMjml($content, $blockSettings, $settings),
            'social' => $this->socialToMjml($content, $blockSettings),
            'footer' => $this->footerToMjml($content, $blockSettings),
            default => '',
        };
    }

    /**
     * Header block to MJML
     */
    protected function headerToMjml(array $content, array $blockSettings, array $templateSettings): string
    {
        $logo = $content['logo'] ?? null;
        $logoWidth = $content['logoWidth'] ?? 150;
        $backgroundColor = $content['backgroundColor'] ?? $templateSettings['primary_color'];
        $padding = $content['padding'] ?? '20px';
        $alignment = $content['alignment'] ?? 'center';

        $logoHtml = $logo 
            ? "<mj-image src=\"{$logo}\" width=\"{$logoWidth}px\" align=\"{$alignment}\" />"
            : '';

        return <<<MJML
    <mj-section background-color="{$backgroundColor}" padding="{$padding}">
      {$logoHtml}
    </mj-section>
MJML;
    }

    /**
     * Text block to MJML
     */
    protected function textToMjml(array $content, array $blockSettings): string
    {
        $html = $content['html'] ?? '';
        $padding = $content['padding'] ?? '20px';
        $alignment = $content['alignment'] ?? 'left';
        $backgroundColor = $blockSettings['backgroundColor'] ?? 'transparent';

        return <<<MJML
    <mj-section background-color="{$backgroundColor}" padding="0">
      <mj-column>
        <mj-text padding="{$padding}" align="{$alignment}" css-class="dark-text">
          {$html}
        </mj-text>
      </mj-column>
    </mj-section>
MJML;
    }

    /**
     * Image block to MJML
     */
    protected function imageToMjml(array $content, array $blockSettings): string
    {
        $src = $content['src'] ?? '';
        $alt = $content['alt'] ?? '';
        $href = $content['href'] ?? null;
        $width = $content['width'] ?? '100%';
        $alignment = $content['alignment'] ?? 'center';
        $padding = $content['padding'] ?? '10px';
        $borderRadius = $content['borderRadius'] ?? '0';

        if (empty($src)) {
            return '';
        }

        $hrefAttr = $href ? "href=\"{$href}\"" : '';

        return <<<MJML
    <mj-section padding="0">
      <mj-column>
        <mj-image src="{$src}" alt="{$alt}" width="{$width}" align="{$alignment}" padding="{$padding}" border-radius="{$borderRadius}" {$hrefAttr} />
      </mj-column>
    </mj-section>
MJML;
    }

    /**
     * Button block to MJML
     */
    protected function buttonToMjml(array $content, array $blockSettings): string
    {
        $text = $content['text'] ?? 'Kliknij';
        $href = $content['href'] ?? '#';
        $backgroundColor = $content['backgroundColor'] ?? '#6366f1';
        $textColor = $content['textColor'] ?? '#ffffff';
        $borderRadius = $content['borderRadius'] ?? '8px';
        $padding = $content['padding'] ?? '12px 24px';
        $alignment = $content['alignment'] ?? 'center';

        return <<<MJML
    <mj-section padding="10px 0">
      <mj-column>
        <mj-button href="{$href}" background-color="{$backgroundColor}" color="{$textColor}" border-radius="{$borderRadius}" inner-padding="{$padding}" align="{$alignment}">
          {$text}
        </mj-button>
      </mj-column>
    </mj-section>
MJML;
    }

    /**
     * Divider block to MJML
     */
    protected function dividerToMjml(array $content, array $blockSettings): string
    {
        $color = $content['color'] ?? '#e2e8f0';
        $width = $content['width'] ?? '100%';
        $padding = $content['padding'] ?? '10px 0';

        return <<<MJML
    <mj-section padding="{$padding}">
      <mj-column>
        <mj-divider border-color="{$color}" border-width="1px" width="{$width}" />
      </mj-column>
    </mj-section>
MJML;
    }

    /**
     * Spacer block to MJML
     */
    protected function spacerToMjml(array $content, array $blockSettings): string
    {
        $height = $content['height'] ?? '30px';

        return <<<MJML
    <mj-section padding="0">
      <mj-column>
        <mj-spacer height="{$height}" />
      </mj-column>
    </mj-section>
MJML;
    }

    /**
     * Columns block to MJML
     */
    protected function columnsToMjml(array $content, array $blockSettings, array $templateSettings): string
    {
        $columnsCount = $content['columns'] ?? 2;
        $columnBlocks = $content['columnBlocks'] ?? $content['children'] ?? [];
        $gap = $content['gap'] ?? '20px';

        $columnWidth = match ($columnsCount) {
            2 => '50%',
            3 => '33.33%',
            4 => '25%',
            default => '50%',
        };

        $columnsHtml = '';
        for ($i = 0; $i < $columnsCount; $i++) {
            $colBlocks = $columnBlocks[$i] ?? [];
            $columnContent = '';
            foreach ($colBlocks as $block) {
                // For nested blocks, we just add text/image directly without section wrapper
                $columnContent .= $this->nestedBlockToMjml($block);
            }
            
            if (empty($columnContent)) {
                $columnContent = '<mj-text>&nbsp;</mj-text>';
            }

            $columnsHtml .= <<<MJML
      <mj-column width="{$columnWidth}">
        {$columnContent}
      </mj-column>
MJML;
        }

        return <<<MJML
    <mj-section padding="20px">
      {$columnsHtml}
    </mj-section>
MJML;
    }

    /**
     * Nested block to MJML (without section wrapper)
     */
    protected function nestedBlockToMjml(array $block): string
    {
        $type = $block['type'] ?? 'text';
        $content = $block['content'] ?? [];

        return match ($type) {
            'text' => $this->nestedTextToMjml($content),
            'image' => $this->nestedImageToMjml($content),
            'button' => $this->nestedButtonToMjml($content),
            default => '',
        };
    }

    protected function nestedTextToMjml(array $content): string
    {
        $html = $content['html'] ?? '';
        $padding = $content['padding'] ?? '10px';
        return "<mj-text padding=\"{$padding}\" css-class=\"dark-text\">{$html}</mj-text>";
    }

    protected function nestedImageToMjml(array $content): string
    {
        $src = $content['src'] ?? '';
        $alt = $content['alt'] ?? '';
        return "<mj-image src=\"{$src}\" alt=\"{$alt}\" />";
    }

    protected function nestedButtonToMjml(array $content): string
    {
        $text = $content['text'] ?? 'Kliknij';
        $href = $content['href'] ?? '#';
        $backgroundColor = $content['backgroundColor'] ?? '#6366f1';
        return "<mj-button href=\"{$href}\" background-color=\"{$backgroundColor}\">{$text}</mj-button>";
    }

    /**
     * Product block to MJML
     */
    protected function productToMjml(array $content, array $blockSettings, array $templateSettings): string
    {
        $image = $content['image'] ?? '';
        $title = $content['title'] ?? 'Produkt';
        $description = $content['description'] ?? '';
        $price = $content['price'] ?? '0.00';
        $oldPrice = $content['oldPrice'] ?? null;
        $currency = $content['currency'] ?? 'PLN';
        $buttonText = $content['buttonText'] ?? 'Kup teraz';
        $buttonUrl = $content['buttonUrl'] ?? '#';
        $primaryColor = $templateSettings['primary_color'] ?? '#6366f1';

        $priceHtml = $oldPrice 
            ? "<span style=\"text-decoration: line-through; color: #94a3b8;\">{$oldPrice} {$currency}</span> <strong style=\"color: #ef4444;\">{$price} {$currency}</strong>"
            : "<strong>{$price} {$currency}</strong>";

        $imageHtml = $image ? "<mj-image src=\"{$image}\" padding=\"0 0 10px 0\" />" : '';

        return <<<MJML
    <mj-section background-color="#ffffff" padding="20px" border-radius="8px">
      <mj-column>
        {$imageHtml}
        <mj-text font-size="18px" font-weight="bold" padding="10px 0 5px 0" css-class="dark-text">
          {$title}
        </mj-text>
        <mj-text padding="0 0 10px 0" css-class="dark-text">
          {$description}
        </mj-text>
        <mj-text font-size="20px" padding="10px 0" css-class="dark-text">
          {$priceHtml}
        </mj-text>
        <mj-button href="{$buttonUrl}" background-color="{$primaryColor}" border-radius="8px">
          {$buttonText}
        </mj-button>
      </mj-column>
    </mj-section>
MJML;
    }

    /**
     * Product grid block to MJML
     */
    protected function productGridToMjml(array $content, array $blockSettings, array $templateSettings): string
    {
        $columns = $content['columns'] ?? 2;
        $products = $content['products'] ?? [];
        $primaryColor = $templateSettings['primary_color'] ?? '#6366f1';

        if (empty($products)) {
            return '';
        }

        $columnWidth = $columns === 2 ? '50%' : '33.33%';
        $productsHtml = '';

        foreach ($products as $product) {
            $image = $product['image'] ?? '';
            $title = $product['title'] ?? '';
            $price = $product['price'] ?? '';
            $currency = $product['currency'] ?? 'PLN';
            $buttonUrl = $product['buttonUrl'] ?? '#';

            $imageHtml = $image ? "<mj-image src=\"{$image}\" padding=\"0 0 10px 0\" />" : '';

            $productsHtml .= <<<MJML
      <mj-column width="{$columnWidth}" padding="10px">
        {$imageHtml}
        <mj-text font-weight="bold" align="center" css-class="dark-text">{$title}</mj-text>
        <mj-text align="center" css-class="dark-text">{$price} {$currency}</mj-text>
        <mj-button href="{$buttonUrl}" background-color="{$primaryColor}" font-size="14px">Kup</mj-button>
      </mj-column>
MJML;
        }

        return <<<MJML
    <mj-section padding="20px">
      {$productsHtml}
    </mj-section>
MJML;
    }

    /**
     * Social block to MJML
     */
    protected function socialToMjml(array $content, array $blockSettings): string
    {
        $icons = $content['icons'] ?? [];
        $iconSize = $content['iconSize'] ?? 32;
        $alignment = $content['alignment'] ?? 'center';
        $padding = $content['padding'] ?? '20px';

        if (empty($icons)) {
            return '';
        }

        $socialHtml = '';
        foreach ($icons as $icon) {
            $type = $icon['type'] ?? 'facebook';
            $url = $icon['url'] ?? '#';
            $socialHtml .= "<mj-social-element name=\"{$type}\" href=\"{$url}\" icon-size=\"{$iconSize}px\" />";
        }

        return <<<MJML
    <mj-section padding="{$padding}">
      <mj-column>
        <mj-social mode="horizontal" align="{$alignment}">
          {$socialHtml}
        </mj-social>
      </mj-column>
    </mj-section>
MJML;
    }

    /**
     * Footer block to MJML
     */
    protected function footerToMjml(array $content, array $blockSettings): string
    {
        $companyName = $content['companyName'] ?? '';
        $address = $content['address'] ?? '';
        $unsubscribeText = $content['unsubscribeText'] ?? 'Wypisz się';
        $unsubscribeUrl = $content['unsubscribeUrl'] ?? '{{unsubscribe_url}}';
        $copyright = $content['copyright'] ?? '';
        $backgroundColor = $content['backgroundColor'] ?? '#1e293b';
        $textColor = $content['textColor'] ?? '#94a3b8';
        $padding = $content['padding'] ?? '30px 20px';

        return <<<MJML
    <mj-section background-color="{$backgroundColor}" padding="{$padding}">
      <mj-column>
        <mj-text color="{$textColor}" align="center" font-size="14px">
          <strong>{$companyName}</strong><br/>
          {$address}
        </mj-text>
        <mj-text color="{$textColor}" align="center" font-size="12px" padding-top="15px">
          <a href="{$unsubscribeUrl}" style="color: {$textColor};">{$unsubscribeText}</a>
        </mj-text>
        <mj-text color="{$textColor}" align="center" font-size="12px" padding-top="10px">
          {$copyright}
        </mj-text>
      </mj-column>
    </mj-section>
MJML;
    }

    /**
     * Validate MJML structure
     */
    public function validate(string $mjml): array
    {
        $errors = [];

        if (!str_contains($mjml, '<mjml>')) {
            $errors[] = 'Missing <mjml> root tag';
        }

        if (!str_contains($mjml, '<mj-body')) {
            $errors[] = 'Missing <mj-body> tag';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
