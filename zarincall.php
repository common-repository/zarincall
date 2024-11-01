<?php
/*
Plugin name: ZarinCall
Plugin URI: http://zarincall.ir
Description: این افزونه برای سیستم وردپرس و ووکامرس نوشته شده است و می توانید به آسانی سایت یا فروشگاه خود از طریق این افزونه به دستیار تلنفی زرین کال ZarinCall.ir متصل نمایید.
Author: zarincall
Author URI: http://zarincall.ir
Version: 1.7
*/

const BASE_URL = "https://ws.zarincall.ir/apiv2/";


function zarinCallFromApi($fileId, $phoneNumberArrays)
{
    $apiKey = get_option('zarincall_api_key');


    $body = array(
        'VoiceId'    => $fileId,
        'Numbers'   => $phoneNumberArrays,
    );

    $args = array(
        'body'        => json_encode($body),
        'timeout'     => '45',
        'redirection' => '5',
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array(
            "Content-Type"  => "application/json; charset=utf-8",
            "Accept"        => "application/json",
            "Authorization" => "basic apikey:" . $apiKey,
        ),
        'data_format' => 'body',
    );

    $response = wp_remote_post(BASE_URL . 'Message/Send', $args);

    $pluginlog = plugin_dir_path(__FILE__) . 'debug.log';

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log($error_message . PHP_EOL, 3, $pluginlog);
    } else {
        $obj = json_decode($response["body"], false);
        if ($obj->R_Success != true) {
            $pluginlog = plugin_dir_path(__FILE__) . 'debug.log';
            error_log($response["body"] . PHP_EOL, 3, $pluginlog);
        }
    }
}

function zarinCallGetUserPulse()
{
    $apiKey = get_option('zarincall_api_key');

    $body = array(
        'ApiKey'    => $apiKey,
    );

    $args = array(
        'body'        => json_encode($body),
        'timeout'     => '45',
        'redirection' => '5',
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array(
            "Content-Type"  => "application/json; charset=utf-8",
            "Accept"        => "application/json",
            "Authorization" => "basic apikey:" . $apiKey,
        ),
        'data_format' => 'body',
    );

    $response = wp_remote_post(BASE_URL . 'User/GetPulse', $args);

    $pluginlog = plugin_dir_path(__FILE__) . 'debug.log';

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log($error_message . PHP_EOL, 3, $pluginlog);
        return false;
    } else {
        return $response["body"];
    }
}

// add zarincall to admin menu
add_action('admin_menu', 'add_zarincall_menu');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'zarincall_add_action_links');

function zarincall_add_action_links($actions)
{
    $mylinks = array(
        '<a href="' . admin_url('admin.php?page=zarincall-manage') . '">پیکربندی</a>',
    );
    $actions = array_merge($actions, $mylinks);
    return $actions;
}

//add register field to register if it enable
if (get_option('zarincall_wellcome_isEnable') == "on") {
    add_action('register_form',  'zarincall_register_form');
    add_action('user_register', 'zarincall_new_user_registered');
    add_filter('registration_errors', 'zarincall_registration_errors', 10, 3);

    add_action('show_user_profile', 'zarincall_update_form');
    add_action('edit_user_profile', 'zarincall_update_form');
    add_action("user_new_form", "zarincall_update_form");


    add_action('user_profile_update_errors', 'zarincall_update_error', 10, 3);


    add_action('user_register', 'zarincall_update_user');
    add_action('personal_options_update', 'zarincall_update_user');
    add_action('edit_user_profile_update', 'zarincall_update_user');
}

