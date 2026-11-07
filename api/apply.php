
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";

function respond($success, $message, $status = 200) {
    http_response_code($status);
    echo json_encode([
        "success" => $success,
        "message" => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Method not allowed", 405);
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION["user_id"])) {
    respond(false, "يجب تسجيل الدخول أولاً", 401);
}

// قراءة البيانات
$jobId = filter_var($_POST["job_id"] ?? null, FILTER_VALIDATE_INT);
$phone = trim($_POST["phone"] ?? "");
$coverLetter = trim($_POST["cover_letter"] ?? "");

if (!$jobId || $jobId < 1) {
    respond(false, "رقم الوظيفة غير صحيح", 400);
}

if ($phone === "") {
    respond(false, "رقم الهاتف مطلوب", 400);
}

$uploadedFile = null;

try {

    // جلب بيانات المستخدم
    $userStmt = $pdo->prepare("
        SELECT id, name, email
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $userStmt->execute([$_SESSION["user_id"]]);
    $user = $userStmt->fetch();

    if (!$user) {
        respond(false, "المستخدم غير موجود", 404);
    }

    // التأكد من أن الوظيفة موجودة ومتاحة
    $jobStmt = $pdo->prepare("
        SELECT id
        FROM jobs
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");

    $jobStmt->execute([$jobId]);

    if (!$jobStmt->fetch()) {
        respond(false, "الوظيفة غير موجودة أو غير متاحة", 404);
    }

    // منع التقديم المكرر
    $checkStmt = $pdo->prepare("
        SELECT id
        FROM applications
        WHERE job_id = ? AND user_id = ?
        LIMIT 1
    ");

    $checkStmt->execute([
        $jobId,
        $user["id"]
    ]);

    if ($checkStmt->fetch()) {
        respond(false, "سبق لك التقديم على هذه الوظيفة", 409);
    }

    // معالجة ملف السيرة الذاتية إذا تم إرفاقه
    $cvUrl = null;

    if (isset($_FILES["cv"]) && $_FILES["cv"]["error"] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES["cv"]["error"] !== UPLOAD_ERR_OK) {
            respond(false, "حدث خطأ أثناء رفع السيرة الذاتية", 400);
        }

        if ($_FILES["cv"]["size"] > 5 * 1024 * 1024) {
            respond(false, "حجم الملف يجب ألا يتجاوز 5 ميجابايت", 400);
        }

        $extension = strtolower(
            pathinfo($_FILES["cv"]["name"], PATHINFO_EXTENSION)
        );

        $allowedExtensions = ["pdf", "doc", "docx"];

        if (!in_array($extension, $allowedExtensions, true)) {
            respond(false, "الملفات المسموحة PDF و DOC و DOCX فقط", 400);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES["cv"]["tmp_name"]);

        $allowedMimes = [
            "pdf" => ["application/pdf"],
            "doc" => ["application/msword", "application/x-ole-storage"],
            "docx" => [
                "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                "application/zip"
            ]
        ];

        if (!in_array($mime, $allowedMimes[$extension], true)) {
            respond(false, "نوع الملف غير صالح", 400);
        }

        $uploadDir = __DIR__ . "/../uploads/cvs/";

        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            respond(false, "مجلد رفع الملفات غير جاهز أو لا يملك صلاحية الكتابة", 500);
        }

        $fileName = bin2hex(random_bytes(16)) . "." . $extension;

        $destination = $uploadDir . $fileName;

        if (!move_uploaded_file($_FILES["cv"]["tmp_name"], $destination)) {
            respond(false, "تعذر حفظ ملف السيرة الذاتية", 500);
        }

        $uploadedFile = $destination;
        $cvUrl = "uploads/cvs/" . $fileName;
    }

    // حفظ طلب التقديم
    $insertStmt = $pdo->prepare("
        INSERT INTO applications
        (
            job_id,
            user_id,
            applicant_name,
            email,
            phone,
            cv_url,
            cover_letter
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $insertStmt->execute([
        $jobId,
        $user["id"],
        $user["name"],
        $user["email"],
        $phone,
        $cvUrl,
        $coverLetter
    ]);

    respond(true, "تم إرسال طلب التقديم بنجاح");

} catch (PDOException $e) {

    // حذف الملف إذا فشل حفظ الطلب
    if ($uploadedFile && file_exists($uploadedFile)) {
        unlink($uploadedFile);
    }

    respond(false, "حدث خطأ أثناء إرسال طلب التقديم", 500);
}

