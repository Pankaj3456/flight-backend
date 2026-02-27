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
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

$user_id = intval($_GET['user_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'No user ID']);
    exit;
}

// user_detials se profile fetch karo
$stmt = $conn->prepare("SELECT * FROM user_detials WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Registration table se naam fetch karo
$mail      = $user['mail'];
$reg_name  = null;
$reg_email = null;

if ($mail) {
    $reg_stmt = $conn->prepare("SELECT name, email FROM registration WHERE email = ?");
    $reg_stmt->bind_param("s", $mail);
    $reg_stmt->execute();
    $reg_result = $reg_stmt->get_result();
    if ($reg_result->num_rows > 0) {
        $reg_user  = $reg_result->fetch_assoc();
        $reg_name  = $reg_user['name'];
        $reg_email = $reg_user['email'];
    }
    $reg_stmt->close();
}

// Saare bookings fetch karo
$all_bookings_sql = "
    SELECT b.id as booking_id, b.booked_at, fh.*
    FROM bookings b
    JOIN flight_hotel fh ON fh.id = b.flight_hotel_id
    JOIN user_detials ud ON ud.id = b.user_id
    WHERE ud.mail = ?
    ORDER BY b.booked_at DESC
";

$pkg_stmt = $conn->prepare($all_bookings_sql);
$pkg_stmt->bind_param("s", $mail);
$pkg_stmt->execute();
$pkg_result = $pkg_stmt->get_result();

$packages = [];
while ($row = $pkg_result->fetch_assoc()) {
    $packages[] = [
        'booking_id'       => $row['booking_id'],
        'booked_at'        => $row['booked_at'],
        'id'               => $row['id'],
        'departing_city'   => $row['departing_city'],
        'destination_city' => $row['destination_city'],
        'airline'          => $row['airline'],
        'fare_b_i_l'       => $row['fare_b_i_l'],
        'flight_price'     => (float)$row['flight_price'],
        'departure_time'   => $row['departure_time'],
        'arrival_time'     => $row['arrival_time'],
        'duration'         => $row['duration'],
        'flight_class'     => $row['flight_class'],
        'meal_included'    => (bool)$row['meal_included'],
        'hotel_name'       => $row['hotel_name'],
        'hotel_price'      => (float)$row['hotel_price'],
        'hotel_rating'     => $row['hotel_rating'],
        'total_price'      => (float)$row['flight_price'] + (float)$row['hotel_price'],
        'amenities' => [
            'pool'        => (bool)$row['pool'],
            'breakfast'   => (bool)$row['breakfast'],
            'wifi'        => (bool)$row['wifi'],
            'parking'     => (bool)$row['parking'],
            'beach'       => (bool)$row['beach'],
            'bar'         => (bool)$row['bar'],
            'spa'         => (bool)$row['spa'],
            'restaurant'  => (bool)$row['restaurant'],
            'fine_dining' => (bool)$row['fine_dining'],
            'sports'      => (bool)$row['sports'],
        ]
    ];
}
$pkg_stmt->close();
$conn->close();

// DOB format
$dob_parts   = explode('-', $user['dob_y_m_d'] ?? '');
$dob_display = '';
if (count($dob_parts) === 3) {
    $months = [
        '01'=>'January','02'=>'February','03'=>'March','04'=>'April',
        '05'=>'May','06'=>'June','07'=>'July','08'=>'August',
        '09'=>'September','10'=>'October','11'=>'November','12'=>'December'
    ];
    $dob_display = ($months[$dob_parts[1]] ?? $dob_parts[1]) . ' ' . (int)$dob_parts[2] . ', ' . $dob_parts[0];
}

echo json_encode([
    'success'   => true,
    'reg_name'  => $reg_name  ?? trim($user['first_name'] . ' ' . $user['last_name']),
    'reg_email' => $reg_email ?? $user['mail'],
    'user' => [
        'first_name'       => $user['first_name'],
        'middle_name'      => $user['middle_name'],
        'last_name'        => $user['last_name'],
        'full_name'        => trim($user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']),
        'email'            => $user['mail'],
        'phone'            => $user['phone_number'],
        'dob'              => $dob_display,
        'address1'         => $user['address_1'],
        'address2'         => $user['address_2'],
        'city'             => $user['city'],
        'state'            => $user['state'],
        'pincode'          => $user['pincode'],
        'country'          => $user['country'],
        'departing'        => $user['departing'],
        'departing_city'   => $user['departing_city'],
        'destination'      => $user['destination'],
        'destination_city' => $user['destination_city'],
        'airline'          => $user['airline'],
        'fare'             => $user['fare'],
    ],
    'packages'  => $packages,
    'pkg_count' => count($packages),
]);
?>