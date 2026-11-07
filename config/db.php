
<?php

$host = "sql111.infinityfree.com";
$dbname = "if0_43380800_DataBase";
$username = "if0_43380800";
$password = "TWyw7lmrQt";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    http_response_code(500);
    exit("Database connection failed.");

}

