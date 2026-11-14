<?php
session_start();
require "auth.php";
require "db.php";
require "cart_storage.php";
require_role(["customer", "seller"]);

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
$vietnamProvinces = [
    "An Giang", "Bắc Ninh", "Cà Mau", "Cao Bằng", "Đắk Lắk", "Điện Biên", "Đồng Nai", "Đồng Tháp",
    "Gia Lai", "Hà Tĩnh", "Hưng Yên", "Khánh Hòa", "Lai Châu", "Lâm Đồng", "Lạng Sơn", "Lào Cai",
    "Nghệ An", "Ninh Bình", "Phú Thọ", "Quảng Ngãi", "Quảng Ninh", "Quảng Trị", "Sơn La", "Tây Ninh",
    "Thái Nguyên", "Thanh Hóa", "Tuyên Quang", "Vĩnh Long", "Cần Thơ", "Đà Nẵng", "Hải Phòng", "Hà Nội",
    "Huế", "Thành phố Hồ Chí Minh",
];

$bookIds = array_map("intval", array_keys($cart));
$ids = implode(",", $bookIds);
$booksResult = mysqli_query($conn, "SELECT id, title, image, price, stock_quantity, is_available, seller_id, approval_status FROM books WHERE id IN ($ids)");
if ($booksResult === false) {
    http_response_code(500);
    exit("Không thể tải giỏ hàng. Hãy xác nhận đã chạy migration database mới nhất.");
}
$booksById = [];
while ($book = mysqli_fetch_assoc($booksResult)) {
    $booksById[(int)$book["id"]] = $book;
}
$total = 0;
$checkoutItems = [];
foreach ($cart as $bookId => $quantity) {
    if (isset($booksById[$bookId])) {
        $book = $booksById[$bookId];
        $lineTotal = (int)$book["price"] * $quantity;
        $total += $lineTotal;
        $checkoutItems[] = [
            "id" => (int)$bookId,
            "title" => $book["title"],
            "image" => $book["image"],
            "price" => (int)$book["price"],
            "quantity" => $quantity,
            "line_total" => $lineTotal,
        ];
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
    $country = "Việt Nam";
    $province = $postText("province");
    $district = $postText("district");
    $ward = $postText("ward");
    $shippingAddress = $postText("shipping_address");
    $orderNote = $postText("order_note");
    $paymentMethod = $postText("payment_method");

    $normalizedPhone = preg_replace('/[\s().-]+/', "", $phone);
    if (is_string($normalizedPhone) && str_starts_with($normalizedPhone, "+84")) {
        $normalizedPhone = "0" . substr($normalizedPhone, 3);
    } elseif (is_string($normalizedPhone) && str_starts_with($normalizedPhone, "84")) {
        $normalizedPhone = "0" . substr($normalizedPhone, 2);
    }

    if ($recipientName === "" || mb_strlen($recipientName, "UTF-8") > 150
        || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) || strlen($recipientEmail) > 190
        || !is_string($normalizedPhone) || !preg_match('/\A0[35789]\d{8}\z/', $normalizedPhone)
        || !in_array($province, $vietnamProvinces, true)
        || $district === "" || mb_strlen($district, "UTF-8") > 100
        || $ward === "" || mb_strlen($ward, "UTF-8") > 100
        || $shippingAddress === "" || mb_strlen($shippingAddress, "UTF-8") > 255
        || mb_strlen($orderNote, "UTF-8") > 2000
        || !in_array($paymentMethod, ["cod", "bank_transfer"], true)) {
        $error = "Vui lòng kiểm tra lại thông tin người nhận, địa chỉ, số điện thoại và phương thức thanh toán.";
    } elseif (!mysqli_begin_transaction($conn)) {
        $error = "Không thể bắt đầu lưu đơn hàng. Vui lòng thử lại.";
    } else {
        $phone = $normalizedPhone;
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
            foreach ($cart as $bookId => $quantity) {
                $book = $lockedBooks[$bookId] ?? null;
                if (!$book || !(bool)$book["is_available"]
                    || ($book["seller_id"] !== null && $book["approval_status"] !== "approved")
                    || (int)$book["stock_quantity"] === 0) {
                    unset($cart[$bookId]);
                } elseif ($quantity > (int)$book["stock_quantity"]) {
                    $cart[$bookId] = (int)$book["stock_quantity"];
                }
            }
            $_SESSION["cart"] = $cart;
            if (!sync_session_cart($conn)) {
                http_response_code(500);
                exit("Không thể cập nhật giỏ hàng theo tồn kho mới.");
            }
            $_SESSION["cart_error"] = "Giỏ hàng đã được tự động điều chỉnh theo tồn kho mới. Vui lòng kiểm tra trước khi đặt hàng.";
            header("Location: cart.php");
            exit;
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
<?php include __DIR__ . '/views/checkout.html';
