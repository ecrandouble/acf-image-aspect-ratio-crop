<?php

namespace Joppuyo\AIARC;

/**
 * Includes the composer Autoloader used for packages and classes in the src/ directory.
 */

defined('ABSPATH') || exit();

/**
 * Autoloader class.
 *
 * @since 3.7.0
 */
class Autoloader
{
    /**
     * Static-only class.
     */
    private function __construct()
    {
    }

    /**
     * Require the autoloader and return the result.
     *
     * If the autoloader is not present, let's log the failure and display a nice admin notice.
     *
     * @return boolean
     */
    public static function init()
    {
        $autoloader = __DIR__ . '/vendor/autoload.php';

        if (!is_readable($autoloader)) {
            self::missingAutoloader();
            return false;
        }

        $autoloader_result = require $autoloader;
        if (!$autoloader_result) {
            return false;
        }

        return $autoloader_result;
    }

    /**
     * If the autoloader is missing, add an admin notice.
     */
    protected static function missingAutoloader()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(
                // phpcs:ignore
                esc_html__(
                    'Your installation of acf-image-aspect-ratio-crop is incomplete.',
                    'acf-image-aspect-ratio-crop-ed'
                )
            );
        }
        add_action('admin_notices', function () {
            ?>
				<div class="notice notice-error">
					<p>
						<?php printf(
          /* translators: 1: is a link to a support document. 2: closing link */
          esc_html__(
              'Your installation of acf-image-aspect-ratio-crop is incomplete.',
              'acf-image-aspect-ratio-crop-ed'
          ),
          '<a href="' . esc_url('mailto:support@ecrandouble.ch') . '">',
          '</a>'
      ); ?>
					</p>
				</div>
				<?php
        });
    }
}
