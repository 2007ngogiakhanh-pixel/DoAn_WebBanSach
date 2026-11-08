<?php
// checkout.php - Đặt hàng và lưu CSDL
session_start();
require "auth.php";
require "db.php";

if (($_SESSION["user_role"] ?? null) !== "customer") {
    if (isset($_SESSION["user_id"])) {
        header("Location: seller/index.php");
    } else {
        header("Location: login.php?next=checkout");
    }
    exit;
}

$cart = $_SESSION["cart"] ?? [];
$validCart = [];
if (is_array($cart)) {
    foreach ($cart as $bookId => $quantity) {
        $bookId = (int)$bookId;
        $quantity = (int)$quantity;
        if ($bookId > 0 && $quantity > 0) {
            $validCart[$bookId] = $quantity;
        }
    }
}
$cart = $validCart;
if (count($cart) === 0) {
    header("Location: cart.php");
    exit;
}

$ids = implode(",", array_map("intval", array_keys($cart)));
$result = mysqli_query($conn, "SELECT id, price FROM books WHERE id IN ($ids) AND (seller_id IS NULL OR approval_status = 'approved')");

$giaBook = [];
$tong    = 0;
while ($book = mysqli_fetch_assoc($result)) {
    $giaBook[$book["id"]] = $book["price"];
    $tong += $book["price"] * $cart[$book["id"]];
}

$loi = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        http_response_code(403);
        exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
    }
    $hoten     = trim($_POST["hoten"] ?? "");
    $dienthoai = trim($_POST["dienthoai"] ?? "");
    $diachi    = trim($_POST["diachi"] ?? "");

    if ($hoten == "" || $dienthoai == "" || $diachi == "") {
        $loi = "Vui lòng nhập đầy đủ thông tin giao hàng!";
    } elseif (count($giaBook) !== count($cart)) {
        $loi = "Một số sách trong giỏ không còn tồn tại. Vui lòng kiểm tra lại giỏ hàng.";
    } else {
        mysqli_begin_transaction($conn);
        $customerId = (int)$_SESSION["user_id"];
        $stmt = mysqli_prepare($conn, "INSERT INTO orders (customer_id, customer_name, phone, address, total_price) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssi", $customerId, $hoten, $dienthoai, $diachi, $tong);
        if (mysqli_stmt_execute($stmt)) {
            $orderId = mysqli_insert_id($conn);
            $detailStmt = mysqli_prepare($conn, "INSERT INTO order_details (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)");
            $detailsSaved = true;
            foreach ($giaBook as $bId => $price) {
                $soluong = $cart[$bId];
                mysqli_stmt_bind_param($detailStmt, "iiii", $orderId, $bId, $soluong, $price);
                if (!mysqli_stmt_execute($detailStmt)) {
                    $detailsSaved = false;
                    break;
                }
            }
            if ($detailsSaved && mysqli_commit($conn)) {
                unset($_SESSION["cart"]);
                header("Location: success.php?id=$orderId");
                exit;
            }
        }

        mysqli_rollback($conn);
        $loi = "Không thể lưu đơn hàng. Vui lòng thử lại.";
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

    <div class="container my-5" style="max-width: 600px;">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">📋 Thông Tin Đặt Hàng</h4>
            </div>
            <div class="card-body">
                <p class="fs-5">Tổng tiền thanh toán: <strong class="text-danger"><?= number_format($tong, 0, ",", ".") ?> đ</strong></p>
                
                <?php if ($loi != ""): ?>
                    <div class="alert alert-danger"><?= $loi ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Họ và tên người nhận:</label>
                        <input type="text" name="hoten" class="form-control" required placeholder="Nguyễn Văn A">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Số điện thoại:</label>
                        <input type="text" name="dienthoai" class="form-control" required placeholder="0901234567">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Địa chỉ giao sách:</label>
                        <textarea name="diachi" class="form-control" rows="3" required placeholder="Số nhà, đường, phường/xã, quận/huyện..."></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">Xác nhận mua hàng</button>
                        <a href="cart.php" class="btn btn-outline-secondary">Quay lại giỏ hàng</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>