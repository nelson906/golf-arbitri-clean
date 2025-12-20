<?php

use Illuminate\Contracts\Auth\Authenticatable;

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
            // ⚠️ AGGIUNGI: Metodi che ritornano null devono ritornare MockCollection
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

            // ⚠️ Paginator methods
            if (in_array($method, ['links', 'render', 'withQueryString', 'appends', 'hasPages', 'total'])) {
                if ($method === 'total') {
                    return 10;
                }
                if ($method === 'hasPages') {
                    return true;
                }

                return new MockPaginatorView;
            }

            // ⚠️ AGGIUNGI: Metodi che ritornano oggetti
            if (in_array($method, ['merge', 'route'])) {
                return new UniversalValue(['value' => 'mock']);
            }

            // ⚠️ AGGIUNGI: Metodi booleani
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

            // ⚠️ Default: ritorna $this per method chaining
            return $this;
        }

        private function generate($name)
        {
            // Date
            if (
                str_contains(strtolower($name), 'date') || str_contains(strtolower($name), '_at') ||
                str_contains(strtolower($name), 'time') || str_contains(strtolower($name), 'deadline')
            ) {
                return now();
            }

            // ⚠️ Numeri → INT (per operazioni matematiche)
            if (
                $name === 'id' || $name === 'total' || str_ends_with($name, 'count') ||
                str_starts_with($name, 'total') || str_starts_with($name, 'avg_') ||
                str_starts_with($name, 'max_') || str_starts_with($name, 'min_') ||
                $name === 'percentage' || str_contains($name, 'percent')
            ) {
                return rand(1, 100);
            }

            // ⚠️ Stringhe comuni
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

            // Booleani
            if (
                str_starts_with($name, 'is_') || str_starts_with($name, 'can_') ||
                str_starts_with($name, 'has_')
            ) {
                return true;
            }

            // ⚠️ Collection/Array properties
            if (in_array($name, ['items', 'data', 'results', 'records'])) {
                return new MockCollection([new UniversalValue]);
            }

            // ⚠️ Default: SEMPRE ritorna UniversalValue (mai null!)
            return new UniversalValue(['id' => rand(1, 999), 'name' => ucfirst($name)]);
        }
    }
}

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
        // ⚠️ Array keywords → Array PHP nativo
        if (
            str_contains($name, 'stats') || str_contains($name, 'colors') ||
            in_array($name, ['filters', 'options', 'settings', 'data'])
        ) {
            return ['total' => 5, 'count' => 5];
        }

        // ⚠️ IMPORTANTE: SEMPRE MockCollection per plurali (MAI null!)
        if (str_ends_with($name, 's') && ! in_array($name, ['class', 'status', 'errors'])) {
            return new MockCollection([
                new UniversalValue(['id' => 1, 'name' => 'Item 1']),
                new UniversalValue(['id' => 2, 'name' => 'Item 2']),
            ]);
        }

        // Booleani
        if (str_starts_with($name, 'is') || str_starts_with($name, 'can')) {
            return true;
        }

        // ⚠️ DEFAULT: SEMPRE UniversalValue (MAI null!)
        return new UniversalValue(['id' => rand(1, 999), 'name' => ucfirst($name)]);
    }
}
