<?php
session_start();
require "auth.php";
require "db.php";
require "cart_storage.php";
require_role("customer");

$customerId = (int)$_SESSION["user_id"];
$profileStmt = mysqli_prepare($conn, "SELECT full_name, email, shipping_name, shipping_email, phone, country, province, district, ward, shipping_address FROM users WHERE id = ?");
if ($profileStmt === false) {
    http_response_code(500);
    exit("Không thể tải thông tin giao hàng. Hãy xác nhận đã chạy migration database mới nhất.");
}
mysqli_stmt_bind_param($profileStmt, "i", $customerId);
mysqli_stmt_execute($profileStmt);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($profileStmt));
if (!$profile) {
    http_response_code(404);
    exit("Không tìm thấy tài khoản khách hàng.");
}

$cart = normalize_cart($_SESSION["cart"] ?? []);
if ($cart === []) {
    header("Location: cart.php");
    exit;
}

$recipientName = $profile["shipping_name"] ?: $profile["full_name"];
$recipientEmail = $profile["shipping_email"] ?: $profile["email"];
$phone = $profile["phone"] ?? "";
$country = $profile["country"] ?: "Việt Nam";
$province = $profile["province"] ?? "";
$district = $profile["district"] ?? "";
$ward = $profile["ward"] ?? "";
$shippingAddress = $profile["shipping_address"] ?? "";
$orderNote = "";
$paymentMethod = "cod";
$error = "";

