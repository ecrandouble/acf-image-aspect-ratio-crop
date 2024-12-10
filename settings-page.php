<?php
$updated = false;
$settings = $this->user_settings;
if (!empty($_POST)) {
    check_admin_referer('acf-image-aspect-ratio-crop');

    if (!empty($_POST['modal_type'])) {
        $settings['modal_type'] = $_POST['modal_type'];
    }

    if (!empty($_POST['delete_unused'])) {
        $settings['delete_unused'] = filter_var(
            $_POST['delete_unused'],
            FILTER_VALIDATE_BOOLEAN
        );
    }

    if (!empty($_POST['allow_no_crop'])) {
        $settings['allow_no_crop'] = filter_var(
            $_POST['allow_no_crop'],
            FILTER_VALIDATE_BOOLEAN
        );
    }

    if (!empty($_POST['rest_api_compat'])) {
        $settings['rest_api_compat'] = filter_var(
            $_POST['rest_api_compat'],
            FILTER_VALIDATE_BOOLEAN
        );
    }

    update_option('acf-image-aspect-ratio-crop-settings', $settings);
    $updated = true;
}
$modal_type = $settings['modal_type'];
$allow_no_crop = $settings['allow_no_crop'];
$delete_unused = $settings['delete_unused'] ?? true;
$rest_api_compat = $settings['rest_api_compat'];
?>
<div class="wrap">
    <h1>
        <?= __(
            'ACF Image Aspect Ratio Crop',
            'acf-image-aspect-ratio-crop-ed'
        ) ?>
    </h1>
    <div class="js-finnish-base-forms-admin-notices"></div>
    <?php if ($updated): ?>
        <div class="notice notice-success">
            <p>
                <?= __(
                    'Options have been updated',
                    'acf-image-aspect-ratio-crop-ed'
                ) ?>
            </p>
        </div>
    <?php endif; ?>
    <form method="post">
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="modal_type">
                            <?= __(
                                'Image displayed in attachment edit modal dialog',
                                'acf-image-aspect-ratio-crop-ed'
                            ) ?>
                        </label>
                    </th>
                    <td>
                        <p>
                            <input type="radio" id="cropped" name="modal_type" value="cropped" <?= checked(
                                $modal_type,
                                'cropped',
                                false
                            ) ?> '>
                            <label for="cropped">
                                <?= __(
                                    'Cropped image',
                                    'acf-image-aspect-ratio-crop-ed'
                                ) ?>
                            </label>
                        </p>
                        <p>
                            <input type="radio" id="original" name="modal_type" value="original"
                            <?= checked($modal_type, 'original', false) ?>
                            '>
                            <label for="original">
                                <?= __(
                                    'Original image',
                                    'acf-image-aspect-ratio-crop-ed'
                                ) ?>
                            </label>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="modal_type">
                            <?= __(
                                'Allow to keep the original dimensions of the image',
                                'acf-image-aspect-ratio-crop-ed'
                            ) ?>
                        </label>
                    </th>
                    <td>
                        <p>
                            <input type="radio" id="allow_no_crop_true" name="allow_no_crop" value="true" <?= checked(
                                $allow_no_crop,
                                true,
                                false
                            ) ?>>
                            <label for="allow_no_crop_true">
                                <?= __(
                                    'Enabled',
                                    'acf-image-aspect-ratio-crop-ed'
                                ) ?>
                            </label>
                        </p>
                        <p>
                            <input type="radio" id="allow_no_crop_false" name="allow_no_crop" value="false" <?= checked(
                                $allow_no_crop,
                                false,
                                false
                            ) ?>>
                            <label for="allow_no_crop_false">
                                <?= __(
                                    'Disabled',
                                    'acf-image-aspect-ratio-crop-ed'
                                ) ?>
                            </label>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="modal_type">
                            <?= __(
                                'Delete unused cropped images',
                                'acf-image-aspect-ratio-crop-ed'
                            ) ?>
                            <?= __(
                                '(Beta feature)',
                                'acf-image-aspect-ratio-crop-ed'
                            ) ?>
                        </label>
                    </th>
                    <td>
                        <p>
                            <input type="radio" id="delete_unused_true" name="delete_unused" value="true" <?= checked(
                                $delete_unused,
                                true,
                                false
                            ) ?>>
                            <label for="delete_unused_true">
                                <?= __(
                                    'Enabled',
                                    'acf-image-aspect-ratio-crop-ed'
                                ) ?>
                            </label>
                        </p>
                        <p>
                            <input type="radio" id="delete_unused_false" name="delete_unused" value="false" <?= checked(
                                $delete_unused,
                                false,
                                false
                            ) ?>>
                            <label for="delete_unused_false">
                                <?= __(
                                    'Disabled',
                                    'acf-image-aspect-ratio-crop-ed'
                                ) ?>
                            </label>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 0">
                        <?= __(
                            'Please note that "Delete unused cropped images" feature is a beta feature because it requires more testing. Please do not enable the option without first backing up your database and uploads in order to prevent potential data loss.',
                            'acf-image-aspect-ratio-crop-ed'
                        ) ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="modal_type">
                            <?= __(
                                'REST API compatibility mode',
                                'acf-image-aspect-ratio-crop-ed'
                            ) ?>
                        </label>
                    </th>
                    <td>
                        <p>
                            <input type="radio" id="rest_api_compat_true" name="rest_api_compat" value="true" <?= checked(
                                $rest_api_compat,
                                true,
                                false
                            ) ?>>
                            <label for="rest_api_compat_true">
                                <?= __(
                                    'Enabled',
                                    'acf-image-aspect-ratio-crop-ed'
                                ) ?>
                            </label>
                        </p>
                        <p>
                            <input type="radio" id="rest_api_compat_false" name="rest_api_compat" value="false" <?= checked(
                                $rest_api_compat,
                                false,
                                false
                            ) ?>>
                            <label for="rest_api_compat_false">
                                <?= __(
                                    'Disabled',
                                    'acf-image-aspect-ratio-crop-ed'
                                ) ?>
                            </label>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 0">
                        <?php __(
                            'When you enable the REST API compatibility mode, cropping in the WordPress administration interface will use admin-ajax.php instead of the REST API. Use this compatibility mode if you do not have REST API enabled. Please note that this is a temporary fix since the REST API is the way forward. The compatibility mode will be removed in a future major release of the plugin.',
                            'acf-image-aspect-ratio-crop-ed'
                        ); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <input class="button-primary js-finnish-base-forms-submit-button" type="submit" name="submit-button" value="Save">
        </p>
        <?php wp_nonce_field('acf-image-aspect-ratio-crop'); ?>
    </form>
</div>
