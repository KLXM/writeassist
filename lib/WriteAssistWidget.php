<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\WriteAssist;

use rex_addon;
use rex_i18n;
use rex_url;

// Only define if InfoCenter is available
if (!class_exists(\KLXM\InfoCenter\AbstractWidget::class)) {
    return;
}

/**
 * WriteAssist Widget for Info Center
 * Provides translation (DeepL) and text improvement (LanguageTool)
 * 
 * @phpstan-ignore-next-line
 */
class WriteAssistWidget extends \KLXM\InfoCenter\AbstractWidget
{
    protected bool $supportsLazyLoading = false;

    public function __construct()
    {
        parent::__construct();
        $this->title = '🪶 ' . rex_i18n::msg('writeassist_widget_title');
        $this->priority = 15;
    }

    public function render(): string
    {
        $package = rex_addon::get('writeassist');
        $deeplApiKey = $package->getConfig('api_key', '');
        
        $content = '
        <div class="writeassist-widget">
            <!-- Tabs -->
            <div class="writeassist-tabs">
                <button type="button" class="writeassist-tab active" data-tab="translate">
                    🌐 ' . rex_i18n::msg('writeassist_tab_translate') . '
                </button>
                <button type="button" class="writeassist-tab" data-tab="improve">
                    ✨ ' . rex_i18n::msg('writeassist_tab_improve') . '
                </button>
            </div>
            
            <!-- Translate Tab -->
            <div class="writeassist-tab-content active" data-tab="translate">
                ' . $this->renderTranslateTab($deeplApiKey) . '
            </div>
            
            <!-- Improve Tab -->
            <div class="writeassist-tab-content" data-tab="improve">
                ' . $this->renderImproveTab() . '
            </div>
        </div>
        ';
        
        return $this->wrapContent($content);
    }
    
    private function renderTranslateTab(string $deeplApiKey): string
    {
        if (empty($deeplApiKey)) {
            $settingsUrl = rex_url::backendPage('writeassist/settings');
            return '
                <div class="writeassist-alert warning">
                    ' . rex_i18n::msg('writeassist_no_deepl_key') . '
                    <a href="' . $settingsUrl . '">' . rex_i18n::msg('writeassist_settings') . '</a>
                </div>
            ';
        }
        
        return '
            <div class="writeassist-form-group">
                <textarea class="writeassist-input writeassist-source" rows="3" placeholder="' . rex_i18n::msg('writeassist_enter_text') . '"></textarea>
            </div>
            
            <div class="writeassist-controls">
                <select class="writeassist-select writeassist-source-lang">
                    <option value="">Auto</option>
                    <option value="DE">DE</option>
                    <option value="EN">EN</option>
                    <option value="FR">FR</option>
                    <option value="ES">ES</option>
                    <option value="IT">IT</option>
                    <option value="NL">NL</option>
                    <option value="PL">PL</option>
                    <option value="PT">PT</option>
                    <option value="RU">RU</option>
                    <option value="JA">JA</option>
                    <option value="ZH">ZH</option>
                </select>
                <span class="writeassist-arrow">→</span>
                <select class="writeassist-select writeassist-target-lang">
                    <option value="DE">DE</option>
                    <option value="EN" selected>EN</option>
                    <option value="FR">FR</option>
                    <option value="ES">ES</option>
                    <option value="IT">IT</option>
                    <option value="NL">NL</option>
                    <option value="PL">PL</option>
                    <option value="PT">PT</option>
                    <option value="RU">RU</option>
                    <option value="JA">JA</option>
                    <option value="ZH">ZH</option>
                    <option value="KO">KO</option>
                    <option value="CS">CS</option>
                    <option value="DA">DA</option>
                    <option value="EL">EL</option>
                    <option value="FI">FI</option>
                    <option value="HU">HU</option>
                    <option value="ID">ID</option>
                    <option value="NB">NB</option>
                    <option value="RO">RO</option>
                    <option value="SK">SK</option>
                    <option value="SV">SV</option>
                    <option value="TR">TR</option>
                    <option value="UK">UK</option>
                </select>
                <button type="button" class="writeassist-btn primary writeassist-translate-btn">
                    ' . rex_i18n::msg('writeassist_translate') . '
                </button>
            </div>
            
            <div class="writeassist-result writeassist-translate-result" style="display:none;">
                <div class="writeassist-form-group">
                    <textarea class="writeassist-input writeassist-target" rows="3" readonly></textarea>
                </div>
                <button type="button" class="writeassist-btn small writeassist-copy-btn">
                    ' . rex_i18n::msg('writeassist_copy') . '
                </button>
            </div>
            
            <div class="writeassist-message writeassist-translate-message" style="display:none;"></div>
        ';
    }
    
    private function renderImproveTab(): string
    {
        return '
            <div class="writeassist-form-group">
                <textarea class="writeassist-input writeassist-improve-source" rows="3" placeholder="' . rex_i18n::msg('writeassist_enter_text_improve') . '"></textarea>
            </div>
            
            <div class="writeassist-controls">
                <select class="writeassist-select writeassist-improve-lang">
                    <option value="auto">Auto</option>
                    <option value="de-DE">DE</option>
                    <option value="de-AT">DE-AT</option>
                    <option value="de-CH">DE-CH</option>
                    <option value="en-US">EN-US</option>
                    <option value="en-GB">EN-GB</option>
                    <option value="fr">FR</option>
                    <option value="es">ES</option>
                    <option value="it">IT</option>
                    <option value="nl">NL</option>
                    <option value="pt-PT">PT</option>
                    <option value="pt-BR">PT-BR</option>
                    <option value="pl-PL">PL</option>
                    <option value="ru-RU">RU</option>
                    <option value="uk-UA">UK</option>
                </select>
                <label class="writeassist-checkbox">
                    <input type="checkbox" class="writeassist-picky-mode">
                    ' . rex_i18n::msg('writeassist_picky_mode') . '
                </label>
                <button type="button" class="writeassist-btn primary writeassist-improve-btn">
                    ' . rex_i18n::msg('writeassist_improve') . '
                </button>
            </div>
            
            <div class="writeassist-result writeassist-improve-result" style="display:none;">
                <div class="writeassist-matches"></div>
                <div class="writeassist-form-group">
                    <textarea class="writeassist-input writeassist-improved" rows="3" readonly></textarea>
                </div>
                <button type="button" class="writeassist-btn small writeassist-copy-improved-btn">
                    ' . rex_i18n::msg('writeassist_copy') . '
                </button>
            </div>
            
            <div class="writeassist-message writeassist-improve-message" style="display:none;"></div>
        ';
    }
}
