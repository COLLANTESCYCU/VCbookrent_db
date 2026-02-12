<?php
// Bootstrap initialization - this runs FIRST, before any content
// Prevents "headers already sent" errors

// Start session before anything else
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set up error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);
