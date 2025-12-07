<?php

/**
 * WriteAssist - Settings Page
 */

$package = rex_addon::get('writeassist');

// Single form for all settings
$form = rex_config_form::factory($package->getName());

// === DeepL Settings ===
$form->addRawField('<fieldset><legend>' . $package->i18n('writeassist_deepl_settings') . '</legend>');

// DeepL API Key
$field = $form->addInputField('text', 'api_key', null, ['class' => 'form-control']);
$field->setLabel($package->i18n('writeassist_api_key'));
$field->setNotice($package->i18n('writeassist_api_key_notice'));

// DeepL API Type
$field = $form->addSelectField('use_free_api');
$field->setLabel($package->i18n('writeassist_api_type'));
$select = $field->getSelect();
$select->addOption($package->i18n('writeassist_api_type_free'), '1');
$select->addOption($package->i18n('writeassist_api_type_pro'), '0');
$field->setNotice($package->i18n('writeassist_api_type_notice'));

$form->addRawField('</fieldset>');

// === LanguageTool Settings ===
$form->addRawField('<fieldset><legend>' . $package->i18n('writeassist_languagetool_settings') . '</legend>');

// Custom API URL (for self-hosted)
$field = $form->addInputField('text', 'languagetool_api_url', null, ['class' => 'form-control']);
$field->setLabel($package->i18n('writeassist_languagetool_url'));
$field->setNotice($package->i18n('writeassist_languagetool_url_notice'));

// Premium Username
$field = $form->addInputField('text', 'languagetool_username', null, ['class' => 'form-control']);
$field->setLabel($package->i18n('writeassist_languagetool_username'));

// Premium API Key
$field = $form->addInputField('text', 'languagetool_api_key', null, ['class' => 'form-control']);
$field->setLabel($package->i18n('writeassist_languagetool_api_key'));
$field->setNotice($package->i18n('writeassist_languagetool_api_key_notice'));

$form->addRawField('</fieldset>');

// === Google Gemini Settings ===
$form->addRawField('<fieldset><legend>' . $package->i18n('writeassist_gemini_settings') . '</legend>');

// Gemini API Key
$field = $form->addInputField('text', 'gemini_api_key', null, ['class' => 'form-control']);
$field->setLabel($package->i18n('writeassist_gemini_api_key'));
$field->setNotice($package->i18n('writeassist_gemini_api_key_notice'));

// Default Model
$field = $form->addSelectField('gemini_model');
$field->setLabel($package->i18n('writeassist_gemini_model'));
$select = $field->getSelect();
$select->addOption('Gemini 2.5 Flash (empfohlen)', 'gemini-2.5-flash');
$select->addOption('Gemini 2.5 Flash-Lite (schnellstes)', 'gemini-2.5-flash-lite');
$select->addOption('Gemini 2.5 Pro (beste Qualität)', 'gemini-2.5-pro');
$select->addOption('Gemini 3 Pro (Preview)', 'gemini-3-pro-preview');
$field->setNotice($package->i18n('writeassist_gemini_model_notice'));

$form->addRawField('</fieldset>');

// === Ignore List Settings ===
$form->addRawField('<fieldset><legend>' . $package->i18n('writeassist_ignore_settings') . '</legend>');

// Ignore words (one per line)
$field = $form->addTextAreaField('ignore_words', null, ['class' => 'form-control', 'rows' => 8]);
$field->setLabel($package->i18n('writeassist_ignore_words'));
$field->setNotice($package->i18n('writeassist_ignore_words_notice'));

$form->addRawField('</fieldset>');

// === Integration Settings ===
$form->addRawField('<fieldset><legend>' . $package->i18n('writeassist_integration_settings') . '</legend>');

// Info Center Widget
$field = $form->addSelectField('enable_infocenter_widget');
$field->setLabel($package->i18n('writeassist_enable_infocenter_widget'));
$select = $field->getSelect();
$select->addOption($package->i18n('writeassist_yes'), '1');
$select->addOption($package->i18n('writeassist_no'), '0');
$field->setNotice($package->i18n('writeassist_enable_infocenter_widget_notice'));

// TinyMCE Plugin
$field = $form->addSelectField('enable_tinymce_plugin');
$field->setLabel($package->i18n('writeassist_enable_tinymce_plugin'));
$select = $field->getSelect();
$select->addOption($package->i18n('writeassist_yes'), '1');
$select->addOption($package->i18n('writeassist_no'), '0');
$field->setNotice($package->i18n('writeassist_enable_tinymce_plugin_notice'));

$form->addRawField('</fieldset>');

// Render form
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $package->i18n('writeassist_settings'), false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
