<?php

namespace App\Services\Forms;

use App\Models\SubscriptionForm;
use App\Models\CustomField;

class FormBuilderService
{
    /**
     * Generate HTML embed code for the form
     */
    public function generateHtmlCode(SubscriptionForm $form): string
    {
        $styles = $this->compileStyles($form->styles ?? []);
        $fields = $this->buildFieldsHtml($form);
        $hiddenFields = $this->buildHiddenFields($form);
        $submitButton = $this->buildSubmitButton($form);
        $policyCheckbox = $form->require_policy ? $this->buildPolicyCheckbox($form) : '';
        $honeypot = $form->honeypot_enabled ? $this->buildHoneypot() : '';

        $formId = 'netsendo-form-' . $form->slug;

        return <<<HTML
<!-- NetSendo Subscription Form -->
<style>
{$styles}
</style>
<form id="{$formId}" class="netsendo-form" action="{$form->public_url}" method="POST">
    {$hiddenFields}
    {$honeypot}
    <div class="netsendo-fields">
        {$fields}
    </div>
    {$policyCheckbox}
    {$submitButton}
</form>
<!-- End NetSendo Form -->
HTML;
    }

    /**
     * Generate JavaScript embed code
     */
    public function generateJsCode(SubscriptionForm $form): string
    {
        $containerId = 'netsendo-form-' . $form->slug;
        $jsUrl = $form->js_url;

        return <<<HTML
<!-- NetSendo Form Container -->
<div id="{$containerId}"></div>
<script src="{$jsUrl}" async></script>
<!-- End NetSendo Form -->
HTML;
    }

    /**
     * Generate iFrame embed code
     */
    public function generateIframeCode(SubscriptionForm $form, array $options = []): string
    {
        $width = $options['width'] ?? '100%';
        $height = $options['height'] ?? '400';
        $iframeUrl = $form->iframe_url;

        return <<<HTML
<!-- NetSendo Form iFrame -->
<iframe
    src="{$iframeUrl}"
    width="{$width}"
    height="{$height}"
    frameborder="0"
    scrolling="no"
    style="border: none; overflow: hidden;"
    title="{$form->name}"
></iframe>
<!-- End NetSendo Form -->
HTML;
    }

