
<?php
session_start();
require_once __DIR__ . "/../config/db.php";

$error = "";
$message = "";

if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// تسجيل الدخول
if (isset($_POST["login"])) {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin["password_hash"])) {
        session_regenerate_id(true);
        $_SESSION["admin_id"] = $admin["id"];
        $_SESSION["admin_username"] = $admin["username"];
        header("Location: admin.php");
        exit;
    } else {
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
    }
}

// حماية لوحة التحكم
$loggedIn = isset($_SESSION["admin_id"]);

// إضافة وظيفة
if ($loggedIn && isset($_POST["add_job"])) {
    $stmt = $pdo->prepare("
        INSERT INTO jobs
        (title, company, city, category, description,
         requirements, salary, job_type, deadline)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        trim($_POST["title"]),
        trim($_POST["company"]),
        trim($_POST["city"]),
        trim($_POST["category"]),
        trim($_POST["description"]),
        trim($_POST["requirements"]),
        trim($_POST["salary"]),
        trim($_POST["job_type"]),
        $_POST["deadline"] ?: null
    ]);

    $message = "تمت إضافة الوظيفة بنجاح";
}

// حذف وظيفة
if ($loggedIn && isset($_GET["delete"])) {
    $id = (int)$_GET["delete"];
    $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: admin.php");
    exit;
}

// تحديث حالة الطلب
if ($loggedIn && isset($_POST["update_application"])) {
    $stmt = $pdo->prepare("
        UPDATE applications SET status = ? WHERE id = ?
    ");
    $stmt->execute([
        $_POST["status"],
        (int)$_POST["application_id"]
    ]);
    $message = "تم تحديث حالة الطلب";
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>إدارة فرصتي</title>

<style>
body {
    font-family: Tahoma, Arial;
    background: #f4f6f9;
    margin: 0;
    color: #222;
}
header {
    background: #123c69;
    color: white;
    padding: 18px;
}
.container {
    max-width: 1100px;
    margin: 25px auto;
    padding: 15px;
}
.card {
    background: white;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 10px;
}
input, textarea, select {
    width: 100%;
    padding: 11px;
    margin: 7px 0 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-sizing: border-box;
}
button, .btn {
    background: #123c69;
    color: white;
    padding: 10px 16px;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
}
.delete {
    background: #c0392b;
}
table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}
th, td {
    padding: 12px;
    border: 1px solid #ddd;
    text-align: right;
}
th {
    background: #eaf0f7;
}
.error { color: red; }
.success { color: green; }
nav a {
    color: white;
    margin-left: 15px;
}
</style>
</head>

<body>

<header>
<h2>فرصتي - لوحة الإدارة</h2>
<?php if ($loggedIn): ?>
مرحباً <?= e($_SESSION["admin_username"]) ?>
<nav>
<a href="admin.php">الرئيسية</a>
<a href="admin.php?logout=1">تسجيل الخروج</a>
</nav>
<?php endif; ?>
</header>

<div class="container">

<?php if (!$loggedIn): ?>

<div class="card">
<h2>تسجيل دخول المدير</h2>
<p class="error"><?= e($error) ?></p>

<form method="POST">
<label>اسم المستخدم</label>
<input type="text" name="username" required>

<label>كلمة المرور</label>
<input type="password" name="password" required>

<button name="login">دخول</button>
</form>
</div>

<?php else: ?>

<p class="success"><?= e($message) ?></p>

<?php
$usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$jobsCount = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$appsCount = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
?>

<div class="card">
<h3>إحصائيات الموقع</h3>
<p>عدد المستخدمين: <?= $usersCount ?></p>
<p>عدد الوظائف: <?= $jobsCount ?></p>
<p>عدد طلبات التقديم: <?= $appsCount ?></p>
</div>

<div class="card">
<h3>إضافة وظيفة جديدة</h3>

<form method="POST">
<input name="title" placeholder="المسمى الوظيفي" required>
<input name="company" placeholder="اسم الشركة" required>
<input name="city" placeholder="المدينة">
<input name="category" placeholder="التصنيف">

<textarea name="description" placeholder="وصف الوظيفة" required></textarea>
<textarea name="requirements" placeholder="المتطلبات"></textarea>

<input name="salary" placeholder="الراتب">
<input name="job_type" placeholder="نوع العمل">

<label>آخر موعد للتقديم</label>
<input type="date" name="deadline">

<button name="add_job">إضافة الوظيفة</button>
</form>
</div>

<div class="card">
<h3>إدارة الوظائف</h3>

<table>
<tr>
<th>الوظيفة</th>
<th>الشركة</th>
<th>المدينة</th>
<th>حذف</th>
</tr>

<?php
$jobs = $pdo->query("SELECT * FROM jobs ORDER BY id DESC")->fetchAll();
foreach ($jobs as $job):
?>
<tr>
<td><?= e($job["title"]) ?></td>
<td><?= e($job["company"]) ?></td>
<td><?= e($job["city"]) ?></td>
<td>
<a class="btn delete"
href="admin.php?delete=<?= (int)$job["id"] ?>"
onclick="return confirm('هل تريد حذف الوظيفة؟')">
حذف
</a>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="card">
<h3>طلبات التقديم</h3>

<table>
<tr>
<th>المتقدم</th>
<th>البريد</th>
<th>الوظيفة</th>
<th>الحالة</th>
<th>تحديث</th>
</tr>

<?php
$applications = $pdo->query("
SELECT applications.*, jobs.title
FROM applications
LEFT JOIN jobs ON jobs.id = applications.job_id
ORDER BY applications.id DESC
")->fetchAll();

foreach ($applications as $app):
?>
<tr>
<td><?= e($app["applicant_name"]) ?></td>
<td><?= e($app["email"]) ?></td>
<td><?= e($app["title"]) ?></td>
<td><?= e($app["status"]) ?></td>
<td>
<form method="POST">
<input type="hidden" name="application_id"
value="<?= (int)$app["id"] ?>">

<select name="status">
<option value="pending">قيد المراجعة</option>
<option value="reviewed">تمت المراجعة</option>
<option value="accepted">مقبول</option>
<option value="rejected">مرفوض</option>
</select>

<button name="update_application">حفظ</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="card">
<h3>المستخدمون</h3>

<table>
<tr>
<th>الاسم</th>
<th>البريد الإلكتروني</th>
<th>الهاتف</th>
</tr>

<?php
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
foreach ($users as $user):
?>
<tr>
<td><?= e($user["name"]) ?></td>
<td><?= e($user["email"]) ?></td>
<td><?= e($user["phone"]) ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<?php endif; ?>

</div>
</body>
</html>

