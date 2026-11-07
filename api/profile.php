
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

    // عرض بيانات المستخدم
    if ($_SERVER["REQUEST_METHOD"] === "GET") {

        $stmt = $pdo->prepare("
            SELECT id, name, email, phone, created_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            respond(false, "المستخدم غير موجود", [], 404);
        }

        respond(true, "تم جلب بيانات الحساب", $user);
    }

    // تعديل بيانات المستخدم
    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!is_array($data)) {
            respond(false, "بيانات الطلب غير صحيحة", [], 400);
        }

        $name = trim($data["name"] ?? "");
        $phone = trim($data["phone"] ?? "");

        if ($name === "") {
            respond(false, "الاسم مطلوب", [], 400);
        }

        if (strlen($name) > 150 || strlen($phone) > 30) {
            respond(false, "الاسم أو رقم الهاتف طويل جداً", [], 400);
        }

        $currentPassword = $data["current_password"] ?? "";
        $newPassword = $data["new_password"] ?? "";

        // جلب كلمة المرور الحالية
        $stmt = $pdo->prepare("
            SELECT password_hash
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            respond(false, "المستخدم غير موجود", [], 404);
        }

        // تحديث كلمة المرور عند طلب تغييرها
        if ($newPassword !== "") {

            if ($currentPassword === "") {
                respond(false, "أدخل كلمة المرور الحالية", [], 400);
            }

            if (!password_verify($currentPassword, $user["password_hash"])) {
                respond(false, "كلمة المرور الحالية غير صحيحة", [], 400);
            }

            if (strlen($newPassword) < 8) {
                respond(false, "كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل", [], 400);
            }

            $passwordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $update = $pdo->prepare("
                UPDATE users
                SET name = ?, phone = ?, password_hash = ?
                WHERE id = ?
            ");

            $update->execute([
                $name,
                $phone,
                $passwordHash,
                $userId
            ]);

        } else {

            $update = $pdo->prepare("
                UPDATE users
                SET name = ?, phone = ?
                WHERE id = ?
            ");

            $update->execute([
                $name,
                $phone,
                $userId
            ]);
        }

        // تحديث الاسم في الجلسة
        $_SESSION["user_name"] = $name;

        respond(true, "تم تحديث بيانات الحساب بنجاح");
    }

    respond(false, "طريقة الطلب غير مدعومة", [], 405);

} catch (PDOException $e) {

    respond(false, "حدث خطأ أثناء معالجة بيانات الحساب", [], 500);

}