//check if woocommerce install
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    //check if woocommerce settings set
    if (get_option('zarincall_woocommrece_onOrderCompelete_isEnable') == "on") {
        add_action('woocommerce_checkout_order_processed', 'zarincall_woocommerce_call', 10, 1);
    }

    //add phone number to woocommerce
    if (get_option("zarincall_woocommrece_add_phone_register_isEnable") == "on") {
        add_action('woocommerce_register_form_start', 'zarincall_wooc_extra_register_fields');
        add_action('woocommerce_register_post', 'zarincall_wooc_validate_extra_register_fields', 10, 3);
        add_action('woocommerce_created_customer', 'zarincall_wooc_save_extra_register_fields');

        //add phone number to edit page
        add_action('woocommerce_edit_account_form', 'zarincall_wooc_edit_account_fields');
        add_action('woocommerce_save_account_details_errors', 'zarincall_wooc_save_edit_account_fields_error', 20, 1);
        add_action('woocommerce_save_account_details', 'zarincall_wooc_save_edit_account_fields', 20, 1);
    }
}

//add field to woocommerce edit account
function zarincall_wooc_edit_account_fields()
{
    $user = wp_get_current_user();
?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="zarincall_phone_number">شماره تلفن :</label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="zarincall_phone_number" id="zarincall_phone_number" value="<?php echo esc_attr($user->zarincall_phone_number); ?>" />
    </p>
<?php
}

//validate phone number in woocommerce edit account
function zarincall_wooc_save_edit_account_fields_error($validation_errors)
{
    if (!isset($_POST['zarincall_phone_number']) || empty($_POST['zarincall_phone_number'])) {
        $validation_errors->add('zarincall_phone_number_empty_error', __('<strong>خطا</strong>: لطفا شماره تلفن خود را وارد نمایید', 'woocommerce'));
    } else {
        $phoneNumber = sanitize_text_field($_POST['zarincall_phone_number']);
        if (!preg_match("/^09[0-9]{9}$/", $phoneNumber)) {
            $validation_errors->add('zarincall_phone_number_valid_error', __('<strong>خطا</strong>: شماره وارد شده معتبر نیست', 'woocommerce'));
        }
    }
}

//save new phone number in woocommerce account page
function zarincall_wooc_save_edit_account_fields($user_id)
{
    update_user_meta($user_id, 'zarincall_phone_number', sanitize_text_field($_POST['zarincall_phone_number']));
}

//add new filed to register from in woocommerce
function zarincall_wooc_extra_register_fields()
{
    $phone_number = '';
    if (!empty($_POST['zarincall_phone_number'])) {
        $phone_number = sanitize_text_field($_POST['zarincall_phone_number']);
    }
?>
    <p class="form-row form-row-wide">
        <label for="zarincall_phone_number">شماره تلفن :<span class="required">*</span></label>
        <input type="text" class="input-text" name="zarincall_phone_number" id="zarincall_phone_number" value="<?php esc_attr_e($phone_number); ?>" />
    </p>
<?php
}

//validate woocommerce register field
function zarincall_wooc_validate_extra_register_fields($username, $email, $validation_errors)
{

    if (!isset($_POST['zarincall_phone_number']) || empty($_POST['zarincall_phone_number'])) {
        $validation_errors->add('zarincall_phone_number_empty_error', __('<strong>خطا</strong>: لطفا شماره تلفن خود را وارد نمایید', 'woocommerce'));
    } else {
        $phoneNumber = sanitize_text_field($_POST['zarincall_phone_number']);
        if (!preg_match("/^09[0-9]{9}$/", $phoneNumber)) {
            $validation_errors->add('zarincall_phone_number_valid_error', __('<strong>خطا</strong>: شماره وارد شده معتبر نیست', 'woocommerce'));
        }
    }

    return $validation_errors;
}

//save phone number and make call if wellcome message is enabled in woocommerce
function zarincall_wooc_save_extra_register_fields($customer_id)
{
    if (isset($_POST['zarincall_phone_number'])) {
        $phoneNumber = sanitize_text_field($_POST['zarincall_phone_number']);
        update_user_meta($customer_id, 'zarincall_phone_number', $phoneNumber);

        if (get_option('zarincall_wellcome_isEnable') == "on") {
            $phoneArray = [$phoneNumber];
            $fileId = get_option('zarincall_wellcome_fileId');
            zarinCallFromApi($fileId, $phoneArray);
        }
    }
}


