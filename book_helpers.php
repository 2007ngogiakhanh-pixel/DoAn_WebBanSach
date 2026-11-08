<?php
function save_book_image(array $file, ?string &$error): ?string
{
    $error = "";
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file["size"] ?? 0) > 5 * 1024 * 1024) {
        $error = "Ảnh tải lên không hợp lệ hoặc vượt quá 5 MB.";
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file["tmp_name"]);
    $extensions = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
    if (!isset($extensions[$mime])) {
        $error = "Ảnh phải có định dạng JPG, PNG hoặc WebP.";
        return null;
    }

    $imageName = bin2hex(random_bytes(16)) . "." . $extensions[$mime];
    $pendingDirectory = __DIR__ . "/pending_uploads";
    if (!is_dir($pendingDirectory) && !mkdir($pendingDirectory, 0755, true) && !is_dir($pendingDirectory)) {
        $error = "Không thể tạo thư mục ảnh chờ duyệt.";
        return null;
    }

    if (!move_uploaded_file($file["tmp_name"], $pendingDirectory . "/" . $imageName)) {
        $error = "Không thể lưu ảnh vào khu vực chờ duyệt.";
        return null;
    }

    return $imageName;
}

function delete_pending_book_image(?string $imageName): void
{
    if ($imageName !== null && preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $imageName)) {
        $path = __DIR__ . "/pending_uploads/" . $imageName;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function promote_pending_book_image(string $imageName): bool
{
    if (!preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $imageName)) {
        return false;
    }

    $source = __DIR__ . "/pending_uploads/" . $imageName;
    $destination = __DIR__ . "/uploads/" . $imageName;
    return is_file($source) && !file_exists($destination) && rename($source, $destination);
}

function normalize_book_category_ids($input): array
{
    if (!is_array($input)) {
        return [];
    }

    $categoryIds = [];
    foreach ($input as $value) {
        if (!is_scalar($value)) {
            continue;
        }
        $categoryId = filter_var($value, FILTER_VALIDATE_INT);
        if ($categoryId !== false && $categoryId > 0) {
            $categoryIds[] = $categoryId;
        }
    }

    return array_values(array_unique($categoryIds));
}

function book_categories_exist(mysqli $conn, array $categoryIds): bool
{
    if ($categoryIds === []) {
        return false;
    }

    $ids = implode(",", array_map("intval", $categoryIds));
    $result = mysqli_query($conn, "SELECT COUNT(*) AS category_count FROM categories WHERE id IN ($ids)");
    if (!$result) {
        return false;
    }

    $row = mysqli_fetch_assoc($result);
    return (int)$row["category_count"] === count($categoryIds);
}

function replace_book_categories(mysqli $conn, int $bookId, array $categoryIds): bool
{
    $delete = mysqli_prepare($conn, "DELETE FROM book_categories WHERE book_id = ?");
    if (!$delete) {
        return false;
    }
    mysqli_stmt_bind_param($delete, "i", $bookId);
    if (!mysqli_stmt_execute($delete)) {
        return false;
    }

    if ($categoryIds !== []) {
        $insert = mysqli_prepare($conn, "INSERT INTO book_categories (book_id, category_id) VALUES (?, ?)");
        if (!$insert) {
            return false;
        }
        foreach ($categoryIds as $categoryId) {
            mysqli_stmt_bind_param($insert, "ii", $bookId, $categoryId);
            if (!mysqli_stmt_execute($insert)) {
                return false;
            }
        }

        $primaryCategoryId = $categoryIds[0];
        $update = mysqli_prepare($conn, "UPDATE books SET category_id = ? WHERE id = ?");
        if (!$update) {
            return false;
        }
        mysqli_stmt_bind_param($update, "ii", $primaryCategoryId, $bookId);
        if (!mysqli_stmt_execute($update)) {
            return false;
        }
    }

    return true;
}

function normalize_pending_category_names($input, ?string &$error): array
{
    $error = "";
    if (!is_string($input)) {
        $error = "Thể loại mới không hợp lệ.";
        return [];
    }

    $rawNames = preg_split('/[,;\r\n]+/u', $input);
    if ($rawNames === false) {
        $error = "Tên thể loại chứa ký tự không hợp lệ.";
        return [];
    }

    $names = [];
    foreach ($rawNames as $rawName) {
        $name = preg_replace('/\s+/u', ' ', trim($rawName));
        if ($name === "") {
            continue;
        }
        if (mb_strlen($name, "UTF-8") > 100) {
            $error = "Tên thể loại không được dài quá 100 ký tự.";
            return [];
        }

        $firstCharacter = mb_substr($name, 0, 1, "UTF-8");
        $name = mb_strtoupper($firstCharacter, "UTF-8") . mb_substr($name, 1, null, "UTF-8");
        $key = mb_strtolower($name, "UTF-8");
        $names[$key] = $name;
    }

    return array_values($names);
}

