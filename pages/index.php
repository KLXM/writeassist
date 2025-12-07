<?php

/**
 * WriteAssist - Main Page
 */

$package = rex_addon::get('writeassist');

echo rex_view::title($package->i18n('writeassist_title'));

// Include subpage
rex_be_controller::includeCurrentPageSubPath();
