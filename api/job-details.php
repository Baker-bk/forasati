
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";

try {

    $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "رقم الوظيفة غير صحيح"
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            company,
            city,
            category,
            description,
            requirements,
            salary,
            job_type,
            deadline,
            created_at
        FROM jobs
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $job = $stmt->fetch();

    if (!$job) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "الوظيفة غير موجودة أو غير متاحة"
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    echo json_encode([
        "success" => true,
        "job" => $job
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "حدث خطأ أثناء جلب تفاصيل الوظيفة"
    ], JSON_UNESCAPED_UNICODE);

}