function resolve_book_category_choices(mysqli $conn, $selectedInput, $newInput, array &$categoryIds, array &$pendingNames, ?string &$error): bool
{
    $categoryIds = normalize_book_category_ids($selectedInput);
    $pendingNames = [];
    $error = "";
    if ($categoryIds !== [] && !book_categories_exist($conn, $categoryIds)) {
        $error = "Danh sách thể loại đã chọn không hợp lệ.";
        return false;
    }

    $newNames = normalize_pending_category_names($newInput, $error);
    if ($error !== "") {
        return false;
    }

    $lookup = mysqli_prepare($conn, "SELECT id FROM categories WHERE name = ? LIMIT 1");
    if (!$lookup) {
        $error = "Không thể kiểm tra thể loại. Vui lòng thử lại.";
        return false;
    }
    foreach ($newNames as $name) {
        mysqli_stmt_bind_param($lookup, "s", $name);
        if (!mysqli_stmt_execute($lookup)) {
            $error = "Không thể kiểm tra thể loại. Vui lòng thử lại.";
            return false;
        }
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup));
        if ($existing) {
            $categoryIds[] = (int)$existing["id"];
        } else {
            $pendingNames[] = $name;
        }
    }

    $categoryIds = array_values(array_unique($categoryIds));
    if ($categoryIds === [] && $pendingNames === []) {
        $error = "Vui lòng chọn thể loại có sẵn hoặc nhập ít nhất một thể loại mới.";
        return false;
    }

    return true;
}

function replace_pending_book_categories(mysqli $conn, int $bookId, array $categoryNames): bool
{
    $delete = mysqli_prepare($conn, "DELETE FROM book_pending_categories WHERE book_id = ?");
    if (!$delete) {
        return false;
    }
    mysqli_stmt_bind_param($delete, "i", $bookId);
    if (!mysqli_stmt_execute($delete)) {
        return false;
    }

    if ($categoryNames === []) {
        return true;
    }

    $insert = mysqli_prepare($conn, "INSERT INTO book_pending_categories (book_id, category_name) VALUES (?, ?)");
    if (!$insert) {
        return false;
    }
    foreach ($categoryNames as $categoryName) {
        mysqli_stmt_bind_param($insert, "is", $bookId, $categoryName);
        if (!mysqli_stmt_execute($insert)) {
            return false;
        }
    }

    return true;
}

function approve_pending_book_categories(mysqli $conn, int $bookId): bool
{
    $stmt = mysqli_prepare($conn, "SELECT category_name FROM book_pending_categories WHERE book_id = ?");
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, "i", $bookId);
    if (!mysqli_stmt_execute($stmt)) {
        return false;
    }
    $pending = mysqli_stmt_get_result($stmt);
    $lookup = mysqli_prepare($conn, "SELECT id FROM categories WHERE name = ? LIMIT 1");
    $create = mysqli_prepare($conn, "INSERT INTO categories (name) VALUES (?)");
    $link = mysqli_prepare($conn, "INSERT IGNORE INTO book_categories (book_id, category_id) VALUES (?, ?)");
    if (!$lookup || !$create || !$link) {
        return false;
    }

    $firstCategoryId = null;
    while ($row = mysqli_fetch_assoc($pending)) {
        $name = $row["category_name"];
        mysqli_stmt_bind_param($lookup, "s", $name);
        if (!mysqli_stmt_execute($lookup)) {
            return false;
        }
        $category = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup));
        if ($category) {
            $categoryId = (int)$category["id"];
        } else {
            mysqli_stmt_bind_param($create, "s", $name);
            if (!mysqli_stmt_execute($create)) {
                return false;
            }
            $categoryId = (int)mysqli_insert_id($conn);
        }

        if ($firstCategoryId === null) {
            $firstCategoryId = $categoryId;
        }
        mysqli_stmt_bind_param($link, "ii", $bookId, $categoryId);
        if (!mysqli_stmt_execute($link)) {
            return false;
        }
    }

    if ($firstCategoryId !== null) {
        $update = mysqli_prepare($conn, "UPDATE books SET category_id = ? WHERE id = ?");
        if (!$update) {
            return false;
        }
        mysqli_stmt_bind_param($update, "ii", $firstCategoryId, $bookId);
        if (!mysqli_stmt_execute($update)) {
            return false;
        }
    }

    $deletePending = mysqli_prepare($conn, "DELETE FROM book_pending_categories WHERE book_id = ?");
    if (!$deletePending) {
        return false;
    }
    mysqli_stmt_bind_param($deletePending, "i", $bookId);
    return mysqli_stmt_execute($deletePending);
}
