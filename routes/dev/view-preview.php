<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\Authenticatable;

// ==========================================
// ⚠️ FAKE ROUTES - Registrale SUBITO
// ==========================================
if (app()->environment(['local', 'staging'])) {
    // Lista route mancanti comuni
    $fakeRoutes = [
        'admin.letter-notifications.create' => 'post',
        'admin.letter-notifications.edit' => 'get',
        'admin.letter-notifications.show' => 'get', // ⚠️ AGGIUNGI QUI le nuove
        'admin.tournament-documents.upload' => 'post',
        'admin.notifications.export' => 'get',
        'admin.notifications.resend' => 'post',
        'admin.notifications.stats' => 'get',
        'admin.tournament-notifications.update' => 'put',
        'tournaments.send-assignment-forms' => 'get',
        'tournaments.send-reminders' => 'post',
        'tournaments.publish-results' => 'post',
        'tournaments.export-entries' => 'get',
        'tournaments.export-assignments' => 'get',
    ];

    foreach ($fakeRoutes as $name => $method) {
        if (!Route::has($name)) {
            Route::match([$method, 'get'], '/fake/' . str_replace('.', '/', $name) . '/{any?}', function () {
                return response()->json(['mock' => true]);
            })->where('any', '.*')->name($name);
        }
    }

    // Route con parametri obbligatori
    if (!Route::has('admin.referees.curriculum')) {
        Route::get('/fake/admin/referees/{referee}/curriculum', function () {
            return response()->json(['mock' => true]);
        })->name('admin.referees.curriculum');
    }

    // ⚠️ AGGIUNGI Route con parametro per letter-notifications
    if (!Route::has('admin.letter-notifications.show')) {
        Route::get('/fake/admin/letter-notifications/{notification}', function () {
            return response()->json(['mock' => true]);
        })->name('admin.letter-notifications.show');
    }
}


// ==========================================
// DEBUG COLLECTOR
// ==========================================
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
// Helper per loggare route mancanti
if (!function_exists('logMissingRoute')) {
    function logMissingRoute($name, $params = [])
    {
        DebugCollector::addIssue('route_missing', "Add to fake routes: '$name'", [
            'route_name' => $name,
            'parameters' => $params,
            'suggestion' => "'$name' => 'get',",
        ]);
    }
}
// ==========================================
// ⚠️ MOCK PAGINATOR - Deve stare QUI, DOPO SmartValue e PRIMA delle funzioni
// ==========================================
class MockPaginator extends \Illuminate\Pagination\LengthAwarePaginator {
    public function __construct($items) {
        $collection = collect($items);
        parent::__construct($collection, $collection->count(), 20, 1);
    }

    public function links($view = null, $data = []) {
        return '';
    }

    public function render($view = null, $data = []) {
        return '';
    }

    public function withQueryString() {
        return $this;
    }

    public function appends($key, $value = null) {
        return $this;
    }
}

