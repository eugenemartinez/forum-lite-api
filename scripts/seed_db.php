<?php

// Adjust the path to autoload.php if your script is in a different subfolder
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// --- Configuration ---
$dotenvPath = __DIR__ . '/../'; // Path to your .env file
$jsonFilePath = __DIR__ . '/seed_db.json';
$tablePrefix = 'forumlite_'; // Your database table prefix

// --- Load Environment Variables ---
try {
    $dotenv = Dotenv::createImmutable($dotenvPath);
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    die("Could not load .env file: " . $e->getMessage() . "\nMake sure it exists at: " . realpath($dotenvPath) . "\n");
}

$seedDbUrl = $_ENV['SEED_DB'] ?? null;

if (!$seedDbUrl) {
    die("SEED_DB environment variable not set.\n");
}

// --- Parse Database Connection String ---
$dbUrlParts = parse_url($seedDbUrl);
if ($dbUrlParts === false || !isset($dbUrlParts['scheme']) || $dbUrlParts['scheme'] !== 'postgresql') {
    die("Invalid SEED_DB URL format. Expected format: postgresql://user:password@host:port/dbname\n");
}

$dbHost = $dbUrlParts['host'] ?? 'localhost';
$dbPort = $dbUrlParts['port'] ?? 5432;
$dbName = isset($dbUrlParts['path']) ? ltrim($dbUrlParts['path'], '/') : null;
$dbUser = $dbUrlParts['user'] ?? null;
$dbPass = $dbUrlParts['pass'] ?? null;

if (!$dbName || !$dbUser) {
    die("Database name or user missing in SEED_DB URL.\n");
}

$dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";

// --- Establish PDO Connection ---
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Successfully connected to the database: {$dbName}\n";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// --- Seed Users ---
echo "Seeding users...\n";
$placeholderUsers = [
    'placeholder_user_1' => ['name' => 'Alice Wonderland', 'email' => 'alice@example.com', 'password' => 'password123'],
    'placeholder_user_2' => ['name' => 'Bob The Builder', 'email' => 'bob@example.com', 'password' => 'password123'],
    'placeholder_user_3' => ['name' => 'Charlie Brown', 'email' => 'charlie@example.com', 'password' => 'password123'],
    'placeholder_user_4' => ['name' => 'Diana Prince', 'email' => 'diana@example.com', 'password' => 'password123'],
    'placeholder_user_5' => ['name' => 'Edward Scissorhands', 'email' => 'edward@example.com', 'password' => 'password123'],
    'placeholder_user_6' => ['name' => 'Fiona Gallagher', 'email' => 'fiona@example.com', 'password' => 'password123'],
    'placeholder_user_7' => ['name' => 'George Jetson', 'email' => 'george@example.com', 'password' => 'password123'],
    'placeholder_user_8' => ['name' => 'Harley Quinn', 'email' => 'harley@example.com', 'password' => 'password123'],
    'placeholder_user_9' => ['name' => 'Indiana Jones', 'email' => 'indy@example.com', 'password' => 'password123'],
    'placeholder_user_10' => ['name' => 'John Wick', 'email' => 'johnw@example.com', 'password' => 'password123'],
    'placeholder_user_11' => ['name' => 'Kara Danvers', 'email' => 'kara@example.com', 'password' => 'password123'],
    'placeholder_user_12' => ['name' => 'Luke Skywalker', 'email' => 'luke@example.com', 'password' => 'password123'],
    'placeholder_user_13' => ['name' => 'Mary Poppins', 'email' => 'mary@example.com', 'password' => 'password123'],
    'placeholder_user_14' => ['name' => 'Neo Anderson', 'email' => 'neo@example.com', 'password' => 'password123'],
    'placeholder_user_15' => ['name' => 'Optimus Prime', 'email' => 'optimus@example.com', 'password' => 'password123'],
];
$actualUserIds = [];

