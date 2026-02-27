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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit;
    }

    // Passenger variables
    $firstname    = trim($_POST['firstname'] ?? '');
    $middlename   = trim($_POST['middlename'] ?? '');
    $lastname     = trim($_POST['lastname'] ?? '');
    $dob_month    = trim($_POST['dob-month'] ?? '');
    $dob_day      = trim($_POST['dob-day'] ?? '');
    $dob_year     = trim($_POST['dob-year'] ?? '');
    $dob_full     = $dob_year . '-' . $dob_month . '-' . $dob_day;
    $email        = trim($_POST['email'] ?? '');
    $phonenumber  = trim($_POST['phonenumber'] ?? '');
    $street1      = trim($_POST['StreetAddress1'] ?? '');
    $street2      = trim($_POST['StreetAddress2'] ?? '');
    $city         = trim($_POST['city'] ?? '');
    $state        = trim($_POST['state'] ?? '');
    $pincode      = trim($_POST['pincode'] ?? '');
    $country      = trim($_POST['country'] ?? '');
    $dep_country  = trim($_POST['departing-country'] ?? '');
    $dep_city     = trim($_POST['departing-city'] ?? '');
    $dest_country = trim($_POST['destination-country'] ?? '');
    $dest_city    = trim($_POST['destination-city'] ?? '');
    $airline      = trim($_POST['airline'] ?? '');
    $fare         = trim($_POST['fare'] ?? '');
    $reg_email    = strtolower(trim($_POST['reg_email'] ?? $email));

    // Insert user into user_detials (prepared statement)
    $insert = $conn->prepare("INSERT INTO user_detials 
        (first_name, middle_name, last_name, dob_y_m_d, mail, phone_number,
         address_1, address_2, city, state, pincode, country,
         departing, departing_city, destination, destination_city, airline, fare)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $insert->bind_param(
        "ssssssssssssssssss",
        $firstname, $middlename, $lastname, $dob_full, $email, $phonenumber,
        $street1, $street2, $city, $state, $pincode, $country,
        $dep_country, $dep_city, $dest_country, $dest_city, $airline, $fare
    );

    $insert->execute();
    $user_id = $conn->insert_id;
    $insert->close();

    // Registration table mein last_user_id update karo
    $update = $conn->prepare("UPDATE registration SET last_user_id = ? WHERE email = ?");
    $update->bind_param("is", $user_id, $reg_email);
    $update->execute();
    $update->close();

    // Flight search (prepared statement)
    $select = $conn->prepare("SELECT 
        departing_country, departing_city,
        destination_country, destination_city,
        airline, fare_b_i_l,
        flight_price, departure_time, arrival_time,
        duration, flight_class, meal_included,
        hotel_name, hotel_price, hotel_rating,
        pool, breakfast, wifi, parking,
        beach, bar, spa, restaurant, fine_dining, sports, id
        FROM flight_hotel
        WHERE departing_city = ? AND destination_city = ?
        ORDER BY FIELD(fare_b_i_l, 'budget', 'intermediate', 'luxury'), flight_price ASC");

    $select->bind_param("ss", $dep_city, $dest_city);
    $select->execute();
    $result = $select->get_result();

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Query error']);
        exit;
    }

    $flights = ['budget' => [], 'intermediate' => [], 'luxury' => []];

    while ($row = $result->fetch_assoc()) {
        $type  = strtolower(trim($row['fare_b_i_l']));
        $entry = [
            'departing_country'   => $row['departing_country'],
            'departing_city'      => $row['departing_city'],
            'destination_country' => $row['destination_country'],
            'destination_city'    => $row['destination_city'],
            'airline'             => $row['airline'],
            'fare_type'           => $row['fare_b_i_l'],
            'flight_price'        => (float)$row['flight_price'],
            'departure_time'      => $row['departure_time'],
            'arrival_time'        => $row['arrival_time'],
            'duration'            => $row['duration'],
            'flight_class'        => $row['flight_class'],
            'meal_included'       => (bool)$row['meal_included'],
            'hotel_name'          => $row['hotel_name'],
            'hotel_price'         => (float)$row['hotel_price'],
            'hotel_rating'        => $row['hotel_rating'],
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
            ],
            'total_price' => (float)$row['flight_price'] + (float)$row['hotel_price'],
            'id' => (int)$row['id'],
        ];

        if (array_key_exists($type, $flights)) {
            $flights[$type][] = $entry;
        }
    }
    $select->close();

    $total_found = count($flights['budget']) + count($flights['intermediate']) + count($flights['luxury']);

    if ($total_found > 0) {
        echo json_encode([
            'success'   => true,
            'user_id'   => $user_id,
            'from'      => $dep_city,
            'to'        => $dest_city,
            'passenger' => $firstname . ' ' . $lastname,
            'flights'   => $flights,
            'count'     => [
                'budget'       => count($flights['budget']),
                'intermediate' => count($flights['intermediate']),
                'luxury'       => count($flights['luxury'])
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "No packages found from '$dep_city' to '$dest_city'."
        ]);
    }

    $conn->close();
}
?>