function zarincall_woocommerce_call($order_id)
{
    $customer_id = get_current_user_id();
    $phoneNumberZarincall = get_user_meta($customer_id, 'zarincall_phone_number', true);
    $phoneNumberBilling = get_user_meta($customer_id, 'billing_phone', true);

    $fileId = get_option('zarincall_woocommrece_onOrderCompelete_fileId');

    if ($phoneNumberZarincall && !empty($phoneNumberZarincall)) {

        $phoneArray = [$phoneNumberZarincall];

        zarinCallFromApi($fileId, $phoneArray);
    } else {
        if ($phoneNumberBilling && !empty($phoneNumberBilling)) {
            $phoneArray = [$phoneNumberBilling];
            zarinCallFromApi($fileId, $phoneArray);
        } else {
            $pluginlog = plugin_dir_path(__FILE__) . 'debug.log';
            error_log(_wp_get_current_user()->user_login . " had no valid phone number make sure he enter billing_phone or custom user phone number" . PHP_EOL, 3, $pluginlog);
        }
    }
}


function add_zarincall_menu()
{

    $page_title = 'تنظیمات افزونه زرین کال ZarinCall';
    $menu_title = 'زرین کال';
    $capability = 'manage_options';
    $menu_slug  = 'zarincall-manage';
    $function   = 'zarincall_manage_options';
    $icon_url   =  plugin_dir_url(__FILE__) . "images/zarincall_icon.png";
    $position   = 4;

    add_menu_page(
        $page_title,
        $menu_title,
        $capability,
        $menu_slug,
        $function,
        $icon_url,
        $position
    );

    // Call register_zarincall_settings function to update database
    add_action('admin_init', 'register_zarincall_settings');
}

function register_zarincall_settings()
{
    register_setting('zarincall-settings', 'zarincall_api_key');
    register_setting('zarincall-settings', 'zarincall_wellcome_isEnable');
    register_setting('zarincall-settings', 'zarincall_wellcome_fileId');
    register_setting('zarincall-settings', 'zarincall_woocommrece_onOrderCompelete_isEnable');
    register_setting('zarincall-settings', 'zarincall_woocommrece_onOrderCompelete_fileId');
    register_setting('zarincall-settings', 'zarincall_woocommrece_add_phone_register_isEnable');
}

function zarincall_manage_options()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    //Get the active tab from the $_GET param
    $tab = null;
    if (isset($_GET['tab'])) {
        $tab = sanitize_text_field($_GET['tab']);
    }
?>
    <style>
        .zarincall__container {
            background-color: white;
            padding-bottom: 30px;
        }
    </style>
    <div class="wrap">
        <!-- Print the page title -->
        <img src="<?php echo plugin_dir_url(__FILE__) . "images/logo.png" ?>" alt="zarincall-logo">
        <!-- Here are our tabs -->
        <nav class="nav-tab-wrapper">
            <a href="?page=zarincall-manage" class="nav-tab <?php if ($tab === null) : ?>nav-tab-active<?php endif; ?>">معرفی افزونه</a>
            <a href="?page=zarincall-manage&tab=settings" class="nav-tab <?php if ($tab === "settings") : ?>nav-tab-active<?php endif; ?>">تنظیمات</a>
            <a href="?page=zarincall-manage&tab=install-guide" class="nav-tab <?php if ($tab === 'install-guide') : ?>nav-tab-active<?php endif; ?>">آموزش راه اندازی</a>
            <a href="?page=zarincall-manage&tab=log" class="nav-tab <?php if ($tab === 'log') : ?>nav-tab-active<?php endif; ?>">لاگ های برنامه</a>
            <a href="?page=zarincall-manage&tab=support" class="nav-tab <?php if ($tab === 'support') : ?>nav-tab-active<?php endif; ?>">پشتیبانی</a>
        </nav>

        <div class="tab-content">
            <?php switch ($tab):
                case 'install-guide':
                    zarinCallTabInstall();
                    break;
                case 'support':
                    zarinCallTabSupport();
                    break;
                case 'log':
                    zarinCallTablog();
                    break;
                case "settings":
                    zarinCallTabSettings();
                    break;
                default:
                    zarinCallTabAbout();
                    break;
            endswitch; ?>
        </div>
    </div>