foreach ($placeholderUsers as $placeholderKey => $userData) {
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM {$tablePrefix}users WHERE email = :email");
        $stmt->execute(['email' => $userData['email']]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            $actualUserIds[$placeholderKey] = $existingUser['id'];
            echo "User {$userData['name']} already exists with ID: {$existingUser['id']}.\n";
        } else {
            // Create user
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO {$tablePrefix}users (name, email, password, email_verified_at, created_at, updated_at)
                 VALUES (:name, :email, :password, NOW(), NOW(), NOW()) RETURNING id"
            );
            $stmt->execute([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => $hashedPassword,
            ]);
            $newUserId = $stmt->fetchColumn();
            $actualUserIds[$placeholderKey] = $newUserId;
            echo "Created user {$userData['name']} with ID: {$newUserId}.\n";
        }
    } catch (PDOException $e) {
        echo "Error seeding user {$userData['name']}: " . $e->getMessage() . "\n";
    }
}

if (count($actualUserIds) < count($placeholderUsers)) {
    die("Failed to seed all placeholder users. Aborting post/comment seeding.\n");
}

// --- Load Post/Comment Data from JSON ---
if (!file_exists($jsonFilePath)) {
    die("Seed data file not found: {$jsonFilePath}\n");
}
$seedDataJson = file_get_contents($jsonFilePath);
$seedData = json_decode($seedDataJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error decoding JSON data: " . json_last_error_msg() . "\n");
}

// --- Seed Posts and Comments ---
echo "Seeding posts and comments...\n";
$postsCreated = 0;
$commentsCreated = 0;

foreach ($seedData as $item) {
    $postAuthorPlaceholder = $item['post_author_placeholder'];
    $postTitle = $item['title'];
    $postContent = $item['content'];
    $commentData = $item['comment'];

    if (!isset($actualUserIds[$postAuthorPlaceholder])) {
        echo "Skipping post '{$postTitle}' due to missing author ID for placeholder '{$postAuthorPlaceholder}'.\n";
        continue;
    }
    $postAuthorId = $actualUserIds[$postAuthorPlaceholder];

    try {
        // Insert Post
        $stmt = $pdo->prepare(
            "INSERT INTO {$tablePrefix}posts (user_id, title, content, created_at, updated_at)
             VALUES (:user_id, :title, :content, NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([
            'user_id' => $postAuthorId,
            'title' => $postTitle,
            'content' => $postContent,
        ]);
        $postId = $stmt->fetchColumn();
        $postsCreated++;
        echo "Created post '{$postTitle}' (ID: {$postId}) by user ID {$postAuthorId}.\n";

        // Insert Comment
        $commentAuthorPlaceholder = $commentData['comment_author_placeholder'];
        $commentContent = $commentData['content'];

        if (!isset($actualUserIds[$commentAuthorPlaceholder])) {
            echo "Skipping comment for post ID {$postId} due to missing author ID for placeholder '{$commentAuthorPlaceholder}'.\n";
            continue;
        }
        $commentAuthorId = $actualUserIds[$commentAuthorPlaceholder];

        $stmt = $pdo->prepare(
            "INSERT INTO {$tablePrefix}comments (post_id, user_id, content, created_at, updated_at)
             VALUES (:post_id, :user_id, :content, NOW(), NOW())"
        );
        $stmt->execute([
            'post_id' => $postId,
            'user_id' => $commentAuthorId,
            'content' => $commentContent,
        ]);
        $commentsCreated++;
        echo "  - Created comment by user ID {$commentAuthorId} for post ID {$postId}.\n";

    } catch (PDOException $e) {
        echo "Error seeding post '{$postTitle}' or its comment: " . $e->getMessage() . "\n";
    }
}

echo "\nSeeding complete.\n";
echo "Total users processed/verified: " . count($actualUserIds) . "\n";
echo "Total posts created: {$postsCreated}\n";
echo "Total comments created: {$commentsCreated}\n";

?>
