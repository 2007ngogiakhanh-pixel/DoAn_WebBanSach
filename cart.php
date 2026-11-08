<?php
// cart.php - Xem danh sách sách trong giỏ
session_start();
require "db.php";

$cart  = $_SESSION["cart"] ?? [];
$items = [];
$tong  = 0;

if (count($cart) > 0) {
    $ids = implode(",", array_keys($cart));
    $result = mysqli_query($conn, "SELECT * FROM books WHERE id IN ($ids)");

    while ($book = mysqli_fetch_assoc($result)) {
        $book["soluong"]   = $cart[$book["id"]];
        $book["thanhtien"] = $book["price"] * $book["soluong"];
        $tong += $book["thanhtien"];
        $items[] = $book;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng của bạn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include "menu.php"; ?>

    <div class="container my-5">
        <h3 class="mb-4">🛒 Giỏ hàng của bạn</h3>

        <?php if (count($items) == 0): ?>
            <div class="alert alert-info py-4 text-center">
                <p class="mb-2">Giỏ hàng của bạn đang trống!</p>
                <a href="index.php" class="btn btn-primary btn-sm">Quay lại chọn sách</a>
            </div>
        <?php else: ?>
            <form method="post" action="cart_update.php">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Ảnh bìa</th>
                                <th>Tên sách</th>
                                <th>Đơn giá</th>
                                <th style="width: 120px;">Số lượng</th>
                                <th>Thành tiền</th>
                                <th style="width: 80px;">Xóa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $sp): ?>
                            <tr>
                                <td class="text-center" style="width: 90px;">
                                    <!-- 📌 VỊ TRÍ HÌNH ẢNH NHỎ TRONG GIỎ HÀNG -->
                                    <img src="uploads/<?= htmlspecialchars($sp["image"]) ?>" 
                                         style="height: 60px; object-fit: cover;" 
                                         class="rounded"
                                         onerror="this.src=''"> <!-- 👈 Ảnh dự phòng nhỏ -->
                                </td>
                                <td><?= htmlspecialchars($sp["title"]) ?></td>
                                <td><?= number_format($sp["price"], 0, ",", ".") ?> đ</td>
                                <td>
                                    <input type="number" name="qty[<?= $sp["id"] ?>]" 
                                           value="<?= $sp["soluong"] ?>" min="0" 
                                           class="form-control form-control-sm text-center">
                                </td>
                                <td class="fw-bold text-danger"><?= number_format($sp["thanhtien"], 0, ",", ".") ?> đ</td>
                                <td>
                                    <button type="submit" formaction="cart_remove.php" 
                                            name="id" value="<?= $sp["id"] ?>" 
                                            class="btn btn-outline-danger btn-sm">Xóa</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="4" class="text-end fw-bold fs-5">Tổng tiền thanh toán:</td>
                                <td colspan="2" class="fw-bold fs-5 text-danger"><?= number_format($tong, 0, ",", ".") ?> đ</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between mt-3">
                    <div>
                        <button type="submit" class="btn btn-secondary">Cập nhật số lượng</button>
                        <button type="submit" formaction="cart_clear.php" 
                                onclick="return confirm('Xóa toàn bộ giỏ hàng?')" 
                                class="btn btn-outline-danger">Xóa hết</button>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-outline-primary">Tiếp tục chọn sách</a>
                        <a href="checkout.php" class="btn btn-success fw-bold px-4">Đặt hàng ngay ➔</a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>