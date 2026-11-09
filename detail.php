<?php
// detail.php - Trang chi tiết sản phẩm
session_start();
require "db.php";
require "auth.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$result = mysqli_query($conn, "SELECT b.*, (SELECT GROUP_CONCAT(c2.name ORDER BY c2.name SEPARATOR ', ') FROM book_categories bc JOIN categories c2 ON c2.id = bc.category_id WHERE bc.book_id = b.id) AS category_names
                               FROM books b 
                               WHERE b.id = $id
                                 AND (b.seller_id IS NULL OR b.approval_status = 'approved')");

if (mysqli_num_rows($result) == 0) {
    header("Location: index.php");
    exit;
}

$book = mysqli_fetch_assoc($result);
$reviewError = "";
$reviewNotice = $_SESSION["review_notice"] ?? "";
unset($_SESSION["review_notice"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (($_SESSION["user_role"] ?? null) !== "customer") {
        $reviewError = "Vui lòng đăng nhập bằng tài khoản khách hàng để đánh giá.";
    } else {
        require_role("customer");
        if (!verify_csrf_token()) {
            http_response_code(403);
            exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
        }

        $rating = filter_var($_POST["rating"] ?? null, FILTER_VALIDATE_INT);
        $commentInput = $_POST["comment"] ?? "";
        $comment = is_string($commentInput) ? trim($commentInput) : "";
        if ($rating === false || $rating < 1 || $rating > 5 || $comment === "" || mb_strlen($comment, "UTF-8") > 2000) {
            $reviewError = "Vui lòng chọn số sao từ 1 đến 5 và nhập nhận xét tối đa 2.000 ký tự.";
        } else {
            $customerId = (int)$_SESSION["user_id"];
            $purchase = mysqli_prepare($conn, "SELECT 1 FROM orders o JOIN order_details od ON od.order_id = o.id WHERE o.customer_id = ? AND o.status = 'delivered' AND od.book_id = ? LIMIT 1");
            mysqli_stmt_bind_param($purchase, "ii", $customerId, $id);
            if (!mysqli_stmt_execute($purchase)) {
                http_response_code(500);
                exit("Không thể xác minh đơn hàng để gửi đánh giá.");
            }
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($purchase))) {
                $reviewError = "Bạn có thể đánh giá sách sau khi đơn hàng được giao.";
            } else {
                $reviewerName = $_SESSION["user_name"];
                $saveReview = mysqli_prepare($conn, "INSERT INTO book_reviews (book_id, customer_id, reviewer_name, rating, comment) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE reviewer_name = VALUES(reviewer_name), rating = VALUES(rating), comment = VALUES(comment), created_at = CURRENT_TIMESTAMP");
                mysqli_stmt_bind_param($saveReview, "iisis", $id, $customerId, $reviewerName, $rating, $comment);
                if (!mysqli_stmt_execute($saveReview)) {
                    http_response_code(500);
                    exit("Không thể lưu đánh giá. Vui lòng thử lại.");
                }
                $_SESSION["review_notice"] = "Đã lưu đánh giá của bạn.";
                header("Location: detail.php?id=" . $id . "#reviews");
                exit;
            }
        }
    }
}

$reviewSummary = mysqli_prepare($conn, "SELECT COUNT(*) AS review_count, AVG(rating) AS average_rating FROM book_reviews WHERE book_id = ?");
mysqli_stmt_bind_param($reviewSummary, "i", $id);
mysqli_stmt_execute($reviewSummary);
$reviewStats = mysqli_fetch_assoc(mysqli_stmt_get_result($reviewSummary));
$reviewCount = (int)$reviewStats["review_count"];
$averageRating = (float)($reviewStats["average_rating"] ?? 0);

$reviewList = mysqli_prepare($conn, "SELECT reviewer_name, rating, comment, created_at FROM book_reviews WHERE book_id = ? ORDER BY created_at DESC, id DESC");
mysqli_stmt_bind_param($reviewList, "i", $id);
mysqli_stmt_execute($reviewList);
$reviews = mysqli_stmt_get_result($reviewList);

