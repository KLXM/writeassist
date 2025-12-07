<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\WriteAssist;

use rex_i18n;

/**
 * LanguageTool API Wrapper
 * 
 * Free grammar, spelling and style checker
 * API: https://languagetool.org/http-api/
 */
class LanguageToolApi
{
    private string $apiUrl = 'https://api.languagetool.org/v2';
    private ?string $apiKey = null;
    private ?string $username = null;
    
    public function __construct(?string $apiKey = null, ?string $username = null)
    {
        $addon = \rex_addon::get('writeassist');
        
        if ($addon->isAvailable()) {
            $this->apiKey = $apiKey ?? $addon->getConfig('languagetool_api_key', '') ?: null;
            $this->username = $username ?? $addon->getConfig('languagetool_username', '') ?: null;
            
            // Custom API URL (for self-hosted instances)
            $customUrl = $addon->getConfig('languagetool_api_url', '');
            if (!empty($customUrl)) {
                $this->apiUrl = rtrim($customUrl, '/');
            }
        }
    }
    
    /**
     * Check text for grammar, spelling and style issues
     * 
     * @param string $text Text to check
     * @param string $language Language code (e.g. 'de-DE', 'en-US', 'auto')
     * @param array<string, mixed> $options Additional options
     * @return array{matches: list<array<string, mixed>>, language: array<string, mixed>|null, software: array<string, mixed>|null}
     * @throws \Exception on API error
     */
    public function check(string $text, string $language = 'auto', array $options = []): array
    {
        // Language parameter is REQUIRED by LanguageTool API
        // Use 'auto' for auto-detection
        if (empty($language)) {
            $language = 'auto';
        }
        
        $params = [
            'text' => $text,
            'language' => $language,
        ];
        
        // Optional API key for premium features
        if (!empty($this->apiKey) && !empty($this->username)) {
            $params['username'] = $this->username;
            $params['apiKey'] = $this->apiKey;
        }
        
        // Enable picky mode for more suggestions
        if ($options['picky'] ?? false) {
            $params['level'] = 'picky';
        }
        
        // Disable specific rule categories
        if (!empty($options['disabledCategories'])) {
            $params['disabledCategories'] = implode(',', $options['disabledCategories']);
        }
        
        // Enable specific rule categories  
        if (!empty($options['enabledCategories'])) {
            $params['enabledCategories'] = implode(',', $options['enabledCategories']);
        }
        
        $response = $this->makeRequest('/check', $params);
        
        // Filter out ignored words
        $matches = $response['matches'] ?? [];
        $matches = $this->filterIgnoredWords($matches, $text);
        
        return [
            'matches' => $matches,
            'language' => $response['language'] ?? null,
            'software' => $response['software'] ?? null,
        ];
    }
    
    /**
     * Filter out matches for ignored words
     * @param list<array<string, mixed>> $matches
     * @return list<array<string, mixed>>
     */
    private function filterIgnoredWords(array $matches, string $text): array
    {
        $addon = \rex_addon::get('writeassist');
        
        // Get ignore words from config
        $ignoreWordsConfig = '';
        try {
            $ignoreWordsConfig = $addon->getConfig('ignore_words', '');
        } catch (\Exception $e) {
            return $matches;
        }
        
        if (empty($ignoreWordsConfig)) {
            return $matches;
        }
        
        // Parse ignore list (one word per line, also handle Windows line endings)
        $ignoreWordsConfig = str_replace("\r\n", "\n", $ignoreWordsConfig);
        $ignoreWords = array_filter(array_map('trim', explode("\n", $ignoreWordsConfig)));
        
        if (empty($ignoreWords)) {
            return $matches;
        }
        
        // Filter matches
        return array_values(array_filter($matches, function($match) use ($text, $ignoreWords) {
            // Get the word that was flagged
            $flaggedWord = mb_substr($text, $match['offset'], $match['length'], 'UTF-8');
            $flaggedWordLower = mb_strtolower($flaggedWord, 'UTF-8');
            
            // Check if flagged word matches any ignore word (case-insensitive)
            foreach ($ignoreWords as $ignoreWord) {
                $ignoreWordLower = mb_strtolower(trim($ignoreWord), 'UTF-8');
                
                if (empty($ignoreWordLower)) {
                    continue;
                }
                
                // Exact match
                if ($flaggedWordLower === $ignoreWordLower) {
                    return false; // Filter out this match
                }
                
                // Check if the ignore word is contained in the flagged word
                // e.g. "KLXM" in "KLXM" or "Crossmedia" flagged when "KLXM Crossmedia" is ignored
                if (mb_strpos($ignoreWordLower, $flaggedWordLower) !== false) {
                    return false;
                }
                
                // Check if flagged word contains the ignore word
                if (mb_strpos($flaggedWordLower, $ignoreWordLower) !== false) {
                    return false;
                }
            }
            
            return true; // Keep this match
        }));
    }
    
