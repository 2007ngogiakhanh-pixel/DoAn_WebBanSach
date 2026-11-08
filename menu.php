<?php
// menu.php - Thanh menu dùng chung responsive
$soLuongGio = array_sum($_SESSION["cart"] ?? []);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <!-- 📌 VỊ TRÍ LOGO WEBSITE (NẾU MUỐN THÊM LOGO DẠNG HÌNH ẢNH) -->
        <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
            <!-- Ghi chú: Nếu muốn dùng logo hình ảnh thay cho chữ icon, bỏ comment dòng img dưới đây.
                 Khuyên dùng: Ảnh logo định dạng PNG trong suốt (kích thước nhỏ khoảng 40x40px hoặc 120x40px) -->
            <!-- <img src="" alt="Logo" style="height: 30px;" class="me-2"> -->
            📚 BOOKSTORE
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" href="index.php">Trang chủ</a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <?php if (isset($_SESSION["user_id"])): ?>
                    <span class="navbar-text text-white me-3">
                        <?= htmlspecialchars($_SESSION["user_name"]) ?>
                        (<?= ($_SESSION["user_role"] ?? "") === "seller" ? "Người bán" : "Khách hàng" ?>)
                    </span>
                    <?php if (($_SESSION["user_role"] ?? "") === "seller"): ?>
                        <a href="seller/index.php" class="btn btn-outline-info me-2">Khu vực người bán</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-outline-light me-2">Đăng xuất</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light me-2">Đăng nhập</a>
                    <a href="register.php" class="btn btn-warning me-2">Đăng ký</a>
                <?php endif; ?>
                <a href="cart.php" class="btn btn-outline-light position-relative">
                    🛒 Giỏ hàng
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $soLuongGio ?>
                    </span>
                </a>
            </div>
        </div>
    </div>
</nav>