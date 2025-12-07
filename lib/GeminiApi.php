<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\WriteAssist;

use rex_addon;

/**
 * Google Gemini API Wrapper
 * 
 * Uses Google AI Studio / Gemini API for text generation
 * API Docs: https://ai.google.dev/docs
 */
class GeminiApi
{
    private readonly string $apiKey;
    private readonly string $model;
    
    public function __construct(?string $apiKey = null)
    {
        $addon = rex_addon::get('writeassist');
        $this->apiKey = $apiKey ?? (string) $addon->getConfig('gemini_api_key', '');
        $this->model = (string) $addon->getConfig('gemini_model', 'gemini-2.5-flash');
    }
    
    /**
     * Check if API is configured
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }
    
    /**
     * Generate text content
     * 
     * @param string $prompt The prompt/instruction
     * @param string $text Optional text to process
     * @param array<string, mixed> $options Additional options
     * @return array{text: string, usage?: array<string, int>}
     * @throws \Exception on API error
     */
    public function generate(string $prompt, string $text = '', array $options = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Gemini API key not configured');
        }
        
        $fullPrompt = $prompt;
        if ($text !== '') {
            $fullPrompt .= "\n\n" . $text;
        }
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 2048,
                'topP' => $options['top_p'] ?? 0.95,
            ]
        ];
        
        // Safety settings (allow most content for text processing)
        $payload['safetySettings'] = [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
        ];
        
        $response = $this->makeRequest(':generateContent', $payload);
        
        // Extract text from response
        $generatedText = '';
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $generatedText = $response['candidates'][0]['content']['parts'][0]['text'];
        }
        
        $result = ['text' => $generatedText];
        
        // Include usage if available
        if (isset($response['usageMetadata'])) {
            $result['usage'] = [
                'prompt_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total_tokens' => $response['usageMetadata']['totalTokenCount'] ?? 0,
            ];
        }
        
        return $result;
    }
    
    /**
     * Rewrite/improve text
     * @return array{text: string, usage?: array<string, int>}
     */
    public function rewrite(string $text, string $style = 'professional'): array
    {
        $styles = [
            'professional' => 'Schreibe den folgenden Text professioneller und formeller um. Behalte die Bedeutung bei.',
            'casual' => 'Schreibe den folgenden Text lockerer und umgangssprachlicher um. Behalte die Bedeutung bei.',
            'simple' => 'Vereinfache den folgenden Text. Verwende einfache Wörter und kurze Sätze.',
            'formal' => 'Schreibe den folgenden Text in einem sehr formellen, geschäftlichen Stil um.',
            'creative' => 'Schreibe den folgenden Text kreativer und ansprechender um.',
            'concise' => 'Kürze den folgenden Text auf das Wesentliche. Entferne Füllwörter und Redundanzen.',
        ];
        
        $prompt = $styles[$style] ?? $styles['professional'];
        $prompt .= ' Antworte nur mit dem umgeschriebenen Text, ohne Erklärungen.';
        
        return $this->generate($prompt, $text);
    }
    
    /**
     * Summarize text
     * @return array{text: string, usage?: array<string, int>}
     */
    public function summarize(string $text, int $maxSentences = 3): array
    {
        $prompt = "Fasse den folgenden Text in maximal {$maxSentences} Sätzen zusammen. " .
                  "Antworte nur mit der Zusammenfassung, ohne Erklärungen.";
        
        return $this->generate($prompt, $text);
    }
    
    /**
     * Expand/elaborate text
     * @return array{text: string, usage?: array<string, int>}
     */
    public function expand(string $text): array
    {
        $prompt = "Erweitere den folgenden Text mit mehr Details und Erklärungen. " .
                  "Behalte den Stil bei. Antworte nur mit dem erweiterten Text.";
        
        return $this->generate($prompt, $text);
    }
    
    /**
     * Generate text from keywords/topic
     * @return array{text: string, usage?: array<string, int>}
     */
    public function generateFromTopic(string $topic, string $type = 'paragraph'): array
    {
        $types = [
            'paragraph' => "Schreibe einen informativen Absatz über: {$topic}",
            'headline' => "Erstelle 5 verschiedene, ansprechende Überschriften für: {$topic}",
            'bullet_points' => "Erstelle eine Aufzählung mit den wichtigsten Punkten zu: {$topic}",
            'intro' => "Schreibe eine einleitende Einführung für einen Artikel über: {$topic}",
            'meta_description' => "Schreibe eine SEO-optimierte Meta-Description (max 160 Zeichen) für: {$topic}",
        ];
        
        $prompt = $types[$type] ?? $types['paragraph'];
        
        return $this->generate($prompt);
    }
    
    /**
     * Generate code with REDAXO context
     * @return array{text: string, usage?: array<string, int>}
     */
    public function generateCode(string $description, string $language = 'php'): array
    {
        $redaxoContext = $this->getRedaxoContext();
        
        $prompt = "Du bist ein erfahrener {$language}-Entwickler mit Expertise in REDAXO CMS. " .
                  "Hier sind wichtige Informationen zum Projekt:\n\n" .
                  $redaxoContext . "\n\n" .
                  "WICHTIG: Verwende immer modernen PHP 8.2+ Code mit:\n" .
                  "- declare(strict_types=1)\n" .
                  "- Typed Properties und Return Types\n" .
                  "- Readonly Properties wo sinnvoll\n" .
                  "- Named Arguments bei Bedarf\n" .
                  "- Match Expressions statt switch\n" .
                  "- Nullsafe Operator (?->) wo passend\n" .
                  "- Arrow Functions für einfache Callbacks\n\n" .
                  "Generiere sauberen, gut kommentierten {$language}-Code für folgende Anforderung. " .
                  "Verwende REDAXO Core-Methoden wo möglich (rex_sql, rex_file, rex_path, rex_addon, rex_article, rex_category, rex_media, rex_clang, rex_user, rex_fragment, rex_view, rex_i18n etc.). " .
                  "Antworte NUR mit dem Code, ohne zusätzliche Erklärungen. " .
                  "Verwende Best Practices und moderne Syntax.\n\n" .
                  "Anforderung: {$description}";
        
        return $this->generate($prompt, '', ['temperature' => 0.3, 'max_tokens' => 4096]);
    }
    
    /**
     * Explain code with REDAXO context
     * @return array{text: string, usage?: array<string, int>}
     */
    public function explainCode(string $code, string $language = 'php'): array
    {
        $prompt = "Du bist ein REDAXO CMS Experte. " .
                  "Erkläre den folgenden {$language}-Code auf Deutsch. " .
                  "Beschreibe was der Code macht und erkläre REDAXO-spezifische Methoden. " .
                  "Weise auf mögliche Probleme oder Verbesserungen hin.";
        
        return $this->generate($prompt, $code);
    }
    
    /**
     * Ask a question about code or REDAXO
     * @return array{text: string, usage?: array<string, int>}
     */
    public function askAboutCode(string $question, string $code = '', string $language = 'php'): array
    {
        $redaxoContext = $this->getRedaxoContext();
        
        $prompt = "Du bist ein REDAXO CMS und {$language} Experte. " .
                  "Hier sind Informationen zum Projekt:\n\n" .
                  $redaxoContext . "\n\n" .
                  "Beantworte die folgende Frage auf Deutsch. " .
                  "Wenn Code-Beispiele nötig sind, verwende modernen PHP 8.2+ Code und REDAXO Core-Methoden.\n\n" .
                  "Frage: {$question}";
        
        return $this->generate($prompt, $code);
    }
    
    /**
     * Improve/refactor code with REDAXO context
     * @return array{text: string, usage?: array<string, int>}
     */
    public function improveCode(string $code, string $language = 'php'): array
    {
        $redaxoContext = $this->getRedaxoContext();
        
        $prompt = "Du bist ein erfahrener {$language}-Entwickler mit Expertise in REDAXO CMS. " .
                  "Hier sind Informationen zum Projekt:\n\n" .
                  $redaxoContext . "\n\n" .
                  "WICHTIG: Verwende immer modernen PHP 8.2+ Code mit:\n" .
                  "- declare(strict_types=1)\n" .
                  "- Typed Properties und Return Types\n" .
                  "- Readonly Properties wo sinnvoll\n" .
                  "- Match Expressions statt switch\n" .
                  "- Nullsafe Operator (?->) wo passend\n" .
                  "- Arrow Functions für einfache Callbacks\n\n" .
                  "Verbessere und refaktoriere den folgenden Code. " .
                  "Verwende REDAXO Core-Methoden wo möglich. " .
                  "Achte auf: Performance, Lesbarkeit, Best Practices, Sicherheit. " .
                  "Antworte NUR mit dem verbesserten Code, ohne Erklärungen.";
        
        return $this->generate($prompt, $code, ['temperature' => 0.2, 'max_tokens' => 4096]);
    }
    
    /**
     * Get REDAXO project context (no sensitive data!)
     */
    private function getRedaxoContext(): string
    {
        $context = [];
        
        // REDAXO Version
        $context[] = "REDAXO Version: " . \rex::getVersion();
        $context[] = "PHP Version: " . PHP_VERSION;
        
        // Important REDAXO resources
        $context[] = "\nWichtige REDAXO Ressourcen:";
        $context[] = "- REDAXO Core: https://github.com/redaxo/redaxo";
        $context[] = "- REDAXO Dokumentation: https://github.com/redaxo/docs";
        $context[] = "- Friends Of REDAXO AddOns: https://github.com/FriendsOfREDAXO";
        $context[] = "- API Dokumentation: https://redaxo.org/api/main/";
        $context[] = "- MForm (Formular-Builder): https://github.com/FriendsOfREDAXO/mform";
        $context[] = "- MBlock (Wiederholbare Blöcke): https://github.com/FriendsOfREDAXO/mblock";
        
        // Installed AddOns (names only, no config!)
        $addons = [];
        foreach (\rex_addon::getAvailableAddons() as $addon) {
            $addons[] = $addon->getName() . ' (' . $addon->getVersion() . ')';
        }
        $context[] = "\nInstallierte AddOns: " . implode(', ', $addons);
        
        // Languages
        $languages = [];
        foreach (\rex_clang::getAll() as $clang) {
            $languages[] = $clang->getCode();
        }
        $context[] = "Sprachen: " . implode(', ', $languages);
        
        // Database tables (structure only, no data!)
        $sql = \rex_sql::factory();
        $tables = $sql->getTablesAndViews();
        $rexTables = array_filter($tables, fn($t) => str_starts_with($t, \rex::getTablePrefix()));
        $context[] = "Datenbank-Tabellen: " . implode(', ', $rexTables);
        
        return implode("\n", $context);
    }
    
    /**
     * Custom prompt
     * @return array{text: string, usage?: array<string, int>}
     */
    public function custom(string $prompt, string $text = ''): array
    {
        return $this->generate($prompt, $text);
    }
    
    /**
     * Get available models
     * @return list<array{id: string, name: string}>
     */
    public static function getAvailableModels(): array
    {
        return [
            ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash (empfohlen)'],
            ['id' => 'gemini-2.5-flash-lite', 'name' => 'Gemini 2.5 Flash-Lite (schnellstes)'],
            ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro (beste Qualität)'],
            ['id' => 'gemini-3-pro-preview', 'name' => 'Gemini 3 Pro (Preview)'],
        ];
    }
    
    /**
     * Make API request using rex_socket
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function makeRequest(string $endpoint, array $payload): array
    {
        // Validate API key
        if ($this->apiKey === '') {
            throw new \Exception('Gemini API key is not configured');
        }
        
        // Build URL parts
        $path = '/v1beta/models/' . urlencode($this->model) . $endpoint . '?key=' . urlencode($this->apiKey);
        
        $jsonBody = json_encode($payload);
        if ($jsonBody === false) {
            throw new \Exception('Failed to encode request payload');
        }
        
        // Use rex_socket for REDAXO-native HTTP requests
        $socket = \rex_socket::factoryUrl('https://generativelanguage.googleapis.com' . $path);
        $socket->setOptions([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $socket->addHeader('Content-Type', 'application/json');
        
        $response = $socket->doPost($jsonBody);
        
        if (!$response->isOk()) {
            $body = $response->getBody();
            $decoded = json_decode($body, true);
            $errorMessage = is_array($decoded) && isset($decoded['error']['message']) 
                ? $decoded['error']['message'] 
                : 'API Error: HTTP ' . $response->getStatusCode();
            throw new \Exception($errorMessage);
        }
        
        $body = $response->getBody();
        $decoded = json_decode($body, true);
        
        if (!is_array($decoded)) {
            throw new \Exception('Invalid API response');
        }
        
        return $decoded;
    }
}
