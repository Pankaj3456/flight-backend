<?php
include("../config/db.php");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true);
$email    = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Please fill all fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

// Password verify karo
$stmt = $conn->prepare("SELECT name, email, password, last_user_id FROM registration WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    $stmt->close(); $conn->close(); exit;
}

$dbUser = $result->fetch_assoc();
$stmt->close();

if (!password_verify($password, $dbUser['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    $conn->close(); exit;
}

// last_user_id se booking check karo
$user_id     = $dbUser['last_user_id'];
$has_booking = false;

if ($user_id) {
    $bk_stmt = $conn->prepare("SELECT id FROM bookings WHERE user_id = ? LIMIT 1");
    $bk_stmt->bind_param("i", $user_id);
    $bk_stmt->execute();
    $bk_result = $bk_stmt->get_result();
    $has_booking = ($bk_result->num_rows > 0);
    $bk_stmt->close();
}

$conn->close();

echo json_encode([
    'success'     => true,
    'name'        => $dbUser['name'],
    'email'       => $dbUser['email'],
    'user_id'     => $user_id,
    'has_booking' => $has_booking
]);
?>