$canReview = false;
$ownReview = null;
if (($_SESSION["user_role"] ?? null) === "customer" && isset($_SESSION["user_id"])) {
    $customerId = (int)$_SESSION["user_id"];
    $purchase = mysqli_prepare($conn, "SELECT 1 FROM orders o JOIN order_details od ON od.order_id = o.id WHERE o.customer_id = ? AND o.status = 'delivered' AND od.book_id = ? LIMIT 1");
    mysqli_stmt_bind_param($purchase, "ii", $customerId, $id);
    mysqli_stmt_execute($purchase);
    $canReview = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($purchase));

    $ownReviewStmt = mysqli_prepare($conn, "SELECT rating, comment FROM book_reviews WHERE book_id = ? AND customer_id = ?");
    mysqli_stmt_bind_param($ownReviewStmt, "ii", $id, $customerId);
    mysqli_stmt_execute($ownReviewStmt);
    $ownReview = mysqli_fetch_assoc(mysqli_stmt_get_result($ownReviewStmt)) ?: null;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($book['title']) ?> - Chi tiết sách</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include "menu.php"; ?>

    <div class="container my-5">
        <div class="row">
            <!-- 📌 VỊ TRÍ ẢNH BÌA SÁCH LỚN (TRANG CHI TIẾT) -->
            <div class="col-md-5 mb-4 text-center">
                <!-- Ghi chú: Hiển thị hình bìa sách khổ lớn. 
                     Khuyên dùng: Ảnh dạng dọc, nét rõ (kích thước khoảng 600x800px hoặc tỉ lệ 3:4) nằm trong thư mục uploads/ -->
                <img src="uploads/<?= htmlspecialchars($book['image']) ?>" 
                     class="img-fluid rounded shadow" 
                     alt="<?= htmlspecialchars($book['title']) ?>"
                     style="max-height: 400px;"
                     onerror="this.src=''"> <!-- 👈 Đã để trống: Ảnh dự phòng khi chưa tải được ảnh sách -->
            </div>

            <!-- Thông tin sách -->
            <div class="col-md-7">
                <h2><?= htmlspecialchars($book['title']) ?></h2>
                <p class="text-muted">Tác giả: <strong><?= htmlspecialchars($book['author']) ?></strong></p>
                <p class="text-muted">Thể loại: <span class="badge bg-secondary"><?= htmlspecialchars($book['category_names'] ?? "") ?></span></p>
                <h3 class="text-danger my-3"><?= number_format($book['price'], 0, ',', '.') ?> đ</h3>
                <p class="text-warning mb-3"><?= $reviewCount > 0 ? number_format($averageRating, 1) . " / 5 ★ · " . $reviewCount . " đánh giá" : "Chưa có đánh giá" ?></p>
                <?php if (empty($book["is_available"])): ?><p><span class="badge bg-secondary">Hết hàng</span></p><?php endif; ?>

                <p class="mt-4"><strong>Mô tả nội dung:</strong></p>
                <p class="text-justify"><?= nl2br(htmlspecialchars($book['description'])) ?></p>

                <hr class="my-4">

                <!-- Form chọn số lượng và mua -->
                <?php if (!empty($book["is_available"])): ?><form action="cart_add.php" method="post" class="row g-3 align-items-center">
                    <input type="hidden" name="id" value="<?= $book['id'] ?>">
                    <div class="col-auto">
                        <label for="qty" class="col-form-label fw-bold">Số lượng mua:</label>
                    </div>
                    <div class="col-auto">
                        <input type="number" id="qty" name="qty" class="form-control" value="1" min="1" style="width: 80px;">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary px-4">🛒 Thêm vào giỏ hàng</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <section class="mt-5" id="reviews">
            <h3>Đánh giá và nhận xét</h3>
            <?php if ($reviewNotice !== ""): ?><div class="alert alert-success"><?= htmlspecialchars($reviewNotice) ?></div><?php endif; ?>
            <?php if ($reviewError !== ""): ?><div class="alert alert-danger"><?= htmlspecialchars($reviewError) ?></div><?php endif; ?>

            <?php if (($_SESSION["user_role"] ?? null) === "customer" && $canReview): ?>
                <form method="post" class="card card-body mb-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <label class="form-label" for="rating">Đánh giá của bạn</label>
                    <select class="form-select mb-3" id="rating" name="rating" required>
                        <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                            <option value="<?= $stars ?>" <?= (int)($ownReview["rating"] ?? 5) === $stars ? "selected" : "" ?>><?= $stars ?> sao</option>
                        <?php endfor; ?>
                    </select>
                    <label class="form-label" for="comment">Nhận xét</label>
                    <textarea class="form-control mb-3" id="comment" name="comment" rows="3" maxlength="2000" required><?= htmlspecialchars($ownReview["comment"] ?? "") ?></textarea>
                    <button class="btn btn-primary align-self-start" type="submit"><?= $ownReview ? "Cập nhật đánh giá" : "Gửi đánh giá" ?></button>
                </form>
            <?php elseif (($_SESSION["user_role"] ?? null) === "customer"): ?>
                <p class="text-muted">Bạn có thể đánh giá sau khi đơn hàng của sách này được giao.</p>
            <?php else: ?>
                <p class="text-muted"><a href="login.php">Đăng nhập bằng tài khoản khách hàng</a> và mua sách để gửi đánh giá sau khi nhận hàng.</p>
            <?php endif; ?>

            <?php if ($reviewCount === 0): ?>
                <p class="text-muted">Chưa có nhận xét nào cho sách này.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php while ($review = mysqli_fetch_assoc($reviews)): ?>
                        <article class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <strong><?= htmlspecialchars($review["reviewer_name"]) ?></strong>
                                <small class="text-muted"><?= htmlspecialchars($review["created_at"]) ?></small>
                            </div>
                            <div class="text-warning" aria-label="<?= (int)$review["rating"] ?> trên 5 sao"><?= str_repeat("★", (int)$review["rating"]) . str_repeat("☆", 5 - (int)$review["rating"]) ?></div>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($review["comment"])) ?></p>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>