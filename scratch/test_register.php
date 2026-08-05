<?php
require_once __DIR__.'/../connection.php';
$name = 'Test User';
$email = 'test' . time() . '@example.com';
$phoneClean = '923000000000';
$password = 'Test1234';
$role = 'buyer';
$city = 'karachi';
$business_name = null;
$verifyToken = bin2hex(random_bytes(32));

try {
    $userId = DB::insert(
        "INSERT INTO users (name,email,phone,password,role,city,business_name,email_verify_token,status)
         VALUES (?,?,?,?,?,?,?,?,'active')",
        [
            $name,
            $email,
            $phoneClean,
            Auth::hashPassword($password),
            $role,
            $city,
            $business_name,
            $verifyToken,
        ]
    );
    echo "Inserted: $userId\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
