<?php

declare(strict_types=1);

/**
 * WriteAssist AddOn for REDAXO
 * 
 * Provides translation (DeepL) and text improvement (LanguageTool) services
 * Can be used standalone or integrated into Info Center
 */

$addon = rex_addon::get('writeassist');

if (rex::isBackend() && rex::getUser()) {
    // Register as Info Center Widget if info_center addon is available and enabled
    if ($addon->getConfig('enable_infocenter_widget', true) && rex_addon::get('info_center')->isAvailable() && class_exists(\KLXM\InfoCenter\InfoCenter::class)) {
        $infoCenter = \KLXM\InfoCenter\InfoCenter::getInstance();
        $widget = new \FriendsOfREDAXO\WriteAssist\WriteAssistWidget();
        $widget->setPriority(1);  // After system widgets
        $infoCenter->registerWidget($widget);
    }

    // Add assets to backend
    rex_view::addCssFile($addon->getAssetsUrl('css/writeassist.css'));
    rex_view::addJsFile($addon->getAssetsUrl('js/writeassist.js'));
}

// Register TinyMCE Plugin if TinyMCE addon is available and enabled
if (rex::isBackend() && rex::getUser() && $addon->getConfig('enable_tinymce_plugin', true) && rex_addon::get('tinymce')->isAvailable()) {
    // Register DeepL translation plugin for TinyMCE
    // Use rex_url::base() for absolute paths that TinyMCE external_plugins requires
    if (class_exists(\FriendsOfRedaxo\TinyMce\PluginRegistry::class)) {
        \FriendsOfRedaxo\TinyMce\PluginRegistry::addPlugin(
            'writeassist_translate',
            rex_url::base('assets/addons/writeassist/js/tinymce-deepl-plugin.js'),
            'writeassist_translate'
        );
    }
}
