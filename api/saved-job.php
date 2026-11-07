
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";

function respond($success, $message, $data = [], $status = 200) {
    http_response_code($status);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// التأكد من تسجيل الدخول
if (!isset($_SESSION["user_id"])) {
    respond(false, "يجب تسجيل الدخول أولاً", [], 401);
}

$userId = $_SESSION["user_id"];

try {

    // عرض الوظائف المحفوظة
    if ($_SERVER["REQUEST_METHOD"] === "GET") {

        $stmt = $pdo->prepare("
            SELECT
                j.id,
                j.title,
                j.company,
                j.city,
                j.category,
                j.salary,
                j.job_type,
                j.created_at,
                s.created_at AS saved_at
            FROM saved_jobs s
            INNER JOIN jobs j ON j.id = s.job_id
            WHERE s.user_id = ?
              AND j.is_active = 1
            ORDER BY s.created_at DESC
        ");

        $stmt->execute([$userId]);

        respond(
            true,
            "تم جلب الوظائف المحفوظة",
            $stmt->fetchAll()
        );
    }

    // إضافة أو حذف وظيفة
    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        $action = $data["action"] ?? "";
        $jobId = filter_var(
            $data["job_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$jobId || $jobId < 1) {
            respond(false, "رقم الوظيفة غير صحيح", [], 400);
        }

        if (!in_array($action, ["save", "remove"], true)) {
            respond(false, "العملية غير صحيحة", [], 400);
        }

        if ($action === "save") {

            // التأكد من وجود الوظيفة
            $check = $pdo->prepare("
                SELECT id
                FROM jobs
                WHERE id = ? AND is_active = 1
                LIMIT 1
            ");

            $check->execute([$jobId]);

            if (!$check->fetch()) {
                respond(false, "الوظيفة غير موجودة أو غير متاحة", [], 404);
            }

            // الحفظ دون تكرار
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO saved_jobs (user_id, job_id)
                VALUES (?, ?)
            ");

            $stmt->execute([$userId, $jobId]);

            respond(true, "تم حفظ الوظيفة في المفضلة");
        }

        if ($action === "remove") {

            $stmt = $pdo->prepare("
                DELETE FROM saved_jobs
                WHERE user_id = ? AND job_id = ?
            ");

            $stmt->execute([$userId, $jobId]);

            respond(true, "تم حذف الوظيفة من المفضلة");
        }
    }

    respond(false, "طريقة الطلب غير مدعومة", [], 405);

} catch (PDOException $e) {

    respond(false, "حدث خطأ أثناء معالجة الوظائف المحفوظة", [], 500);

}

