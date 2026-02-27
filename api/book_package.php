<?php
include("../config/db.php");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed.']);
    exit;
}

$user_id         = intval($_POST['user_id'] ?? 0);
$flight_hotel_id = intval($_POST['flight_hotel_id'] ?? 0);

if (!$user_id || !$flight_hotel_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

// Already booked check karo
$check = $conn->prepare("SELECT id FROM bookings WHERE user_id = ? AND flight_hotel_id = ?");
$check->bind_param("ii", $user_id, $flight_hotel_id);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode([
        'success'      => true,
        'already'      => true,
        'message'      => 'You already booked this package!',
        'redirect_url' => 'user.html?user_id=' . $user_id
    ]);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

// Booking insert karo
$insert = $conn->prepare("INSERT INTO bookings (user_id, flight_hotel_id, booked_at) VALUES (?, ?, NOW())");
$insert->bind_param("ii", $user_id, $flight_hotel_id);

if ($insert->execute()) {
    echo json_encode([
        'success'      => true,
        'already'      => false,
        'message'      => 'Package booked successfully!',
        'redirect_url' => 'user.html?user_id=' . $user_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Booking failed: ' . $conn->error]);
}

$insert->close();
$conn->close();
?>