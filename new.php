<?php
if (!defined('BASE_DIR')) {
    http_response_code(403);
    exit;
}

// cek apakah super admin atau tidak
// hanya super admin yang bisa menambah user baru
$login = new \login();
$userLogin = $login->cek_login();
if (!$userLogin || !is_admin()) {
    include_once 'views/403.php';
    exit;
}

$data = $_POST;
$error = [];
if (!empty($data)) {
    $gda  = new \gdauth();
    $gda->insert($data);
    $error = $gda->get_errors();
}
?>
<div class="row py-3">
    <div class="col-12">
        <h1 class="h4 mb-3">New Google Drive Account</h1>
        <?php
        if (!empty($data)) {
            if (!empty($error)) {
                $alert = '<div class="alert alert-danger">';
                $cError = count($error);
                $i = 0;
                foreach ($error as $err) {
                    $alert .= '<i class="fa fa-exclamation-circle"></i><span class="ml-2">' . $err . '</span>';
                    if ($i < ($cError - 1)) $alert .= '<br>';
                    $i++;
                }
                $alert .= '</div>';
            } else {
                $alert = '<div class="alert alert-success"><i class="fa fa-check"></i><span class="ml-2">New Google Account added successfully.</span></div>';
            }
            echo $alert;
        }
        ?>
        <form action="./admin.php?go=gdrive_accounts/new" method="post" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-12 col-md-6">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" class="form-control" maxlength="100" required>
                        <div class="invalid-feedback">Must be valid!</div>
                    </div>
                    <div class="form-group">
                        <label for="api_key">API Key</label>
                        <input type="text" name="api_key" id="api_key" class="form-control" maxlength="50" required>
                        <div class="invalid-feedback">Must be filled!</div>
                    </div>
                    <div class="form-group">
                        <label for="client_id">Client ID</label>
                        <input type="text" name="client_id" id="client_id" class="form-control" maxlength="100" required>
                        <div class="invalid-feedback">Must be filled!</div>
                    </div>
                    <div class="form-group">
                        <label for="client_secret">Client Secret</label>
                        <input type="text" name="client_secret" id="client_secret" class="form-control" maxlength="50" required>
                        <div class="invalid-feedback">Must be filled!</div>
                    </div>
                    <div class="form-group">
                        <label for="refresh_token">Refresh Token</label>
                        <textarea name="refresh_token" id="refresh_token" class="form-control" maxlength="150" required></textarea>
                        <div class="invalid-feedback">Must be filled!</div>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="custom-select" required>
                            <option value="0">Inactive</option>
                            <option value="1">Active</option>
                        </select>
                        <div class="invalid-feedback">Must choose one!</div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <button type="button" class="btn btn-secondary" onclick="location.href='admin.php?go=gdrive_accounts'">
                        <i class="fa fa-arrow-left mr-2"></i>
                        <span>Back</span>
                    </button>
                    <button name="simpan" type="submit" class="btn btn-success">
                        <i class="fa fa-save mr-2"></i>
                        <span>Save</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