$bookIds = array_map("intval", array_keys($cart));
$ids = implode(",", $bookIds);
$booksResult = mysqli_query($conn, "SELECT id, title, price, stock_quantity, is_available, seller_id, approval_status FROM books WHERE id IN ($ids)");
if ($booksResult === false) {
    http_response_code(500);
    exit("Không thể tải giỏ hàng. Hãy xác nhận đã chạy migration database mới nhất.");
}
$booksById = [];
while ($book = mysqli_fetch_assoc($booksResult)) {
    $booksById[(int)$book["id"]] = $book;
}
$total = 0;
foreach ($cart as $bookId => $quantity) {
    if (isset($booksById[$bookId])) {
        $total += (int)$booksById[$bookId]["price"] * $quantity;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        http_response_code(403);
        exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
    }

    $postText = static function (string $key): string {
        $value = $_POST[$key] ?? "";
        return is_string($value) ? trim($value) : "";
    };
    $recipientName = $postText("recipient_name");
    $recipientEmail = $postText("recipient_email");
    $phone = $postText("phone");
    $country = $postText("country");
    $province = $postText("province");
    $district = $postText("district");
    $ward = $postText("ward");
    $shippingAddress = $postText("shipping_address");
    $orderNote = $postText("order_note");
    $paymentMethod = $postText("payment_method");

    if ($recipientName === "" || mb_strlen($recipientName, "UTF-8") > 150
        || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) || strlen($recipientEmail) > 190
        || !preg_match('/\A[0-9+()\-\s]{8,20}\z/', $phone)
        || $country === "" || mb_strlen($country, "UTF-8") > 100
        || $province === "" || mb_strlen($province, "UTF-8") > 100
        || $district === "" || mb_strlen($district, "UTF-8") > 100
        || $ward === "" || mb_strlen($ward, "UTF-8") > 100
        || $shippingAddress === "" || mb_strlen($shippingAddress, "UTF-8") > 255
        || mb_strlen($orderNote, "UTF-8") > 2000
        || !in_array($paymentMethod, ["cod", "bank_transfer"], true)) {
        $error = "Vui lòng kiểm tra lại thông tin người nhận, địa chỉ, số điện thoại và phương thức thanh toán.";
    } elseif (!mysqli_begin_transaction($conn)) {
        $error = "Không thể bắt đầu lưu đơn hàng. Vui lòng thử lại.";
    } else {
        $lockedResult = mysqli_query($conn, "SELECT id, price, stock_quantity, is_available, seller_id, approval_status FROM books WHERE id IN ($ids) FOR UPDATE");
        if ($lockedResult === false) {
            mysqli_rollback($conn);
            http_response_code(500);
            exit("Không thể kiểm tra tồn kho. Hãy xác nhận đã chạy migration database mới nhất.");
        }
        $lockedBooks = [];
        while ($book = mysqli_fetch_assoc($lockedResult)) {
            $lockedBooks[(int)$book["id"]] = $book;
        }

        $stockValid = count($lockedBooks) === count($cart);
        foreach ($cart as $bookId => $quantity) {
            $book = $lockedBooks[$bookId] ?? null;
            if (!$book || !(bool)$book["is_available"] || (int)$book["stock_quantity"] < $quantity
                || ($book["seller_id"] !== null && $book["approval_status"] !== "approved")) {
                $stockValid = false;
                break;
            }
        }

        if (!$stockValid) {
            mysqli_rollback($conn);
            $error = "Một số sách đã hết hàng hoặc tồn kho không đủ. Vui lòng cập nhật lại giỏ hàng.";
        } else {
            $total = 0;
            foreach ($cart as $bookId => $quantity) {
                $total += (int)$lockedBooks[$bookId]["price"] * $quantity;
            }
            $address = implode(", ", [$shippingAddress, $ward, $district, $province, $country]);
            $orderStmt = mysqli_prepare($conn, "INSERT INTO orders (order_code, customer_id, customer_name, recipient_email, phone, address, total_price, country, province, district, ward, order_note, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $orderSaved = false;
            if ($orderStmt !== false) {
                for ($attempt = 0; $attempt < 10 && !$orderSaved; $attempt++) {
                    $orderCode = (string)random_int(1000000000, 9999999999);
                    mysqli_stmt_bind_param($orderStmt, "sissssissssss", $orderCode, $customerId, $recipientName, $recipientEmail, $phone, $address, $total, $country, $province, $district, $ward, $orderNote, $paymentMethod);
                    $orderSaved = mysqli_stmt_execute($orderStmt);
                    if (!$orderSaved && mysqli_errno($conn) !== 1062) {
                        break;
                    }
                }
            }

            $saved = $orderSaved;
            if ($saved) {
                $orderId = (int)mysqli_insert_id($conn);
                $detailStmt = mysqli_prepare($conn, "INSERT INTO order_details (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stockStmt = mysqli_prepare($conn, "UPDATE books SET stock_quantity = ?, is_available = ? WHERE id = ? AND stock_quantity = ?");
                $saved = $detailStmt !== false && $stockStmt !== false;
                foreach ($cart as $bookId => $quantity) {
                    if (!$saved) {
                        break;
                    }

                    $price = (int)$lockedBooks[$bookId]["price"];
                    mysqli_stmt_bind_param($detailStmt, "iiii", $orderId, $bookId, $quantity, $price);
                    $saved = mysqli_stmt_execute($detailStmt);
                    if (!$saved) {
                        break;
                    }

                    $remainingStock = (int)$lockedBooks[$bookId]["stock_quantity"] - $quantity;
                    $availability = $remainingStock > 0 ? 1 : 0;
                    $oldStock = (int)$lockedBooks[$bookId]["stock_quantity"];
                    mysqli_stmt_bind_param($stockStmt, "iiii", $remainingStock, $availability, $bookId, $oldStock);
                    $saved = mysqli_stmt_execute($stockStmt) && mysqli_stmt_affected_rows($stockStmt) === 1;
                }
            }

            if ($saved) {
                $profileUpdate = mysqli_prepare($conn, "UPDATE users SET shipping_name = ?, shipping_email = ?, phone = ?, country = ?, province = ?, district = ?, ward = ?, shipping_address = ? WHERE id = ?");
                $saved = $profileUpdate !== false;
                if ($saved) {
                    mysqli_stmt_bind_param($profileUpdate, "ssssssssi", $recipientName, $recipientEmail, $phone, $country, $province, $district, $ward, $shippingAddress, $customerId);
                    $saved = mysqli_stmt_execute($profileUpdate);
                }
            }
            if ($saved) {
                $clearCart = mysqli_prepare($conn, "DELETE FROM user_cart_items WHERE user_id = ?");
                $saved = $clearCart !== false;
                if ($saved) {
                    mysqli_stmt_bind_param($clearCart, "i", $customerId);
                    $saved = mysqli_stmt_execute($clearCart);
                }
            }

            if ($saved && mysqli_commit($conn)) {
                unset($_SESSION["cart"]);
                header("Location: success.php?id=$orderId");
                exit;
            }

            mysqli_rollback($conn);
            $error = "Không thể lưu đơn hàng hoặc cập nhật tồn kho. Vui lòng thử lại.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác nhận đặt hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include "menu.php"; ?>
    <main class="container my-5" style="max-width: 760px;">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white"><h4 class="mb-0">Thông tin đặt hàng</h4></div>
            <div class="card-body">
                <p class="fs-5">Tổng tiền: <strong class="text-danger"><?= number_format($total, 0, ",", ".") ?> đ</strong></p>
                <?php if ($error !== ""): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <h5>Thông tin người nhận</h5>
                    <div class="mb-3"><label class="form-label">Họ và tên</label><input class="form-control" name="recipient_name" maxlength="150" value="<?= htmlspecialchars($recipientName) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="recipient_email" maxlength="190" value="<?= htmlspecialchars($recipientEmail) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Số điện thoại</label><input class="form-control" type="tel" name="phone" maxlength="20" value="<?= htmlspecialchars($phone) ?>" required></div>
                    <h5 class="mt-4">Địa chỉ giao hàng</h5>
                    <div class="mb-3"><label class="form-label">Quốc gia</label><input class="form-control" name="country" maxlength="100" value="<?= htmlspecialchars($country) ?>" required></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Tỉnh/Thành phố</label><input class="form-control" name="province" maxlength="100" value="<?= htmlspecialchars($province) ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Quận/Huyện</label><input class="form-control" name="district" maxlength="100" value="<?= htmlspecialchars($district) ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Phường/Xã</label><input class="form-control" name="ward" maxlength="100" value="<?= htmlspecialchars($ward) ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Địa chỉ nhận hàng</label><input class="form-control" name="shipping_address" maxlength="255" value="<?= htmlspecialchars($shippingAddress) ?>" placeholder="Số nhà, tên đường..." required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Ghi chú cho người bán</label><textarea class="form-control" name="order_note" rows="3" maxlength="2000"><?= htmlspecialchars($orderNote) ?></textarea></div>
                    <fieldset class="mb-4">
                        <legend class="form-label">Phương thức thanh toán</legend>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment-cod" value="cod" <?= $paymentMethod === "cod" ? "checked" : "" ?>>
                            <label class="form-check-label" for="payment-cod">Thanh toán khi nhận hàng (COD)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment-transfer" value="bank_transfer" <?= $paymentMethod === "bank_transfer" ? "checked" : "" ?>>
                            <label class="form-check-label" for="payment-transfer">Chuyển khoản thủ công</label>
                        </div>
                        <div class="form-text">Chuyển khoản chưa tích hợp cổng thanh toán; bộ phận bán hàng sẽ liên hệ hướng dẫn sau khi xác nhận đơn.</div>
                    </fieldset>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">Xác nhận mua hàng</button>
                        <a href="cart.php" class="btn btn-outline-secondary">Quay lại giỏ hàng</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
