<?php

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';

/**
 * Custom Image Editor Class for Image Manipulation through GD
 *
 * @see WP_Image_Editor_GD
 */
class NPX_Image_Editor_GD extends WP_Image_Editor_GD
{
    /**
     * Crops Image.
     *
     * @since 3.5.0
     *
     * @param int  $src_x   The start x position to crop from.
     * @param int  $src_y   The start y position to crop from.
     * @param int  $src_w   The width to crop.
     * @param int  $src_h   The height to crop.
     * @param int  $dst_w   Optional. The destination width.
     * @param int  $dst_h   Optional. The destination height.
     * @param bool $src_abs Optional. If the source crop points are absolute.
     * @return true|WP_Error
     */
    public function crop(
        $src_x,
        $src_y,
        $src_w,
        $src_h,
        $dst_w = null,
        $dst_h = null,
        $src_abs = false
    ) {
        $dst_x = 0;
        $dst_y = 0;
        /*
         * If destination width/height isn't specified,
         * use same as width/height from source.
         */
        if (!$dst_w) {
            $dst_w = $src_w;
        }
        if (!$dst_h) {
            $dst_h = $src_h;
        }

        $original_w = imagesx($this->image);
        $original_h = imagesy($this->image);
        $src_w = min($original_w, $src_w);
        $src_h = min($original_h, $src_h);

        if ($src_x < 0) {
            $dst_x = abs($src_x);
            $src_x = 0;
            $src_w -= $dst_x;
        }
        if ($src_y < 0) {
            $dst_y = abs($src_y);
            $src_y = 0;
            $src_h -= $dst_y;
        }
        if ($src_x + $src_w > $original_w) {
            $src_w = $original_w - $src_x;
        }
        if ($src_y + $src_h > $original_h) {
            $src_h = $original_h - $src_y;
        }

        foreach ([$src_w, $src_h, $dst_w, $dst_h] as $value) {
            if (!is_numeric($value) || (int) $value <= 0) {
                return new WP_Error(
                    'image_crop_error',
                    __('Image crop failed.'),
                    $this->file
                );
            }
        }

        $dst = imagecreatetruecolor($dst_w, $dst_h);

        if (
            is_gd_image($dst) &&
            function_exists('imagealphablending') &&
            function_exists('imagesavealpha')
        ) {
            imagealphablending($dst, true);
            imagesavealpha($dst, true);
            // imagealphablending( $this->image, true );
            // imagesavealpha( $dst, true );
        }

        imagefill($dst, 0, 0, 0x7f000000);

        // imagefilledrectangle($dst, 0, 0, $dst_w, $dst_h, imagecolorallocatealpha($dst,0,0,0,0));

        if ($src_abs) {
            $src_w -= $src_x;
            $src_h -= $src_y;
        }

        if (function_exists('imageantialias')) {
            imageantialias($dst, true);
        }

        imagecopy(
            $dst,
            $this->image,
            $dst_x,
            $dst_y,
            (int) $src_x,
            (int) $src_y,
            (int) $src_w,
            (int) $src_h
        );

        if (is_gd_image($dst)) {
            imagedestroy($this->image);
            $this->image = $dst;
            $this->update_size();
            return true;
        }

        return new WP_Error(
            'image_crop_error',
            __('Image crop failed.'),
            $this->file
        );
    }
}