// ==========================================
// SMART VALUE - Classe Universale
// ==========================================
class SmartValue implements
    Authenticatable,
    \ArrayAccess,
    \Countable,
    \IteratorAggregate,
    \Stringable,
    \JsonSerializable
{
    private $data = [];

    public function __construct($data = [])
    {
        $this->data = is_array($data) ? $data : ['value' => $data];
        if (!isset($this->data['id'])) $this->data['id'] = rand(1, 999);
        if (!isset($this->data['name'])) $this->data['name'] = 'Mock';
    }

    // Authenticatable
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

    // Property Access
    public function __get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
        return $this->generate($name);
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __isset($name)
    {
        return true;
    }

    // ArrayAccess
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        if (isset($this->data[$offset])) {
            return $this->data[$offset];
        }

        if (is_numeric($offset)) {
            return new SmartValue(['id' => $offset + 1, 'name' => "Item $offset"]);
        }

        return $this->generate($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    // Countable
    public function count(): int
    {
        return $this->data['total'] ?? $this->data['count'] ?? 5;
    }

    // Iterator
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->data);
    }

    // Stringable - IMPORTANTE per usarlo come chiave array
    // In SmartValue, sostituisci __toString con:
    public function __toString(): string
    {
        // ⚠️ IMPORTANTE: Quando usato come chiave array, deve ritornare stringa semplice
        if (isset($this->data['id']) && is_numeric($this->data['id'])) {
            return (string)$this->data['id'];
        }
        if (isset($this->data['name']) && is_string($this->data['name'])) {
            return $this->data['name'];
        }
        // Fallback
        return 'mock_' . spl_object_id($this);
    }

    public function jsonSerialize(): mixed
    {
        return $this->data;
    }

    // Method calls
    public function __call($method, $args)
    {
        // Query builder
        if (in_array($method, ['assignments', 'users', 'referees', 'where', 'orderBy', 'with', 'load', 'latest', 'oldest'])) {
            return $this;
        }

        if (in_array($method, ['get', 'all', 'pluck'])) {
            return collect([new SmartValue(['id' => 1, 'name' => 'Item 1'])]);
        }

        if ($method === 'first') {
            return new SmartValue(['id' => 1, 'name' => 'First']);
        }

        if ($method === 'count') {
            return 5;
        }

        if ($method === 'format') {
            return now()->format($args[0] ?? 'd/m/Y');
        }

        // Collection methods → ritorna collection invece di fallire
        if (in_array($method, ['isEmpty', 'isNotEmpty', 'keys', 'values', 'filter', 'map'])) {
            DebugCollector::addIssue('method_on_wrong_type', "Method '$method()' called on non-collection");

            if ($method === 'isEmpty') return false;
            if ($method === 'isNotEmpty') return true;
            if ($method === 'keys') return collect(['key1', 'key2']);
            if ($method === 'values') return collect([1, 2, 3]);
            return collect([]);
        }

        // hasRole, can, is...
        if (str_starts_with($method, 'has') || str_starts_with($method, 'can') || str_starts_with($method, 'is')) {
            return true;
        }

        return $this;
    }

    private function generate($name)
    {
        // ⚠️ PRIORITÀ MASSIMA: Date keywords
        $dateKeywords = ['date', '_at', 'deadline', 'time', 'timestamp', 'created', 'updated', 'deleted', 'published'];

        $lowerName = strtolower($name);
        foreach ($dateKeywords as $keyword) {
            if (str_contains($lowerName, $keyword)) {
                return now(); // ⚠️ Sempre Carbon per date
            }
        }

        // Numeri → INT nativo (ma NON per date!)
        if (
            $name === 'id' || str_starts_with($name, 'total') || str_ends_with($name, 'count') ||
            str_starts_with($name, 'avg_') || str_starts_with($name, 'max_') || str_starts_with($name, 'min_')
        ) {
            return 5;
        }

        // Stringhe
        $strings = [
            'name' => 'Mock Name',
            'email' => 'mock@example.com',
            'phone' => '+39 333 1234567',
            'status' => 'active',
            'role' => 'referee',
            'user_type' => 'referee',
            'level' => 'nazionale',
        ];

        if (isset($strings[$name])) {
            return $strings[$name];
        }

        // Booleani
        if (str_starts_with($name, 'is_') || str_starts_with($name, 'can_') || str_starts_with($name, 'has_')) {
            return true;
        }

        // Collection keywords
        if (in_array($name, ['items', 'data', 'results', 'records', 'list', 'rows'])) {
            return collect([new SmartValue(['id' => 1, 'name' => 'Item'])]);
        }

        // Default → SmartValue
        return new SmartValue(['id' => rand(1, 999), 'name' => ucfirst($name)]);
    }
}

// ==========================================
// FUNZIONI HELPER
// ==========================================

