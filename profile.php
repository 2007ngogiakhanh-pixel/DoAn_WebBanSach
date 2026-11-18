<?php
session_start();
require "auth.php";
require "db.php";
require_once "shipping_helpers.php";
require_role(["customer", "seller"]);

$userId = (int)$_SESSION["user_id"];
$provinces = vietnam_provinces();
$stmt = mysqli_prepare($conn, "SELECT full_name, email, password_hash, phone, province, district, ward, shipping_address FROM users WHERE id = ?");
if (!$stmt) {
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
$notice = "";
$currentPassword = "";
$newPassword = "";
$confirmPassword = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        http_response_code(403);
        exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
    }

    $readPost = static function (string $key): string {
        $value = $_POST[$key] ?? "";
        return is_string($value) ? trim($value) : "";
    };
    $fullName = $readPost("full_name");
    $email = $readPost("email");
    $phoneInput = $readPost("phone");
    $phone = preg_replace('/[\s().-]+/', "", $phoneInput);
    $province = $readPost("province");
    $district = $readPost("district");
    $ward = $readPost("ward");
    $shippingAddress = $readPost("shipping_address");
    $currentPassword = $readPost("current_password");
    $newPassword = $readPost("new_password");
    $confirmPassword = $readPost("confirm_password");

    if (is_string($phone) && str_starts_with($phone, "+84")) {
        $phone = "0" . substr($phone, 3);
    } elseif (is_string($phone) && str_starts_with($phone, "84")) {
        $phone = "0" . substr($phone, 2);
    }

    $passwordChangeRequested = $currentPassword !== "" || $newPassword !== "" || $confirmPassword !== "";
    if ($fullName === "" || mb_strlen($fullName, "UTF-8") > 150
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190
        || !is_string($phone) || ($phone !== "" && !preg_match('/\A0[35789]\d{8}\z/', $phone))
        || ($province !== "" && !in_array($province, $provinces, true))
        || mb_strlen($district, "UTF-8") > 100 || mb_strlen($ward, "UTF-8") > 100
        || mb_strlen($shippingAddress, "UTF-8") > 255) {
        $error = "Vui lòng kiểm tra lại họ tên, email, số điện thoại và địa chỉ.";
    } elseif ($passwordChangeRequested
        && ($currentPassword === "" || !password_verify($currentPassword, $account["password_hash"]))) {
        $error = "Mật khẩu hiện tại không đúng.";
    } elseif ($passwordChangeRequested
        && (strlen($newPassword) < 8 || $newPassword !== $confirmPassword)) {
        $error = "Mật khẩu mới phải có ít nhất 8 ký tự và hai lần nhập phải khớp nhau.";
    } else {
        $emailChanged = $email !== $account["email"];
        if ($passwordChangeRequested) {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, shipping_name = ?, shipping_email = ?, phone = ?, country = 'Việt Nam', province = ?, district = ?, ward = ?, shipping_address = ?, password_hash = ? WHERE id = ?");
            if ($update) {
                mysqli_stmt_bind_param($update, "ssssssssssi", $fullName, $email, $fullName, $email, $phone, $province, $district, $ward, $shippingAddress, $passwordHash, $userId);
            }
        } else {
            $update = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, shipping_name = ?, shipping_email = ?, phone = ?, country = 'Việt Nam', province = ?, district = ?, ward = ?, shipping_address = ? WHERE id = ?");
            if ($update) {
                mysqli_stmt_bind_param($update, "sssssssssi", $fullName, $email, $fullName, $email, $phone, $province, $district, $ward, $shippingAddress, $userId);
            }
        }

        if (!$update) {
            http_response_code(500);
            exit("Không thể cập nhật thông tin tài khoản.");
        }
        if (!mysqli_stmt_execute($update)) {
            if (mysqli_errno($conn) === 1062) {
                $error = "Email này đã được tài khoản khác sử dụng.";
            } else {
                http_response_code(500);
                exit("Không thể lưu thông tin tài khoản.");
            }
        } else {
            $account["full_name"] = $fullName;
            $account["email"] = $email;
            $account["phone"] = $phone;
            $account["province"] = $province;
            $account["district"] = $district;
            $account["ward"] = $ward;
            $account["shipping_address"] = $shippingAddress;
            $_SESSION["user_name"] = $fullName;
            if ($passwordChangeRequested) {
                $account["password_hash"] = $passwordHash;
            }
            if ($emailChanged || $passwordChangeRequested) {
                session_regenerate_id(true);
            }
            $currentPassword = "";
            $newPassword = "";
            $confirmPassword = "";
            $notice = "Thông tin tài khoản đã được cập nhật.";
        }
    }

    if ($error !== "") {
        $account["full_name"] = $fullName;
        $account["email"] = $email;
        $account["phone"] = $phoneInput;
        $account["province"] = $province;
        $account["district"] = $district;
        $account["ward"] = $ward;
        $account["shipping_address"] = $shippingAddress;
    }
}
?>
<?php include __DIR__ . "/views/profile.html";
