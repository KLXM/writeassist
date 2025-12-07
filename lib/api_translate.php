<?php

declare(strict_types=1);

use FriendsOfREDAXO\WriteAssist\DeeplApi;

/**
 * API Endpoint for DeepL Translation
 */
class rex_api_writeassist_translate extends rex_api_function
{
    protected $published = true;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        $user = rex::getUser();
        if (!$user) {
            rex_response::setStatus(rex_response::HTTP_UNAUTHORIZED);
            rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        // Try POST first, fallback to REQUEST
        $text = rex_post('text', 'string', '') ?: rex_request('text', 'string', '');
        $targetLang = rex_post('target_lang', 'string', '') ?: rex_request('target_lang', 'string', 'EN');
        $sourceLang = rex_post('source_lang', 'string', '') ?: rex_request('source_lang', 'string', '');
        $preserveFormatting = rex_post('preserve_formatting', 'bool', false) || rex_request('preserve_formatting', 'bool', false);

        if ($text === '') {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            rex_response::sendJson(['error' => 'No text provided']);
            exit;
        }

        try {
            $api = new DeeplApi();
            $result = $api->translate($text, $targetLang, $sourceLang !== '' ? $sourceLang : null, $preserveFormatting);
            
            rex_response::sendJson([
                'success' => true,
                'translation' => $result['text'],
                'detected_source_language' => $result['detected_source_language']
            ]);
        } catch (Exception $e) {
            rex_response::setStatus(rex_response::HTTP_INTERNAL_ERROR);
            rex_response::sendJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }
}
