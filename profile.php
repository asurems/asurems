<?php
/**
 * ASU-REMS — My Profile / Profile Picture
 * Save as: profile.php (project root, beside login.php and dashboard.php).
 * Requires the one-time users.profile_photo migration from the signup update.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile_avatar.php';
requireLogin();

$user = currentUser();
$userId = (int)($user['user_id'] ?? 0);
if ($userId < 1) {
    http_response_code(403);
    exit('Please sign in again.');
}
$pageTitle = 'My Profile | ASU-REMS';
$activePage = 'my_profile';
$role = (string)($user['role'] ?? '');
$initials = strtoupper(substr((string)($user['first_name'] ?? 'U'), 0, 1)
           . substr((string)($user['last_name'] ?? ''), 0, 1));

$escape = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$basePath = rtrim(BASE_URL, '/');
$destination = $basePath . '/dashboard.php';
$returnParam = trim((string)($_POST['return'] ?? $_GET['return'] ?? ''));
if ($returnParam !== '' && strlen($returnParam) <= 1500 && !preg_match('/[\r\n]/', $returnParam)) {
    $parts = parse_url($returnParam);
    if (is_array($parts) && empty($parts['host']) && empty($parts['scheme'])
        && str_starts_with((string)($parts['path'] ?? ''), $basePath . '/')
        && (string)($parts['path'] ?? '') !== $basePath . '/profile.php') {
        $destination = $returnParam;
    }
}

if (empty($_SESSION['rems_photo_csrf'])) {
    $_SESSION['rems_photo_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['rems_photo_csrf'];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session has expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') !== 'update_profile_picture') {
        $errors[] = 'Invalid profile action.';
    } elseif (!isset($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])
        || is_array($_FILES['profile_photo']['error'] ?? null)) {
        $errors[] = 'Choose one profile picture first.';
    } else {
        $file = $_FILES['profile_photo'];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'The profile picture is too large. Maximum size is 2 MB.'
                : 'Please choose a valid JPG, PNG, or WEBP photo.';
        } else {
            $tmp = (string)($file['tmp_name'] ?? '');
            $bytes = (int)($file['size'] ?? 0);
            if ($bytes < 1 || $bytes > 2 * 1024 * 1024 || !is_uploaded_file($tmp)) {
                $errors[] = 'Profile pictures must be 2 MB or smaller.';
            } else {
                $supported = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $mime = (string)((new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '');
                $dimensions = @getimagesize($tmp);
                if (!isset($supported[$mime]) || !is_array($dimensions)
                    || (string)($dimensions['mime'] ?? '') !== $mime
                    || (int)($dimensions[0] ?? 0) < 1 || (int)($dimensions[1] ?? 0) < 1
                    || (int)($dimensions[0] ?? 0) > 6000 || (int)($dimensions[1] ?? 0) > 6000) {
                    $errors[] = 'Select a valid JPG, PNG, or WEBP image (maximum 6000 × 6000 pixels).';
                }
            }
        }
        if (!$errors) {
            $newFile = '';
            try {
                $before = $pdo->prepare('SELECT profile_photo FROM users WHERE user_id = ? LIMIT 1');
                $before->execute([$userId]);
                $oldPhoto = (string)($before->fetchColumn() ?: '');

                $uploadDir = __DIR__ . '/uploads/profile_pictures';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    throw new RuntimeException('Could not create the profile-pictures folder.');
                }
                $rulesFile = $uploadDir . '/.htaccess';
                if (!is_file($rulesFile)) {
                    $rules = "Options -Indexes\n<FilesMatch \"\\.(php[0-9]*|phtml|phar|cgi|pl|py|sh)$\">\nRequire all denied\n</FilesMatch>\n";
                    if (file_put_contents($rulesFile, $rules, LOCK_EX) === false) {
                        throw new RuntimeException('Could not protect the profile-pictures folder.');
                    }
                }
                $name = bin2hex(random_bytes(16)) . '.' . $supported[$mime];
                $newFile = $uploadDir . '/' . $name;
                if (!move_uploaded_file($tmp, $newFile)) {
                    throw new RuntimeException('The picture could not be saved.');
                }
                @chmod($newFile, 0644);
                $relative = 'uploads/profile_pictures/' . $name;
                $update = $pdo->prepare('UPDATE users SET profile_photo = ? WHERE user_id = ?');
                $update->execute([$relative, $userId]);

                // Remove only the previous image generated by this feature.
                if (preg_match('~\Auploads/profile_pictures/[a-f0-9]{32}\.(?:jpg|png|webp)\z~D', $oldPhoto)) {
                    $oldFile = __DIR__ . '/' . $oldPhoto;
                    if ($oldFile !== $newFile && is_file($oldFile)) @unlink($oldFile);
                }
                $url = $basePath . '/profile.php?updated=1';
                if ($destination !== $basePath . '/dashboard.php') {
                    $url .= '&return=' . rawurlencode($destination);
                }
                header('Location: ' . $url, true, 303);
                exit;
            } catch (Throwable $e) {
                if ($newFile !== '' && is_file($newFile)) @unlink($newFile);
                error_log('ASU-REMS profile photo update failed: ' . $e->getMessage());
                $errors[] = 'Could not update the picture. Please check the database migration and upload-folder permissions.';
            }
        }
    }
}

$picture = remsProfilePictureRelativePath($pdo, $userId);
$pictureUrl = $picture !== '' ? $basePath . '/' . $picture : '';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<div class="main-page">
    <header class="asu-topbar rems-profile-topbar">
        <div class="topbar-left rems-profile-brandline">
            <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Toggle navigation"><i class="bi bi-list" aria-hidden="true"></i></button>
            <div class="rems-profile-brandcopy"><strong>Research &amp; Extension Management System</strong><span>Aklan State University</span></div>
        </div>
        <div class="topbar-user rems-profile-user">
            <div class="topbar-user-text"><strong><?= $escape(fullName($user)); ?></strong><span><?= $escape(roleLabel($role)); ?></span></div>
            <?= remsProfileAvatarHtml($pdo, $user, $initials ?: 'U', 'dashboard-avatar'); ?>
        </div>
    </header>

    <main class="page-content rems-profile-page">
        <div class="rems-profile-shell">
            <section class="rems-profile-heading" aria-label="Profile page heading">
                <div>
                    <span class="rems-profile-eyebrow">ACCOUNT SETTINGS</span>
                    <h1>My Profile</h1>
                    <p>Manage your account picture and view your profile details.</p>
                </div>

            </section>

            <section class="rems-profile-card" aria-labelledby="remsProfileHeading">
                <div class="rems-profile-card-title">
                    <div class="rems-profile-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></div>
                    <div>
                        <h2 id="remsProfileHeading">Profile Information</h2>
                        <p>Your account details in ASU-REMS</p>
                    </div>
                </div>

                <?php if (($_GET['updated'] ?? '') === '1'): ?>
                    <div class="alert alert-success rems-profile-message" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i> Your profile picture was updated successfully.</div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger rems-profile-message" role="alert">
                        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                        <div><?php foreach ($errors as $error): ?><div><?= $escape($error); ?></div><?php endforeach; ?></div>
                    </div>
                <?php endif; ?>

                <div class="rems-profile-identity">
                    <div class="rems-photo-preview-frame">
                        <?php if ($pictureUrl !== ''): ?>
                            <img id="remsProfilePreview" src="<?= $escape($pictureUrl); ?>" alt="Your current profile picture" class="rems-photo-preview" loading="eager">
                            <span id="remsProfilePreviewInitials" class="rems-photo-placeholder" hidden><?= $escape($initials ?: 'U'); ?></span>
                        <?php else: ?>
                            <img id="remsProfilePreview" src="" alt="Selected profile picture preview" class="rems-photo-preview" hidden>
                            <span id="remsProfilePreviewInitials" class="rems-photo-placeholder"><?= $escape($initials ?: 'U'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="rems-profile-identity-copy">
                        <h3><?= $escape(fullName($user)); ?></h3>
                        <span class="rems-profile-role"><i class="bi bi-person-badge" aria-hidden="true"></i> <?= $escape(roleLabel($role)); ?></span>
                        <div class="rems-profile-account-email"><i class="bi bi-envelope" aria-hidden="true"></i><span><?= $escape((string)($user['email'] ?? 'Email not available')); ?></span></div>
                    </div>
                    <div class="rems-profile-picture-caption"><i class="bi bi-shield-check" aria-hidden="true"></i> Account photo</div>
                </div>

                <div class="rems-profile-section-heading">
                    <h3>Profile Picture</h3>
                    <p>Update the photo displayed beside your name across the system.</p>
                </div>

                <form method="post" enctype="multipart/form-data" class="rems-profile-form" id="remsPhotoForm">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrf); ?>">
                    <input type="hidden" name="action" value="update_profile_picture">
                    <input type="hidden" name="return" value="<?= $escape($destination); ?>">

                    <div class="rems-photo-upload-field">
                        <div class="rems-upload-icon"><i class="bi bi-image" aria-hidden="true"></i></div>
                        <div class="rems-upload-description">
                            <strong>Choose a new photo</strong>
                            <span id="remsPhotoHelp">JPG, PNG, or WEBP · Maximum 2 MB</span>
                            <small id="remsPhotoFileName" aria-live="polite">No file selected</small>
                        </div>
                        <div class="rems-upload-choose">
                            <input id="remsPhotoInput" class="rems-photo-input" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" aria-describedby="remsPhotoHelp remsPhotoFileName" required>
                            <label for="remsPhotoInput" class="rems-profile-choose-btn"><i class="bi bi-upload" aria-hidden="true"></i> Choose Photo</label>
                        </div>
                    </div>
                    <p class="rems-upload-validation" id="remsPhotoFeedback" role="alert" hidden></p>

                    <div class="rems-profile-actions">
                        <a class="rems-profile-cancel" href="<?= $escape($destination); ?>">Cancel</a>
                        <button class="rems-profile-save" id="remsProfileSave" type="submit" disabled><i class="bi bi-check2-circle" aria-hidden="true"></i> Save Changes</button>
                    </div>
                </form>
            </section>
            <p class="rems-profile-footnote"><i class="bi bi-lock" aria-hidden="true"></i> Only you can update your account picture.</p>
        </div>
    </main>
</div>
<style>
/* ASU-REMS My Profile — minimal professional account settings; no animations. */
.rems-profile-topbar{height:76px!important;min-height:76px!important;padding:0 30px!important;display:flex!important;align-items:center!important;justify-content:space-between!important;background:#fff!important;border-bottom:1px solid rgba(0,122,51,.12)!important}
.rems-profile-brandline,.rems-profile-user{display:flex;align-items:center;gap:14px}.rems-profile-brandcopy{padding-left:18px;border-left:3px solid #007a33;line-height:1.2}.rems-profile-brandcopy strong{display:block;font-size:17px;font-weight:800;color:#101810}.rems-profile-brandcopy span{display:block;font-size:12px;color:#657068;margin-top:4px}.rems-profile-user .topbar-user-text{text-align:right}
.rems-profile-user .user-avatar{width:46px!important;min-width:46px!important;height:46px!important;border-radius:50%!important;background:#ffd51f!important;color:#003f1a!important;font-size:15px!important;font-weight:900!important;box-shadow:0 5px 14px rgba(0,80,36,.12)!important}
.rems-profile-page{min-height:calc(100vh - 76px);padding:28px 32px 35px!important;background:radial-gradient(circle at 90% 5%,rgba(255,213,31,.10),transparent 25%),radial-gradient(circle at 8% 7%,rgba(0,122,51,.07),transparent 27%),linear-gradient(135deg,#dff1e3 0%,#e8f0e3 34%,#f1eee2 68%,#f4e6d5 100%)!important;font-family:"Century Gothic","CenturyGothic",Arial,sans-serif;color:#17291e}
.main-page:has(> .rems-profile-page){background:linear-gradient(135deg,#dff1e3 0%,#e8f0e3 34%,#f1eee2 68%,#f4e6d5 100%)!important}
.rems-profile-shell{width:100%;max-width:none;margin:0 auto}
.rems-profile-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 18px}
.rems-profile-eyebrow{display:block;color:#007a33;font-size:10px;font-weight:800;letter-spacing:.09em}
.rems-profile-heading h1{color:#102b1c!important;font-size:28px;font-weight:800;line-height:1.2;margin:5px 0 5px;letter-spacing:-.025em}
.rems-profile-heading p{font-size:12px;margin:0;color:#65736a;line-height:1.5}
.rems-profile-back{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:39px;padding:0 14px;border:1px solid #d5e4da;border-radius:9px;background:#fff;color:#00672d!important;font-size:11px;font-weight:800;text-decoration:none!important;white-space:nowrap}
.rems-profile-back:hover,.rems-profile-back:focus-visible{border-color:#9ac4a7;background:#f2f8f3;color:#004e24!important}
.rems-profile-card{background:#fff;border:1px solid #e1eae3;border-top:3px solid #007a33;border-radius:16px;box-shadow:0 12px 32px rgba(20,62,34,.055);overflow:hidden}
.rems-profile-card-title{display:flex;align-items:center;gap:12px;min-height:76px;padding:16px 26px;border-bottom:1px solid #ebf0ec}
.rems-profile-icon{width:40px;min-width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:11px;background:#edf6ef;color:#007a33;font-size:20px}
.rems-profile-card-title h2{color:#173321;font-size:15px;font-weight:800;margin:0 0 3px}.rems-profile-card-title p{margin:0;font-size:10.5px;color:#758379}
.rems-profile-message{display:flex;align-items:flex-start;gap:9px;margin:18px 26px 0;padding:12px 14px;border-radius:10px;font-size:12px;line-height:1.5}
.rems-profile-message i{font-size:16px;line-height:1.25}
.rems-profile-identity{display:flex;align-items:center;gap:22px;margin:23px 26px 0;padding:21px 22px;background:#f8fbf8;border:1px solid #e7f0e9;border-radius:13px}
.rems-photo-preview-frame{display:flex;align-items:center;justify-content:center;width:106px;height:106px;flex:0 0 106px;overflow:hidden;border-radius:50%;border:4px solid #fff;outline:1px solid #deecdf;background:#ffd51f;box-shadow:0 4px 12px rgba(0,63,26,.07)}
.rems-photo-preview{display:block;width:100%;height:100%;object-fit:cover}.rems-photo-preview[hidden],.rems-photo-placeholder[hidden]{display:none!important}.rems-photo-placeholder{font-size:30px;font-weight:800;color:#003f1a}
.rems-profile-identity-copy{min-width:0;flex:1}.rems-profile-identity-copy h3{font-size:19px;font-weight:800;color:#123d25;margin:0 0 9px;line-height:1.25;overflow-wrap:anywhere}
.rems-profile-role{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:20px;color:#17643b;background:#e9f4ec;border:1px solid #d8e8dc;font-size:10.5px;font-weight:700}
.rems-profile-account-email{display:flex;align-items:center;gap:8px;margin-top:10px;font-size:11.5px;color:#6c7a71;overflow-wrap:anywhere}.rems-profile-account-email i{font-size:13px;color:#829087}
.rems-profile-picture-caption{margin-left:auto;display:inline-flex;align-items:center;gap:7px;align-self:flex-start;color:#657a6b;font-size:10px;font-weight:700;white-space:nowrap}
.rems-profile-picture-caption i{color:#008239;font-size:13px}
.rems-profile-section-heading{padding:25px 26px 16px}.rems-profile-section-heading h3{font-size:14px;font-weight:800;color:#1a3223;margin:0 0 5px}.rems-profile-section-heading p{font-size:11px;color:#758179;margin:0;line-height:1.5}
.rems-profile-form{padding:0 26px 24px}
.rems-photo-upload-field{display:flex;align-items:center;gap:14px;padding:17px 18px;border:1px solid #dbe7de;border-radius:11px;background:#fff}
.rems-upload-icon{width:41px;height:41px;min-width:41px;display:flex;align-items:center;justify-content:center;border:1px solid #dceadf;border-radius:10px;background:#f1f8f3;color:#007a33;font-size:19px}
.rems-upload-description{min-width:0;flex:1;display:flex;flex-direction:column;gap:4px}.rems-upload-description strong{color:#1c3425;font-size:12px;font-weight:800}.rems-upload-description span{color:#78847a;font-size:10.5px}.rems-upload-description small{font-size:10.5px;color:#55725d;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rems-upload-choose{position:relative;flex-shrink:0}.rems-photo-input{position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;top:50%;left:50%}
.rems-profile-choose-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:39px;padding:0 14px;border:1px solid #bddbc5;background:#fff;border-radius:9px;color:#00672d;font-size:11px;font-weight:800;cursor:pointer;margin:0;white-space:nowrap}
.rems-profile-choose-btn:hover{background:#f0f8f2;border-color:#7db58d}.rems-photo-input:focus-visible+.rems-profile-choose-btn{outline:3px solid rgba(0,122,51,.3);outline-offset:2px}
.rems-upload-validation{margin:8px 2px 0;color:#a62b2b;font-size:11px}.rems-upload-validation[hidden]{display:none}
.rems-profile-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding-top:20px;margin-top:19px;border-top:1px solid #edf1ed}
.rems-profile-actions>*{display:inline-flex;align-items:center;justify-content:center;gap:7px;border-radius:9px;min-height:41px;padding:0 18px;font-size:11px;font-weight:800;text-decoration:none!important}
.rems-profile-cancel{border:1px solid #d4e4da;background:#fff;color:#2c6b43!important}.rems-profile-cancel:hover{background:#f6faf7;color:#184d2d!important}
.rems-profile-save{border:1px solid #007a33;background:#007a33;color:#fff;cursor:pointer}.rems-profile-save:hover:not(:disabled){background:#005d29;border-color:#005d29}.rems-profile-save:disabled{opacity:.52;cursor:not-allowed}
.rems-profile-footnote{display:flex;align-items:center;justify-content:center;gap:7px;color:#7d887f;font-size:10px;margin:16px 0 0}.rems-profile-footnote i{color:#64816c}
.rems-profile-page *{animation:none!important;transition:none!important}
@media(max-width:760px){.rems-profile-page{padding:20px 16px 28px!important}.rems-profile-topbar{padding:0 16px!important}.rems-profile-user .topbar-user-text{display:none}.rems-profile-brandcopy strong{font-size:13px}.rems-profile-brandcopy span{font-size:10px}.rems-profile-heading h1{font-size:25px}.rems-profile-card-title{padding:15px 18px}.rems-profile-identity{margin:17px 18px 0;padding:18px;gap:15px;flex-wrap:wrap}.rems-photo-preview-frame{width:89px;height:89px;flex-basis:89px}.rems-profile-identity-copy h3{font-size:16px}.rems-profile-picture-caption{display:none}.rems-profile-section-heading{padding:22px 18px 14px}.rems-profile-form{padding:0 18px 20px}}
@media(max-width:520px){.rems-profile-heading{align-items:flex-start;gap:9px}.rems-profile-heading p{font-size:10.5px}.rems-profile-back{padding:0 10px}.rems-profile-identity{flex-direction:column;align-items:flex-start}.rems-profile-identity-copy{width:100%}.rems-photo-upload-field{flex-wrap:wrap;padding:14px}.rems-upload-description{flex-basis:calc(100% - 62px)}.rems-upload-choose{width:100%}.rems-profile-choose-btn{width:100%}.rems-profile-actions>*{flex:1}.rems-profile-card-title h2{font-size:14px}}

/* ASU-REMS My Profile — green-accent account heading (design only). */
.rems-profile-page .rems-profile-heading{
    position:relative;
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:space-between;
    min-height:118px;
    padding:23px 29px;
    margin-bottom:18px;
    border-radius:17px;
    background:linear-gradient(112deg,#005c26 0%,#007a33 67%,#289638 100%);
    box-shadow:0 14px 30px rgba(0,87,38,.13);
}
.rems-profile-page .rems-profile-heading::after{
    content:"";
    position:absolute;
    right:-36px;
    top:-106px;
    width:300px;
    height:265px;
    border-radius:48px;
    transform:rotate(-15deg);
    background:linear-gradient(135deg,rgba(255,213,31,.14),rgba(255,255,255,.045));
    pointer-events:none;
}
.rems-profile-page .rems-profile-heading > *{position:relative;z-index:1}
.rems-profile-page .rems-profile-eyebrow{color:#fce991!important;font-size:10px;letter-spacing:.09em}
.rems-profile-page .rems-profile-heading h1{color:#fff!important;font-size:29px;margin:6px 0 5px}
.rems-profile-page .rems-profile-heading p{color:rgba(255,255,255,.9)!important;font-size:11.5px}
.rems-profile-page .rems-profile-back{
    background:#ffd51f!important;
    color:#003f1a!important;
    border:1px solid #ffd51f!important;
    border-radius:10px;
    min-height:40px;
    box-shadow:none;
}
.rems-profile-page .rems-profile-back:hover,
.rems-profile-page .rems-profile-back:focus-visible{
    background:#ffe262!important;
    color:#003f1a!important;
    border-color:#ffe262!important;
}
@media(max-width:760px){
    .rems-profile-page .rems-profile-heading{min-height:110px;padding:20px 21px}
    .rems-profile-page .rems-profile-heading h1{font-size:26px}
}
@media(max-width:520px){
    .rems-profile-page .rems-profile-heading{min-height:105px;padding:17px;gap:12px}
    .rems-profile-page .rems-profile-heading h1{font-size:23px}
    .rems-profile-page .rems-profile-heading p{font-size:10px;max-width:235px}
    .rems-profile-page .rems-profile-back{min-height:36px;padding:0 10px}
}


/* ============================================================
   ASU-REMS MY PROFILE — DARK MODE ONLY
   Complements includes/rems_theme.php without altering the
   profile photo upload workflow or the existing light design.
   ============================================================ */
html[data-rems-theme="dark"] body .main-page:has(> .rems-profile-page) {
    background:#0b1d15!important;
}
html[data-rems-theme="dark"] body .main-page > header.rems-profile-topbar {
    background:#11271b!important;
    border-bottom-color:#365544!important;
    box-shadow:none!important;
}
html[data-rems-theme="dark"] body .rems-profile-topbar .rems-profile-brandcopy strong,
html[data-rems-theme="dark"] body .rems-profile-topbar .topbar-user-text strong {
    color:#edf5ee!important;
}
html[data-rems-theme="dark"] body .rems-profile-topbar .rems-profile-brandcopy span,
html[data-rems-theme="dark"] body .rems-profile-topbar .topbar-user-text span {
    color:#bfd4c4!important;
}
html[data-rems-theme="dark"] body .rems-profile-page {
    color:#edf5ee!important;
    background:#0b1d15!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-card {
    background:#152e21!important;
    border-color:#355d43!important;
    border-top-color:#238a4c!important;
    box-shadow:0 12px 32px rgba(0,0,0,.15)!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-card-title {
    background:#183627!important;
    border-bottom-color:#385744!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-card-title h2,
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-identity-copy h3,
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-section-heading h3,
html[data-rems-theme="dark"] body .rems-profile-page .rems-upload-description strong {
    color:#f0f7ef!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-card-title p,
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-section-heading p,
html[data-rems-theme="dark"] body .rems-profile-page .rems-upload-description span,
html[data-rems-theme="dark"] body .rems-profile-page .rems-upload-description small,
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-account-email,
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-account-email i,
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-picture-caption,
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-footnote {
    color:#bfd2c2!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-identity {
    background:#1c3a2a!important;
    border-color:#3b634a!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-role {
    color:#d6f3dd!important;
    background:#25533a!important;
    border-color:#407856!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-picture-caption i,
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-footnote i {
    color:#87cfa1!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-photo-upload-field {
    background:#193527!important;
    border-color:#3b6149!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-icon,
html[data-rems-theme="dark"] body .rems-profile-page .rems-upload-icon {
    background:#254c36!important;
    color:#a6e5ba!important;
    border-color:#3b7050!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-photo-preview-frame {
    outline-color:#466d51!important;
    border-color:#dceadc!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-choose-btn,
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-cancel {
    background:#244b35!important;
    color:#eef7ef!important;
    border-color:#52745a!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-choose-btn:hover,
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-cancel:hover {
    background:#2b5a40!important;
    color:#ffffff!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-actions {
    border-top-color:#395a43!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-save:not(:disabled) {
    background:#008841!important;
    border-color:#008841!important;
    color:#ffffff!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-message.alert-success {
    background:#204d33!important;
    border-color:#3c7651!important;
    color:#e0f5e5!important;
}
html[data-rems-theme="dark"] body .rems-profile-page .rems-profile-message.alert-danger {
    background:#4b2628!important;
    border-color:#8d474a!important;
    color:#ffe8e8!important;
}


/* =========================================================
   TOPBAR PHOTO — MATCH DASHBOARD.PHP EXACTLY
   Same 46px circular avatar and single soft dashboard shadow;
   NO outer yellow ring, custom halo, scaling, or animation.
   All other page styles and interactive controls are unchanged.
   ========================================================= */
html body .main-page > header.asu-topbar .dashboard-avatar {
    box-sizing: border-box !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex: 0 0 46px !important;
    width: 46px !important;
    height: 46px !important;
    min-width: 46px !important;
    padding: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 50% !important;
    background: #ffd51f !important;
    color: #003f1a !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    box-shadow: 0 8px 18px rgba(255, 213, 31, .25) !important;
    animation: none !important;
    transition: none !important;
    transform: none !important;
}
html body .main-page > header.asu-topbar .dashboard-avatar img {
    display: block !important;
    width: 100% !important;
    height: 100% !important;
    max-width: none !important;
    max-height: none !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 50% !important;
    object-fit: cover !important;
}
@media (max-width: 480px) {
    html body .main-page > header.asu-topbar .dashboard-avatar {
        flex: 0 0 40px !important;
        width: 40px !important;
        height: 40px !important;
        min-width: 40px !important;
    }
}
</style>
<script>
(() => {
    const input = document.getElementById('remsPhotoInput');
    const preview = document.getElementById('remsProfilePreview');
    const fallback = document.getElementById('remsProfilePreviewInitials');
    const filename = document.getElementById('remsPhotoFileName');
    const feedback = document.getElementById('remsPhotoFeedback');
    const save = document.getElementById('remsProfileSave');
    const form = document.getElementById('remsPhotoForm');
    if (!input || !preview || !form) return;
    let objectUrl = null;
    const originalSrc = preview.getAttribute('src') || '';
    const originallyHidden = preview.hidden;
    const originallyFallbackHidden = fallback ? fallback.hidden : true;

    function setError(message) {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.hidden = !message;
    }
    function restorePreview() {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        preview.src = originalSrc;
        preview.hidden = originallyHidden;
        if (fallback) fallback.hidden = originallyFallbackHidden;
        if (filename) filename.textContent = 'No file selected';
        if (save) save.disabled = true;
    }
    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        setError('');
        if (!file) { restorePreview(); return; }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size < 1 || file.size > 2 * 1024 * 1024) {
            input.value = '';
            restorePreview();
            setError('Please choose a JPG, PNG, or WEBP picture up to 2 MB.');
            return;
        }
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        preview.src = objectUrl;
        preview.hidden = false;
        if (fallback) fallback.hidden = true;
        if (filename) filename.textContent = file.name;
        if (save) save.disabled = false;
    });
    form.addEventListener('submit', (event) => {
        if (!input.files || !input.files.length) {
            event.preventDefault();
            setError('Choose a profile picture before saving.');
        }
    });
    window.addEventListener('pagehide', () => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
