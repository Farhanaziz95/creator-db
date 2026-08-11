<?php
/**
 * Converts ANY uncaught PHP error, warning, or fatal into a clean JSON
 * response instead of letting it crash into raw HTML/error text — same
 * root cause as the earlier DataTables "Invalid JSON response" bug (a PHP
 * crash mid-response breaks whatever the frontend was expecting to parse).
 *
 * Call setup_json_error_handling() as the very first line in any endpoint
 * that does real work (AI calls, file parsing, external APIs) — anywhere a
 * crash currently means "no idea what happened, check nothing."
 */
function setup_json_error_handling(): void
{
    ini_set('display_errors', '0'); // never leak raw PHP error HTML into what should be JSON
    error_reporting(E_ALL);

    // Turns warnings/notices into catchable exceptions so a try/catch
    // around your endpoint logic actually catches them.
    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    // Catches true fatals (out of memory, syntax-level issues, etc.) that
    // even a try/catch can't intercept — this runs no matter how the
    // script dies.
    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            echo json_encode([
                'error' => 'Fatal error: ' . $error['message'] . ' in ' . basename($error['file']) . ' line ' . $error['line'],
            ]);
        }
    });
}
