<?php

class Upload
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    private const ALLOWED_PDF = ['application/pdf'];

    /**
     * Process and save an uploaded image as WebP.
     *
     * @param  array  $file     $_FILES entry
     * @param  string $subdir   Subdirectory inside /uploads (e.g., 'fotos', 'logos')
     * @param  int    $maxW     Target max width in px
     * @param  int    $maxH     Target max height in px
     * @param  int    $quality  WebP quality 0–100
     * @return string Relative path from /uploads root (e.g., 'fotos/abc123.webp')
     */
    public static function image(
        array  $file,
        string $subdir  = 'fotos',
        int    $maxW    = 400,
        int    $maxH    = 400,
        int    $quality = 80
    ): string {
        self::assertNoError($file);
        self::assertSize($file);
        self::assertImageMime($file);

        $targetDir = UPLOAD_PATH . '/' . $subdir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.webp';
        $destPath = $targetDir . '/' . $filename;

        self::convertToWebP($file['tmp_name'], $destPath, $maxW, $maxH, $quality);

        return $subdir . '/' . $filename;
    }

    /**
     * Process and save an uploaded PDF.
     *
     * @return string Relative path from /uploads root
     */
    public static function pdf(array $file, string $subdir = 'materiais'): string
    {
        self::assertNoError($file);
        self::assertSize($file);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, self::ALLOWED_PDF, true)) {
            throw new RuntimeException('Tipo de arquivo não permitido. Apenas PDF é aceito.');
        }

        $targetDir = UPLOAD_PATH . '/' . $subdir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.pdf';
        $destPath = $targetDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException('Falha ao salvar o arquivo.');
        }

        return $subdir . '/' . $filename;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private static function assertNoError(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msgs = [
                UPLOAD_ERR_INI_SIZE   => 'O arquivo excede o limite do servidor.',
                UPLOAD_ERR_FORM_SIZE  => 'O arquivo excede o limite do formulário.',
                UPLOAD_ERR_PARTIAL    => 'O arquivo foi enviado parcialmente.',
                UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
                UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar o arquivo.',
            ];
            throw new RuntimeException($msgs[$file['error']] ?? 'Erro desconhecido no upload.');
        }
    }

    private static function assertSize(array $file): void
    {
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $mb = UPLOAD_MAX_SIZE / 1024 / 1024;
            throw new RuntimeException("O arquivo excede o tamanho máximo de {$mb}MB.");
        }
    }

    private static function assertImageMime(array $file): void
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!array_key_exists($mime, self::ALLOWED_MIME)) {
            throw new RuntimeException('Tipo de imagem não permitido. Use JPEG, PNG, GIF ou WebP.');
        }
    }

    private static function convertToWebP(
        string $src,
        string $dest,
        int    $maxW,
        int    $maxH,
        int    $quality
    ): void {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $src);
        finfo_close($finfo);

        $img = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($src),
            'image/png'  => imagecreatefrompng($src),
            'image/gif'  => imagecreatefromgif($src),
            'image/webp' => imagecreatefromwebp($src),
            default      => throw new RuntimeException('Formato de imagem não suportado.'),
        };

        if ($img === false) {
            throw new RuntimeException('Falha ao processar a imagem.');
        }

        // Fix PNG transparency
        if ($mime === 'image/png') {
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
        }

        [$origW, $origH] = [imagesx($img), imagesy($img)];

        // Crop to square then resize (for profile photos)
        $minDim = min($origW, $origH);
        $srcX   = (int) (($origW - $minDim) / 2);
        $srcY   = (int) (($origH - $minDim) / 2);

        $canvas = imagecreatetruecolor($maxW, $maxH);
        imagecopyresampled($canvas, $img, 0, 0, $srcX, $srcY, $maxW, $maxH, $minDim, $minDim);

        imagedestroy($img);

        if (!imagewebp($canvas, $dest, $quality)) {
            imagedestroy($canvas);
            throw new RuntimeException('Falha ao converter a imagem para WebP.');
        }

        imagedestroy($canvas);
    }

    /**
     * Delete a stored file (relative path from /uploads).
     */
    public static function delete(string $relativePath): void
    {
        if (empty($relativePath)) return;
        $full = UPLOAD_PATH . '/' . ltrim($relativePath, '/');
        if (is_file($full)) {
            unlink($full);
        }
    }
}
