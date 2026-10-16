<?php

require __DIR__ . '/vendor/autoload.php';

$twig = new Twig\Environment(
    new Twig\Loader\FilesystemLoader(__DIR__ . '/templates'),
    ['autoescape' => 'html']
);

// Read the database connection parameters from environment variables
$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASSWORD');

// Read the password file path from an environment variable
// $password_file_path = getenv('PASSWORD_FILE_PATH');

// Read the password from the file
// $db_pass = trim(file_get_contents($password_file_path));

// Create a new PDO instance
$db_handle = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);

// Create the "messages" table if it doesn't exist
$db_handle->exec("
 CREATE TABLE IF NOT EXISTS messages (
     id INT AUTO_INCREMENT PRIMARY KEY,
     message VARCHAR(255) NOT NULL
 )
");

// Create message
$stmt = $db_handle->query("SELECT COUNT(*) as count FROM messages");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['count'];
$message_prefix = getenv('MESSAGE_PREFIX') ?: 'message-';
$initial_message = $message_prefix . $count;
$stmt = $db_handle->prepare('SELECT id FROM messages WHERE message = :message LIMIT 1');
$stmt->execute(['message' => $initial_message]);
if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    $stmt = $db_handle->prepare('INSERT INTO messages (message) VALUES (:message)');
    $stmt->execute(['message' => $initial_message]);
}

// Retrieve all records from the "messages" table
$stmt = $db_handle->query("SELECT * FROM messages");
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo $twig->render('messages.html.twig', ['messages' => $messages]);

// Close the database connection
$db_handle = null;
?>
