<?php
// menu.php - Thanh menu dùng chung responsive
$soLuongGio = array_sum($_SESSION["cart"] ?? []);
?>
<div class="store-promo-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <span>Chào mừng bạn đến với nhà sách trực tuyến</span>
        <span class="d-none d-sm-inline">Mở sách hay · Khám phá điều mới</span>
    </div>
</div>
<header class="store-header">
    <div class="container">
        <div class="store-header-main">
            <a class="store-brand" href="index.php" aria-label="Bookstore - Trang chủ">
                <span class="store-brand-icon" aria-hidden="true">B</span>
                <span class="store-brand-name">BOOK<span>STORE</span><small>GÓC NHỎ CỦA TRI THỨC</small></span>
            </a>
            <form class="store-search" method="get" action="index.php" role="search">
                <label class="visually-hidden" for="store-search-input">Tìm sách, tác giả</label>
                <input id="store-search-input" type="search" name="q" placeholder="Tìm sách, tác giả..." autocomplete="off">
                <button type="submit" aria-label="Tìm kiếm">Tìm kiếm</button>
            </form>
            <div class="store-header-actions">
                <?php if (isset($_SESSION["admin"])): ?>
                    <a href="admin/index.php" class="store-account-link"><span aria-hidden="true">⚙</span><span>Quản trị</span></a>
                    <a href="admin/logout.php" class="store-account-link"><span aria-hidden="true">↪</span><span>Đăng xuất</span></a>
                <?php elseif (isset($_SESSION["user_id"])): ?>
                    <div class="store-user-greeting">
                        <span class="store-action-icon" aria-hidden="true">♙</span>
                        <span><?= htmlspecialchars($_SESSION["user_name"]) ?><small><?= ($_SESSION["user_role"] ?? "") === "seller" ? "Người bán" : "Khách hàng" ?></small></span>
                    </div>
                    <?php if (($_SESSION["user_role"] ?? "") === "seller"): ?>
                        <a href="seller/index.php" class="store-account-link"><span aria-hidden="true">▤</span><span>Kênh bán hàng</span></a>
                    <?php endif; ?>
                    <?php if (in_array($_SESSION["user_role"] ?? "", ["customer", "seller"], true)): ?>
                        <a href="my_orders.php" class="store-account-link"><span aria-hidden="true">▣</span><span>Đơn hàng</span></a>
                    <?php endif; ?>
                    <a href="profile.php" class="store-account-link"><span aria-hidden="true">♙</span><span>Tài khoản</span></a>
                    <a href="logout.php" class="store-account-link"><span aria-hidden="true">↪</span><span>Đăng xuất</span></a>
                <?php else: ?>
                    <a href="login.php" class="store-account-link"><span aria-hidden="true">♙</span><span>Tài khoản<small>Đăng nhập</small></span></a>
                    <a href="register.php" class="store-account-link"><span aria-hidden="true">＋</span><span>Đăng ký</span></a>
                <?php endif; ?>
                <a href="cart.php" class="store-cart-link">
                    <span class="store-action-icon" aria-hidden="true">🛒</span>
                    <span class="store-cart-label">Giỏ hàng</span>
                    <span class="store-cart-count"><?= $soLuongGio ?></span>
                </a>
            </div>
        </div>
        <nav class="store-navigation" aria-label="Điều hướng chính">
            <a href="index.php" class="store-nav-home"><span aria-hidden="true">☰</span> Danh mục sách</a>
            <a href="index.php">Trang chủ</a>
            <?php if (isset($_SESSION["user_id"]) && in_array($_SESSION["user_role"] ?? "", ["customer", "seller"], true)): ?>
                <a href="my_orders.php">Đơn hàng của tôi</a>
            <?php endif; ?>
            <span class="store-navigation-note">Sách hay cho mọi hành trình</span>
        </nav>
    </div>
</header>