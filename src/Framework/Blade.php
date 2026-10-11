<?php

declare(strict_types=1);

namespace Slendie\Framework;

use Exception;
use ReflectionClass;

/**
 * Blade Template Engine
 *
 * A lightweight Blade template engine implementation that supports:
 * - Template inheritance (@extends, @section, @yield)
 * - Control structures (@if, @foreach)
 * - Variable output ({{ }}, {!! !!})
 * - Includes (@include)
 * - Asset management (@asset)
 * - Static method calls
 *
 * @packages Slendie\Framework\Blade
 */
final class Blade
{
    /**
     * The base path where Blade template files are stored
     * @var string
     */
    private string $viewsPath;

    /**
     * Constructor: Initialize the Blade template engine with a views directory path
     *
     * If no path is provided, it will attempt to read 'views_path' from config/app.php.
     * The config file must be located at the project root in /config/app.php.
     *
     * @param string|null $viewsPath Optional. The path to the directory containing Blade template files.
     *                                If not provided, reads from config/app.php['views_path']
     */
    public function __construct(string|null $viewsPath = null)
    {
        if ($viewsPath === null) {
            // Load config/app.php to get the default views path
            $configPath = BASE_PATH . '/config/app.php';
            if (file_exists($configPath)) {
                $config = require $configPath;
                $viewsPath = $config['views_path'] ?? BASE_PATH . '/views';
            } else {
                // Fallback to default path if config file doesn't exist
                $viewsPath = BASE_PATH . '/views';
            }
        }

        // Remove trailing slash if present to ensure consistent path formatting
        $this->viewsPath = mb_rtrim($viewsPath, '/');
    }

    /**
     * Set the views path (override the default config)
     *
     * Allows changing the views directory path after instantiation.
     * Useful for testing or when you need to override the default configuration.
     *
     * @param string $viewsPath The path to the directory containing Blade template files
     * @return void
     */
    public function setPath(string $viewsPath): void
    {
        // Remove trailing slash if present to ensure consistent path formatting
        $this->viewsPath = mb_rtrim($viewsPath, '/');
    }

    /**
     * Render a Blade view template
     *
     * Main public method to render a Blade template. If the view contains
     * @extends directive, it will be used as the layout.
     * The layout parameter is kept for backward compatibility but is deprecated.
     *
     * @param string $view The view name (without .blade.php extension), supports dot syntax
     * @param array $data Data to pass to the view (and layout if @extends is used)
     * @param string|null $layout DEPRECATED: Layout name. Use @extends in view instead.
     * @param array $layoutData DEPRECATED: Data to pass to layout. Use data array instead.
     * @return string The rendered HTML content
     */
    public function render(string $view, array $data = [], string|null $layout = null, array $layoutData = []): string
    {
        $viewPath = $this->path($view);
        if (!file_exists($viewPath)) {
            throw new Exception('View not found: ' . $viewPath);
        }
        $viewContent = file_get_contents($viewPath);

        // Extract @extends directive from view content
        $extends = $this->extractExtends($viewContent);
        $layoutName = $extends['layout'] ?? $layout;
        $viewContentWithoutExtends = $extends['content'];

        // Extract sections from view content
        $sections = $this->extractSections($viewContentWithoutExtends);

        // Compile any remaining view content (outside of sections)
        $remainingContent = $this->compileContent($viewContentWithoutExtends, $data);

        // If sections were found, compile each section content
        $compiledSections = [];
        foreach ($sections as $sectionName => $sectionContent) {
            $compiledSections[$sectionName] = $this->compileContent($sectionContent, $data);
        }

        // Determine the main content to pass to layout
        // Use 'content' section if it exists, otherwise use remaining content
        $content = $compiledSections['content'] ?? $remainingContent;

        // If layout is specified (from @extends or parameter), wrap content in layout
        if ($layoutName) {
            $layoutPath = $this->path($layoutName);
            $layoutContent = file_get_contents($layoutPath);

            // Merge layout data with view data and add content
            // Also add all compiled sections for potential use in layout
            $mergedData = array_merge($data, $layoutData, ['content' => $content], $compiledSections);

            // Compile the layout with the view content
            return $this->compileContent($layoutContent, $mergedData);
        }

        return $content;
    }

    /**
     * Get the full file path for a given view name
     *
     * Constructs the complete file path by combining:
     * - The base views directory path
     * - The view name (supports dot syntax like 'layouts.front' -> 'layouts/front')
     * - The .blade.php extension
     *
     * Example: path('home') returns '/path/to/views/home.blade.php'
     * Example: path('layouts.front') returns '/path/to/views/layouts/front.blade.php'
     *
     * @param string $view The view name (without .blade.php extension), supports dot syntax
     * @return string The full file path to the Blade template
     */
    private function path(string $view): string
    {
        // Convert dot syntax to directory separator (e.g., 'layouts.front' -> 'layouts/front')
        $viewPath = str_replace('.', '/', $view);
        return $this->viewsPath . '/' . $viewPath . '.blade.php';
    }

