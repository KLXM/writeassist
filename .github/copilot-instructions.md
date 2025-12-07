# WriteAssist Addon - Development Instructions

## Overview

WriteAssist is a REDAXO CMS addon providing AI-powered writing assistance including:
- DeepL translation (standalone + TinyMCE integration)
- LanguageTool grammar/spell checking
- Article translation with smart slice handling

## Architecture

### Core Components

```
writeassist/
├── lib/
│   ├── DeeplApi.php              # DeepL API wrapper
│   ├── LanguageToolApi.php       # LanguageTool API wrapper
│   ├── ValueAnalyzer.php         # Detects value formats (JSON, HTML, MBlock, etc.)
│   ├── ValueTranslator.php       # Base translator interface
│   ├── Translator/
│   │   ├── HtmlTranslator.php    # Translates HTML content
│   │   ├── MBlockTranslator.php  # Translates MBlock JSON structures
│   │   ├── MFormTranslator.php   # Translates MForm JSON structures
│   │   └── JsonTranslator.php    # Translates generic JSON with key whitelist
│   ├── ModuleMapping.php         # Stores/retrieves module translation configs
│   ├── ArticleTranslator.php     # Orchestrates article/slice translation
│   └── TranslationJob.php        # Job queue for bulk translations
├── pages/
│   ├── index.php                 # Main translation page
│   ├── settings.php              # API configuration
│   ├── module_mapping.php        # Module mapping wizard
│   └── article_translate.php     # Article translation UI
├── fragments/
│   ├── mapping_wizard.php        # Interactive mapping configuration
│   ├── article_selector.php      # Article tree with checkboxes
│   ├── translation_preview.php   # Side-by-side preview
│   └── progress_bar.php          # Translation progress display
└── assets/
    └── js/
        ├── tinymce-deepl-plugin.js    # TinyMCE translation plugin
        ├── module-mapping.js          # Mapping wizard JS
        └── article-translator.js      # Article translation UI JS
```

## Value Format Detection

The addon must handle various value formats in `rex_article_slice.value1-20`:

| Format | Detection | Handling |
|--------|-----------|----------|
| Plain Text | No HTML tags, no JSON | Direct translation |
| HTML | Contains `<tag>` | DeepL with `tag_handling: 'html'` |
| Escaped HTML | Contains `&lt;` `&gt;` | Decode → translate → encode |
| MBlock JSON | Contains `REX_TEMPLATE_BLOCK` | Parse, translate specified fields |
| MForm JSON | Array with `type`, `name`, `value` | Parse, translate text/textarea fields |
| Media | Ends with image/file extension | Skip |
| Link | Starts with `rex://` | Skip |
| Number | `is_numeric()` | Skip |

### ValueAnalyzer Class

```php
class ValueAnalyzer {
    /**
     * Analyze a value and return its type and translatable fields
     * @return array{type: string, translatable: bool, fields?: array}
     */
    public function analyze(string $value): array;
    
    /**
     * Detect if value is MBlock JSON
     */
    private function isMBlock(array $decoded): bool;
    
    /**
     * Detect if value is MForm JSON
     */
    private function isMForm(array $decoded): bool;
    
    /**
     * Analyze JSON fields and suggest which are translatable
     * Keys like 'title', 'text', 'content', 'description' → suggested
     * Keys like 'icon', 'image', 'link', 'id', 'class' → not suggested
     */
    private function analyzeJsonFields(array $data): array;
}
```

## Module Mapping

Stores which values of each module should be translated and how.

### Storage Structure

```php
// Stored in rex_config('writeassist', 'module_mapping_<ID>')
[
    'module_id' => 12,
    'values' => [
        1 => [
            'type' => 'mblock',
            'translate_fields' => ['title', 'content'],
            'sample' => '{"REX_TEMPLATE_BLOCK":[...]}'  // For reference
        ],
        2 => [
            'type' => 'html'
        ],
        3 => [
            'type' => 'skip'
        ]
    ]
]
```

### Mapping Wizard Flow

1. User selects module from dropdown
2. For each value (1-20):
   - User pastes example output OR system loads from existing slice
   - ValueAnalyzer detects format
   - For JSON: checkboxes for which fields to translate
   - User confirms/adjusts
   - Save and continue to next value
3. Mapping saved to config

## Article Translation

### Translation Flow

1. **Article Selection**
   - Tree view with checkboxes
   - Filter by category
   - Show existing translation status per clang
   - Select source clang

2. **Target & Options**
   - Select target clang
   - Conflict strategy:
     - `skip` - Don't touch articles with existing content
     - `overwrite` - Replace all translatable values
     - `merge` - Only fill empty values
     - `append` - Add translated slices at end (rarely useful)
   - Options:
     - Translate article meta (name, SEO fields)
     - Include offline articles
     - Set translated articles offline for review