<?php
}

//settings tab
function zarinCallTabSettings()
{
?>
    <div style="padding-right: 30px;padding-top: 20px;" class="zarincall__container">
        <form method="post" action="options.php">
            <?php settings_fields('zarincall-settings'); ?>
            <?php do_settings_sections('zarincall-settings'); ?>
            <table class="form-table">
                <?php
                if (!empty(get_option('zarincall_api_key'))) {
                    $response = zarinCallGetUserPulse();
                    if ($response != false) {
                        $obj = json_decode($response, false);
                        if ($obj->R_Success == true) {
                ?>
                            <tr>
                                <th colspan="4"><span style="color: green;">اعبار فعلی شما : <?php echo esc_attr($obj->Pulse) ?></span></th>
                            </tr>
                        <?php
                        } else {
                        ?>
                            <tr>
                                <th colspan="4"><span style="color: red;">خطایی در برقراری برقراری ارتباط با zarincall بوجود آمد علت خطا : <?php echo esc_attr($obj->R_Message) ?></span></th>
                            </tr>
                <?php
                        }
                    }
                }
                ?>
                <tr valign="top">
                    <th scope="row">api key :</th>
                    <td><input style="text-align: left;" type="text" class="regular-text" name="zarincall_api_key" value="<?php echo esc_attr(get_option('zarincall_api_key')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">ارسال پیام بعد از ثبت نام کاربر جدید</th>
                    <td><input type="checkbox" <?php if (get_option('zarincall_wellcome_isEnable') === "on") : ?>checked<?php endif; ?> name="zarincall_wellcome_isEnable" value="on"></td>
                </tr>
                <tr valign="top">
                    <th scope="row">شناسه فایل خوش آمد گویی</th>
                    <td><input style="text-align: left;" type="text" class="regular-text" name="zarincall_wellcome_fileId" value="<?php echo esc_attr(get_option('zarincall_wellcome_fileId')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th colspan="4">....................تنطیمات ووکامرس..........................</th>
                </tr>
                <?php
                //check if woocommerce installed and active or not
                if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                ?>
                    <tr valign="top">
                        <th scope="row">اضافه کردن فیلد شماره تلفن به ثبت نام ووکامرس</th>
                        <td><input type="checkbox" <?php if (get_option('zarincall_woocommrece_add_phone_register_isEnable') === "on") : ?>checked<?php endif; ?> name="zarincall_woocommrece_add_phone_register_isEnable" value="on"></td>
                    </tr>
                    <tr>
                        <td colspan="4"><small>در صورت فعال بودن این گزینه پیام به این شماره و در غیر این صورت پیام به شماره تفلن وارد شده در صورت حساب (billing_phone) ارسال میشود</small></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">ارسال پیام بعد از خرید موفق در ووکامرس</th>
                        <td><input type="checkbox" <?php if (get_option('zarincall_woocommrece_onOrderCompelete_isEnable') === "on") : ?>checked<?php endif; ?> name="zarincall_woocommrece_onOrderCompelete_isEnable" value="on"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">شناسه فایل برای ارسال بعد از خرید موفق در ووکامرس</th>
                        <td><input class="regular-text" style="text-align: left;" type="text" name="zarincall_woocommrece_onOrderCompelete_fileId" value="<?php echo esc_attr(get_option('zarincall_woocommrece_onOrderCompelete_fileId')); ?>" /></td>
                    </tr>
                <?php
                } else {
                ?>
                    <tr>
                        <th colspan="4"><span style="color: red;">درصورتی که به تنظیمات ووکامرس احتیاج دارید ابتدا آن را نصب و فعال نمایید</span></th>
                    </tr>
                <?php
                }
                ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}