    /**
     * Extract and parse @extends directive from view content
     *
     * Looks for @extends directive in the format:
     * - @extends('layouts.front')
     * - @extends("layouts.front")
     *
     * Removes the directive from content and returns the layout name.
     * Supports dot syntax which will be converted to path.
     *
     * @param string $content The view content to parse (passed by reference)
     * @return array Array with 'layout' (string|null) and 'content' (string without @extends)
     */
    private function extractExtends(string &$content): array
    {
        $layout = null;

        // Pattern to match @extends with parentheses: @extends('layouts.front') or @extends("layouts.front")
        $pattern = '/@extends\s*\(\s*[\'"](.+?)[\'"]\s*\)\s*\r?\n?/';

        if (preg_match($pattern, $content, $matches)) {
            $layout = mb_trim($matches[1]);
            // Remove the @extends directive from content
            $content = preg_replace($pattern, '', $content);
        }

        return [
            'layout' => $layout,
            'content' => $content
        ];
    }

    /**
     * Extract sections from view content
     *
     * Looks for @section('name') ... @endsection patterns and extracts the content.
     * Removes the @section and @endsection directives from content.
     *
     * @param string $content The view content to parse (passed by reference)
     * @return array Array with section names as keys and their content as values
     */
    private function extractSections(string &$content): array
    {
        $sections = [];

        // Pattern to match @section('name') ... @endsection
        // Supports single and double quotes, with optional whitespace
        // The pattern uses non-greedy matching and dot-all flag to capture multiline content
        $pattern = '/@section\s*\(\s*[\'"](.+?)[\'"]\s*\)\s*\r?\n(.*?)@endsection/s';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $sectionName = mb_trim($match[1]);
                $sectionContent = $match[2];

                // Store the section content (trim leading/trailing whitespace)
                $sections[$sectionName] = $sectionContent;

                // Remove the @section ... @endsection block from content
                $content = str_replace($match[0], '', $content);
            }
        }

        return $sections;
    }

    /**
     * Get variable value from data array
     *
     * Removes the $ prefix from variable names if present and retrieves the value.
     *
     * @param string $varName Variable name (with or without $ prefix)
     * @param array $data Data array containing variables
     * @return mixed The variable value or null if not found
     */
    private function getVariableValue(string $varName, array $data): mixed
    {
        // Remove $ prefix if present
        $varName = mb_ltrim($varName, '$');
        return $data[$varName] ?? null;
    }


    /**
     * Evaluate an expression using PHP's eval in a controlled environment
     *
     * Supports any valid PHP expression:
     * - Variables: $var, $var['key'], $var[0]
     * - Function calls: count($arr), has_route('name'), route('register')
     * - Operators: ternary, comparison, logical, etc.
     * - Arrays: [1, 2, 3], ['a' => 'b']
     * - Constants: JSON_PRETTY_PRINT, true, false, null
     *
     * @param string $expression The PHP expression to evaluate
     * @param array $data The data array containing variables
     * @return mixed The evaluated value
     */
    private function evaluateExpression(string $expression, array $data): mixed
    {
        $expression = mb_trim($expression);
        
        // Extract variables from data array into current scope
        // Use EXTR_OVERWRITE to ensure extracted variables take precedence
        extract($data, EXTR_OVERWRITE);
        
        // Evaluate the expression safely
        // Suppress warnings for undefined variables (common in templates)
        try {
            $result = @eval("return ({$expression});");
            return $result;
        } catch (\Throwable $e) {
            // If evaluation fails, return null
            return null;
        }
    }



    /**
     * Evaluate a condition using PHP's eval in a controlled environment
     *
     * Supports any valid PHP condition:
     * - Comparisons: ===, !==, ==, !=, <=, >=, <, >
     * - Logical operators: ||, &&, and, or, xor
     * - Negation: !
     * - Variables: $var
     * - Function calls: has_route('name'), isset($var), empty($arr)
     * - Complex expressions: ($a && $b) || ($c === $d)
     *
     * @param string $condition The PHP condition to evaluate
     * @param array $data The data array
     * @return bool The condition result
     */
    private function evaluateCondition(string $condition, array $data): bool
    {
        $condition = mb_trim($condition);
        
        // Handle empty condition
        if ($condition === '') {
            return false;
        }
        
        // Extract variables from data array into current scope
        // Use EXTR_OVERWRITE to ensure extracted variables take precedence
        extract($data, EXTR_OVERWRITE);
        
        // Evaluate the condition safely
        // Suppress warnings for undefined variables (common in templates)
        try {
            // Ensure the eval code ends with semicolon for proper parsing
            $evalCode = "return ({$condition});";
            $result = @eval($evalCode);
            
            // eval() returns null if there was a parse error
            // Since we always have a return statement, null indicates an error
            // We need to distinguish between null (error) and false (valid result)
            if ($result === null) {
                // Try without @ to see if there's an actual error
                // If it's a parse error, it will throw an exception
                try {
                    $result = eval($evalCode);
                    // If we get here, eval succeeded but returned null
                    // This shouldn't happen with our return statement, but handle it
                    if ($result === null) {
                        return false;
                    }
                } catch (\ParseError $e) {
                    // Parse error - condition is invalid
                    return false;
                }
            }
            
            // Convert to boolean
            return (bool) $result;
        } catch (\Throwable $e) {
            // If evaluation fails, return false
            return false;
        }
    }

    /**
     * Process static method calls in template
     *
     * Handles patterns like {{ ClassName::methodName('arg1', 'arg2') }}
     *
     * @param string $content The template content
     * @param array $data The data array
     * @return string Content with static method calls processed
     */
    private function processStaticMethodCalls(string $content, array $data): string
    {
        return preg_replace_callback('/{{\s*([A-Z]\w+)::(\w+)\s*\(([^)]*)\)\s*}}/', function ($matches) use ($data) {
            $className = $matches[1];
            $methodName = $matches[2];
            $argsStr = mb_trim($matches[3]);

            // Parse arguments (simple string arguments)
            $args = [];
            if (!empty($argsStr)) {
                // Remove quotes and extract arguments
                $argsStr = preg_replace('/[\'"]/', '', $argsStr);
                $args = array_map('trim', explode(',', $argsStr));
            }

            // Execute the static method call
            try {
                if (class_exists($className)) {
                    $reflection = new ReflectionClass($className);
                    if ($reflection->hasMethod($methodName)) {
                        $method = $reflection->getMethod($methodName);
                        if ($method->isStatic()) {
                            return $method->invokeArgs(null, $args);
                        }
                    }
                }
            } catch (Exception $e) {
                // If execution fails, return empty string
                return '';
            }

            return '';
        }, $content);
    }

    /**
     * Process unescaped variables in template
     *
     * Handles patterns like {{ $var }} or {{ $var['key'] }} - raw output (not HTML escaped)
     *
     * @param string $content The template content
     * @param array $data The data array
     * @return string Content with variables processed
     */
    private function processUnescapedVariables(string $content, array $data): string
    {
        return preg_replace_callback('/{{\s*(.+?)\s*}}/', function ($matches) use ($data) {
            $value = $this->evaluateExpression($matches[1], $data);
            return is_scalar($value) ? (string)$value : '';
        }, $content);
    }

    /**
     * Process escaped variables in template
     *
     * Handles patterns like {!! $var !!} - HTML escaped (safe output)
     *
     * @param string $content The template content
     * @param array $data The data array
     * @return string Content with escaped variables processed
     */
    private function processEscapedVariables(string $content, array $data): string
    {
        return preg_replace_callback('/{!!\s*(.+?)\s*!!}/', function ($matches) use ($data) {
            $value = $this->evaluateExpression($matches[1], $data);
            return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES, 'UTF-8');
        }, $content);
    }

    /**
     * Process variables in template content
     *
     * Processes static method calls, unescaped variables ({{ }}), and escaped variables ({!! !!}).
     *
     * @param string $content The template content
     * @param array $data The data array
     * @return string Content with all variables processed
     */
    private function processVariables(string $content, array $data): string
    {
        // Process static method calls first {{ ClassName::method() }}
        $content = $this->processStaticMethodCalls($content, $data);

        // Process unescaped variables {{ $var['key'] }} - raw output
        $content = $this->processUnescapedVariables($content, $data);

        // Process escaped variables {!! $var['key'] !!} - HTML escaped (safe)
        $content = $this->processEscapedVariables($content, $data);

        return $content;
    }

    /**
     * Parse @foreach header to extract array, item, and key variables
     *
     * @param string $header The @foreach header line
     * @param array $data The data array
     * @return array Array with 'arrayVar', 'itemVar', 'keyVar' keys
     */
    private function parseForeachHeader(string $header, array $data): array
    {
        $arrayVar = null;
        $itemVar = null;
        $keyVar = null;

        // Pattern: @foreach($array as $item) or @foreach($array as $key => $item)
        if (preg_match('/^@foreach\s*\((.+)\)$/', $header, $matches)) {
            $expression = mb_trim($matches[1]);

            // Match: arrayExpr as $itemVar or arrayExpr as $keyVar => $itemVar
            if (preg_match('/^(.+?)\s+as\s+\$?(\w+)(?:\s*=>\s+\$?(\w+))?$/', $expression, $exprMatches)) {
                $arrayExpr = mb_trim($exprMatches[1]);

                // Determine key and item variables
                if (!empty($exprMatches[3])) {
                    $keyVar = $exprMatches[2];
                    $itemVar = $exprMatches[3];
                } else {
                    $itemVar = $exprMatches[2];
                }

                // Evaluate the array expression using eval
                $arrayVar = $this->evaluateExpression($arrayExpr, $data);
            }
        }

        return [
            'arrayVar' => $arrayVar,
            'itemVar' => $itemVar,
            'keyVar' => $keyVar
        ];
    }

    /**
     * Find the matching @endforeach for a @foreach directive
     *
     * Handles nested @foreach directives by tracking depth.
     *
     * @param string $content The template content
     * @param int $startPos Starting position after @foreach header
     * @return int Position of the matching @endforeach
     */
    private function findMatchingEndforeach(string $content, int $startPos): int
    {
        $pos = $startPos;
        $depth = 1;
        $len = mb_strlen($content);

        while ($pos < $len && $depth > 0) {
            $nextForeach = mb_strpos($content, '@foreach', $pos);
            $nextEnd = mb_strpos($content, '@endforeach', $pos);

            if ($nextEnd === false) {
                $nextEnd = $len;
            }

            // If we find another @foreach before @endforeach, increase depth
            if ($nextForeach !== false && $nextForeach < $nextEnd) {
                $depth++;
                $pos = $nextForeach + 9; // Length of '@foreach'
            } else {
                // Found matching @endforeach
                $depth--;
                $pos = $nextEnd + 11; // Length of '@endforeach'
            }
        }

        return $pos - 11; // Return position of start of @endforeach
    }

    /**
     * Process @foreach directives in template
     *
     * Handles @foreach($array as $item) ... @endforeach patterns.
     * Supports nested foreach loops and key-value iteration.
     *
     * @param string $content The template content
     * @param array $data The data array (passed by reference to allow variable updates)
     * @return string Content with @foreach directives processed
     */
    private function processForeach(string $content, array &$data): string
    {
        $output = '';
        $offset = 0;

        while (true) {
            // Find next @foreach directive
            $start = mb_strpos($content, '@foreach', $offset);
            if ($start === false) {
                // No more @foreach directives, append remaining content
                $output .= mb_substr($content, $offset);
                break;
            }

            // Append content before @foreach
            $output .= mb_substr($content, $offset, $start - $offset);

            // Find end of @foreach header (end of line)
            $headerEnd = mb_strpos($content, "\n", $start);
            if ($headerEnd === false) {
                // No newline found, append remaining and break
                $output .= mb_substr($content, $start);
                break;
            }

            // Extract header line
            $header = mb_substr($content, $start, $headerEnd - $start);
            $header = mb_rtrim($header, "\r");

            // Parse foreach header to get array, item, and key variables
            $foreachInfo = $this->parseForeachHeader($header, $data);
            $arrayVar = $foreachInfo['arrayVar'];
            $itemVar = $foreachInfo['itemVar'];
            $keyVar = $foreachInfo['keyVar'];

            // If parsing failed, skip this @foreach
            if ($arrayVar === null || $itemVar === null) {
                $offset = $headerEnd + 1;
                continue;
            }

            // Find matching @endforeach
            $blockEnd = $this->findMatchingEndforeach($content, $headerEnd + 1);
            $blockContent = mb_substr($content, $headerEnd + 1, $blockEnd - ($headerEnd + 1));

            // Skip if array is not actually an array
            if (!is_array($arrayVar)) {
                $offset = $blockEnd + 11; // Skip past @endforeach
                continue;
            }

            // Process the loop
            $result = '';
            $lastValue = null;
            $lastKey = null;

            foreach ($arrayVar as $key => $value) {
                // Create loop data with current item
                $loopData = $data;
                $loopData[$itemVar] = $value;
                $lastValue = $value;

                // Add key variable if specified
                if ($keyVar !== null) {
                    $loopData[$keyVar] = $key;
                    $lastKey = $key;
                }

                // Process block content with loop data
                $processedBlock = $this->processDirectives($blockContent, $loopData);
                $processedBlock = $this->processVariables($processedBlock, $loopData);
                $result .= $processedBlock;
            }

            // Update data with last values (for use after loop)
            if ($lastValue !== null) {
                $data[$itemVar] = $lastValue;
            }
            if ($keyVar !== null && $lastKey !== null) {
                $data[$keyVar] = $lastKey;
            }

            $output .= $result;
            $offset = $blockEnd + 11; // Move past @endforeach
        }

        return $output;
    }

    /**
     * Parse @if or @elseif condition from template
     *
     * Extracts the condition from @if or @elseif directive, handling both parenthesized and non-parenthesized forms.
     *
     * @param string $content The template content
     * @param int $startPos Position of @if or @elseif directive
     * @return array Array with 'condition' and 'headerEnd' keys
     */
    private function parseIfCondition(string $content, int $startPos): array
    {
        $len = mb_strlen($content);
        
        // Detect if it's @if or @elseif
        $checkStr = mb_substr($content, $startPos, 8);
        if (mb_substr($checkStr, 0, 3) === '@if') {
            $pos = $startPos + 3; // Skip '@if'
        } elseif (mb_substr($checkStr, 0, 7) === '@elseif') {
            $pos = $startPos + 7; // Skip '@elseif'
        } else {
            // Fallback: assume @if
            $pos = $startPos + 3;
        }

        // Skip whitespace
        while ($pos < $len && ($content[$pos] === ' ' || $content[$pos] === "\t")) {
            $pos++;
        }

        $condition = '';

        // Check if condition is in parentheses
        if ($pos < $len && $content[$pos] === '(') {
            $pos++; // Skip opening parenthesis
            $condStart = $pos;
            $parens = 1;

            // Find matching closing parenthesis
            while ($pos < $len && $parens > 0) {
                $char = $content[$pos];
                if ($char === '(') {
                    $parens++;
                } elseif ($char === ')') {
                    $parens--;
                }
                $pos++;
            }

            $condEnd = $pos - 1;
            $condition = mb_trim(mb_substr($content, $condStart, $condEnd - $condStart));
            $headerEnd = $pos; // Right after closing parenthesis
        } else {
            // No parentheses: read to end of line
            $lineEnd = mb_strpos($content, "\n", $pos);
            if ($lineEnd === false) {
                $lineEnd = $len;
            }
            $condition = mb_trim(mb_substr($content, $pos, $lineEnd - $pos));
            $headerEnd = $lineEnd;
        }

        return [
            'condition' => $condition,
            'headerEnd' => $headerEnd
        ];
    }

    /**
     * Find matching @endif and optional @else/@elseif for @if directive
     *
     * Handles nested @if directives and finds @else and @elseif at the same depth.
     *
     * @param string $content The template content
     * @param int $startPos Starting position after @if header
     * @return array Array with 'endPos', 'elsePos', and 'elseifPositions' keys
     */
    private function findMatchingEndif(string $content, int $startPos): array
    {
        $pos = $startPos;
        $depth = 1;
        $len = mb_strlen($content);
        $elsePos = null;
        $elseifPositions = [];

        while ($pos < $len && $depth > 0) {
            $nextIf = mb_strpos($content, '@if', $pos);
            $nextElse = mb_strpos($content, '@else', $pos);
            $nextEnd = mb_strpos($content, '@endif', $pos);

            // Collect all candidates
            $candidates = [];
            if ($nextIf !== false) {
                $candidates[] = $nextIf;
            }
            if ($nextElse !== false) {
                $candidates[] = $nextElse;
            }
            if ($nextEnd !== false) {
                $candidates[] = $nextEnd;
            }

            if (empty($candidates)) {
                $next = $len;
            } else {
                $next = min($candidates);
            }

            // Process the next directive
            if ($next === $nextIf) {
                // Nested @if - increase depth
                $depth++;
                $pos = $nextIf + 3;
            } elseif ($next === $nextElse && $depth === 1) {
                // Check if it's @elseif or @else
                $checkPos = $nextElse + 5; // After '@else'
                $checkStr = mb_substr($content, $checkPos, 2);
                
                if ($checkStr === 'if') {
                    // It's @elseif - capture it and skip past the condition
                    $elseifPositions[] = $nextElse;
                    // Parse the condition to find where the header ends
                    $elseifInfo = $this->parseIfCondition($content, $nextElse);
                    $pos = $elseifInfo['headerEnd'];
                } else {
                    // It's @else - capture it (only if no @else found yet)
                    if ($elsePos === null) {
                        $elsePos = $nextElse;
                    }
                    $pos = $nextElse + 5;
                }
            } elseif ($next === $nextEnd) {
                // Matching @endif - decrease depth
                $depth--;
                $pos = $nextEnd + 6;
            } else {
                $pos++;
            }
        }

        return [
            'endPos' => $pos - 6, // Position of start of @endif
            'elsePos' => $elsePos,
            'elseifPositions' => $elseifPositions
        ];
    }

    /**
     * Process @if directives in template
     *
     * Handles @if ... @elseif ... @else ... @endif patterns.
     * Supports nested @if directives, multiple @elseif, and complex conditions.
     *
     * @param string $content The template content
     * @param array $data The data array (passed by reference)
     * @return string Content with @if directives processed
     */
    private function processIf(string $content, array &$data): string
    {
        $output = '';
        $offset = 0;
        $len = mb_strlen($content);

        while (true) {
            // Find next @if directive
            $start = mb_strpos($content, '@if', $offset);
            if ($start === false) {
                // No more @if directives, append remaining content
                $output .= mb_substr($content, $offset);
                break;
            }

            // Append content before @if
            $output .= mb_substr($content, $offset, $start - $offset);

            // Parse condition from @if header
            $ifInfo = $this->parseIfCondition($content, $start);
            $condition = $ifInfo['condition'];
            $headerEnd = $ifInfo['headerEnd'];

            // Find matching @endif and optional @else/@elseif
            $endifInfo = $this->findMatchingEndif($content, $headerEnd);
            $endPos = $endifInfo['endPos'];
            $elsePos = $endifInfo['elsePos'];
            $elseifPositions = $endifInfo['elseifPositions'];

            // Build list of all conditional blocks (if, elseif, else)
            $blocks = [];
            
            // Add @if block
            $ifBlockEnd = !empty($elseifPositions) ? $elseifPositions[0] : ($elsePos ?? $endPos);
            $blocks[] = [
                'type' => 'if',
                'condition' => $condition,
                'start' => $headerEnd,
                'end' => $ifBlockEnd
            ];

            // Add @elseif blocks
            foreach ($elseifPositions as $index => $elseifPos) {
                // Parse condition from @elseif
                $elseifInfo = $this->parseIfCondition($content, $elseifPos);
                $elseifCondition = $elseifInfo['condition'];
                $elseifHeaderEnd = $elseifInfo['headerEnd'];

                // Determine end of this elseif block
                // It ends at the start of the next elseif/else/endif
                $nextElseifPos = $elseifPositions[$index + 1] ?? null;
                if ($nextElseifPos !== null) {
                    // Next elseif exists - parse it to get its header end (start of its block)
                    $nextElseifInfo = $this->parseIfCondition($content, $nextElseifPos);
                    $elseifBlockEnd = $nextElseifInfo['headerEnd'];
                } else {
                    // No more elseif - ends at else or endif
                    $elseifBlockEnd = $elsePos ?? $endPos;
                }

                $blocks[] = [
                    'type' => 'elseif',
                    'condition' => $elseifCondition,
                    'start' => $elseifHeaderEnd,
                    'end' => $elseifBlockEnd
                ];
            }

            // Add @else block if exists
            if ($elsePos !== null) {
                $blocks[] = [
                    'type' => 'else',
                    'condition' => null, // @else has no condition
                    'start' => $elsePos + 5, // Right after '@else'
                    'end' => $endPos
                ];
            }

            // Evaluate conditions in order and process the first matching block
            $processed = false;
            foreach ($blocks as $block) {
                $shouldProcess = false;

                if ($block['type'] === 'if' || $block['type'] === 'elseif') {
                    $shouldProcess = $this->evaluateCondition($block['condition'], $data);
                } elseif ($block['type'] === 'else') {
                    // @else is processed only if no previous block was processed
                    $shouldProcess = !$processed;
                }

                if ($shouldProcess) {
                    $blockContent = mb_substr($content, $block['start'], $block['end'] - $block['start']);
                    $result = $this->processDirectives($blockContent, $data);
                    $result = $this->processVariables($result, $data);
                    $output .= $result;
                    $processed = true;
                    break; // Stop after processing first matching block
                }
            }

            $offset = $endPos + 6; // Move past @endif
        }

        return $output;
    }

    /**
     * Remove remaining @section and @endsection directives
     *
     * Cleans up any section directives that weren't extracted earlier.
     * This ensures they don't appear in the final HTML output.
     *
     * @param string $content The template content
     * @return string Content with section directives removed
     */
    private function processSections(string $content): string
    {
        // Remove any remaining @section and @endsection directives
        $content = preg_replace('/@section\s*\(\s*[\'"](.+?)[\'"]\s*\)\s*\r?\n?/s', '', $content);
        $content = preg_replace('/@endsection\s*\r?\n?/s', '', $content);
        return $content;
    }

    /**
     * Process @include directives
     *
     * Replaces @include('view.name') with the compiled content of the included view.
     * Supports dot syntax for directory separators (e.g., 'partials.counter-check' -> 'partials/counter-check.blade.php').
     * The included view is compiled with the same data, allowing for nested includes and directives.
     *
     * @param string $content The content to process
     * @param array $data The data array to pass to included views (passed by reference)
     * @return string The content with @include directives replaced
     */
    private function processInclude(string $content, array &$data): string
    {
        // Pattern to match @include('view.name') or @include("view.name")
        return preg_replace_callback('/@include\s*\(\s*[\'"](.+?)[\'"]\s*\)/', function ($matches) use (&$data) {
            $viewName = mb_trim($matches[1]);
            $includePath = $this->path($viewName);

            // Check if the file exists
            if (!file_exists($includePath)) {
                // Return empty string if file doesn't exist
                return '';
            }

            // Read the included file content
            $includeContent = file_get_contents($includePath);

            // Compile the included content recursively (so it can contain other directives)
            return $this->compileContent($includeContent, $data);
        }, $content);
    }

    /**
     * Process @yield directives
     *
     * Replaces @yield('name') with the content of the section if it exists,
     * or an empty string if the section doesn't exist.
     *
     * @param string $content The content to process
     * @param array $data The data array containing sections (passed by reference)
     * @return string The content with @yield directives replaced
     */
    private function processYield(string $content, array &$data): string
    {
        // Pattern to match @yield('name') or @yield("name")
        return preg_replace_callback('/@yield\s*\(\s*[\'"](.+?)[\'"]\s*\)/', function ($matches) use ($data) {
            $sectionName = mb_trim($matches[1]);
            // Return the section content if it exists, otherwise empty string
            return isset($data[$sectionName]) ? $data[$sectionName] : '';
        }, $content);
    }

    /**
     * Process @asset directives
     *
     * Replaces @asset('js/main.js') or @asset('css/style.css') with Vite client script,
     * entry script tag, and CSS tags.
     * The asset path should include the full relative path from the assets directory.
     *
     * @param string $content The content to process
     * @param array $data The data array (not used but kept for consistency, passed by reference)
     * @return string The content with @asset directives replaced
     */
    private function processAsset(string $content, array &$data): string
    {
        // Pattern to match @asset('js/main.js') or @asset("css/style.css")
        return preg_replace_callback('/@asset\s*\(\s*[\'"](.+?)[\'"]\s*\)/', function ($matches) {
            $assetPath = mb_trim($matches[1]);
            $output = '';

            // Only process if Vite class exists
            if (!class_exists(Vite::class)) {
                return '';
            }

            // Add Vite client script (for dev mode)
            $output .= Vite::client();

            // Add the entry point script tag (required for both dev and production)
            $output .= Vite::scriptTag($assetPath);

            // Add CSS tags (for production only, dev mode injects CSS via Vite)
            $cssTags = Vite::cssTags($assetPath);
            if ($cssTags) {
                $output .= $cssTags;
            }

            return $output;
        }, $content);
    }

    /**
     * Process @vite directive
     *
     * Replaces @vite(['js/app.js']) or @vite('js/app.js') with Vite client script,
     * entry script tag, and CSS tags.
     * Supports both array syntax and single string syntax.
     *
     * @param string $content The content to process
     * @param array $data The data array (not used but kept for consistency, passed by reference)
     * @return string The content with @vite directives replaced
     */
    private function processVite(string $content, array &$data): string
    {
        // Pattern to match @vite(['js/app.js']) or @vite('js/app.js')
        return preg_replace_callback('/@vite\s*\(\s*(\[[^\]]+\]|[\'"](.+?)[\'"])\s*\)/', function ($matches) use ($data) {
            $output = '';

            // Only process if Vite class exists
            if (!class_exists(Vite::class)) {
                return '';
            }

            // Determine if it's an array or single string
            $firstMatch = $matches[1];
            $assets = [];

            if (preg_match('/^\[(.*)\]$/', $firstMatch, $arrayMatches)) {
                // It's an array - evaluate it using eval
                $arrayContent = $arrayMatches[1];
                $parsedArray = $this->evaluateExpression('[' . $arrayContent . ']', $data);
                if (is_array($parsedArray)) {
                    $assets = $parsedArray;
                }
            } else {
                // It's a single string (from matches[2])
                $assets = [$matches[2] ?? $firstMatch];
            }

            // Add Vite client script (for dev mode) - only once
            $output .= Vite::client();

            // Process each asset
            foreach ($assets as $assetPath) {
                // Remove quotes if present
                $assetPath = mb_trim($assetPath, '"\'');

                // Add the entry point script tag
                $output .= Vite::scriptTag($assetPath);

                // Add CSS tags (for production only, dev mode injects CSS via Vite)
                $cssTags = Vite::cssTags($assetPath);
                if ($cssTags) {
                    $output .= $cssTags;
                }
            }

            return $output;
        }, $content);
    }

    /**
     * Find matching @enderror for a @error directive
     *
     * Handles nested @error directives by tracking depth.
     *
     * @param string $content The template content
     * @param int $startPos Starting position after @error header
     * @return int|false Position of the matching @enderror or false if not found
     */
    private function findMatchingEnderror(string $content, int $startPos): int|false
    {
        $pos = $startPos;
        $depth = 1;
        $len = mb_strlen($content);

        // Ensure startPos is within bounds
        if ($startPos >= $len) {
            return false;
        }

        while ($pos < $len && $depth > 0) {
            $nextError = mb_strpos($content, '@error', $pos);
            $nextEnd = mb_strpos($content, '@enderror', $pos);

            if ($nextEnd === false) {
                // No @enderror found
                return false;
            }

            // If we find another @error before @enderror, increase depth
            if ($nextError !== false && $nextError < $nextEnd) {
                $depth++;
                $pos = $nextError + 6; // Length of '@error'
            } else {
                // Found matching @enderror
                $depth--;
                if ($depth > 0) {
                    $pos = $nextEnd + 10; // Length of '@enderror'
                } else {
                    // This is the matching @enderror
                    return $nextEnd;
                }
            }
        }

        // If depth is still > 0, we didn't find a matching @enderror
        if ($depth > 0) {
            return false;
        }

        return $pos - 10; // Return position of start of @enderror
    }

    /**
     * Process @error directives in template
     *
     * Handles @error('field') ... @enderror patterns.
     * Checks if there's an error for the specified field in session or data.
     * Makes $message variable available within the block.
     *
     * @param string $content The template content
     * @param array $data The data array (passed by reference)
     * @return string Content with @error directives processed
     */
    private function processError(string $content, array &$data): string
    {
        $output = '';
        $offset = 0;
        $len = mb_strlen($content);

        while ($offset < $len) {
            // Find next @error directive
            $start = mb_strpos($content, '@error', $offset);
            if ($start === false) {
                // No more @error directives, append remaining content
                $output .= mb_substr($content, $offset);
                break;
            }

            // Append content before @error
            $output .= mb_substr($content, $offset, $start - $offset);

            // Find end of @error header (end of line or closing parenthesis)
            $headerEnd = mb_strpos($content, "\n", $start);
            if ($headerEnd === false) {
                $headerEnd = $len;
            }

            // Extract header line
            $header = mb_substr($content, $start, $headerEnd - $start);
            $header = mb_rtrim($header, "\r");

            // Parse field name from @error('field') or @error("field")
            $fieldName = null;
            if (preg_match('/@error\s*\(\s*[\'"](.+?)[\'"]\s*\)/', $header, $matches)) {
                $fieldName = mb_trim($matches[1]);
            }

            // Find matching @enderror
            $blockEnd = $this->findMatchingEnderror($content, $headerEnd + 1);

            // Validate blockEnd
            if ($blockEnd === false || $blockEnd < $headerEnd) {
                // No matching @enderror found, skip this @error and continue
                $offset = $headerEnd + 1;
                continue;
            }

            // Ensure blockEnd doesn't exceed content length
            if ($blockEnd >= $len) {
                $blockEnd = $len;
            }

            $blockContent = mb_substr($content, $headerEnd + 1, $blockEnd - ($headerEnd + 1));

            // Get errors from data array or session, prioritizing form_errors
            // Check form_errors first, then errors, then session
            $errors = [];
            if (isset($data['form_errors']) && is_array($data['form_errors'])) {
                $errors = $data['form_errors'];
            } elseif (isset($data['errors']) && is_array($data['errors'])) {
                $errors = $data['errors'];
            } elseif (isset($_SESSION['form_errors']) && is_array($_SESSION['form_errors'])) {
                $errors = $_SESSION['form_errors'];
            } elseif (isset($_SESSION['errors']) && is_array($_SESSION['errors'])) {
                $errors = $_SESSION['errors'];
            }

            // Check if there's an error for this field
            if ($fieldName !== null && isset($errors[$fieldName]) && $errors[$fieldName] !== '') {
                $errorMessage = $errors[$fieldName];

                // Add $message variable to data for use within the block
                // Merge to ensure all data is available, including the message
                $errorData = array_merge($data, ['message' => $errorMessage]);

                // Process block content with error data
                // Use compileContentWithoutError to avoid recursion
                $processedBlock = $this->compileContentWithoutError($blockContent, $errorData);
                $output .= $processedBlock;
            }

            // Move past @enderror (10 characters)
            $offset = $blockEnd + 10;

            // Ensure offset doesn't exceed content length
            if ($offset > $len) {
                $offset = $len;
                break;
            }
        }

        return $output;
    }

    /**
     * Process inline @error directive (for class attributes)
     *
     * Handles @error('field') ... @enderror in inline contexts (like class attributes).
     * Processes the content between @error and @enderror only if error exists.
     * Uses regex to properly handle the replacement while preserving spacing.
     *
     * @param string $content The content to process
     * @param array $data The data array (passed by reference)
     * @return string The content with inline @error directives replaced
     */
    private function processInlineError(string $content, array &$data): string
    {
        // Get errors from data array or session, prioritizing form_errors
        // Check form_errors first, then errors, then session
        $errors = [];
        if (isset($data['form_errors']) && is_array($data['form_errors'])) {
            $errors = $data['form_errors'];
        } elseif (isset($data['errors']) && is_array($data['errors'])) {
            $errors = $data['errors'];
        } elseif (isset($_SESSION['form_errors']) && is_array($_SESSION['form_errors'])) {
            $errors = $_SESSION['form_errors'];
        } elseif (isset($_SESSION['errors']) && is_array($_SESSION['errors'])) {
            $errors = $_SESSION['errors'];
        }

        // Pattern to match INLINE @error('field') content @enderror (NO newlines allowed)
        // This distinguishes inline (same line) from block (multiple lines) @error
        // Using [ \t]* instead of \s* to avoid matching newlines
        // [^\n\r@]* captures any character except newline and @ to avoid matching across lines
        return preg_replace_callback(
            '/@error[ \t]*\([ \t]*[\'"]([^\'"]+)[\'"][ \t]*\)[ \t]*([^\n\r@]*)[ \t]*@enderror/',
            function ($matches) use ($errors) {
                $fieldName = mb_trim($matches[1]);
                $blockContent = mb_trim($matches[2]); // Content between @error and @enderror

                // If error exists for this field, include the block content with space
                if (isset($errors[$fieldName])) {
                    return $blockContent . ' ';
                }

                // If no error, return empty string
                // The remaining classes after @enderror will be preserved
                return '';
            },
            $content
        );
    }

    /**
     * Process @csrf directive
     *
     * Replaces @csrf with a hidden input field containing the CSRF token.
     *
     * @param string $content The content to process
     * @param array $data The data array (not used but kept for consistency, passed by reference)
     * @return string The content with @csrf directives replaced
     */
    private function processCsrf(string $content, array &$data): string
    {
        // Pattern to match @csrf (with optional whitespace)
        return preg_replace_callback('/@csrf\s*\r?\n?/', function () {
            if (!class_exists(CSRF::class)) {
                return '';
            }
            return CSRF::field();
        }, $content);
    }

    /**
     * Process all directives in template content
     *
     * Processes directives in order:
     * 1. @include (so included files can contain other directives)
     * 2. @foreach (can contain @if)
     * 3. @if
     * 4. @error (block form: @error('field') ... @enderror)
     * 5. @vite
     * 6. @asset
     * 7. @csrf
     * 8. @yield
     * 9. Remove remaining @section directives
     * 10. Process inline @error (for class attributes)
     *
     * Variables are processed separately after directives.
     *
     * @param string $content The template content
     * @param array $data The data array (passed by reference)
     * @return string Content with directives processed
     */
    private function processDirectives(string $content, array &$data): string
    {
        // Process directives in order
        $content = $this->processInclude($content, $data);
        $content = $this->processForeach($content, $data);
        $content = $this->processIf($content, $data);

        // Process inline @error BEFORE block @error
        // Inline: @error('field') content @enderror on same line (no newlines)
        // Block: @error('field')\n...\n@enderror (with newlines)
        $content = $this->processInlineError($content, $data);
        $content = $this->processError($content, $data);

        $content = $this->processVite($content, $data);
        $content = $this->processAsset($content, $data);
        $content = $this->processCsrf($content, $data);
        $content = $this->processYield($content, $data);

        // Remove any remaining section directives
        $content = $this->processSections($content);

        return $content;
    }

    /**
     * Compile template content (without reading from file)
     *
     * Processes directives and variables in the given content string.
     * This is the main compilation method that orchestrates the processing.
     *
     * @param string $content The raw template content
     * @param array $data Data to pass to the template (passed by reference)
     * @return string The compiled content
     */
    private function compileContent(string $content, array &$data): string
    {
        // Process directives (@foreach, @if, @include, etc.) first
        $content = $this->processDirectives($content, $data);

        // Then process variables in the remaining content
        $content = $this->processVariables($content, $data);

        return $content;
    }

    /**
     * Compile template content without processing @error directives
     *
     * Used internally to avoid recursion when processing @error blocks.
     * Processes all directives except @error, then processes variables.
     *
     * @param string $content The raw template content
     * @param array $data Data to pass to the template (passed by reference)
     * @return string The compiled content
     */
    private function compileContentWithoutError(string $content, array &$data): string
    {
        // Process directives excluding @error to avoid recursion
        $content = $this->processInclude($content, $data);
        $content = $this->processForeach($content, $data);
        $content = $this->processIf($content, $data);
        $content = $this->processInlineError($content, $data);
        // Skip processError to avoid recursion
        $content = $this->processVite($content, $data);
        $content = $this->processAsset($content, $data);
        $content = $this->processCsrf($content, $data);
        $content = $this->processYield($content, $data);
        $content = $this->processSections($content);

        // Then process variables
        $content = $this->processVariables($content, $data);

        return $content;
    }

    /**
     * Compile a template file from disk
     *
     * Reads a template file and compiles it. This is a convenience method
     * that combines file reading with compilation.
     *
     * @param string $templatePath Full path to the template file
     * @param array $data Data to pass to the template
     * @return string The compiled content
     */
    private function compile(string $templatePath, array $data): string
    {
        $content = file_get_contents($templatePath);
        return $this->compileContent($content, $data);
    }
}
