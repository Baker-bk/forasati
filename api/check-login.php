
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

if (isset($_SESSION["user_id"])) {

    echo json_encode([
        "success" => true,
        "logged_in" => true,
        "user" => [
            "id" => $_SESSION["user_id"],
            "name" => $_SESSION["user_name"],
            "email" => $_SESSION["user_email"]
        ]
    ], JSON_UNESCAPED_UNICODE);

} else {

    echo json_encode([
        "success" => true,
        "logged_in" => false,
        "message" => "المستخدم غير مسجل الدخول"
    ], JSON_UNESCAPED_UNICODE);

}

