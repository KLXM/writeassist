<?php

declare(strict_types=1);

use FriendsOfREDAXO\WriteAssist\LanguageToolApi;

/**
 * API Endpoint for LanguageTool Text Improvement
 */
class rex_api_writeassist_improve extends rex_api_function
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

        $text = rex_post('text', 'string', '') ?: rex_request('text', 'string', '');
        $language = rex_post('language', 'string', '') ?: rex_request('language', 'string', '');
        $autoCorrect = rex_post('auto_correct', 'bool', false) || rex_request('auto_correct', 'bool', false);
        $picky = rex_post('picky', 'bool', false) || rex_request('picky', 'bool', false);

        // Default to auto if empty
        if ($language === '') {
            $language = 'auto';
        }

        if ($text === '') {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            rex_response::sendJson(['error' => 'No text provided']);
            exit;
        }

        try {
            $api = new LanguageToolApi();
            $result = $api->check($text, $language, ['picky' => $picky]);
            
            $response = [
                'success' => true,
                'matches' => $result['matches'],
                'language' => $result['language'],
                'match_count' => count($result['matches']),
            ];
            
            // Auto-correct if requested
            if ($autoCorrect && $result['matches'] !== []) {
                $response['corrected_text'] = $api->applyCorrections($text, $result['matches']);
            }
            
            rex_response::sendJson($response);
        } catch (\Exception $e) {
            rex_response::setStatus(rex_response::HTTP_INTERNAL_ERROR);
            rex_response::sendJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }
}
