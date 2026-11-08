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
    if (!move_uploaded_file($file["tmp_name"], __DIR__ . "/uploads/" . $imageName)) {
        $error = "Không thể lưu ảnh. Vui lòng kiểm tra quyền ghi thư mục uploads.";
        return null;
    }

    return $imageName;
}
