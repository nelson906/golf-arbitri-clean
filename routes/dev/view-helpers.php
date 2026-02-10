<?php

/**
 * Laravel View Previewer - Helpers & Mock Classes
 *
 * Features:
 * - View preview with mock data
 * - Batch testing all views
 * - Orphaned view detection (NEW)
 * - Performance metrics (NEW)
 *
 * @version 2.0
 */

use Illuminate\Contracts\Auth\Authenticatable;

// ============================================
// DEBUG COLLECTOR
// ============================================
if (! class_exists('DebugCollector')) {
    class DebugCollector
    {
        private static $issues = [];

        public static function addIssue($type, $message, $context = [])
        {
            self::$issues[] = compact('type', 'message', 'context');
        }

        public static function getIssues()
        {
            return self::$issues;
        }

        public static function clear()
        {
            self::$issues = [];
        }

        public static function hasIssues()
        {
            return count(self::$issues) > 0;
        }
    }
}

// ============================================
// MOCK PAGINATOR VIEW
// ============================================
if (! class_exists('MockPaginatorView')) {
    class MockPaginatorView
    {
        public function __toString()
        {
            return '';
        }

        public function __call($m, $a)
        {
            return $this;
        }
    }
}

// ============================================
// MOCK COLLECTION
// ============================================
if (! class_exists('MockCollection')) {
    class MockCollection extends \Illuminate\Support\Collection
    {
        public function links($view = null, $data = [])
        {
            return new MockPaginatorView;
        }

        public function render($view = null, $data = [])
        {
            return new MockPaginatorView;
        }

        public function withQueryString()
        {
            return $this;
        }

        public function appends($key, $value = null)
        {
            return $this;
        }
    }
}

