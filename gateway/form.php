<?php
require_once('../../../../wp-load.php');

$plugin = new JavaabuWoocommercePlugin();

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;
$secure_number = isset($_GET['secure_number']) ? $_GET['secure_number'] : null;
$pass = $plugin->verifyOrder($order_id, $secure_number);

if (! $pass) {
    wp_redirect(home_url());
}

$uploaded = null;
if (isset($_FILES['proof-of-payment'])) {
    $uploaded = $plugin->upload_proof_of_payment($order_id, $_FILES['proof-of-payment']);
    var_dump($uploaded);
    if ($uploaded) {
        $plugin->make_onhold($order_id);
    }
}

$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$theme_color = ! empty($plugin->get_option('bank')) ? $plugin->get_option('bank') : '#8b8b8b';
?>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title><?php echo $plugin->get_option( 'title' ); ?></title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body style="background-color: <?php echo $theme_color; ?>">
        <?php if($logo = $plugin->get_option('site_logo')): ?>
        <img class="site-logo" src="<?php echo $logo ?>" alt="Site logo">
        <?php endif; ?>

        <div class="body-wrapper">
            <form  method="POST" enctype="multipart/form-data">
                <div class="form-header">
                    <h1>Account Transfer & Slip Uploading</h1>
                    <p>Please transfer the bill total to the following bank account and upload the payment slip here.</p>
                </div>

                <div class="barcode">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($url) ?>" alt="">
                    <p>Scan the above QR code to view this page on another device</p>
                </div>

                <div class="form-body">
                    <dl>
                        <dt>Bank: </dt>
                        <dd><?php echo $plugin->get_option('bank') ?></dd>
                    </dl>

                    <dl>
                        <dt>Account Name: </dt>
                        <dd><?php echo $plugin->get_option('account_name') ?>
                            <a href="#" class="copy-btn" data-clipboard-text="<?php echo $plugin->get_option('account_name') ?>">
                                <img width="15px" src="img/copy.svg" alt="Copy">
                            </a>
                        </dd>
                    </dl>

                    <dl>
                        <dt>Account Number: </dt>
                        <dd><?php echo $plugin->get_option('account_number') ?>
                            <a href="#" class="copy-btn" data-clipboard-text="<?php echo $plugin->get_option('account_number') ?>">
                                <img width="15px" src="img/copy.svg" alt="Copy">
                            </a>
                        </dd>
                    </dl>

                    <dl>
                        <dt>Amount: </dt>
                        <dd><?php echo $plugin->order_total($order_id) ?></dd>
                    </dl>
                </div>

                <div class="custom-file-attach">
                    <a class="custom-attach-btn" style="border: 1px solid <?php echo $theme_color; ?>">
                        <img width="15px" src="img/attachment.svg" alt="File Icon" style="margin-right: 15px;"><span class="custom-file-name">Click Here Attach Slip</span>
                    </a>
                    <input class="custom-file-btn" type="file" name="proof-of-payment" required/>
                </div>

                <div class="form-footer">
                    <button class="btn" name="Action" value="Value" style="background-color: <?php echo $theme_color; ?>; color: white;">Complete Order</button>
                    <a href="<?php echo $plugin->order_return_url($order_id) ?>" class="btn">Cancel Order</a>
                </div>
            </form>
        </div>
        <p class="credit">Developed by <a href="https://javaabu.com">Javaabu</a></p>
        <script src="js/jquery-3.5.1.min.js"></script>
        <script src="js/clipboard.min.js"></script>
        <script>
            var clipboard = new ClipboardJS('.copy-btn');

            //custom file attaching
            $('.custom-file-attach').each(function (index, uploader) {
                var fake_btn = $(uploader).find('.custom-attach-btn');
                var actual_btn = $(uploader).find('.custom-file-btn');
                var file_name_display = $(uploader).find('.custom-file-name');

                fake_btn.click(function() {
                    actual_btn.click();
                });

                actual_btn.change(function() {
                    file_name_display.text(actual_btn[0].files[0].name);
                });
            });
        </script>
    </body>
</html>