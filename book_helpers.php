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
