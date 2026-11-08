<?php
// success.php - Thông báo mua hàng thành công
session_start();
$id = (int)($_GET["id"] ?? 0);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt hàng thành công</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include "menu.php"; ?>

    <div class="container my-5 text-center" style="max-width: 500px;">
        <div class="card shadow-sm p-4">
            <h1 class="display-3 text-success">🎉</h1>
            <h3 class="text-success">Đặt Hàng Thành Công!</h3>
            <p class="mt-3">Mã đơn hàng của bạn là: <strong class="fs-4 text-primary">#<?= $id ?></strong></p>
            <p class="text-muted">Bộ phận nhà sách sẽ sớm liên hệ xác nhận và giao hàng cho bạn.</p>
            <div class="mt-4">
                <a href="index.php" class="btn btn-primary">Tiếp tục mua sách</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>