//install tab
function zarinCallTabInstall()
{
?>
    <style>
        ol li {
            font-weight: bold;
            margin-bottom: 30px;
        }
    </style>
    <div style="padding-right: 30px;padding-top: 20px;padding-left: 30px;" class="zarincall__container">
        <h3 style="font-family: tahoma;">آموزش راه اندازی</h3>
        <ol>
            <li>برای راه اندازی ابتدا در سایت زین کال zarincall.ir ثبت نام بفرمایید</li>
            <li>وارد حساب کاربری خود شوید ApiKey ایجاد کرده و آن را در افزونه وارد کنید</li>
            <li>فایل های صوتی خود را ضبط کرده و در حساب کاربری زرین کال خود آپلود نمایید.</li>
            <li>پس از اپلود فایل ها شناسه فایل ها را در افزونه وارد نمایید.</li>
        </ol>
        <p>همچنین می توانید مراحل کار را از طریق ویدئوی زیر مشاهده بفرماید.</p>
        <style>
            .h_iframe-aparat_embed_frame {
                position: relative;
            }

            .h_iframe-aparat_embed_frame .ratio {
                display: block;
                width: 100%;
                height: auto;
            }

            .h_iframe-aparat_embed_frame iframe {
                top: 0;
                width: 100%;
                height: 500px;
            }
        </style>
        <div style="margin-bottom: 10px;text-align: center;"><span style="display: block;"></span><iframe style="width: 70%;height: 500px;" src="https://www.aparat.com/video/video/embed/videohash/eqwx8/vt/frame" title="با شاتل موبایل همه جا آنتن داری" allowFullScreen="true" webkitallowfullscreen="true" mozallowfullscreen="true"></iframe></div>
    </div>
<?php
}

//about tab
function zarinCallTabAbout()
{
?>

    <div style="padding-right: 30px;padding-top: 20px;" class="zarincall__container">
        <h3 style="font-family: tahoma;">معرفی امکانات افزونه</h3>
        <p>زرین کال یک دستیار تماس تلفنی است.</p>
        <p>این افزونه خوش آمد گویی زرین کال است.</p>
        <p>این افزونه برای سیستم وردپرس و ووکامرس نوشته شده است و می توانید به آسانی سایت یا فروشگاه خود از طریق این افزونه به دستیار تلنفی زرین کال ZarinCall.ir متصل نمایید.</p>
        <p>وقتی مشتری در سایت شما ثبت نام کرد تماس خوش آمدگویی با او برقرار می شود و شما می توانید محتوا این تماس صوتی را هر طور که می خواهید ضبط کنید یا حتی میتوانید در این تماس مشتری را راهنمایی نمایید.</p>
        <p>قابلیت دیگر این افزونه برقراری تماس پس از خرید است.</p>
        <p>اگر ووکامرس روی سایت شما نصب باشد می توانید این افزونه را تنظیم کنید تا پس از ثبت و پرداخت سفارش مشتری یک تماس صوتی با او برقرار شود و مثلا از او بابت خرید تشکر کنید یا به مشتری اعلام کنید که کالا یا محصول خریداری شده چه زمانی برای او ارسال می شود یا چطوری از آن استفاده کند.
            این باعث می شود مشتری شما احساس خوبی از سفارش خود داشته باشد و مشتری کاملا با برند شما آشنا شود.
        </p>
        <p>زرین کال ، دستیار تلفنی شما</p>
        <p>ZarinCall.ir</p>
    </div>
<?php
}

//log tab
function zarinCallTablog()
{
    $pluginlog = plugin_dir_path(__FILE__) . 'debug.log';
    $content = '';
    if (file_exists($pluginlog)) {
        $content = file_get_contents($pluginlog);
    }
?>
    <div style="padding-right: 30px;padding-top: 20px;padding-left: 30px;" class="zarincall__container">
        <h3 style="font-family: tahoma;">لاگ های برنامه</h3>
        <p>در این قسمت لاگ هایی که در صورت ایجاد خطا در پلاگین ایجاد شوند نشان داده میشوند</p>
        <textarea disabled style="width: 100%;text-align: left;" rows="15">
            <?php echo esc_textarea($content) ?>
        </textarea>
    </div>
<?php
}