function analyzeViewRecursive($viewName, &$allVariables = [])
{
    $viewFile = resource_path('views/' . str_replace('.', '/', $viewName) . '.blade.php');

    if (!file_exists($viewFile)) {
        return $allVariables;
    }

    $content = file_get_contents($viewFile);

    preg_match_all('/\$(\w+)/', $content, $matches);
    $allVariables = array_merge($allVariables, $matches[1]);

    // Include
    preg_match_all('/@include\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $includes);
    foreach ($includes[1] as $includedView) {
        analyzeViewRecursive($includedView, $allVariables);
    }

    // Extends
    if (preg_match('/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $extends)) {
        analyzeViewRecursive($extends[1], $allVariables);
    }

    return array_unique($allVariables);
}

function generateValue($name)
{
    // Array keywords → Array PHP nativo
    if (
        str_contains($name, 'stats') || str_contains($name, 'Stats') ||
        in_array($name, ['filters', 'validation', 'conflicts', 'issues', 'chartData'])
    ) {
        return [
            'total' => 5,
            'count' => 5,
            'items' => [1, 2, 3, 4, 5],
            'data' => [1, 2, 3, 4, 5],
        ];
    }

    // ⚠️ Plurali → MockPaginator (per supportare ->links())
    if (str_ends_with($name, 's') && !in_array($name, ['class', 'status', 'errors'])) {
        return new MockPaginator([
            new SmartValue(['id' => 1, 'name' => 'Item 1']),
            new SmartValue(['id' => 2, 'name' => 'Item 2']),
        ]);
    }

    // Booleani
    if (str_starts_with($name, 'is') || str_starts_with($name, 'can')) {
        return true;
    }

    // Default → SmartValue
    return new SmartValue(['id' => rand(1, 999), 'name' => ucfirst($name)]);
}

// ==========================================
// ROUTE
// ==========================================

Route::get('/dev/view-preview/{view?}', function ($view = null) {

    if (!$view) {
        $viewsPath = resource_path('views');
        $allViews = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace($viewsPath . '/', '', $file->getPathname());
                if (str_contains($relativePath, '.blade.php')) {
                    $viewName = str_replace('.blade.php', '', $relativePath);
                    $viewName = str_replace('/', '.', $viewName);
                    $allViews[] = [
                        'name' => $viewName,
                        'path' => $relativePath,
                        'size' => $file->getSize() // ⚠️ FIX: Aggiungi size
                    ];
                }
            }
        }

        usort($allViews, fn($a, $b) => strcmp($a['name'], $b['name']));
        return view('dev.view-list', ['views' => $allViews]);
    }


    $viewName = str_replace('/', '.', $view);

    if (!view()->exists($viewName)) {
        abort(404, "View '$viewName' non trovata");
    }

    DebugCollector::clear();

    // Error handler
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        if (!str_contains($errfile, 'vendor')) {
            DebugCollector::addIssue('warning', $errstr, ['file' => basename($errfile), 'line' => $errline]);
        }
        return true;
    });

    try {
        $allVariables = analyzeViewRecursive($viewName);

        // Genera TUTTE le variabili
        $data = ['errors' => new \Illuminate\Support\ViewErrorBag()];
        foreach ($allVariables as $varName) {
            if (!isset($data[$varName])) {
                $value = generateValue($varName);
                $data[$varName] = $value ?? new SmartValue(['id' => 1, 'name' => ucfirst($varName)]);
            }
        }

        // Common vars
        $commonVars = ['conflicts', 'issues', 'stats', 'zones', 'clubs', 'items', 'data', 'referee', 'tournament'];
        foreach ($commonVars as $commonVar) {
            if (!isset($data[$commonVar])) {
                $data[$commonVar] = generateValue($commonVar);
            }
        }

        $fakeUser = new SmartValue([
            'id' => 1,
            'name' => 'Mock User',
            'email' => 'mock@example.com',
            'user_type' => 'referee',
            'is_admin' => true,
        ]);

        Auth::setUser($fakeUser);

        // Render
        $renderedView = view($viewName, $data)->render();

        restore_error_handler();
        Auth::logout();

        // Debug panel
        if (DebugCollector::hasIssues()) {
            $debugPanel = view('dev.debug-panel', [
                'issues' => DebugCollector::getIssues(),
                'viewName' => $viewName,
            ])->render();

            $renderedView .= $debugPanel;
        }

        return response($renderedView);
    } catch (\Throwable $e) {
        restore_error_handler();
        try {
            Auth::logout();
        } catch (\Throwable $e2) {
        }

        return response()->view('dev.view-error', [
            'view' => $viewName,
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
})->name('dev.view-preview')->where('view', '.*');
