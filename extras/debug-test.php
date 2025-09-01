<?php
/**
 * Debug Test Script for Bible Reading Challenge
 *
 * This script helps verify that Xdebug is properly configured and working
 * Use this to test your debugging setup before working on the main application
 *
 * Usage:
 * 1. Move this script into www/
 * 2. Start your development environment: ./dev-start.sh
 * 3. Start debugging in VS Code (F5 - "Docker: Listen for Xdebug")
 * 4. Or run via CLI: docker-compose exec php php /var/www/html/debug-test.php
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🐛 Xdebug Test Script</h1>\n";
echo "<p>Testing debugging setup for Bible Reading Challenge</p>\n";

// Test 1: Check if Xdebug is loaded
echo "<h2>📋 Test 1: Xdebug Extension Check</h2>\n";
if (extension_loaded('xdebug')) {
    echo "✅ <strong>SUCCESS:</strong> Xdebug extension is loaded<br>\n";
    echo "📍 <em>Set a breakpoint on the next line to test step debugging</em><br>\n";
    $xdebug_loaded = true; // <- SET BREAKPOINT HERE
} else {
    echo "❌ <strong>ERROR:</strong> Xdebug extension is NOT loaded<br>\n";
    $xdebug_loaded = false;
}

// Test 2: Xdebug configuration
echo "<h2>⚙️ Test 2: Xdebug Configuration</h2>\n";
if ($xdebug_loaded) {
    $xdebug_info = [];

    // Get Xdebug mode
    if (function_exists('xdebug_info')) {
        $info = xdebug_info();
        $xdebug_info['version'] = $info['version'] ?? 'Unknown';
    }

    // Get configuration values
    $config_items = [
        'xdebug.mode',
        'xdebug.client_host',
        'xdebug.client_port',
        'xdebug.start_with_request',
        'xdebug.log_level',
        'xdebug.idekey'
    ];

    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Configuration</th><th>Value</th><th>Status</th></tr>\n";

    foreach ($config_items as $item) {
        $value = ini_get($item);
        $status = '';

        // Provide recommendations
        switch ($item) {
            case 'xdebug.mode':
                $status = (strpos($value, 'debug') !== false) ? '✅ Debug enabled' : '⚠️ Debug not enabled';
                break;
            case 'xdebug.client_host':
                $status = ($value !== '') ? '✅ Client host set' : '❌ No client host';
                break;
            case 'xdebug.client_port':
                $status = ($value == '9003') ? '✅ Standard port' : '⚠️ Non-standard port';
                break;
            case 'xdebug.start_with_request':
                $status = ($value == '1' || $value === 'yes') ? '✅ Auto-start enabled' : '⚠️ Manual start required';
                break;
            default:
                $status = ($value !== '') ? '✅ Configured' : '⚠️ Not set';
        }

        echo "<tr><td>{$item}</td><td>" . ($value ?: '<em>not set</em>') . "</td><td>{$status}</td></tr>\n";
    }
    echo "</table>\n";
}

// Test 3: Environment variables
echo "<h2>🌍 Test 3: Environment Variables</h2>\n";
$env_vars = [
    'APP_ENV',
    'XDEBUG_MODE',
    'XDEBUG_CLIENT_HOST',
    'XDEBUG_CLIENT_PORT',
    'PHP_MEMORY_LIMIT'
];

echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>Environment Variable</th><th>Value</th></tr>\n";

foreach ($env_vars as $var) {
    $value = getenv($var) ?: $_ENV[$var] ?? '<em>not set</em>';
    echo "<tr><td>{$var}</td><td>{$value}</td></tr>\n";
}
echo "</table>\n";

// Test 4: Debugging functionality
echo "<h2>🔍 Test 4: Debugging Features</h2>\n";

// Test array for step debugging
$test_data = [
    'users' => [
        ['id' => 1, 'name' => 'John Doe', 'progress' => 25],
        ['id' => 2, 'name' => 'Jane Smith', 'progress' => 50],
        ['id' => 3, 'name' => 'Bob Wilson', 'progress' => 75]
    ],
    'readings' => [
        'Genesis 1-3',
        'Psalm 23',
        'John 3:16',
        'Romans 8:28'
    ]
];

echo "<p>📍 <strong>Set breakpoints in the loop below to test step debugging:</strong></p>\n";
echo "<ul>\n";

// Process test data - good place for breakpoints
foreach ($test_data['users'] as $index => $user) {
    $progress_status = '';

    // Set breakpoint here to inspect variables
    if ($user['progress'] >= 75) {
        $progress_status = 'Excellent progress! 🌟';
    } elseif ($user['progress'] >= 50) {
        $progress_status = 'Good progress 👍';
    } elseif ($user['progress'] >= 25) {
        $progress_status = 'Getting started 📖';
    } else {
        $progress_status = 'Just beginning 🌱';
    }

    echo "<li>{$user['name']} (ID: {$user['id']}) - {$user['progress']}% - {$progress_status}</li>\n";

    // Another good breakpoint location
    $calculation = $user['progress'] * 1.5; // <- SET BREAKPOINT HERE
}

echo "</ul>\n";

// Test 5: Function call stack
echo "<h2>📚 Test 5: Function Call Stack</h2>\n";

function level1_function($data) {
    echo "<p>📍 Level 1 function called</p>\n";
    return level2_function($data . " -> Level 1");
}

function level2_function($data) {
    echo "<p>📍 Level 2 function called</p>\n";
    return level3_function($data . " -> Level 2");
}

function level3_function($data) {
    echo "<p>📍 Level 3 function called</p>\n";
    // Set breakpoint here to see call stack
    $result = "Final result: " . $data . " -> Level 3"; // <- SET BREAKPOINT HERE
    return $result;
}

$stack_test_result = level1_function("Starting");
echo "<p><strong>Call stack result:</strong> {$stack_test_result}</p>\n";

// Test 6: Exception handling
echo "<h2>⚠️ Test 6: Exception Debugging</h2>\n";

try {
    echo "<p>Testing exception handling...</p>\n";

    // Simulate a potential error condition
    $test_array = ['a', 'b', 'c'];

    // This will work fine
    echo "<p>Accessing valid index: {$test_array[1]}</p>\n";

    // Uncomment the line below to test exception debugging
    // throw new Exception("This is a test exception for debugging");

    echo "<p>✅ Exception test completed (no exception thrown)</p>\n";

} catch (Exception $e) {
    echo "<p>🚨 <strong>Exception caught:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p>📍 <em>Set breakpoint in catch block to debug exceptions</em></p>\n";

    // Set breakpoint here to inspect exception details
    $error_details = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]; // <- SET BREAKPOINT HERE
}

// Test 7: Database connection (if applicable)
echo "<h2>🗄️ Test 7: Database Connection Test</h2>\n";

try {
    $db_path = '/var/www/html/brc.db';

    if (file_exists($db_path)) {
        $pdo = new PDO('sqlite:' . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Test query
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' LIMIT 5");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($tables)) {
            echo "<p>✅ Database connection successful</p>\n";
            echo "<p><strong>Found tables:</strong> " . implode(', ', $tables) . "</p>\n";

            // Set breakpoint here to inspect database results
            $db_info = [
                'path' => $db_path,
                'tables' => $tables,
                'table_count' => count($tables)
            ]; // <- SET BREAKPOINT HERE
        } else {
            echo "<p>⚠️ Database connected but no tables found</p>\n";
        }
    } else {
        echo "<p>⚠️ Database file not found at: {$db_path}</p>\n";
    }

} catch (Exception $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>\n";
}

// Summary
echo "<h2>📊 Debug Test Summary</h2>\n";
echo "<div style='background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px;'>\n";
echo "<h3>✅ If debugging is working correctly, you should be able to:</h3>\n";
echo "<ol>\n";
echo "<li>Set breakpoints on the marked lines</li>\n";
echo "<li>Step through code execution</li>\n";
echo "<li>Inspect variable values in your IDE</li>\n";
echo "<li>See the call stack</li>\n";
echo "<li>Evaluate expressions in the debug console</li>\n";
echo "</ol>\n";

echo "<h3>🔧 Debugging Tips:</h3>\n";
echo "<ul>\n";
echo "<li><strong>VS Code:</strong> Use F5 to start debugging, F10 for step over, F11 for step into</li>\n";
echo "<li><strong>Breakpoints:</strong> Click in the gutter next to line numbers</li>\n";
echo "<li><strong>Variables:</strong> Hover over variables or check the Variables panel</li>\n";
echo "<li><strong>Watch:</strong> Add expressions to the Watch panel</li>\n";
echo "<li><strong>Console:</strong> Use the Debug Console to evaluate expressions</li>\n";
echo "</ul>\n";

echo "<h3>📚 Next Steps:</h3>\n";
echo "<ul>\n";
echo "<li>Try setting breakpoints on different lines</li>\n";
echo "<li>Practice stepping through the code</li>\n";
echo "<li>Inspect the \$test_data array contents</li>\n";
echo "<li>Test debugging with your actual application code</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<hr>\n";
echo "<p><em>Debug test completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
echo "<p><strong>Environment:</strong> " . (getenv('APP_ENV') ?: 'Unknown') . "</p>\n";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>\n";
echo "<p><strong>Xdebug Version:</strong> " . (extension_loaded('xdebug') ? phpversion('xdebug') : 'Not loaded') . "</p>\n";

?>