    /**
     * Generate dynamic JavaScript that renders the form
     */
    public function generateDynamicJs(SubscriptionForm $form): string
    {
        $formHtml = addslashes($this->generateHtmlCode($form));
        $containerId = 'netsendo-form-' . $form->slug;

        return <<<JS
(function() {
    var container = document.getElementById('{$containerId}');
    if (!container) {
        console.error('NetSendo: Container #{$containerId} not found');
        return;
    }
    container.innerHTML = '{$formHtml}';

    // Add form submit handler for AJAX
    var form = container.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(form);
            var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            var originalText = submitBtn ? submitBtn.textContent || submitBtn.value : '';

            if (submitBtn) {
                submitBtn.disabled = true;
                if (submitBtn.tagName === 'BUTTON') {
                    submitBtn.textContent = 'Wysyłanie...';
                } else {
                    submitBtn.value = 'Wysyłanie...';
                }
            }

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        container.innerHTML = '<div class="netsendo-success">' + (data.message || 'Dziękujemy za zapisanie się!') + '</div>';
                    }
                } else {
                    alert(data.message || 'Wystąpił błąd. Spróbuj ponownie.');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        if (submitBtn.tagName === 'BUTTON') {
                            submitBtn.textContent = originalText;
                        } else {
                            submitBtn.value = originalText;
                        }
                    }
                }
            })
            .catch(function(error) {
                console.error('NetSendo form error:', error);
                alert('Wystąpił błąd połączenia. Spróbuj ponownie.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    if (submitBtn.tagName === 'BUTTON') {
                        submitBtn.textContent = originalText;
                    } else {
                        submitBtn.value = originalText;
                    }
                }
            });
        });
    }
})();
JS;
    }

    /**
     * Compile CSS from styles array
     */
    public function compileStyles(array $styles): string
    {
        $s = array_merge(SubscriptionForm::$defaultStyles, $styles);

        // Calculate RGBA background
        $bgColor = $this->hexToRgba($s['bgcolor'], ($s['bgcolor_opacity'] ?? 100) / 100);

        // Calculate box shadow
        $boxShadow = 'none';
        if (!empty($s['shadow_enabled'])) {
            $shadowColor = $this->hexToRgba($s['shadow_color'] ?? '#000000', ($s['shadow_opacity'] ?? 15) / 100);
            $shadowX = $s['shadow_x'] ?? 0;
            $shadowY = $s['shadow_y'] ?? 10;
            $shadowBlur = $s['shadow_blur'] ?? 20;
            $boxShadow = "{$shadowX}px {$shadowY}px {$shadowBlur}px {$shadowColor}";
        }

        // Calculate background (solid or gradient)
        $background = $bgColor;
        if (!empty($s['gradient_enabled'])) {
            $gradientDir = $s['gradient_direction'] ?? 'to bottom right';
            $gradientFrom = $s['gradient_from'] ?? '#6366F1';
            $gradientTo = $s['gradient_to'] ?? '#8B5CF6';
            $background = "linear-gradient({$gradientDir}, {$gradientFrom}, {$gradientTo})";
        }

        // Animation keyframes
        $animationCss = '';
        if (!empty($s['animation_enabled'])) {
            $animationType = $s['animation_type'] ?? 'fadeIn';
            $animationCss = $this->getAnimationCss($animationType);
        }

        // Glassmorphism backdrop filter for transparency
        $backdropFilter = '';
        if (($s['bgcolor_opacity'] ?? 100) < 100) {
            $backdropFilter = 'backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);';
        }

        return <<<CSS
{$animationCss}
.netsendo-form {
    font-family: {$s['font_family']} !important;
    background: {$background} !important;
    border: {$s['border_width']}px solid {$s['border_color']} !important;
    border-radius: {$s['border_radius']}px !important;
    padding: {$s['padding']}px !important;
    color: {$s['text_color']} !important;
    box-sizing: border-box !important;
    box-shadow: {$boxShadow} !important;
    {$backdropFilter}
}
.netsendo-form * {
    box-sizing: border-box !important;
}
.netsendo-fields {
    display: flex !important;
    flex-direction: column !important;
    gap: {$s['fields_vertical_margin']}px !important;
}
.netsendo-field {
    display: flex !important;
    flex-direction: column !important;
    gap: 6px !important;
}
.netsendo-field label {
    font-size: {$s['label_font_size']}px !important;
    font-weight: {$s['label_font_weight']} !important;
    color: {$s['text_color']} !important;
}
.netsendo-field input,
.netsendo-field select,
.netsendo-field textarea {
    height: {$s['field_height']}px !important;
    padding: 8px 12px !important;
    background-color: {$s['field_bgcolor']} !important;
    color: {$s['field_text']} !important;
    border: 1px solid {$s['field_border_color']} !important;
    border-radius: {$s['field_border_radius']}px !important;
    font-size: 14px !important;
    transition: all 0.2s ease !important;
    width: 100% !important;
}
.netsendo-field input:focus,
.netsendo-field select:focus,
.netsendo-field textarea:focus {
    outline: none !important;
    background-color: {$s['field_bgcolor_active']} !important;
    color: {$s['field_text_active']} !important;
    border-color: {$s['field_border_color_active']} !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
}
.netsendo-field input::placeholder {
    color: {$s['placeholder_color']} !important;
}
.netsendo-submit-wrap {
    margin-top: {$s['fields_vertical_margin']}px !important;
    text-align: {$s['submit_align']} !important;
}
.netsendo-submit {
    background-color: {$s['submit_color']} !important;
    color: {$s['submit_text_color']} !important;
    border: 1px solid {$s['submit_border_color']} !important;
    border-radius: {$s['submit_border_radius']}px !important;
    padding: {$s['submit_padding_v']}px {$s['submit_padding_h']}px !important;
    font-size: {$s['submit_font_size']}px !important;
    font-weight: {$s['submit_font_weight']} !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    width: {$this->getSubmitWidth($s)} !important;
}
.netsendo-submit:hover {
    background-color: {$s['submit_hover_color']} !important;
    transform: translateY(-1px) !important;
}
.netsendo-submit:disabled {
    opacity: 0.6 !important;
    cursor: not-allowed !important;
    transform: none !important;
}
.netsendo-policy {
    display: flex !important;
    align-items: flex-start !important;
    gap: 8px !important;
    margin-top: {$s['fields_vertical_margin']}px !important;
    font-size: 12px !important;
}
.netsendo-policy input[type="checkbox"] {
    width: 16px !important;
    height: 16px !important;
    margin-top: 2px !important;
}
.netsendo-policy a {
    color: {$s['submit_color']} !important;
}
.netsendo-success {
    padding: 20px !important;
    text-align: center !important;
    color: #059669 !important;
    background-color: #D1FAE5 !important;
    border-radius: {$s['border_radius']}px !important;
}
.netsendo-honey {
    position: absolute !important;
    left: -9999px !important;
    opacity: 0 !important;
    height: 0 !important;
    width: 0 !important;
}
CSS;
    }

    /**
     * Get submit button width CSS
     */
    protected function getSubmitWidth(array $styles): string
    {
        return ($styles['submit_full_width'] ?? true) ? '100%' : 'auto';
    }

    /**
     * Build HTML for form fields
     */
    public function buildFieldsHtml(SubscriptionForm $form): string
    {
        $fields = $form->fields ?? [];
        $labelPosition = $form->label_position;
        $showPlaceholders = $form->show_placeholders;
        $styles = array_merge(SubscriptionForm::$defaultStyles, $form->styles ?? []);

        $html = '';
        foreach ($fields as $field) {
            $html .= $this->buildSingleField($field, $labelPosition, $showPlaceholders, $styles);
        }

        return $html;
    }

    /**
     * Build HTML for a single field
     */
    protected function buildSingleField(array $field, string $labelPosition, bool $showPlaceholders, array $styles = []): string
    {
        $id = htmlspecialchars($field['id']);
        $type = $field['type'] ?? 'text';
        $label = htmlspecialchars($field['label'] ?? '');
        $placeholder = $showPlaceholders ? htmlspecialchars($field['placeholder'] ?? '') : '';
        $required = !empty($field['required']) ? 'required' : '';
        $requiredMark = !empty($field['required']) ? ' <span style="color: #EF4444 !important;">*</span>' : '';

        // Generate inline styles for label
        $labelInlineStyles = implode(' ', [
            "font-size: {$styles['label_font_size']}px !important;",
            "font-weight: {$styles['label_font_weight']} !important;",
            "color: {$styles['text_color']} !important;",
        ]);

        $labelHtml = $labelPosition !== 'hidden'
            ? "<label for=\"field-{$id}\" style=\"{$labelInlineStyles}\">{$label}{$requiredMark}</label>"
            : '';

        $inputName = $this->getInputName($id);
        $inputType = $this->getInputType($type);

        // Generate inline styles for input
        $inputInlineStyles = implode(' ', [
            "height: {$styles['field_height']}px !important;",
            "padding: 8px 12px !important;",
            "background-color: {$styles['field_bgcolor']} !important;",
            "color: {$styles['field_text']} !important;",
            "border: 1px solid {$styles['field_border_color']} !important;",
            "border-radius: {$styles['field_border_radius']}px !important;",
            "font-size: 14px !important;",
            "width: 100% !important;",
            "box-sizing: border-box !important;",
        ]);

        $inputHtml = "<input type=\"{$inputType}\" id=\"field-{$id}\" name=\"{$inputName}\" placeholder=\"{$placeholder}\" style=\"{$inputInlineStyles}\" {$required}>";

        return <<<HTML
<div class="netsendo-field">
    {$labelHtml}
    {$inputHtml}
</div>
HTML;
    }


    /**
     * Get input name for field
     */
    protected function getInputName(string $fieldId): string
    {
        // Standard fields
        $standardFields = ['email', 'fname', 'lname', 'phone'];
        if (in_array($fieldId, $standardFields)) {
            return $fieldId;
        }

        // Custom fields use fields[id] format
        return "fields[{$fieldId}]";
    }

    /**
     * Get HTML input type
     */
    protected function getInputType(string $fieldType): string
    {
        return match ($fieldType) {
            'email' => 'email',
            'phone' => 'tel',
            'number' => 'number',
            'date' => 'date',
            default => 'text',
        };
    }

    /**
     * Build hidden fields
     */
    protected function buildHiddenFields(SubscriptionForm $form): string
    {
        $csrf = csrf_token();
        return <<<HTML
<input type="hidden" name="_token" value="{$csrf}">
HTML;
    }

    /**
     * Build submit button
     */
    protected function buildSubmitButton(SubscriptionForm $form): string
    {
        $text = htmlspecialchars($form->styles['submit_text'] ?? 'Zapisz się');
        $s = array_merge(SubscriptionForm::$defaultStyles, $form->styles ?? []);

        // Generate inline styles for maximum CSS protection
        $inlineStyles = implode(' ', [
            "background-color: {$s['submit_color']} !important;",
            "color: {$s['submit_text_color']} !important;",
            "border: 1px solid {$s['submit_border_color']} !important;",
            "border-radius: {$s['submit_border_radius']}px !important;",
            "padding: {$s['submit_padding_v']}px {$s['submit_padding_h']}px !important;",
            "font-size: {$s['submit_font_size']}px !important;",
            "font-weight: {$s['submit_font_weight']} !important;",
            "cursor: pointer !important;",
            "width: " . ($s['submit_full_width'] ? '100%' : 'auto') . " !important;",
        ]);

        return <<<HTML
<div class="netsendo-submit-wrap">
    <button type="submit" class="netsendo-submit" style="{$inlineStyles}">{$text}</button>
</div>
HTML;
    }


    /**
     * Build policy checkbox
     */
    protected function buildPolicyCheckbox(SubscriptionForm $form): string
    {
        $url = htmlspecialchars($form->policy_url ?? '#');

        return <<<HTML
<div class="netsendo-policy">
    <input type="checkbox" id="netsendo-policy" name="policy" value="1" required>
    <label for="netsendo-policy">
        Akceptuję <a href="{$url}" target="_blank">politykę prywatności</a>
    </label>
</div>
HTML;
    }

    /**
     * Build honeypot field (anti-spam)
     */
    protected function buildHoneypot(): string
    {
        return <<<HTML
<div class="netsendo-honey" aria-hidden="true">
    <label for="netsendo-website">Zostaw to pole puste</label>
    <input type="text" id="netsendo-website" name="website" tabindex="-1" autocomplete="off">
</div>
HTML;
    }

    /**
     * Validate form structure
     */
    public function validateStructure(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Nazwa formularza jest wymagana';
        }

        if (empty($data['contact_list_id'])) {
            $errors['contact_list_id'] = 'Wybierz listę mailingową';
        }

        if (empty($data['fields'])) {
            $errors['fields'] = 'Formularz musi zawierać co najmniej jedno pole';
        } else {
            // Check if email field exists
            $hasEmail = collect($data['fields'])->contains('id', 'email');
            if (!$hasEmail) {
                $errors['fields'] = 'Formularz musi zawierać pole Email';
            }
        }

        return $errors;
    }

    /**
     * Get available field types
     */
    public function getAvailableFields(): array
    {
        $standardFields = [
            [
                'id' => 'email',
                'type' => 'email',
                'label' => 'Adres e-mail',
                'placeholder' => 'Wpisz swój adres e-mail',
                'required' => true,
                'removable' => false,
            ],
            [
                'id' => 'fname',
                'type' => 'text',
                'label' => 'Imię',
                'placeholder' => 'Wpisz swoje imię',
                'required' => false,
                'removable' => true,
            ],
            [
                'id' => 'lname',
                'type' => 'text',
                'label' => 'Nazwisko',
                'placeholder' => 'Wpisz swoje nazwisko',
                'required' => false,
                'removable' => true,
            ],
            [
                'id' => 'phone',
                'type' => 'phone',
                'label' => 'Telefon',
                'placeholder' => 'Wpisz numer telefonu',
                'required' => false,
                'removable' => true,
            ],
        ];

        // Get custom fields (only public ones visible in forms)
        $customFields = CustomField::public()
            ->orderBy('name')
            ->get()
            ->map(function ($field) {
                return [
                    'id' => 'custom_' . $field->id,
                    'type' => $field->type,
                    'label' => $field->name,
                    'placeholder' => $field->name,
                    'required' => false,
                    'removable' => true,
                    'custom_field_id' => $field->id,
                ];
            })
            ->toArray();

        return [
            'standard' => $standardFields,
            'custom' => $customFields,
        ];
    }

    /**
     * Convert hex color to RGBA string
     */
    protected function hexToRgba(string $hex, float $alpha = 1): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        if ($alpha >= 1) {
            return "rgb({$r}, {$g}, {$b})";
        }

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    /**
     * Get animation CSS keyframes and rules
     */
    protected function getAnimationCss(string $type): string
    {
        $animations = [
            'fadeIn' => <<<CSS
@keyframes netsendoFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.netsendo-form { animation: netsendoFadeIn 0.4s ease-out; }
CSS,
            'slideUp' => <<<CSS
@keyframes netsendoSlideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.netsendo-form { animation: netsendoSlideUp 0.5s ease-out; }
CSS,
            'pulse' => <<<CSS
@keyframes netsendoPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}
.netsendo-form:hover { animation: netsendoPulse 2s ease-in-out infinite; }
CSS,
            'bounce' => <<<CSS
@keyframes netsendoBounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}
.netsendo-form { animation: netsendoBounce 0.6s ease-out; }
CSS,
        ];

        return $animations[$type] ?? '';
    }

    /**
     * Get design presets for frontend
     */
    public function getDesignPresets(): array
    {
        return SubscriptionForm::$designPresets;
    }
}