//support tab
function zarinCallTabSupport()
{
?>
    <div style="padding-right: 30px;padding-top: 20px;" class="zarincall__container">
        <h3 style="font-family: tahoma;">پشتیبانی</h3>
        <p>در صورت نیاز به راهنمایی با ما تماس بگرید:</p>
        <p>جهت اطلاعات شماره تماس با ما از لینک اقدام بفرمایید.</p>
        <a target="__blank" href="https://zarincall.ir/contacts">تماس با ما</a>
    </div>
<?php
}

function zarincall_update_form($user)
{
?>
    <h3><?php _e("تنظیمات شماره تلفن"); ?></h3>
    <table class="form-table">
        <th><label for="zarincall_phone_number"><?php _e('شماره تلفن شما', 'zarincall') ?></label></th>
        <td><input type="text" name="zarincall_phone_number" id="zarincall_phone_number" class="input" value="<?php echo esc_attr(get_the_author_meta('zarincall_phone_number', $user->ID)); ?>" size="25" /></td>
    </table>
<?php

}

//for update phone number in normal wordpress
function zarincall_update_user($userid)
{
    if (!current_user_can('edit_user', $userid))
        return false;

    $phoneNumber = sanitize_text_field($_POST['zarincall_phone_number']);
    update_user_meta($userid, 'zarincall_phone_number', $phoneNumber);
}

//for error phone number update in normal wordpress
function zarincall_update_error($errors, $update, $user)
{
    if (!isset($_POST['zarincall_phone_number'])) {
        $errors->add('phone_number_error', __('<strong>خطا</strong>: لطفا شماره تلفن خود را وارد نمایید', 'zarincall'));
    } else {
        $phoneNumber = sanitize_text_field($_POST['zarincall_phone_number']);

        if (empty($phoneNumber)) {
            $errors->add('phone_number_error', __('<strong>خطا</strong>: لطفا شماره تلفن خود را وارد نمایید', 'zarincall'));
        }

        if (!preg_match("/^09[0-9]{9}$/", $phoneNumber)) {
            $errors->add('phone_number_error', __('<strong>خطا</strong>: شماره وارد شده معتبر نیست', 'zarincall'));
        }
    }
}

//register from for normal wordpress
function zarincall_register_form()
{
    $phone_number = (!empty(sanitize_text_field($_POST['zarincall_phone_number']))) ? sanitize_text_field($_POST['zarincall_phone_number']) : '';
?>
    <p>
        <label for="zarincall_phone_number"><?php _e('شماره تلفن شما', 'zarincall') ?><br />
            <input type="text" name="zarincall_phone_number" id="zarincall_phone_number" class="input" value="<?php echo esc_attr_e($phone_number); ?>" size="25" /></label>
    </p>
<?php
}

//register from save for normal wordpress
function zarincall_new_user_registered($user_id)
{

    $phoneNumber = sanitize_text_field($_POST['zarincall_phone_number']);

    if (!empty($phoneNumber)) {
        update_user_meta($user_id, 'zarincall_phone_number', $phoneNumber);

        //send wellcome Message here

        $phoneArray = [$phoneNumber];
        $fileId = get_option('zarincall_wellcome_fileId');

        zarinCallFromApi($fileId, $phoneArray);
    }
}

//register from validate for normal wordpress
function zarincall_registration_errors($errors, $sanitized_user_login, $user_email)
{
    if (!isset($_POST['zarincall_phone_number'])) {
        $errors->add('phone_number_error', __('<strong>خطا</strong>: لطفا شماره تلفن خود را وارد نمایید', 'zarincall'));
    } else {

        $phoneNumber = sanitize_text_field($_POST['zarincall_phone_number']);

        if (empty($phoneNumber)) {
            $errors->add('phone_number_error', __('<strong>خطا</strong>: لطفا شماره تلفن خود را وارد نمایید', 'zarincall'));
        }

        if (!preg_match("/^09[0-9]{9}$/", $phoneNumber)) {
            $errors->add('phone_number_error', __('<strong>خطا</strong>: شماره وارد شده معتبر نیست', 'zarincall'));
        }
    }

    return $errors;
}
