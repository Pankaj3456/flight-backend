<?php
header('Content-Type: application/json');
echo json_encode([
    'status'  => 'ok',
    'message' => 'Flight Reservation Backend is running!'
]);
?>