// ============================================
// UNIVERSAL VALUE (Mock Object)
// ============================================
if (! class_exists('UniversalValue')) {
    class UniversalValue implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable, \Stringable, Authenticatable
    {
        private $data = [];

        public function __construct($data = [])
        {
            $this->data = is_array($data) ? $data : ['value' => $data];
            if (! isset($this->data['id'])) {
                $this->data['id'] = rand(1, 999);
            }
            if (! isset($this->data['name'])) {
                $this->data['name'] = 'Mock';
            }
        }

        public function getAuthIdentifierName()
        {
            return 'id';
        }

        public function getAuthIdentifier()
        {
            return $this->data['id'] ?? 1;
        }

        public function getAuthPassword()
        {
            return '';
        }

        public function getAuthPasswordName()
        {
            return 'password';
        }

        public function getRememberToken()
        {
            return null;
        }

        public function setRememberToken($value) {}

        public function getRememberTokenName()
        {
            return null;
        }

        public function __get($name)
        {
            if (isset($this->data[$name])) {
                return $this->data[$name];
            }
            $this->data[$name] = $this->generate($name);

            return $this->data[$name];
        }

        public function __set($name, $value)
        {
            $this->data[$name] = $value;
        }

        public function __isset($name)
        {
            return true;
        }

        public function offsetExists($offset): bool
        {
            return true;
        }

        public function offsetGet($offset): mixed
        {
            if (isset($this->data[$offset])) {
                return $this->data[$offset];
            }
            if (is_numeric($offset)) {
                return new UniversalValue(['id' => $offset + 1, 'name' => "Item $offset"]);
            }
            $this->data[$offset] = $this->generate($offset);

            return $this->data[$offset];
        }

        public function offsetSet($offset, $value): void
        {
            $this->data[$offset] = $value;
        }

        public function offsetUnset($offset): void
        {
            unset($this->data[$offset]);
        }

        public function count(): int
        {
            return 5;
        }

        public function getIterator(): \Traversable
        {
            return new \ArrayIterator($this->data);
        }

        public function __toString(): string
        {
            if (isset($this->data['id'])) {
                return (string) $this->data['id'];
            }
            if (isset($this->data['name'])) {
                return (string) $this->data['name'];
            }

            return 'mock';
        }

        public function jsonSerialize(): mixed
        {
            return $this->data;
        }

        public function __call($method, $args)
        {
            if (in_array($method, ['isEmpty', 'isNotEmpty', 'keys', 'values', 'where', 'filter'])) {
                if ($method === 'isEmpty') {
                    return false;
                }
                if ($method === 'isNotEmpty') {
                    return true;
                }
                if ($method === 'where') {
                    return new MockCollection([new UniversalValue]);
                }

                return new MockCollection(['mock']);
            }

            if (in_array($method, ['links', 'render', 'withQueryString', 'appends', 'hasPages', 'total'])) {
                if ($method === 'total') {
                    return 10;
                }
                if ($method === 'hasPages') {
                    return true;
                }

                return new MockPaginatorView;
            }

            if (in_array($method, ['merge', 'route'])) {
                return new UniversalValue(['value' => 'mock']);
            }

            if (in_array($method, ['canBeRetried'])) {
                return true;
            }

            if ($method === 'count') {
                return 5;
            }
            if ($method === 'format') {
                return now()->format($args[0] ?? 'd/m/Y');
            }
            if ($method === 'first') {
                return new UniversalValue(['id' => 1]);
            }
            if (in_array($method, ['get', 'all'])) {
                return new MockCollection([new UniversalValue]);
            }

            return $this;
        }

        private function generate($name)
        {
            // Dates
            if (str_contains(strtolower($name), 'date') ||
                str_contains(strtolower($name), '_at') ||
                str_contains(strtolower($name), 'time') ||
                str_contains(strtolower($name), 'deadline')) {
                return now();
            }

            // Numbers
            if ($name === 'id' || $name === 'total' ||
                str_ends_with($name, 'count') ||
                str_starts_with($name, 'total') ||
                str_starts_with($name, 'avg_') ||
                str_starts_with($name, 'max_') ||
                str_starts_with($name, 'min_') ||
                $name === 'percentage' ||
                str_contains($name, 'percent')) {
                return rand(1, 100);
            }

            // Common strings
            $strings = [
                'name' => 'Mock Name',
                'email' => 'mock@example.com',
                'status' => 'active',
                'role' => 'Mock Role',
                'type' => 'general',
                'title' => 'Mock Title',
                'message' => 'Mock Message',
                'content' => 'Mock Content',
            ];

            if (isset($strings[$name])) {
                return $strings[$name];
            }

            // Booleans
            if (str_starts_with($name, 'is_') ||
                str_starts_with($name, 'can_') ||
                str_starts_with($name, 'has_')) {
                return true;
            }

            // Collections
            if (in_array($name, ['items', 'data', 'results', 'records'])) {
                return new MockCollection([new UniversalValue]);
            }

            return new UniversalValue(['id' => rand(1, 999), 'name' => ucfirst($name)]);
        }
    }
}