    /**
     * Apply corrections to text based on check results
     * 
     * @param string $text Original text
     * @param list<array<string, mixed>> $matches Matches from check()
     * @param bool $useFirstSuggestion Use first suggestion for each match
     * @return string Corrected text
     */
    public function applyCorrections(string $text, array $matches, bool $useFirstSuggestion = true): string
    {
        // Sort matches by offset in reverse order to preserve positions
        usort($matches, function($a, $b) {
            return $b['offset'] <=> $a['offset'];
        });
        
        foreach ($matches as $match) {
            if (!empty($match['replacements']) && $useFirstSuggestion) {
                $replacement = $match['replacements'][0]['value'];
                // Use mb_substr for proper UTF-8 handling
                $before = mb_substr($text, 0, $match['offset'], 'UTF-8');
                $after = mb_substr($text, $match['offset'] + $match['length'], null, 'UTF-8');
                $text = $before . $replacement . $after;
            }
        }
        
        return $text;
    }
    
    /**
     * Get supported languages
     * @return list<array{code: string, name: string}>
     */
    public function getLanguages(): array
    {
        try {
            $result = $this->makeRequest('/languages', [], 'GET');
            /** @var list<array{code: string, name: string}> */
            return $result;
        } catch (\Exception $e) {
            return $this->getDefaultLanguages();
        }
    }
    
    /**
     * Make API request using rex_socket
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function makeRequest(string $endpoint, array $params, string $method = 'POST'): array
    {
        $url = $this->apiUrl . $endpoint;
        
        // Build query string - use & as separator (not &amp;)
        $postData = http_build_query($params, '', '&');
        
        // Use rex_socket for REDAXO-native HTTP requests
        if ($method === 'GET') {
            if ($postData !== '') {
                $url .= '?' . $postData;
            }
            $socket = \rex_socket::factoryUrl($url);
            $socket->addHeader('Accept', 'application/json');
            $response = $socket->doGet();
        } else {
            $socket = \rex_socket::factoryUrl($url);
            $socket->addHeader('Content-Type', 'application/x-www-form-urlencoded');
            $socket->addHeader('Accept', 'application/json');
            $response = $socket->doPost($postData);
        }
        
        if (!$response->isOk()) {
            $body = $response->getBody();
            $errorData = json_decode($body, true);
            $message = is_array($errorData) ? ($errorData['message'] ?? 'API Error: ' . $response->getStatusCode()) : 'API Error: ' . $response->getStatusCode();
            throw new \Exception($message);
        }
        
        $body = $response->getBody();
        $decoded = json_decode($body, true);
        
        return is_array($decoded) ? $decoded : [];
    }
    
    /**
     * Default languages (fallback)
     * @return list<array{code: string, name: string}>
     */
    private function getDefaultLanguages(): array
    {
        return [
            ['code' => 'auto', 'name' => 'Auto-Detect'],
            ['code' => 'de-DE', 'name' => 'Deutsch (Deutschland)'],
            ['code' => 'de-AT', 'name' => 'Deutsch (Österreich)'],
            ['code' => 'de-CH', 'name' => 'Deutsch (Schweiz)'],
            ['code' => 'en-US', 'name' => 'English (US)'],
            ['code' => 'en-GB', 'name' => 'English (GB)'],
            ['code' => 'fr', 'name' => 'Français'],
            ['code' => 'es', 'name' => 'Español'],
            ['code' => 'it', 'name' => 'Italiano'],
            ['code' => 'nl', 'name' => 'Nederlands'],
            ['code' => 'pt-PT', 'name' => 'Português'],
            ['code' => 'pt-BR', 'name' => 'Português (Brasil)'],
            ['code' => 'pl-PL', 'name' => 'Polski'],
            ['code' => 'ru-RU', 'name' => 'Русский'],
            ['code' => 'uk-UA', 'name' => 'Українська'],
        ];
    }
}
