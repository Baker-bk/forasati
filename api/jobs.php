
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";

try {

    $sql = "SELECT
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
            WHERE is_active = 1";

    $params = [];

    // البحث بالعنوان أو اسم الشركة
    if (!empty($_GET["search"])) {
        $sql .= " AND (title LIKE ? OR company LIKE ?)";
        $search = "%" . trim($_GET["search"]) . "%";
        $params[] = $search;
        $params[] = $search;
    }

    // التصفية حسب المدينة
    if (!empty($_GET["city"])) {
        $sql .= " AND city = ?";
        $params[] = trim($_GET["city"]);
    }

    // التصفية حسب التخصص
    if (!empty($_GET["category"])) {
        $sql .= " AND category = ?";
        $params[] = trim($_GET["category"]);
    }

    // التصفية حسب نوع العمل
    if (!empty($_GET["job_type"])) {
        $sql .= " AND job_type = ?";
        $params[] = trim($_GET["job_type"]);
    }

    $sql .= " ORDER BY created_at DESC";

    // عدد النتائج في الصفحة
    $limit = 20;

    $page = isset($_GET["page"])
        ? max(1, (int)$_GET["page"])
        : 1;

    $offset = ($page - 1) * $limit;

    $sql .= " LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $jobs = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "count" => count($jobs),
        "page" => $page,
        "jobs" => $jobs
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "حدث خطأ أثناء جلب الوظائف"
    ], JSON_UNESCAPED_UNICODE);

}

