<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\WriteAssist;

use Exception;
use rex_addon;
use rex_i18n;

/**
 * DeepL API Wrapper
 */
class DeeplApi
{
    private readonly string $apiKey;
    private readonly string $apiUrl;
    private readonly bool $isFreeApi;
    
    public function __construct(?string $apiKey = null)
    {
        $addon = rex_addon::get('writeassist');
        $this->apiKey = $apiKey ?? (string) $addon->getConfig('api_key', '');
        $this->isFreeApi = (bool) $addon->getConfig('use_free_api', true);
        
        // Free API uses api-free.deepl.com, Pro API uses api.deepl.com
        $this->apiUrl = $this->isFreeApi 
            ? 'https://api-free.deepl.com/v2'
            : 'https://api.deepl.com/v2';
    }
    
    /**
     * Translate text
     * 
     * @param string $text Text to translate
     * @param string $targetLang Target language code (e.g. 'DE', 'EN', 'FR')
     * @param string|null $sourceLang Source language code (null = auto-detect)
     * @param bool $preserveFormatting Whether to preserve HTML formatting
     * @return array{text: string, detected_source_language: string|null} Result with 'text' and 'detected_source_language'
     * @throws Exception on API error
     */
    public function translate(string $text, string $targetLang, ?string $sourceLang = null, bool $preserveFormatting = false): array
    {
        if (empty($this->apiKey)) {
            throw new Exception(rex_i18n::msg('writeassist_no_api_key'));
        }
        
        $params = [
            'text' => [$text],  // DeepL API expects text as array
            'target_lang' => strtoupper($targetLang),
        ];
        
        // Enable HTML tag handling to preserve formatting
        if ($preserveFormatting) {
            $params['tag_handling'] = 'html';
        }
        
        if ($sourceLang) {
            $params['source_lang'] = strtoupper($sourceLang);
        }
        
        $response = $this->makeRequest('/translate', $params);
        
        if (isset($response['translations'][0])) {
            return [
                'text' => $response['translations'][0]['text'],
                'detected_source_language' => $response['translations'][0]['detected_source_language'] ?? null
            ];
        }
        
        throw new Exception(rex_i18n::msg('writeassist_translation_failed'));
    }
    
    /**
     * Get available source languages
     * @return list<array{language: string, name: string}>
     */
    public function getSourceLanguages(): array
    {
        return $this->getLanguages('source');
    }
    
    /**
     * Get available target languages
     * @return list<array{language: string, name: string}>
     */
    public function getTargetLanguages(): array
    {
        return $this->getLanguages('target');
    }
    
    /**
     * Get usage statistics (character count, limit)
     * @return array{character_count: int, character_limit: int, error?: string}
     */
    public function getUsage(): array
    {
        if (empty($this->apiKey)) {
            return [
                'character_count' => 0,
                'character_limit' => 0,
                'error' => 'No API key configured'
            ];
        }
        
        try {
            $result = $this->makeRequest('/usage', []);
            return [
                'character_count' => (int) ($result['character_count'] ?? 0),
                'character_limit' => (int) ($result['character_limit'] ?? 0),
            ];
        } catch (Exception $e) {
            return [
                'character_count' => 0,
                'character_limit' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get languages
     * @return list<array{language: string, name: string}>
     */
    private function getLanguages(string $type = 'target'): array
    {
        if ($this->apiKey === '') {
            // Return default languages if no API key
            return $this->getDefaultLanguages();
        }
        
        try {
            $params = [
                'type' => $type
            ];
            
            $result = $this->makeRequest('/languages', $params);
            /** @var list<array{language: string, name: string}> */
            return $result;
        } catch (Exception $e) {
            return $this->getDefaultLanguages();
        }
    }
    
    /**
     * Make API request using rex_socket
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws Exception
     */
    private function makeRequest(string $endpoint, array $params): array
    {
        $url = $this->apiUrl . $endpoint;
        $jsonBody = json_encode($params);
        
        if ($jsonBody === false) {
            throw new Exception('Failed to encode request parameters');
        }
        
        // Use rex_socket for REDAXO-native HTTP requests
        $socket = \rex_socket::factoryUrl($url);
        $socket->addHeader('Authorization', 'DeepL-Auth-Key ' . $this->apiKey);
        $socket->addHeader('Content-Type', 'application/json');
        
        $response = $socket->doPost($jsonBody);
        
        if (!$response->isOk()) {
            $body = $response->getBody();
            $error = json_decode($body, true);
            $message = is_array($error) ? ($error['message'] ?? 'API Error: ' . $response->getStatusCode()) : 'API Error: ' . $response->getStatusCode();
            throw new Exception($message);
        }
        
        $body = $response->getBody();
        $decoded = json_decode($body, true);
        
        if (!is_array($decoded)) {
            throw new Exception('Invalid API response');
        }
        
        return $decoded;
    }
    
    /**
     * Default languages (fallback if API not available)
     * @return list<array{language: string, name: string}>
     */
    private function getDefaultLanguages(): array
    {
        return [
            ['language' => 'DE', 'name' => 'German'],
            ['language' => 'EN', 'name' => 'English'],
            ['language' => 'FR', 'name' => 'French'],
            ['language' => 'ES', 'name' => 'Spanish'],
            ['language' => 'IT', 'name' => 'Italian'],
            ['language' => 'NL', 'name' => 'Dutch'],
            ['language' => 'PL', 'name' => 'Polish'],
            ['language' => 'PT', 'name' => 'Portuguese'],
            ['language' => 'RU', 'name' => 'Russian'],
            ['language' => 'JA', 'name' => 'Japanese'],
            ['language' => 'ZH', 'name' => 'Chinese'],
        ];
    }
}
