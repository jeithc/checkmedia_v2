<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ImageWatermarkService
{
    /** @var string|null|false Cached font path (null=not checked, false=no font, string=path) */
    private static string|null|false $cachedFontPath = null;

    /**
     * Add watermark with date and time to an uploaded image
     * 
     * @param UploadedFile $file
     * @param string|null $dateTime Custom date time string, defaults to current time
     * @return UploadedFile Modified file
     */
    public function addWatermark(UploadedFile $file, ?string $dateTime = null): UploadedFile
    {
        $dateTime = $dateTime ?? now()->format('Y-m-d H:i:s');
        $path = $file->getRealPath();
        
        // Read file once into string to avoid double disk reads
        $imageData = file_get_contents($path);
        if ($imageData === false) {
            return $file;
        }

        // Create image from string (faster than imagecreatefrom* for already-loaded data)
        $image = @imagecreatefromstring($imageData);
        unset($imageData); // Free memory immediately
        
        if (!$image) {
            return $file;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $mimeType = $file->getMimeType();

        // Enable alpha blending for transparency
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Get cached font path
        $fontPath = $this->getFontPath();
        $text = $dateTime;

        if ($fontPath) {
            // Use TTF font for better quality
            $padding = 10;
            $fontSize = max(12, min($width, $height) / 40);
            
            $bbox = @imagettfbbox($fontSize, 0, $fontPath, $text);
            if ($bbox) {
                $textWidth = abs($bbox[4] - $bbox[0]);
                $textHeight = abs($bbox[5] - $bbox[1]);
                
                $x = $width - $textWidth - $padding * 2;
                $y = $height - $textHeight - $padding;

                // Semi-transparent background
                $bgColor = imagecolorallocatealpha($image, 0, 0, 0, 60);
                imagefilledrectangle($image, $x - $padding, $y - $textHeight - $padding, $x + $textWidth + $padding, $y + $padding, $bgColor);

                // White text
                $textColor = imagecolorallocate($image, 255, 255, 255);
                imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $text);
            }
        } else {
            // Fallback: built-in font
            $padding = 5;
            $font = 3;
            
            $textWidth = strlen($text) * imagefontwidth($font);
            $textHeight = imagefontheight($font);
            
            $x = $width - $textWidth - $padding * 2;
            $y = $height - $textHeight - $padding;

            $bgColor = imagecolorallocatealpha($image, 0, 0, 0, 60);
            imagefilledrectangle($image, $x - $padding, $y - $padding, $x + $textWidth + $padding, $y + $textHeight + $padding, $bgColor);

            $textColor = imagecolorallocate($image, 255, 255, 255);
            imagestring($image, $font, $x, $y, $text, $textColor);
        }

        // Save to temporary file with optimized settings
        $tempPath = tempnam(sys_get_temp_dir(), 'wm_');
        
        $saved = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $tempPath, 85), // 85 is good balance
            'image/png' => imagepng($image, $tempPath, 4),    // Level 4 is much faster than 9
            'image/gif' => imagegif($image, $tempPath),
            'image/webp' => imagewebp($image, $tempPath, 85),
            default => false,
        };

        imagedestroy($image);

        if (!$saved) {
            @unlink($tempPath);
            return $file;
        }

        return new UploadedFile(
            $tempPath,
            $file->getClientOriginalName(),
            $mimeType,
            null,
            true
        );
    }

    /**
     * Get path to font file (cached)
     */
    private function getFontPath(): ?string
    {
        // Return cached result
        if (self::$cachedFontPath !== null) {
            return self::$cachedFontPath ?: null;
        }

        // Check if imagettftext is available
        if (!function_exists('imagettftext')) {
            self::$cachedFontPath = false;
            return null;
        }

        // Try common system fonts
        $fonts = [
            '/System/Library/Fonts/Helvetica.ttc',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            'C:/Windows/Fonts/arial.ttf',
        ];

        foreach ($fonts as $font) {
            if (file_exists($font)) {
                self::$cachedFontPath = $font;
                return $font;
            }
        }

        self::$cachedFontPath = false;
        return null;
    }
}
