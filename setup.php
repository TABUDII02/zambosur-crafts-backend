<?php
/**
 * Database Setup Script for ZamboSur Crafts
 * Run this script once to initialize the database
 */

require_once 'config.php';

echo "Setting up ZamboSur Crafts database...\n";

// Read the SQL file
$sql = file_get_contents('database.sql');

if (!$sql) {
    die("Error: Could not read database.sql file\n");
}

// Split into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

// Connect to MySQL (without selecting database first)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "Connected to MySQL server\n";

// Drop database if exists and recreate
$conn->query("DROP DATABASE IF EXISTS zambosur_db");
$conn->query("CREATE DATABASE zambosur_db");
$conn->query("USE zambosur_db");

echo "Database recreated\n";

// Execute each statement
foreach ($statements as $statement) {
    if (!empty($statement)) {
        echo "Executing: " . substr($statement, 0, 50) . "...\n";
        if ($conn->query($statement) === TRUE) {
            echo "✓ Success\n";
        } else {
            echo "✗ Error: " . $conn->error . "\n";
        }
    }
}

$conn->close();
echo "Database setup complete!\n";
?>