3. **Preview**
   - Show what will be translated
   - Estimated character count
   - DeepL quota check
   - Side-by-side preview option

4. **Execution**
   - Progress bar per article
   - Async for large batches (cronjob/queue)
   - Error handling per slice

5. **Results**
   - Summary of translated/skipped/failed
   - Links to edit translated articles
   - Option to undo (if implemented)

### ArticleTranslator Class

```php
class ArticleTranslator {
    public function __construct(
        private DeeplApi $deepl,
        private ModuleMapping $mapping
    ) {}
    
    /**
     * Translate articles from source to target clang
     */
    public function translate(
        array $articleIds,
        int $sourceClang,
        int $targetClang,
        string $conflictStrategy = 'merge',
        array $options = []
    ): TranslationResult;
    
    /**
     * Translate a single slice
     */
    private function translateSlice(
        rex_article_slice $source,
        rex_article_slice $target,
        string $targetLang
    ): void;
    
    /**
     * Get appropriate translator for value type
     */
    private function getTranslator(string $type): ValueTranslator;
}
```

## API Endpoints

### rex_api_writeassist_translate
Translates text via DeepL (used by TinyMCE plugin)

```php
// Request
POST /index.php?rex-api-call=writeassist_translate
text=Hello&target_lang=DE&preserve_formatting=1

// Response
{"translation": "Hallo", "detected_source": "EN"}
```

### rex_api_writeassist_analyze
Analyzes a value and returns detected type

```php
// Request
POST /index.php?rex-api-call=writeassist_analyze
value={"REX_TEMPLATE_BLOCK":[...]}

// Response
{"type": "mblock", "fields": [...]}
```

### rex_api_writeassist_article_translate
Triggers article translation (potentially async)

```php
// Request
POST /index.php?rex-api-call=writeassist_article_translate
articles=[1,2,3]&source_clang=1&target_clang=2&strategy=merge

// Response
{"job_id": 123, "status": "queued"}
// or for small batches:
{"status": "completed", "results": [...]}
```

## TinyMCE Integration

The addon registers a TinyMCE plugin via `PluginRegistry`:

```php
// In boot.php
if (rex_addon::get('tinymce')->isAvailable()) {
    \FriendsOfRedaxo\TinyMce\PluginRegistry::register(
        'writeassist_translate',
        rex_url::base('assets/addons/writeassist/js/tinymce-deepl-plugin.js')
    );
}
```

### Plugin Features
- Dropdown button with target language selection
- Translates selected HTML content
- Preserves formatting (bold, italic, links, etc.)
- Shows loading indicator during translation

## Database Schema

No custom tables required. Uses:
- `rex_config` for settings and module mappings
- `rex_article_slice` for reading/writing slice values
- `rex_article` for article meta translation

## Extension Points

### WRITEASSIST_TRANSLATE_VALUE
Called before translating a value, allows modification or skip.

```php
rex_extension::register('WRITEASSIST_TRANSLATE_VALUE', function(rex_extension_point $ep) {
    $value = $ep->getSubject();
    $params = $ep->getParams();
    // $params['module_id'], $params['value_id'], $params['type']
    
    // Return modified value or null to skip
    return $value;
});
```

### WRITEASSIST_AFTER_ARTICLE_TRANSLATE
Called after article translation is complete.

```php
rex_extension::register('WRITEASSIST_AFTER_ARTICLE_TRANSLATE', function(rex_extension_point $ep) {
    $result = $ep->getSubject();
    // Log, notify, trigger cache clear, etc.
});
```

## Coding Standards

- Follow REDAXO coding conventions (PSR-12)
- Use `rex_sql` for database operations
- Use `rex_i18n::msg()` for all UI strings
- Use fragments for reusable UI components
- Use `rex_api_function` for AJAX endpoints
- Always validate user permissions
- Handle DeepL API errors gracefully (rate limits, quota exceeded)
- Use `rex_logger` for error logging

## Testing Considerations

- Test with various module types (text, MBlock, MForm, custom)
- Test conflict strategies with pre-filled target articles
- Test with large articles (many slices)
- Test DeepL quota handling
- Test with special characters and Unicode
- Test HTML preservation in translations

## Security

- All API endpoints must check `rex::getUser()` permissions
- Sanitize all user input
- Use `rex_csrf_token` for form submissions
- DeepL API key stored securely in `rex_config`
- No direct file system access from user input

## Performance

- Batch multiple values in single DeepL request where possible
- Use async queue for large translation jobs
- Cache module mappings (don't reload from config each time)
- Consider rate limiting for DeepL API calls