// ============================================
// VIEW ANALYZER (Core Logic)
// ============================================
if (! function_exists('analyzeViewRecursive')) {
    function analyzeViewRecursive($viewName, &$allVariables = [])
    {
        $viewFile = resource_path('views/'.str_replace('.', '/', $viewName).'.blade.php');
        if (! file_exists($viewFile)) {
            return $allVariables;
        }

        $content = file_get_contents($viewFile);
        preg_match_all('/\$(\w+)/', $content, $matches);
        $allVariables = array_merge($allVariables, $matches[1]);

        preg_match_all('/@include\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $includes);
        foreach ($includes[1] as $includedView) {
            analyzeViewRecursive($includedView, $allVariables);
        }

        if (preg_match('/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $extends)) {
            analyzeViewRecursive($extends[1], $allVariables);
        }

        return array_unique($allVariables);
    }
}

if (! function_exists('generateValue')) {
    function generateValue($name)
    {
        // Arrays
        if (str_contains($name, 'stats') ||
            str_contains($name, 'colors') ||
            in_array($name, ['filters', 'options', 'settings', 'data'])) {
            return ['total' => 5, 'count' => 5];
        }

        // Collections (plurals)
        if (str_ends_with($name, 's') &&
            ! in_array($name, ['class', 'status', 'errors'])) {
            return new MockCollection([
                new UniversalValue(['id' => 1, 'name' => 'Item 1']),
                new UniversalValue(['id' => 2, 'name' => 'Item 2']),
            ]);
        }

        // Booleans
        if (str_starts_with($name, 'is') || str_starts_with($name, 'can')) {
            return true;
        }

        return new UniversalValue(['id' => rand(1, 999), 'name' => ucfirst($name)]);
    }
}

// ============================================
// NEW: ORPHANED VIEW DETECTOR
// ============================================
if (! function_exists('detectOrphanedViews')) {
    /**
     * Detect views that are never referenced in code
     *
     * @return array ['orphaned' => [...], 'used' => [...], 'total' => N]
     */
    function detectOrphanedViews()
    {
        try {
            $viewsPath = resource_path('views');
            $allViews = [];

            // Get all blade views
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($viewsPath)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = str_replace($viewsPath.'/', '', $file->getPathname());
                if (! str_contains($relativePath, '.blade.php')) {
                    continue;
                }

                $viewName = str_replace('.blade.php', '', $relativePath);
                $viewName = str_replace('/', '.', $viewName);

                // Skip vendor, mail, components, and dev views
                if (str_starts_with($viewName, 'vendor.') ||
                    str_starts_with($viewName, 'mail.') ||
                    str_starts_with($viewName, 'components.') ||
                    str_starts_with($viewName, 'dev.')) {
                    continue;
                }

                $allViews[] = $viewName;
            }
        } catch (\Throwable $e) {
            // If scanning fails, return empty result
            return [
                'orphaned' => [],
                'used' => [],
                'total' => 0,
                'orphaned_count' => 0,
                'used_count' => 0,
                'error' => 'Error scanning views: '.$e->getMessage(),
            ];
        }

        // Search for view references in code
        $searchPaths = [
            app_path(),
            resource_path('views'),
            base_path('routes'),
        ];

        $usedViews = [];
        $patterns = [
            '/view\s*\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/',           // view('name')
            '/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]/',               // @extends('name')
            '/@include\s*\(\s*[\'"]([^\'"]+)[\'"]/',               // @include('name')
            '/return\s+view\s*\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/',  // return view('name')
            '/view:\s*[\'"]([^\'"]+)[\'"]/',                        // view: 'name' (Mail classes)
        ];

        foreach ($searchPaths as $searchPath) {
            try {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($searchPath)
                );

                foreach ($files as $file) {
                    try {
                        if (! $file->isFile() ||
                            ! in_array($file->getExtension(), ['php', 'blade'])) {
                            continue;
                        }

                        $content = @file_get_contents($file->getPathname());

                        if (! $content) {
                            continue;
                        }

                        foreach ($patterns as $pattern) {
                            if (preg_match_all($pattern, $content, $matches)) {
                                foreach ($matches[1] as $viewRef) {
                                    $usedViews[] = $viewRef;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // Skip problematic files
                        continue;
                    }
                }
            } catch (\Throwable $e) {
                // Skip problematic search paths
                continue;
            }
        }

        $usedViews = array_unique($usedViews);
        $orphanedViews = array_diff($allViews, $usedViews);

        return [
            'orphaned' => array_values($orphanedViews),
            'used' => array_values($usedViews),
            'total' => count($allViews),
            'orphaned_count' => count($orphanedViews),
            'used_count' => count($usedViews),
        ];
    }
}

// ============================================
// NEW: PERFORMANCE METRICS
// ============================================
if (! function_exists('measureViewPerformance')) {
    /**
     * Measure view rendering performance
     *
     * @param  string  $viewName
     * @param  array  $data
     * @param  int  $iterations
     * @return array
     */
    function measureViewPerformance($viewName, $data = [], $iterations = 10)
    {
        if (! view()->exists($viewName)) {
            return ['error' => 'View not found'];
        }

        $times = [];
        $memoryUsage = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            $startMemory = memory_get_usage();

            try {
                ob_start();
                echo view($viewName, $data)->render();
                ob_end_clean();

                $endTime = microtime(true);
                $endMemory = memory_get_usage();

                $times[] = ($endTime - $startTime) * 1000; // Convert to ms
                $memoryUsage[] = ($endMemory - $startMemory) / 1024; // Convert to KB
            } catch (\Throwable $e) {
                return [
                    'error' => $e->getMessage(),
                    'iterations_completed' => $i,
                ];
            }
        }

        return [
            'view' => $viewName,
            'iterations' => $iterations,
            'times_ms' => $times,
            'memory_kb' => $memoryUsage,
            'avg_time_ms' => round(array_sum($times) / count($times), 2),
            'min_time_ms' => round(min($times), 2),
            'max_time_ms' => round(max($times), 2),
            'avg_memory_kb' => round(array_sum($memoryUsage) / count($memoryUsage), 2),
            'rating' => getRatingForPerformance(
                array_sum($times) / count($times),
                array_sum($memoryUsage) / count($memoryUsage)
            ),
        ];
    }
}

if (! function_exists('getRatingForPerformance')) {
    function getRatingForPerformance($avgTimeMs, $avgMemoryKb)
    {
        // Time rating
        if ($avgTimeMs < 10) {
            $timeRating = 'excellent';
        } elseif ($avgTimeMs < 50) {
            $timeRating = 'good';
        } elseif ($avgTimeMs < 100) {
            $timeRating = 'fair';
        } else {
            $timeRating = 'slow';
        }

        // Memory rating
        if ($avgMemoryKb < 100) {
            $memRating = 'excellent';
        } elseif ($avgMemoryKb < 500) {
            $memRating = 'good';
        } elseif ($avgMemoryKb < 1000) {
            $memRating = 'fair';
        } else {
            $memRating = 'heavy';
        }

        return [
            'time' => $timeRating,
            'memory' => $memRating,
            'overall' => ($timeRating === 'excellent' && $memRating === 'excellent')
                ? 'excellent'
                : (($timeRating === 'slow' || $memRating === 'heavy')
                    ? 'needs-optimization'
                    : 'acceptable'),
        ];
    }
}

if (! function_exists('benchmarkAllViews')) {
    /**
     * Benchmark all views for performance
     *
     * @param  int  $iterations
     * @return array
     */
    function benchmarkAllViews($iterations = 5)
    {
        $viewsPath = resource_path('views');
        $results = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($viewsPath)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($viewsPath.'/', '', $file->getPathname());
            if (! str_contains($relativePath, '.blade.php')) {
                continue;
            }

            $viewName = str_replace('.blade.php', '', $relativePath);
            $viewName = str_replace('/', '.', $viewName);

            // Skip vendor and mail
            if (str_starts_with($viewName, 'vendor.') ||
                str_starts_with($viewName, 'mail.')) {
                continue;
            }

            // Generate mock data
            $variables = analyzeViewRecursive($viewName);
            $data = ['errors' => new \Illuminate\Support\ViewErrorBag];
            foreach ($variables as $varName) {
                if (! isset($data[$varName])) {
                    $data[$varName] = generateValue($varName);
                }
            }

            $result = measureViewPerformance($viewName, $data, $iterations);
            if (! isset($result['error'])) {
                $results[] = $result;
            }
        }

        // Sort by avg time (slowest first)
        usort($results, function ($a, $b) {
            return $b['avg_time_ms'] <=> $a['avg_time_ms'];
        });

        return [
            'results' => $results,
            'total_views' => count($results),
            'slowest' => array_slice($results, 0, 10),
            'fastest' => array_slice(array_reverse($results), 0, 10),
            'summary' => [
                'avg_time_ms' => round(
                    array_sum(array_column($results, 'avg_time_ms')) / count($results),
                    2
                ),
                'avg_memory_kb' => round(
                    array_sum(array_column($results, 'avg_memory_kb')) / count($results),
                    2
                ),
            ],
        ];
    }
}
