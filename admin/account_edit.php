<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: login.php");
    exit;
}
require "../auth.php";
require "../db.php";

$userId = filter_var($_POST["id"] ?? $_GET["id"] ?? null, FILTER_VALIDATE_INT);
if ($userId === false || $userId <= 0) {
    http_response_code(400);
    exit("Tài khoản không hợp lệ.");
}

$stmt = mysqli_prepare($conn, "SELECT id, full_name, email, gender, phone, shipping_name, shipping_email, country, province, district, ward, shipping_address, bank_name, bank_account_name, bank_account_number FROM users WHERE id = ?");
if ($stmt === false) {
    http_response_code(500);
    exit("Không thể tải thông tin tài khoản.");
}
mysqli_stmt_bind_param($stmt, "i", $userId);
if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    exit("Không thể tải thông tin tài khoản.");
}
$account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$account) {
    http_response_code(404);
    exit("Không tìm thấy tài khoản.");
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        http_response_code(403);
        exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
    }

    $fields = [
        "full_name" => 150,
        "email" => 190,
        "gender" => 32,
        "phone" => 20,
        "shipping_name" => 150,
        "shipping_email" => 190,
        "country" => 100,
        "province" => 100,
        "district" => 100,
        "ward" => 100,
        "shipping_address" => 255,
        "bank_name" => 100,
        "bank_account_name" => 150,
        "bank_account_number" => 50,
    ];
    foreach ($fields as $field => $maxLength) {
        $input = $_POST[$field] ?? "";
        $account[$field] = is_string($input) ? trim($input) : "";
        if (mb_strlen($account[$field], "UTF-8") > $maxLength) {
            $error = "Một hoặc nhiều thông tin vượt quá độ dài cho phép.";
        }
    }

    $genderOptions = ["", "male", "female", "other", "prefer_not_to_say"];
    if ($account["full_name"] === "" || !filter_var($account["email"], FILTER_VALIDATE_EMAIL)
        || !in_array($account["gender"], $genderOptions, true)
        || ($account["shipping_email"] !== "" && !filter_var($account["shipping_email"], FILTER_VALIDATE_EMAIL))) {
        $error = "Vui lòng kiểm tra họ tên, email và giới tính.";
    }

    $phone = preg_replace('/[\s().-]+/', "", $account["phone"]);
    if (is_string($phone) && str_starts_with($phone, "+84")) {
        $phone = "0" . substr($phone, 3);
    } elseif (is_string($phone) && str_starts_with($phone, "84")) {
        $phone = "0" . substr($phone, 2);
    }
    if ($account["phone"] !== "" && (!is_string($phone) || !preg_match('/\A0[35789]\d{8}\z/', $phone))) {
        $error = "Số điện thoại phải là số di động Việt Nam hợp lệ.";
    } elseif (is_string($phone)) {
        $account["phone"] = $phone;
    }

    $bankFields = ["bank_name", "bank_account_name", "bank_account_number"];
    $filledBankFields = count(array_filter($bankFields, static fn(string $field): bool => $account[$field] !== ""));
    if ($filledBankFields !== 0 && $filledBankFields !== count($bankFields)) {
        $error = "Vui lòng nhập đầy đủ tên ngân hàng, tên chủ tài khoản và số tài khoản.";
    }

    if ($error === "") {
        foreach (["gender", "phone", "shipping_name", "shipping_email", "country", "province", "district", "ward", "shipping_address", "bank_name", "bank_account_name", "bank_account_number"] as $field) {
            if ($account[$field] === "") {
                $account[$field] = null;
            }
        }
        $update = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, gender = ?, phone = ?, shipping_name = ?, shipping_email = ?, country = ?, province = ?, district = ?, ward = ?, shipping_address = ?, bank_name = ?, bank_account_name = ?, bank_account_number = ? WHERE id = ?");
        if ($update === false) {
            http_response_code(500);
            exit("Không thể cập nhật tài khoản.");
        }
        mysqli_stmt_bind_param(
            $update,
            "ssssssssssssssi",
            $account["full_name"],
            $account["email"],
            $account["gender"],
            $account["phone"],
            $account["shipping_name"],
            $account["shipping_email"],
            $account["country"],
            $account["province"],
            $account["district"],
            $account["ward"],
            $account["shipping_address"],
            $account["bank_name"],
            $account["bank_account_name"],
            $account["bank_account_number"],
            $userId
        );
        if (mysqli_stmt_execute($update)) {
            header("Location: index.php#accounts");
            exit;
        }
        $error = mysqli_errno($conn) === 1062
            ? "Email này đã được tài khoản khác sử dụng."
            : "Không thể cập nhật tài khoản. Vui lòng thử lại.";
    }
}
?>
<?php include __DIR__ . '/views/account_edit.html';
