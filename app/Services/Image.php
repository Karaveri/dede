<?php declare(strict_types=1);
namespace App\Services;

final class Image
{
    /**
     * @param string $src   Orijinal dosya (public/uploads/... veya mutlak yol)
     * @param int    $w     hedef genişlik
     * @param int    $h     hedef yükseklik
     * @param string $fit   cover|contain
     * @return string       public URL ( /thumbs/.. )
     */
    public static function thumb(string $src, int $w, int $h, string $fit = 'cover'): string
    {
        $srcPath = self::resolveSource($src);
        if (!is_file($srcPath)) return self::placeholder($w, $h);

        [$mime, $ext] = self::mimeExt($srcPath);
        if (!$mime) return self::placeholder($w, $h);

        $hash = substr(sha1($srcPath . '|' . filemtime($srcPath) . "|$w|$h|$fit"), 0, 16);
        $destRel = "/thumbs/{$w}x{$h}/{$hash}." . $ext;
        $destAbs = dirname(__DIR__, 2) . '/public' . $destRel;

        if (is_file($destAbs)) return $destRel;

        @mkdir(dirname($destAbs), 0775, true);

        [$sw, $sh] = getimagesize($srcPath) ?: [0, 0];
        if ($sw < 1 || $sh < 1) return self::placeholder($w, $h);

        $srcImg = self::load($srcPath, $mime);
        if (!$srcImg) return self::placeholder($w, $h);

        // Hesap
        $dstImg = imagecreatetruecolor($w, $h);
        imagealphablending($dstImg, false); imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
        imagefilledrectangle($dstImg, 0, 0, $w, $h, $transparent);

        $ratioS = $sw / $sh; $ratioD = $w / $h;
        if ($fit === 'contain') {
            if ($ratioD > $ratioS) { // yükseklik belirler
                $nh = $h; $nw = (int)round($h * $ratioS);
                $dx = (int)(($w - $nw) / 2); $dy = 0;
            } else {
                $nw = $w; $nh = (int)round($w / $ratioS);
                $dx = 0; $dy = (int)(($h - $nh) / 2);
            }
            imagecopyresampled($dstImg, $srcImg, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);
        } else { // cover
            if ($ratioD > $ratioS) { // genişlik belirler → kaynak yükseklikten kırp
                $nh = (int)round($sw / $ratioD);
                $sy = (int)max(0, ($sh - $nh) / 2);
                imagecopyresampled($dstImg, $srcImg, 0, 0, 0, $sy, $w, $h, $sw, $nh);
            } else {
                $nw = (int)round($sh * $ratioD);
                $sx = (int)max(0, ($sw - $nw) / 2);
                imagecopyresampled($dstImg, $srcImg, 0, 0, $sx, 0, $w, $h, $nw, $sh);
            }
        }

        self::save($dstImg, $destAbs, $mime);
        imagedestroy($dstImg); imagedestroy($srcImg);

        return $destRel;
    }

    private static function resolveSource(string $src): string
    {
        if (str_starts_with($src, '/')) {
            return dirname(__DIR__, 2) . '/public' . $src;
        }
        if (preg_match('~^https?://~i', $src)) return $src; // dış URL → placeholder
        // uploads göreli ise:
        return dirname(__DIR__, 2) . '/public/uploads/' . ltrim($src, '/');
    }

    private static function placeholder(int $w, int $h): string
    {
        return "data:image/svg+xml;utf8," . rawurlencode(
            "<svg xmlns='http://www.w3.org/2000/svg' width='$w' height='$h'>
                <rect width='100%' height='100%' fill='#f2f2f2'/>
                <text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' font-size='12' fill='#999'>$w×$h</text>
            </svg>"
        );
    }

    private static function mimeExt(string $path): array
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $path) ?: null;
        finfo_close($finfo);
        $map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        return [$mime, $map[$mime] ?? null];
    }

    private static function load(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/gif'  => imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : null,
            default      => null
        };
    }

    private static function save($img, string $path, string $mime): void
    {
        switch ($mime) {
            case 'image/jpeg': imagejpeg($img, $path, 85); break;
            case 'image/png' : imagepng($img, $path, 6); break;
            case 'image/gif' : imagegif($img, $path); break;
            case 'image/webp': if (function_exists('imagewebp')) imagewebp($img, $path, 85); break;
        }
    }
}
