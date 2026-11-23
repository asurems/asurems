<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/activity_logger.php';

/*
 * Login and registration destinations.
 * All roles open the dashboard after login or signup.
 */
function authDestination(string $role): string {
    return BASE_URL . '/dashboard.php';
}

if (isset($_SESSION['user_id'])) {
    // Validate activity/account status before redirecting an existing session.
    require_once __DIR__ . '/includes/auth.php';
    requireLogin();
    $destination = authDestination((string)($_SESSION['role'] ?? ''));
    session_write_close();
    redirect($destination);
}

$view = $_GET['view'] ?? 'welcome';
if (!in_array($view, ['welcome', 'login', 'signup'], true)) {
    $view = 'welcome';
}

$errors = [];
$success = '';
$flash = getFlash();

// The existing Research Management module requires the exact role 'faculty'.
$allowedRoles = [
    'faculty' => 'Faculty / Researcher',
    'extension_coordinator' => 'Extensionist',
];
$titles = ['Mr.', 'Ms.', 'Mrs.', 'Mx.','Prof.', 'Dr.' ];
$extensions = ['', 'Jr.', 'Sr.', 'III'];
$civilStatuses = ['Single', 'Married', 'Widow', 'Separated'];
$genderOptions = ['Man', 'Woman', 'LGBTQ+', 'Prefer not to say'];

function posted(string $key): string {
    return trim((string)($_POST[$key] ?? ''));
}

/**
 * Validate an optional signup avatar on the server.
 * Images are stored using a random filename, never the user-supplied name.
 */
function validateSignupProfilePhoto(array &$errors): ?array {
    if (!isset($_FILES['profile_photo'])) {
        return null;
    }
    $file = $_FILES['profile_photo'];
    if (!is_array($file) || is_array($file['error'] ?? null)) {
        $errors[] = 'Please choose one profile picture only.';
        return null;
    }

    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        $errors[] = in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'Profile picture is too large. Maximum size: 2 MB.'
            : 'Profile picture upload failed. Please select the image again.';
        return null;
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 2 * 1024 * 1024 || !is_uploaded_file($tmpName)) {
        $errors[] = 'Choose a JPG, PNG, or WEBP image up to 2 MB.';
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $imageInfo = @getimagesize($tmpName);
    if (!isset($allowedMimeTypes[$mime]) || !is_array($imageInfo) ||
        ($imageInfo['mime'] ?? '') !== $mime ||
        ($imageInfo[0] ?? 0) < 1 || ($imageInfo[1] ?? 0) < 1 ||
        ($imageInfo[0] ?? 0) > 6000 || ($imageInfo[1] ?? 0) > 6000) {
        $errors[] = 'Please select a valid JPG, PNG, or WEBP picture.';
        return null;
    }

    return [
        'temporary_path' => $tmpName,
        'extension' => $allowedMimeTypes[$mime],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $view = $action === 'signup' ? 'signup' : 'login';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session expired. Refresh and try again.';
    } elseif ($action === 'login') {
        $login = posted('login');
        $password = (string)($_POST['password'] ?? '');

        if ($login === '' || $password === '') {
            $errors[] = 'Enter your email/username and password.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid email/username or password.';
            } elseif ($user['status'] !== 'Active') {
                $errors[] = 'Your account is inactive. Please contact the administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['last_activity'] = time();
                $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')
                    ->execute([$user['user_id']]);

                // Save a NEW history row on EVERY successful sign-in.
                // This is an INSERT, so previous logins are never overwritten.
                remsLogSuccessfulLogin($pdo, $user);

                // Persist the new session before the destination page starts.
                session_write_close();
                redirect(authDestination((string)$user['role']));
            }
        }
    } elseif ($action === 'signup') {
        $fields = [
            'email', 'title', 'first_name', 'middle_name', 'last_name',
            'extension', 'birth_date', 'civil_status', 'gender', 'citizenship',
            'country', 'region', 'province', 'city', 'barangay',
            'street_address', 'zip_code', 'contact_number', 'role'
        ];
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = posted($field);
        }
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $required = [
            'email', 'title', 'first_name', 'last_name', 'birth_date', 'civil_status',
            'gender', 'citizenship', 'country', 'region', 'province', 'city',
            'barangay', 'street_address', 'zip_code', 'contact_number', 'role'
        ];
        foreach ($required as $key) {
            if ($data[$key] === '') {
                $errors[] = 'Complete all required registration fields.';
                break;
            }
        }
        if ($password === '' || $confirm === '') $errors[] = 'Enter and confirm your password.';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 190) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errors[] = 'Password must contain 8 to 72 characters.';
        }
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';
        if (!isset($allowedRoles[$data['role']])) $errors[] = 'Choose a valid role.';
        if (!in_array($data['title'], $titles, true) ||
            !in_array($data['extension'], $extensions, true) ||
            !in_array($data['civil_status'], $civilStatuses, true) ||
            !in_array($data['gender'], $genderOptions, true)) {
            $errors[] = 'Choose valid personal information options.';
        }
        $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $data['birth_date']);
        if (!$birth || $birth->format('Y-m-d') !== $data['birth_date'] ||
            $birth > new DateTimeImmutable('today')) {
            $errors[] = 'Enter a valid birth date.';
        }
        if (!preg_match('/^[0-9+()\s-]{7,25}$/', $data['contact_number'])) {
            $errors[] = 'Enter a valid contact number.';
        }
        foreach ($data as $key => $value) {
            if (mb_strlen($value) > 190) {
                $errors[] = 'One or more fields are too long.';
                break;
            }
        }

        // Profile picture is optional, but any selected file must pass validation.
        $profilePhotoUpload = validateSignupProfilePhoto($errors);

        if (!$errors) {
            $savedProfilePhoto = null;
            try {
                $pdo->beginTransaction();
                $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
                $check->execute([$data['email']]);
                if ($check->fetch()) {
                    $errors[] = 'This email address is already registered.';
                } else {
                    // Existing ASU-REMS login supports username or email.
                    // Generate a stable unique username from email with collision checking.
                    $prefix = preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $data['email'])[0]));
                    $prefix = substr($prefix ?: 'user', 0, 18);
                    do {
                        $username = $prefix . bin2hex(random_bytes(4));
                        $checkName = $pdo->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
                        $checkName->execute([$username]);
                    } while ($checkName->fetch());

                    $fullName = trim(implode(' ', array_filter([
                        $data['first_name'], $data['middle_name'], $data['last_name'], $data['extension']
                    ])));
                    $available = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
                    if ($profilePhotoUpload !== null && !in_array('profile_photo', $available, true)) {
                        throw new RuntimeException('Missing users.profile_photo column. Apply the photo migration before uploading pictures.');
                    }
                    $userData = [
                        'username' => $username,
                        'email' => $data['email'],
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $data['role'],
                        // Only Faculty/Researcher and Extensionist can register.
                        'status' => 'Active',
                    ];
                    foreach (['full_name' => $fullName, 'name' => $fullName,
                              'first_name' => $data['first_name'],
                              'middle_name' => $data['middle_name'],
                              'last_name' => $data['last_name']] as $field => $value) {
                        if (in_array($field, $available, true)) $userData[$field] = $value;
                    }
                    if (in_array('created_at', $available, true)) {
                        $userData['created_at'] = date('Y-m-d H:i:s');
                    }
                    $columns = implode(', ', array_map(fn($key) => "`$key`", array_keys($userData)));
                    $markers = implode(', ', array_fill(0, count($userData), '?'));
                    $pdo->prepare("INSERT INTO users ($columns) VALUES ($markers)")
                        ->execute(array_values($userData));
                    $userId = (int)$pdo->lastInsertId();

                    // Requires the accompanying one-time SQL migration.
                    $profile = $pdo->prepare('INSERT INTO user_registration_profiles
                        (user_id, title, first_name, middle_name, last_name, name_extension,
                         birth_date, civil_status, gender, citizenship, country, region, province,
                         city, barangay, street_address, zip_code, contact_number, requested_role)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $profile->execute([
                        $userId, $data['title'], $data['first_name'], $data['middle_name'],
                        $data['last_name'], $data['extension'], $data['birth_date'],
                        $data['civil_status'], $data['gender'], $data['citizenship'],
                        $data['country'], $data['region'], $data['province'], $data['city'],
                        $data['barangay'], $data['street_address'], $data['zip_code'],
                        $data['contact_number'], $data['role']
                    ]);
                    // Store the optional avatar and its relative path on the user record.
                    // Keeping the DB update inside this transaction allows rollback on failure.
                    if ($profilePhotoUpload !== null) {
                        $photoDirectory = __DIR__ . '/uploads/profile_pictures';
                        if (!is_dir($photoDirectory) &&
                            !mkdir($photoDirectory, 0755, true) &&
                            !is_dir($photoDirectory)) {
                            throw new RuntimeException('Could not create the profile picture folder.');
                        }

                        // Disable directory listings and script execution for this upload folder.
                        $htaccessPath = $photoDirectory . '/.htaccess';
                        if (!is_file($htaccessPath)) {
                            $rules = "Options -Indexes\n<FilesMatch \"\\.(php[0-9]*|phtml|phar|cgi|pl|py|sh)$\">\nRequire all denied\n</FilesMatch>\n";
                            if (file_put_contents($htaccessPath, $rules, LOCK_EX) === false) {
                                throw new RuntimeException('Could not protect the profile picture folder.');
                            }
                        }

                        $photoName = bin2hex(random_bytes(16)) . '.' . $profilePhotoUpload['extension'];
                        $savedProfilePhoto = $photoDirectory . '/' . $photoName;
                        if (!move_uploaded_file($profilePhotoUpload['temporary_path'], $savedProfilePhoto)) {
                            throw new RuntimeException('Could not save the profile picture.');
                        }

                        $relativePhotoPath = 'uploads/profile_pictures/' . $photoName;
                        $updatePhoto = $pdo->prepare('UPDATE users SET profile_photo = ? WHERE user_id = ?');
                        $updatePhoto->execute([$relativePhotoPath, $userId]);
                    }
                    // Initialize last-login while registration is still transactional.
                    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')
                        ->execute([$userId]);
                    $pdo->commit();

                    // Signup automatically signs the new user in; record that first session.
                    remsLogSuccessfulLogin($pdo, [
                        'user_id' => $userId,
                        'first_name' => $data['first_name'],
                        'middle_name' => $data['middle_name'],
                        'last_name' => $data['last_name'],
                        'email' => $data['email'],
                        'role' => $data['role'],
                        'username' => $username,
                    ]);

                    // New users get a new user_id and their own private records.
                    // Start a session immediately after successful registration.
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['role'] = $data['role'];
                    $_SESSION['username'] = $username;
                    $_SESSION['last_activity'] = time();

                    // All roles go to the dashboard after signup.
                    session_write_close();
                    redirect(authDestination($data['role']));
                }
                if ($pdo->inTransaction()) $pdo->rollBack();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($savedProfilePhoto !== null && is_file($savedProfilePhoto)) {
                    @unlink($savedProfilePhoto);
                }
                error_log('ASU-REMS registration: ' . $exception->getMessage());
                $errors[] = 'Registration could not be completed. Check the profile picture setup and database migration.';
            }
        }
    } else {
        $errors[] = 'Invalid action.';
    }
}

$pageTitle = 'ASU-REMS Portal';
require __DIR__ . '/includes/header.php';
?>
<?php require_once __DIR__ . '/includes/rems_theme.php'; ?>
<style>
:root{--g950:#022b13;--g900:#043b1c;--g800:#075a29;--g700:#087b37;--yellow:#f4ca24;--ink:#17201a;--muted:#738078;--border:#dfe7e1}
*{box-sizing:border-box}
html,body{margin:0;width:100%;height:100%}
body{overflow:hidden;background:#f8faf8;color:var(--ink);font-family:"Century Gothic",Arial,sans-serif}
.auth-page{display:grid;grid-template-columns:44% 56%;height:100vh;height:100dvh;overflow:hidden}
.auth-left{position:relative;isolation:isolate;overflow:hidden;background:radial-gradient(circle at 18% 12%,rgba(255,255,255,.12),transparent 27%),linear-gradient(145deg,var(--g950),var(--g800) 65%,var(--g700))}
.auth-left:before{content:"";position:absolute;z-index:-2;width:720px;height:125px;left:-260px;top:38%;transform:rotate(-39deg);border-radius:35px;background:linear-gradient(90deg,rgba(244,202,36,.22),rgba(255,255,255,.08))}
.auth-left:after{content:"";position:absolute;z-index:-2;width:550px;height:550px;right:-340px;bottom:-270px;border:85px solid rgba(255,255,255,.055);border-radius:50%}
.brand-ribbon{position:absolute;z-index:-2;width:650px;height:120px;left:-100px;top:56%;transform:rotate(-39deg);border-radius:30px;background:linear-gradient(90deg,rgba(255,255,255,.055),rgba(244,202,36,.11))}
.left-content{position:relative;z-index:1;display:flex;flex-direction:column;height:100%;padding:clamp(28px,4vw,58px) clamp(30px,5vw,76px)}
.brand-row{display:flex;align-items:center;gap:18px}
.brand-mark{height:68px;width:68px;flex:0 0 68px;display:grid;place-items:center;border-radius:20px;background:var(--yellow);color:var(--g950);font-size:32px}
.brand-copy strong{display:block;color:#fff;font-size:25px;font-weight:900}.brand-copy small{display:block;margin-top:5px;color:rgba(255,255,255,.75);font-size:14px}
.brand-hero{margin:auto 0;padding:20px 0}.brand-hero h1{margin:0;color:#fff;font-size:clamp(45px,4.8vw,76px);line-height:1.03;font-weight:900;letter-spacing:-.055em}.brand-hero h1 span{display:block;white-space:nowrap}.brand-hero .yellow{color:var(--yellow)}
.auth-right{min-width:0;min-height:0;height:100%;display:flex;align-items:center;justify-content:center;padding:26px 45px;overflow:hidden;background:radial-gradient(circle at 88% 10%,rgba(244,202,36,.13),transparent 29%),linear-gradient(135deg,#dff1e3,#e8f0e3 36%,#f1eee2 68%,#f4e6d5)}
.auth-right.signup-panel{overflow-y:auto;overscroll-behavior:contain;align-items:flex-start;justify-content:center;scrollbar-width:thin;scrollbar-color:#9dbbac transparent}
.auth-form-wrap{width:min(100%,540px);margin:auto 0}.signup-panel .auth-form-wrap{margin:0 auto;padding:12px 0 32px}
.form-top{margin-bottom:24px}.form-icon{height:78px;width:78px;display:grid;place-items:center;border-radius:23px;background:linear-gradient(145deg,var(--g700),var(--g900));color:var(--yellow);font-size:33px;box-shadow:0 15px 32px rgba(8,123,55,.18)}
.form-eyebrow{margin:0 0 10px;color:var(--g700);font-size:14px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}.form-heading{margin:0 0 28px;color:var(--ink);font-size:clamp(43px,4vw,58px);font-weight:900;line-height:1.08;letter-spacing:-.045em}
.action-group{display:grid;gap:17px}.action-button{width:100%;min-height:62px;display:inline-flex;align-items:center;justify-content:center;gap:12px;padding:13px 22px;border-radius:17px;font-size:16px;font-weight:850;text-decoration:none;cursor:pointer;transition:.18s ease}.action-button i{font-size:19px}.action-button:hover{transform:translateY(-2px)}
.primary-button{border:0;background:linear-gradient(135deg,var(--g700),var(--g900));color:#fff;box-shadow:0 14px 28px rgba(8,123,55,.17)}.primary-button:hover{color:#fff;box-shadow:0 18px 36px rgba(8,123,55,.23)}.secondary-button{border:1px solid #d0dfd4;background:rgba(255,255,255,.88);color:var(--g800)}.secondary-button:hover{color:var(--g900);background:#fff}
.back-link{display:inline-flex;align-items:center;gap:10px;width:fit-content;margin-top:23px;padding:5px 0;color:var(--g700);font-size:15px;font-weight:750;text-decoration:none}.back-link:hover{color:var(--g900);text-decoration:underline}
.field-group{margin-bottom:17px}.field-label{display:block;margin:0 0 8px 2px;color:#344039;font-size:14px;font-weight:800}.field-control{position:relative}.field-control>i{position:absolute;top:50%;left:18px;transform:translateY(-50%);color:#8b9790;font-size:18px;pointer-events:none}.field-control input,.field-control select{width:100%;height:57px;padding:0 48px;border:1px solid var(--border);border-radius:16px;outline:none;background:rgba(255,255,255,.95);color:#1d2720;font:inherit;font-size:14px;transition:.18s ease}.field-control input:focus,.field-control select:focus{border-color:rgba(8,123,55,.55);box-shadow:0 0 0 4px rgba(8,123,55,.09)}.field-control input::placeholder{color:#a1aaa4}
/* Keep the password eye at the exact vertical center, in both states. */
.field-control:has(> .password-toggle) > input{display:block;padding-right:58px}
.field-control > .password-toggle{
    position:absolute;
    top:0;
    bottom:0;
    right:10px;
    margin:auto 0;
    transform:none !important;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:38px;
    height:38px;
    padding:0;
    line-height:1;
    border:0;
    border-radius:10px;
    background:transparent;
    color:#86928a;
    font-size:17px;
    cursor:pointer;
}
.field-control > .password-toggle i{display:block;line-height:1;pointer-events:none}
.field-control > .password-toggle:hover{background:#eef5f0;color:var(--g700)}
.alert-box{display:flex;gap:10px;margin:0 0 15px;padding:12px 14px;border-radius:12px;font-size:13px;line-height:1.5}.alert-danger{background:#fff7f7;border:1px solid #f0caca;color:#8e3b3b}.alert-success{background:#f3fbf5;border:1px solid #cae6d1;color:#2d7141}
/* Registration: only the right panel scrolls; left branding stays fixed. */
.register-mode .form-top{margin-bottom:13px}.register-mode .form-icon{width:60px;height:60px;font-size:26px;border-radius:17px}.register-mode .form-eyebrow{font-size:12px;margin-bottom:6px}.register-mode .form-heading{font-size:38px;margin-bottom:20px}.register-mode .field-label{font-size:12px;margin-bottom:6px}.register-mode .field-group{margin-bottom:13px}.register-mode .field-control input,.register-mode .field-control select{height:48px;font-size:13px;border-radius:13px}.register-mode .action-button{min-height:51px;font-size:14px}
.section-heading{display:flex;align-items:center;gap:10px;margin:24px 0 15px;color:var(--g800);font-size:13px;font-weight:900;letter-spacing:.02em}.section-heading:after{content:"";height:1px;flex:1;background:#c9dacf}.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}.field-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.field-grid .field-group{min-width:0}.hint{font-size:11px;color:#68786d;margin:-3px 0 15px;line-height:1.5}
/* Each personal name field occupies a whole row across the right panel. */
.name-fields{display:grid;grid-template-columns:minmax(0,1fr);gap:0;width:100%;}
.name-fields .field-group{grid-column:1 / -1;min-width:0;width:100%;}
.register-mode .name-fields .field-control,
.register-mode .name-fields input{display:block;width:100%;min-width:0;max-width:none;}

@media(max-width:1100px) and (min-width:761px){.auth-page{grid-template-columns:43% 57%}.left-content{padding-left:35px;padding-right:25px}.brand-hero h1{font-size:clamp(36px,4.4vw,54px)}}
@media(max-height:760px) and (min-width:761px){.brand-hero h1{font-size:clamp(38px,4vw,59px)}.auth-right:not(.signup-panel){padding:12px 35px}.auth-right:not(.signup-panel) .form-icon{width:62px;height:62px;font-size:27px}.auth-right:not(.signup-panel) .form-heading{font-size:43px;margin-bottom:20px}.auth-right:not(.signup-panel) .field-control input{height:52px}.auth-right:not(.signup-panel) .action-button{min-height:53px}}
@media(max-width:760px){html,body{height:auto;min-height:100%;overflow-x:hidden;overflow-y:auto}body{overflow-y:auto}.auth-page{height:auto;min-height:100vh;grid-template-columns:1fr;overflow:visible}.auth-left{height:135px}.left-content{height:135px;justify-content:center;padding:22px 25px}.brand-hero{display:none}.brand-mark{height:55px;width:55px;flex-basis:55px;font-size:25px}.brand-copy strong{font-size:20px}.brand-copy small{font-size:12px}.auth-right{height:auto;min-height:calc(100vh - 135px);padding:30px 25px;overflow:visible}.auth-right.signup-panel{height:calc(100dvh - 135px);min-height:0;overflow-y:auto}.auth-form-wrap{margin:auto}.form-heading{font-size:40px}}
@media(max-width:500px){.field-grid,.field-grid.three{grid-template-columns:1fr}.register-mode .form-heading{font-size:33px}.field-label{font-size:13px}.action-button{min-height:55px;font-size:14px}}
/* PUBLIC SITE FOOTER — unified ASU-REMS deep green, gold, and beige palette. */
html:has(.asu-public-footer),
body:has(.asu-public-footer){
    height:auto!important;
    min-height:100%!important;
    overflow-x:hidden!important;
    overflow-y:auto!important;
}
body:has(.asu-public-footer) .auth-page{
    height:100vh;
    height:100dvh;
    min-height:600px;
}
.auth-footer-jump{
    margin-top:24px;
    display:inline-flex;
    align-items:center;
    gap:9px;
    padding:7px 0;
    font-size:13px;
    font-weight:750;
    color:#075a29;
    text-decoration:none;
}
.auth-footer-jump:hover,.auth-footer-jump:focus-visible{color:#043b1c;text-decoration:underline}
.auth-footer-jump i{font-size:15px}
.asu-public-footer{
    --footer-deep:#043b1c;
    --footer-green:#075a29;
    --footer-gold:#f4ca24;
    --footer-amber:#e8aa51;
    position:relative;
    isolation:isolate;
    width:100%;
    overflow:hidden;
    border:0;
    /* Keep the footer visually continuous with the left-side ASU-REMS branding. */
    background:
        radial-gradient(ellipse 45% 85% at 100% 0%,rgba(244,202,36,.09),transparent 75%),
        linear-gradient(118deg,#03351d 0%,#054725 48%,#086b38 100%);
    color:#f6faf5;
    font-family:"Century Gothic",Arial,sans-serif;
}
/* Instead of the former thick gold strip with a sudden orange block,
   use one restrained accent that sits inside the site's content margins. */
.asu-public-footer::before{
    content:"";
    position:absolute;
    z-index:1;
    top:0;
    left:50%;
    transform:translateX(-50%);
    width:calc(100% - 56px);
    max-width:1336px;
    height:2px;
    border-radius:999px;
    background:linear-gradient(90deg,
        rgba(244,202,36,0) 0%,
        rgba(244,202,36,.76) 20%,
        rgba(244,202,36,.90) 78%,
        rgba(244,202,36,0) 100%);
    pointer-events:none;
}
.asu-public-footer *{box-sizing:border-box}
.asu-public-footer .asu-footer-grid{
    width:100%;
    max-width:1480px;
    margin:0 auto;
    padding:36px clamp(28px,4.8vw,72px) 29px;
    display:grid;
    grid-template-columns:minmax(245px,1.48fr) minmax(135px,.73fr) minmax(175px,.88fr) minmax(245px,1.12fr);
    column-gap:clamp(24px,3.2vw,50px);
    row-gap:32px;
    align-items:start;
}
/* Consistent, uppercase section headings aligned across all four columns. */
.asu-public-footer .asu-footer-grid h2{
    margin:0 0 20px;
    color:#ffda63;
    font-size:14px;
    font-weight:900;
    line-height:1.5;
    letter-spacing:.055em;
    text-transform:uppercase;
}
.asu-public-footer .asu-footer-grid h2::after{
    content:"";
    display:block;
    width:38px;
    height:2px;
    margin-top:12px;
    border-radius:999px;
    background:var(--footer-gold);
}
.asu-footer-about p{
    width:100%;
    max-width:none;
    margin:0 0 14px;
    color:#f5faef;
    font-size:12px;
    line-height:1.85;
    text-align:justify;
    text-align-last:left;
    text-justify:inter-word;
    hyphens:auto;
    overflow-wrap:break-word;
}
.asu-footer-about p:last-child{margin-bottom:0}
.asu-footer-about-lead{font-weight:650}
.asu-footer-links{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:12px}
.asu-footer-links li{margin:0;padding:0;font-size:12px;line-height:1.55}
.asu-footer-links a{color:#f5f8f4;text-decoration:none;font-weight:600}
.asu-footer-links a:hover,.asu-footer-links a:focus-visible{
    color:#ffe17a;
    text-decoration:underline;
    text-underline-offset:4px;
}
.asu-footer-links .asu-footer-info{color:#f0f5e9}
.asu-footer-links [hidden]{display:none!important}
.asu-footer-contact{
    padding:22px 21px;
    border:1px solid #f0dba3;
    border-radius:13px;
    background:#fff9ec;
    box-shadow:0 8px 24px rgba(0,0,0,.09);
}
.asu-footer-contact strong{display:block;margin-bottom:13px;font-size:13px;color:#073e26;font-weight:850}
.asu-footer-contact p{margin:0 0 12px;font-size:11.5px;line-height:1.7;color:#4e5b50}
.asu-footer-contact p:last-child{margin-bottom:0}
.asu-footer-contact p i{color:#c67d2d;margin-right:5px}
.asu-footer-contact .asu-footer-official-link{
    display:inline-flex;
    gap:8px;
    align-items:center;
    justify-content:center;
    margin-top:4px;
    padding:10px 13px;
    border-radius:9px;
    border:1px solid #e6ba66;
    background:#f5d57e;
    color:#123c24;
    text-decoration:none;
    font-size:11px;
    font-weight:800;
    line-height:1.2;
}
.asu-footer-contact .asu-footer-official-link:hover,
.asu-footer-contact .asu-footer-official-link:focus-visible{
    background:#f3c46a;
    color:#082e1a;
    border-color:#e8ac49;
}
.asu-footer-social{
    width:100%;
    max-width:1480px;
    margin:0 auto;
    padding:2px clamp(28px,4.8vw,72px) 27px;
    display:flex;
    justify-content:center;
    align-items:center;
    gap:14px;
}
.asu-footer-social-link{
    width:42px;
    height:42px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid rgba(255,227,155,.62);
    border-radius:50%;
    background:rgba(255,249,235,.12);
    color:#ffe39b;
    font-size:19px;
    text-decoration:none;
    box-shadow:0 5px 15px rgba(0,0,0,.07);
}
.asu-footer-social-link:hover,
.asu-footer-social-link:focus-visible{
    background:#fff1cc;
    border-color:#fff1cc;
    color:#075a29;
    outline-offset:3px;
    transform:translateY(-1px);
}
.asu-public-footer .asu-footer-bottom{
    width:100%;
    max-width:1480px;
    margin:0 auto!important;
    padding:16px clamp(28px,4.8vw,72px) 17px!important;
    border-top:1px solid rgba(244,202,36,.24);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px 28px;
    flex-wrap:wrap;
    background:rgba(1,32,17,.18);
    color:#eef0e6;
    font-size:11px;
    line-height:1.65;
}
.asu-public-footer .asu-footer-bottom span{min-width:0}
.asu-public-footer .asu-footer-bottom span:last-child{
    font-weight:750;
    color:var(--footer-gold);
    text-align:right;
}
@media(max-width:1120px){
    .asu-public-footer .asu-footer-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:34px 32px}
}
@media(max-width:760px){
    body:has(.asu-public-footer) .auth-page{height:auto;min-height:100dvh}
    .asu-public-footer .asu-footer-grid{grid-template-columns:repeat(2,minmax(0,1fr));padding-top:39px;gap:30px 25px}
    .asu-footer-brand,.asu-footer-contact-col{grid-column:1/-1}
    .asu-public-footer .asu-footer-bottom{flex-direction:column;align-items:flex-start;gap:8px}
    .asu-public-footer .asu-footer-bottom span:last-child{text-align:left}
}
@media(max-width:470px){
    .asu-public-footer .asu-footer-grid{grid-template-columns:1fr;gap:24px}
    .asu-footer-brand,.asu-footer-contact-col{grid-column:auto}
    .asu-public-footer .asu-footer-grid h2{margin-bottom:17px}
    .asu-footer-about p{text-align:left}
    .asu-footer-links{gap:11px}
    .asu-public-footer::before{width:calc(100% - 48px)}
}

/* ===== WPU-inspired ASU-REMS landing: layout inspiration only, not WPU assets ===== */
.portal-navbar{height:82px;width:100%;padding:0 clamp(24px,3vw,58px);display:flex;align-items:center;justify-content:space-between;gap:22px;background:rgba(255,255,255,.98);border-bottom:1px solid #e1e9e5;box-shadow:0 3px 16px rgba(18,48,28,.045);position:sticky;top:0;z-index:35;font-family:"Century Gothic",Arial,sans-serif}
.portal-logo{display:inline-flex;align-items:center;gap:12px;text-decoration:none;min-width:0;color:#09391e}
.portal-logo-icon{display:grid;place-items:center;flex:0 0 52px;width:52px;height:52px;border-radius:15px;color:#0b4a29;background:#f7ca2b;font-size:24px}
.portal-logo-copy strong{display:block;font-size:18px;line-height:1.16;font-weight:900;letter-spacing:-.02em}.portal-logo-copy small{display:block;margin-top:4px;font-size:11px;color:#6d7c72;font-weight:600;white-space:nowrap}
.portal-navlinks,.portal-nav-actions{display:flex;align-items:center;gap:7px}
.portal-navlinks button,.portal-navlinks a{appearance:none;border:0;background:transparent;color:#405146;font-size:13px;font-weight:800;border-radius:11px;padding:13px 17px;text-decoration:none;cursor:pointer;font-family:inherit}
.portal-navlinks button:hover,.portal-navlinks button:focus-visible,.portal-navlinks a:hover{background:#eff6ee;color:#07672e}
.portal-nav-actions{gap:10px}.portal-nav-actions a{display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;min-height:43px;padding:10px 18px;border-radius:12px;text-decoration:none;font-size:13px;font-weight:850}
.portal-nav-signup{color:#064721;border:1px solid #d4e2d8;background:#fff}.portal-nav-signup:hover{background:#f5faf5}
.portal-nav-login{color:white;background:#064b2a;border:1px solid #064b2a}.portal-nav-login:hover{background:#073a22;color:#fff}
.portal-viewport{height:calc(100vh - 82px);height:calc(100dvh - 82px);min-height:0;padding:clamp(18px,2.4vw,34px) clamp(22px,2.65vw,48px);background:radial-gradient(circle at 0% 4%,#e7f4ea 0%,transparent 40%),linear-gradient(130deg,#eef5f0,#f5f7f4 60%,#fff9ed);overflow:hidden}
.portal-hero-grid{display:grid;grid-template-columns:minmax(0,1.76fr) minmax(350px,.87fr);gap:clamp(18px,2vw,32px);height:100%;width:100%;max-width:1730px;margin:0 auto;min-height:0}
.portal-banner{min-width:0;height:100%;position:relative;isolation:isolate;overflow:hidden;border-radius:27px;display:flex;align-items:center;color:white;background:radial-gradient(circle at 77% 12%,rgba(253,204,60,.25) 0%,transparent 33%),linear-gradient(120deg,rgba(1,34,18,.96),rgba(3,73,39,.95) 58%,rgba(5,100,46,.90)),linear-gradient(150deg,#06331f,#128448);box-shadow:0 18px 48px rgba(13,60,35,.12)}
.portal-banner:before{content:"";position:absolute;z-index:-1;width:95%;height:37%;transform:rotate(-33deg);top:43%;left:-38%;border-radius:60px;background:linear-gradient(90deg,rgba(255,205,35,.20),rgba(255,255,255,.035))}
.portal-banner:after{content:"";position:absolute;z-index:-1;width:560px;height:560px;border:100px solid rgba(255,255,255,.06);border-radius:50%;right:-320px;bottom:-260px}
.portal-banner-art{position:absolute;z-index:-1;right:-42px;top:9%;width:38%;height:78%;border:2px solid rgba(245,204,45,.2);border-radius:35px;transform:rotate(-13deg);background:linear-gradient(145deg,rgba(244,202,36,.08),rgba(255,255,255,.035))}
.portal-banner-art:after{content:"";position:absolute;inset:14%;border:2px solid rgba(255,255,255,.11);border-radius:30px}
.portal-banner-body{position:relative;z-index:1;padding:clamp(24px,4vw,68px);width:100%;max-width:850px}
.portal-pill{display:inline-flex;gap:10px;align-items:center;padding:10px 15px;border-radius:99px;background:#ffe69a;color:#083d23;font-weight:850;font-size:12px;letter-spacing:.05em}
.portal-banner h1{margin:24px 0 18px;font-size:clamp(33px,3.35vw,61px);letter-spacing:-.045em;line-height:1.11;font-weight:900;max-width:780px}
.portal-banner h1 em{color:#ffdb54;font-style:normal}
.portal-banner-desc{font-size:clamp(13px,1.15vw,17px);line-height:1.75;max-width:670px;color:rgba(255,255,255,.88);margin:0 0 30px}
.portal-hero-actions{display:flex;align-items:center;flex-wrap:wrap;gap:12px}
.portal-explore-btn,.portal-official-link{display:inline-flex;gap:9px;align-items:center;justify-content:center;min-height:49px;border-radius:13px;padding:12px 19px;font-family:inherit;font-weight:850;font-size:13px;text-decoration:none;cursor:pointer}
.portal-explore-btn{border:1px solid #f6d55f;background:#ffe18b;color:#113d26}.portal-explore-btn:hover{background:#ffeaaa}
.portal-official-link{border:1px solid rgba(255,255,255,.45);background:rgba(255,255,255,.1);color:#fff}.portal-official-link:hover{background:rgba(255,255,255,.19);color:#fff}
.portal-hero-chips{display:flex;gap:11px;flex-wrap:wrap;margin-top:clamp(24px,4.5vw,55px)}
.portal-hero-chips span{display:inline-flex;gap:9px;align-items:center;padding:13px 16px;border:1px solid rgba(255,255,255,.22);border-radius:14px;background:rgba(255,255,255,.09);backdrop-filter:blur(3px);font-size:12px;font-weight:800;color:#f5f9f4}
.portal-hero-chips i{color:#ffe08e;font-size:16px}
.portal-login-card{min-width:0;height:100%;background:linear-gradient(145deg,#ffffff 0%,#fffefa 45%,#fff6e8 100%);border:1px solid #cbd9ce;border-radius:26px;box-shadow:0 22px 55px rgba(16,63,34,.14),0 3px 12px rgba(16,63,34,.05);overflow:hidden;position:relative;display:flex;align-items:center;justify-content:center;padding:clamp(20px,3vw,50px)}
.portal-login-inner{width:100%;max-width:470px}
.portal-login-brand{width:65px;height:65px;display:grid;place-items:center;margin:0 auto 19px;border-radius:19px;background:linear-gradient(140deg,#0d8541,#06401f);color:#ffda54;font-size:29px}
.portal-login-card h2{font-size:clamp(27px,2.4vw,37px);font-weight:900;color:#093a22;line-height:1.2;margin:0 0 12px;text-align:center;letter-spacing:-.035em}
.portal-login-subtitle{font-size:13px;color:#607266;line-height:1.7;max-width:300px;text-align:center;margin:0 auto 26px}
.portal-login-card .field-control input{height:51px;border-radius:12px;background:#fff}
.portal-login-card .field-group{margin-bottom:17px}.portal-login-card .field-label{font-size:12px}
.portal-login-card .action-button{min-height:53px;border-radius:12px;font-size:14px;margin-top:9px}
.portal-login-register{display:block;text-align:center;margin-top:20px;font-size:12px;line-height:1.8;color:#66796c}
.portal-login-register a{font-weight:850;color:#086d36;text-decoration:none}.portal-login-register a:hover{text-decoration:underline}
/* Revealed by Explore only: content is beneath the landing screen, never a popup. */
.portal-information[hidden]{display:none!important}
.portal-information{background:linear-gradient(180deg,#f5f8f5 0%,#fff9ef 100%);padding:48px clamp(22px,4vw,70px) 58px}
.portal-information-inner{max-width:1550px;margin:0 auto}
.portal-section-kicker{font-size:12px;text-transform:uppercase;font-weight:900;letter-spacing:.11em;color:#137244;margin:0 0 12px}
.portal-section-title{font-size:clamp(26px,2.7vw,38px);font-weight:900;letter-spacing:-.04em;color:#133723;margin:0 0 23px}
.portal-about-grid{display:grid;grid-template-columns:1.35fr 1fr 1fr;gap:19px}
.portal-info-card{padding:26px 28px;border-radius:19px;background:#fff;border:1px solid #e4eae4;box-shadow:0 6px 22px rgba(14,65,34,.035)}
.portal-info-card > i{font-size:24px;color:#0f8a4a}
.portal-info-card h3{font-size:17px;font-weight:850;color:#113b25;margin:11px 0}
.portal-info-card p{font-size:13px;line-height:1.8;color:#5b6a61;margin:0;text-align:justify}
#portalAbout,#portalFeatures,#asuPublicFooter{scroll-margin-top:95px}
.portal-features-title{margin:37px 0 18px;font-size:20px;font-weight:900;color:#103b24}
.portal-feature-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}
.portal-feature-strip span{display:flex;align-items:center;gap:9px;font-size:12px;line-height:1.4;font-weight:800;background:#e5f2e9;color:#135c33;padding:16px;border-radius:13px}
.portal-feature-strip i{font-size:18px;color:#0d8241}
/* On small screens the hero itself may scroll to keep the sign-in accessible. */
@media(max-width:1250px){.portal-navbar{padding-inline:23px}.portal-navlinks button{padding:10px 11px}.portal-banner-body{padding:34px}.portal-banner h1{font-size:clamp(33px,3.5vw,47px)}.portal-hero-chips{margin-top:25px}.portal-hero-chips span{padding:11px}}
@media(max-width:1000px){.portal-navbar{height:75px}.portal-viewport{height:calc(100dvh - 75px);padding:18px;overflow-y:auto}.portal-hero-grid{height:auto;min-height:100%;grid-template-columns:1fr}.portal-banner{min-height:440px}.portal-login-card{min-height:510px}.portal-navlinks{display:none}.portal-about-grid{grid-template-columns:1fr 1fr}.portal-about-grid .portal-info-card:first-child{grid-column:1/-1}.portal-feature-strip{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.portal-navbar{height:69px;padding:0 14px;gap:8px}.portal-logo-icon{width:43px;height:43px;flex-basis:43px;font-size:20px}.portal-logo-copy strong{font-size:15px}.portal-logo-copy small{font-size:9px}.portal-nav-actions{gap:6px}.portal-nav-actions a{font-size:10px;min-height:36px;padding:7px 9px;border-radius:9px}.portal-viewport{height:calc(100dvh - 69px);padding:12px}.portal-banner{min-height:470px;border-radius:20px}.portal-banner-body{padding:28px 23px}.portal-banner h1{font-size:clamp(32px,8vw,45px)}.portal-banner-desc{font-size:13px}.portal-hero-chips span{font-size:11px}.portal-login-card{border-radius:20px;padding:24px 22px;min-height:510px}.portal-information{padding:35px 16px}.portal-about-grid,.portal-feature-strip{grid-template-columns:1fr}.portal-about-grid .portal-info-card:first-child{grid-column:auto}.portal-info-card{padding:23px}}
@media(max-height:730px) and (min-width:1001px){.portal-viewport{padding:18px 24px}.portal-banner-body{padding:28px 35px}.portal-banner h1{font-size:clamp(32px,3vw,49px);margin:17px 0 11px}.portal-banner-desc{margin-bottom:18px}.portal-hero-chips{margin-top:20px}.portal-login-card{padding:19px 28px}.portal-login-brand{width:52px;height:52px;font-size:24px;margin-bottom:12px}.portal-login-card h2{font-size:29px;margin-bottom:7px}.portal-login-subtitle{margin-bottom:14px}.portal-login-card .field-group{margin-bottom:11px}.portal-login-card .field-control input{height:45px}.portal-login-register{margin-top:13px}}

/* Welcome is exactly one screen tall until About, Features or Contact is selected. */
html:has(.portal-navbar), body:has(.portal-navbar) { height:100%!important; overflow-x:hidden!important; overflow-y:hidden!important; }
html.explore-unlocked:has(.portal-navbar), body.explore-unlocked:has(.portal-navbar) { height:auto!important; min-height:100%!important; overflow-x:hidden!important; overflow-y:auto!important; }
.asu-public-footer[hidden]{display:none!important}
#asuFooterAboutTitle,#asuFooterFeaturesTitle,#asuFooterContactTitle{scroll-margin-top:105px}
/* The green banner keeps its original look; the headline is now the only hero copy. */
.portal-banner h1{margin-bottom:0}
@media(max-width:1000px){.portal-viewport{overflow-y:auto}}


/* Separate About page link on the right; keep the existing landing design unchanged. */
.portal-nav-actions .portal-nav-about{color:#064721;border:1px solid #d4e2d8;background:#fff}
.portal-nav-actions .portal-nav-about:hover,.portal-nav-actions .portal-nav-about:focus-visible{background:#f1f8f1;color:#054621}
@media(max-width:600px){.portal-logo-copy small{display:none}.portal-nav-actions{gap:4px}.portal-nav-actions a{padding:7px 8px}}
/* ASU-REMS: balance the main Sign In panel and restore the three-column footer. */
html:has(.portal-navbar),
body:has(.portal-navbar) {
    height:auto!important;
    min-height:100%!important;
    overflow-x:hidden!important;
    overflow-y:auto!important;
}
.portal-hero-grid {
    grid-template-columns:minmax(0,1.45fr) minmax(430px,1fr);
}
.portal-login-card { padding:clamp(22px,2.55vw,42px); }
.portal-login-inner { width:100%;max-width:540px;margin-inline:auto; }
.portal-login-subtitle { max-width:400px; }
.asu-public-footer .asu-footer-grid {
    grid-template-columns:minmax(0,.88fr) minmax(0,.85fr) minmax(290px,1.2fr);
    gap:clamp(24px,4vw,66px);
    max-width:1400px;
    padding-top:36px;
    padding-bottom:29px;
}
.asu-public-footer .asu-footer-contact-col { grid-column:auto; }
.asu-public-footer .asu-footer-bottom { max-width:1400px; }
@media(max-width:1120px) and (min-width:1001px) {
    .portal-hero-grid {grid-template-columns:minmax(0,1.15fr) minmax(425px,1fr);}
    .portal-banner-body {padding:28px;}
    .portal-banner h1 {font-size:clamp(30px,3.2vw,40px);}
}
@media(max-width:1000px) {
    .portal-viewport {height:auto;min-height:calc(100dvh - 75px);overflow:visible;}
    .portal-hero-grid {height:auto;min-height:0;grid-template-columns:minmax(0,1fr);}
    .portal-banner {min-height:370px;}
    .portal-login-card {height:auto;min-height:510px;padding:32px;}
    .asu-public-footer .asu-footer-grid {grid-template-columns:repeat(2,minmax(0,1fr));}
}
@media(max-width:600px) {
    .portal-viewport {min-height:calc(100dvh - 69px);}
    .portal-login-card {padding:26px 22px;}
    .asu-public-footer .asu-footer-grid {grid-template-columns:minmax(0,1fr);}
}
/* Welcome Sign In panel: make the right side the same generous proportion
   as the registration screen, without changing registration itself. */
@media (min-width:1001px) {
    .portal-hero-grid {
        grid-template-columns:minmax(0,44fr) minmax(0,56fr);
    }
    .portal-login-card {
        padding:clamp(28px,3.5vw,56px);
    }
    .portal-login-inner {
        width:100%;
        max-width:640px;
        margin-inline:auto;
    }
    .portal-banner-body {
        padding:clamp(28px,3vw,48px);
    }
    .portal-banner h1 {
        font-size:clamp(30px,2.55vw,45px);
        line-height:1.18;
    }
}


/* Login and registration share ONE public shell. Only the right card changes. */
.portal-signup-card {
    display:block;
    height:100%;
    overflow-y:auto;
    overflow-x:hidden;
    overscroll-behavior:contain;
    scrollbar-width:thin;
    scrollbar-color:#a7c0ae transparent;
}
.portal-signup-card .portal-login-inner {
    width:100%;
    max-width:640px;
    margin:0 auto;
    padding:5px 0 24px;
}
.portal-signup-card .portal-login-brand {
    width:58px;height:58px;font-size:25px;
    margin:0 auto 12px;
}
.portal-signup-card h2 {font-size:clamp(27px,2.2vw,34px);margin-bottom:15px;}
.portal-signup-card .section-heading {margin:22px 0 16px;}
.portal-signup-card .field-group{margin-bottom:13px;}
.portal-signup-card .field-control input,
.portal-signup-card .field-control select {height:49px;background:#fff;border-radius:12px;}
.portal-signup-card .field-grid {gap:0 14px;}
.portal-signup-card .action-button {margin:13px 0 0;}
.portal-signup-card .name-fields {display:grid;grid-template-columns:1fr;}
.portal-signup-card .name-fields .field-group {width:100%;}
.portal-nav-actions .portal-nav-signup.portal-nav-active {
    background:#064b2a;color:#fff;border-color:#064b2a;
}
.portal-nav-actions .portal-nav-login:not(.portal-nav-active) {
    background:#fff;color:#064721;border:1px solid #d4e2d8;
}
@media (max-width:1000px) {
    .portal-signup-card {height:auto;max-height:none;overflow:visible;}
    .portal-signup-card .portal-login-inner {max-width:640px;}
}
@media (max-width:600px) {
    .portal-signup-card .field-grid.three {grid-template-columns:1fr;}
}
/* ASU-REMS layout refinement: the left green area is the page background,
   not a separate rounded card. Only the Sign In / Create Account side is a card. */
@media (min-width:1001px) {
    .portal-viewport {
        padding:0;
        background:linear-gradient(110deg,#e9f4eb,#f6f7f3 56%,#fff8eb);
    }
    .portal-hero-grid {
        grid-template-columns:minmax(0,44fr) minmax(0,56fr);
        gap:0;
        max-width:none;
    }
    .portal-banner {
        height:100%;
        border-radius:0;
        box-shadow:none;
    }
    .portal-banner-body {
        padding:clamp(38px,4.8vw,82px);
    }
    .portal-login-card {
        height:calc(100% - 44px);
        margin:22px clamp(18px,2.1vw,36px);
    }
}
@media (max-width:1000px) {
    .portal-viewport {padding:0;}
    .portal-hero-grid {gap:0;}
    .portal-banner {
        border-radius:0;
        box-shadow:none;
        min-height:330px;
    }
    .portal-banner-body {padding:36px 32px;}
    .portal-login-card {margin:17px;}
}
@media (max-width:600px) {
    .portal-banner {min-height:270px;}
    .portal-banner-body {padding:29px 24px;}
    .portal-banner h1 {font-size:clamp(29px,7.5vw,40px);}
    .portal-login-card {margin:12px;}
}

/* Reference branding: match the original four-line title and its placement.
   Only the green area changes; the right Sign In / Create Account UI is untouched. */
.portal-banner-body {
    height:100%;
    max-width:none;
    display:flex;
    flex-direction:column;
    align-items:flex-start;
    padding:clamp(35px,4.6vw,76px) clamp(34px,5vw,88px);
}
.portal-banner-brand {
    display:flex;
    align-items:center;
    gap:clamp(13px,1.5vw,22px);
    flex-shrink:0;
}
.portal-banner-brand-icon {
    display:grid;
    place-items:center;
    width:clamp(64px,5.4vw,86px);
    height:clamp(64px,5.4vw,86px);
    flex-shrink:0;
    border-radius:clamp(16px,1.5vw,22px);
    background:#f9ce25;
    color:#03341c;
    font-size:clamp(28px,2.55vw,41px);
}
.portal-banner-brand-name strong {
    display:block;
    color:#fff;
    font-size:clamp(21px,2.05vw,32px);
    font-weight:900;
    letter-spacing:-.025em;
    line-height:1.2;
}
.portal-banner-brand-name small {
    display:block;
    margin-top:10px;
    color:rgba(255,255,255,.81);
    font-size:clamp(12px,1.1vw,18px);
    line-height:1.3;
}
.portal-banner-headline {
    margin:auto 0;
    width:100%;
    padding:20px 0;
}
.portal-banner-headline h1,
.portal-banner h1 {
    max-width:none;
    margin:0;
    color:#fff;
    font-family:"Century Gothic",Arial,sans-serif;
    font-size:clamp(38px,4.1vw,72px);
    font-weight:900;
    letter-spacing:-.057em;
    line-height:1.045;
}
.portal-banner-headline h1 span { display:block; white-space:nowrap; }
.portal-banner-headline h1 .portal-title-gold { color:#f8cd26; }
@media(max-width:1250px) and (min-width:1001px) {
    .portal-banner-body { padding-inline:clamp(30px,4.5vw,60px); }
    .portal-banner-headline h1,.portal-banner h1 { font-size:clamp(37px,4vw,56px); }
}
@media(max-width:1000px) {
    .portal-banner-body { padding:34px 32px; }
    .portal-banner-brand-icon { width:60px;height:60px;font-size:27px;border-radius:17px; }
    .portal-banner-brand-name strong { font-size:22px; }
    .portal-banner-brand-name small { font-size:12px;margin-top:5px; }
    .portal-banner-headline h1,.portal-banner h1 {font-size:clamp(32px,5.2vw,48px);}
}
@media(max-width:600px) {
    .portal-banner { min-height:335px; }
    .portal-banner-body {padding:26px 24px;}
    .portal-banner-brand-icon { width:49px;height:49px;font-size:23px;border-radius:14px; }
    .portal-banner-brand-name strong {font-size:18px;}
    .portal-banner-brand-name small {font-size:11px;}
    .portal-banner-headline {padding:12px 0;}
    .portal-banner-headline h1,.portal-banner h1 { font-size:clamp(29px,7.4vw,41px); }
}

/* Single source of branding: navbar logo above, headline centered vertically on the left. */
.portal-banner-headline { margin:auto 0; }

/* Small moon switch beside Create Account; existing theme handler is unchanged. */
.portal-theme-toggle {
    appearance:none;
    position:relative;
    display:inline-block;
    flex:0 0 55px;
    width:55px;
    min-width:55px;
    height:29px;
    padding:0;
    border:1px solid #dfe1e7;
    border-radius:999px;
    background:#e8e8ee;
    color:#16191e;
    cursor:pointer;
    vertical-align:middle;
    transition:background-color .18s ease,border-color .18s ease;
}
/* White moon badge on the left. */
.portal-theme-toggle::before {
    content:"";
    position:absolute;
    left:3px;
    top:3px;
    width:21px;
    height:21px;
    border-radius:50%;
    background:#fff;
}
/* White circular slider on the right. */
.portal-theme-toggle::after {
    content:"";
    position:absolute;
    right:4px;
    top:4px;
    width:19px;
    height:19px;
    border-radius:50%;
    background:#fff;
    box-shadow:0 1px 3px rgba(25,30,42,.13);
    transition:background-color .18s ease;
}
.portal-theme-toggle i {
    position:absolute;
    z-index:1;
    left:6px;
    top:50%;
    width:16px;
    transform:translateY(-50%);
    color:#141820;
    font-size:14px;
    line-height:1;
    text-align:center;
    pointer-events:none;
}
.portal-theme-toggle:hover {background:#dfe0e7;border-color:#c9ced5;}
.portal-theme-toggle:focus-visible {outline:3px solid #edc447;outline-offset:3px;}

/* Public-page dark-mode refinements; shared theme also covers About and internal pages. */
html[data-rems-theme="dark"] body:has(.portal-navbar) {background:#0a1c15;color:#e8f2e8;}
html[data-rems-theme="dark"] .portal-navbar {
    background:#10271c;border-bottom-color:#294738;
    box-shadow:0 4px 20px rgba(0,0,0,.12);
}
html[data-rems-theme="dark"] .portal-logo,
html[data-rems-theme="dark"] .portal-logo-copy strong {color:#f1f6ec;}
html[data-rems-theme="dark"] .portal-logo-copy small {color:#b3c9b8;}
html[data-rems-theme="dark"] .portal-nav-actions a:not(.portal-nav-active) {
    background:#193628;color:#e5f0e4;border-color:#42604c;
}
html[data-rems-theme="dark"] .portal-nav-actions a:not(.portal-nav-active):hover,
html[data-rems-theme="dark"] .portal-nav-actions a:not(.portal-nav-active):focus-visible {
    background:#2a4935;color:#fff;
}
html[data-rems-theme="dark"] .portal-nav-actions .portal-nav-active {
    background:#0b6938;color:#fff;border-color:#37965e;
}
html[data-rems-theme="dark"] .portal-theme-toggle {
    background:#34453b;
    color:#f9d568;
    border-color:#5b7164;
}
html[data-rems-theme="dark"] .portal-theme-toggle::before {
    left:auto;
    right:3px;
    background:#21392a;
}
html[data-rems-theme="dark"] .portal-theme-toggle::after {
    right:auto;
    left:4px;
    background:#f9faf7;
}
html[data-rems-theme="dark"] .portal-theme-toggle i {
    left:auto;
    right:6px;
    color:#ffda69;
}
html[data-rems-theme="dark"] .portal-theme-toggle:hover {background:#40594a;}
html[data-rems-theme="dark"] .portal-viewport {background:#0b1f17;}
html[data-rems-theme="dark"] .portal-banner {
    background:radial-gradient(circle at 77% 12%,rgba(255,203,57,.15),transparent 35%),
               linear-gradient(115deg,#001e10,#04472a 75%,#05552b);
}
html[data-rems-theme="dark"] .portal-login-card {
    background:linear-gradient(145deg,#142c22 0%,#1a3026 50%,#262e25 100%);
    border-left-color:#385242;
}
html[data-rems-theme="dark"] .portal-login-card h2 {color:#f7f8ed;}
html[data-rems-theme="dark"] .portal-login-subtitle,
html[data-rems-theme="dark"] .portal-login-register {color:#c3d4c6;}
html[data-rems-theme="dark"] .portal-login-register a {color:#ffde75;}
html[data-rems-theme="dark"] .portal-login-card .field-label {color:#e6f0e7;}
html[data-rems-theme="dark"] .portal-login-card .field-control input,
html[data-rems-theme="dark"] .portal-login-card .field-control select {
    background:#223e31!important;
    background-color:#223e31!important;
    color:#fffef8!important;
    -webkit-text-fill-color:#fffef8!important;
    border:1px solid #56705d!important;
    color-scheme:dark;
}
html[data-rems-theme="dark"] .portal-login-card .field-control input::placeholder {color:#b2c4b7;opacity:1;}
html[data-rems-theme="dark"] .portal-login-card .field-control > i,
html[data-rems-theme="dark"] .portal-login-card .password-toggle {color:#c4d3bf;}
html[data-rems-theme="dark"] .portal-login-card .password-toggle:hover {background:#355843;color:#fff;}
html[data-rems-theme="dark"] .portal-login-card .field-control input:focus,
html[data-rems-theme="dark"] .portal-login-card .field-control select:focus {
    border-color:#e9c95b;box-shadow:0 0 0 3px rgba(243,213,98,.17);
}
html[data-rems-theme="dark"] .portal-login-card .section-heading {color:#f5d56a;}
html[data-rems-theme="dark"] .portal-login-card .section-heading:after {background:#527057;}
html[data-rems-theme="dark"] .portal-login-card .action-button.primary-button {
    background:linear-gradient(110deg,#088042,#07542e);color:white;
    border:1px solid #36935c;
}
html[data-rems-theme="dark"] .portal-login-card .alert-danger {background:#472624;border-color:#9e5550;color:#ffe6e1;}
html[data-rems-theme="dark"] .portal-login-card .alert-success {background:#164834;border-color:#548264;color:#e6ffeb;}
html[data-rems-theme="dark"] .asu-public-footer {
    background:linear-gradient(115deg,#052c1b,#0b472d 60%,#24513a);
}
html[data-rems-theme="dark"] .asu-footer-contact {
    background:#163628;border-color:#789360;
    box-shadow:none;
}
html[data-rems-theme="dark"] .asu-footer-contact strong {color:#fff5cf;}
html[data-rems-theme="dark"] .asu-footer-contact p {color:#dee9dc;}
html[data-rems-theme="dark"] .asu-footer-contact .asu-footer-official-link {
    background:#e9c869;color:#13341f;border-color:#dbb75a;
}
@media(max-width:600px) {
    .portal-theme-toggle {width:47px;min-width:47px;flex-basis:47px;height:26px;}
    .portal-theme-toggle::before {width:20px;height:20px;top:2px;left:2px;}
    .portal-theme-toggle::after {width:18px;height:18px;top:3px;right:3px;}
    .portal-theme-toggle i {left:5px;font-size:12px;}
    html[data-rems-theme="dark"] .portal-theme-toggle::before {left:auto;right:2px;}
    html[data-rems-theme="dark"] .portal-theme-toggle::after {right:auto;left:3px;}
    html[data-rems-theme="dark"] .portal-theme-toggle i {left:auto;right:5px;}
    .portal-nav-actions {gap:4px;}
    .portal-nav-actions a {font-size:10px;padding:7px 7px;}
}
@media(max-width:385px) {
    .portal-logo-copy strong {font-size:12px;}
    .portal-logo-icon {width:37px;height:37px;flex-basis:37px;}
    .portal-navbar {padding-inline:9px;}
    .portal-nav-actions a {font-size:9px;padding:6px 5px;}
    .portal-theme-toggle {width:44px;min-width:44px;flex-basis:44px;}
}
/* Flush Sign In / Create Account with the right side of the landing page.
   Keep all inner form spacing, inputs, green left panel, and theme styles unchanged. */
.portal-login-card {
    width:100%;
    margin:0;
    border:0;
    border-radius:0;
    box-shadow:none;
}
/* One natural page scrollbar to reach the footer; no nested landing scroll. */
@media (max-width:1000px) {
    .portal-viewport {overflow:visible!important;}
}
/* The footer itself is not a scroll container. */
.asu-public-footer {overflow:hidden;}

@media (min-width:1001px) {
    .portal-login-card { height:100%; }
}


/* Final public-page refinement: full-height institutional branding at left,
   and one calm, clearly-defined form card at right. Visual changes only. */
@media (min-width:1001px) {
  html:has(.portal-navbar), body:has(.portal-navbar) {
    height:100% !important;
    min-height:100% !important;
    overflow-x:hidden !important;
    overflow-y:hidden !important;
  }
  .portal-viewport {
    height:calc(100vh - 82px);
    height:calc(100dvh - 82px);
    min-height:0;
    overflow:hidden;
    padding:0;
    /* Matching ASU-green backdrop, slightly softer than the left branding panel. */
    background:
      radial-gradient(ellipse at 94% 12%,rgba(244,202,36,.13),transparent 48%),
      radial-gradient(ellipse at 88% 90%,rgba(255,255,255,.055),transparent 42%),
      linear-gradient(116deg,#073b26 0%,#0a5635 54%,#177a48 100%);
  }
  .portal-hero-grid {
    height:100%;
    gap:0;
    grid-template-columns:minmax(0,44fr) minmax(0,56fr);
    max-width:none;
  }
  .portal-banner {
    width:100%;
    height:100%;
    margin:0;
    border:0;
    border-radius:0;
    box-shadow:none;
  }
  .portal-login-card {
    width:calc(100% - 60px);
    height:calc(100% - 52px);
    margin:26px 30px;
    padding:clamp(26px,3vw,48px);
    border:1px solid #d5e3d8;
    border-radius:23px;
    background:linear-gradient(145deg,#ffffff 0%,#fffefa 53%,#fff8ee 100%);
    box-shadow:0 20px 48px rgba(0,29,15,.26),0 3px 12px rgba(0,29,15,.11);
  }
  .portal-login-card:not(.portal-signup-card) .portal-login-inner {
    width:100%;
    max-width:640px;
  }
  .portal-signup-card {
    overflow-x:hidden;
    overflow-y:auto;
    overscroll-behavior:contain;
  }
}
/* Compact desktop: keep enough room for form elements at narrower widths. */
@media (min-width:1001px) and (max-width:1200px) {
  .portal-login-card {
    width:calc(100% - 40px);
    height:calc(100% - 40px);
    margin:20px;
    padding:clamp(22px,2.5vw,36px);
    border-radius:21px;
  }
}
/* Small screens: the green banner is flat, the form remains the sole card. */
@media (max-width:1000px) {
  html:has(.portal-navbar), body:has(.portal-navbar) {
    height:auto !important;
    min-height:100% !important;
    overflow-x:hidden !important;
    overflow-y:auto !important;
  }
  .portal-viewport {
    height:auto;
    min-height:calc(100dvh - 75px);
    overflow:visible !important;
    padding:0;
    background:
      radial-gradient(circle at 90% 15%,rgba(244,202,36,.12),transparent 48%),
      linear-gradient(155deg,#063b26 0%,#0b5937 65%,#147746 100%);
  }
  .portal-hero-grid {
    height:auto;
    min-height:0;
    grid-template-columns:minmax(0,1fr);
    gap:0;
  }
  .portal-banner {
    width:100%;
    height:auto;
    min-height:320px;
    margin:0;
    border-radius:0;
    box-shadow:none;
  }
  .portal-login-card {
    width:calc(100% - 36px);
    height:auto;
    min-height:480px;
    margin:18px;
    border:1px solid #d5e3d8;
    border-radius:21px;
    background:linear-gradient(145deg,#fff,#fff9f0);
    box-shadow:0 14px 34px rgba(0,29,15,.24);
  }
  .portal-signup-card {overflow:visible;}
}
@media (max-width:600px) {
  .portal-viewport {min-height:calc(100dvh - 69px);}
  .portal-banner {min-height:285px;}
  .portal-login-card {
    width:calc(100% - 24px);
    min-height:420px;
    margin:12px;
    padding:24px 20px;
    border-radius:19px;
  }
}
/* Existing shared dark-mode preference still controls the form surface. */
html[data-rems-theme="dark"] .portal-viewport {
  background:
    radial-gradient(circle at 90% 18%,rgba(244,202,36,.055),transparent 44%),
    linear-gradient(120deg,#071b12 0%,#0a2b1d 52%,#10412b 100%);
}
html[data-rems-theme="dark"] .portal-login-card {
  background:linear-gradient(145deg,#142c22 0%,#1a3026 55%,#262e25 100%);
  border-color:#3b5746;
  box-shadow:0 14px 36px rgba(0,0,0,.22);
}


/* Seamless ASU green backdrop: remove the mismatched vertical color band.
   A single continuous green gradient sits behind BOTH columns, while the
   original geometric art remains on the left and the cream form stays a card. */
.portal-viewport {
    background:
        radial-gradient(circle at 78% 12%, rgba(244,202,36,.10), transparent 38%),
        linear-gradient(115deg, #022e1b 0%, #064b2e 52%, #0d623c 100%);
}
.portal-banner {
    background: transparent;
}
@media (min-width:1001px) {
    .portal-login-card {
        width:calc(100% - 44px);
        margin:26px 22px;
    }
}
html[data-rems-theme="dark"] .portal-viewport {
    background:
        radial-gradient(circle at 78% 12%,rgba(244,202,36,.035),transparent 42%),
        linear-gradient(115deg,#001a10 0%,#052e1e 53%,#0a3c29 100%);
}
html[data-rems-theme="dark"] .portal-banner {
    background:transparent;
}


/* ASU university-green palette — sampled from the university website screenshot.
   This keeps the unified two-panel background and does not affect the forms. */
:root {
  --asu-reference-green: #0d8935;
  --asu-reference-gold: #f4ca24;
}
/* Continuous green gradient, using brighter ASU green at the right edge. */
.portal-viewport {
  background:
    radial-gradient(ellipse at 80% 12%,rgba(247,204,46,.13),transparent 44%),
    linear-gradient(110deg,#03441f 0%,#076b31 46%,#0d8935 100%);
}
.portal-banner { background: transparent; }
.portal-banner-art { border-color:rgba(248,212,57,.30); }
.portal-banner-headline h1 .portal-title-gold { color:#ffdb50; }
.portal-logo-icon { background:#f4ca24; }
.portal-nav-actions .portal-nav-active,
.portal-nav-actions .portal-nav-signup.portal-nav-active {
  background:#0b7732;
  border-color:#0b7732;
  color:#fff;
}
.portal-login-brand { background:linear-gradient(145deg,#0d8935,#05572b); }
.portal-login-card .action-button.primary-button {
  background:linear-gradient(110deg,#0b8938 0%,#055628 100%);
  color:#fff;
}
.portal-login-card .action-button.primary-button:hover {
  background:linear-gradient(110deg,#0a7f33 0%,#044c24 100%);
}
/* Keep the existing dark appearance fully consistent when selected. */
html[data-rems-theme="dark"] .portal-viewport {
  background:
    radial-gradient(ellipse at 80% 12%,rgba(244,202,36,.04),transparent 44%),
    linear-gradient(110deg,#001a10 0%,#052e1e 53%,#0a3c29 100%);
}
html[data-rems-theme="dark"] .portal-nav-actions .portal-nav-active,
html[data-rems-theme="dark"] .portal-nav-actions .portal-nav-signup.portal-nav-active {
  background:#0b6938;
  border-color:#37965e;
  color:#fff;
}


/* ASU-REMS unified palette — use the values from dashboard.php.
   Color-only refinements. All login, signup, layouts, and behaviors are unchanged. */
:root {
  --asu-green:#007a33;
  --asu-green-dark:#005c26;
  --asu-green-deep:#003f1a;
  --asu-yellow:#ffd51f;
  --asu-orange:#ff6a00;
  --asu-ink:#101810;
  --asu-muted:#657068;
  --g950:#003f1a;
  --g900:#005c26;
  --g800:#007a33;
  --g700:#007a33;
  --yellow:#ffd51f;
}
.portal-navbar { background:#fff; border-bottom-color:rgba(0,122,51,.14); }
.portal-logo {color:#003f1a;}
.portal-logo-icon {background:#ffd51f;color:#003f1a;}
.portal-logo-copy small {color:#657068;}
.portal-viewport {
  background:
    radial-gradient(circle at 85% 12%,rgba(255,213,31,.12),transparent 37%),
    linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%);
}
.portal-banner {background:transparent;}
.portal-banner-art {border-color:rgba(255,213,31,.3);}
.portal-banner-headline h1 .portal-title-gold {color:#ffd51f;}
.portal-nav-actions .portal-nav-active,
.portal-nav-actions .portal-nav-signup.portal-nav-active {
  background:#007a33;
  border-color:#007a33;
  color:#fff;
}
.portal-nav-actions a:not(.portal-nav-active) {border-color:#d7e5da;color:#003f1a;}
.portal-theme-toggle {border-color:#dfe1e7;}
.portal-login-card {
  background:linear-gradient(145deg,#fff 0%,#fffefa 58%,#fff7e9 100%);
  border-color:#dce8df;
}
.portal-login-brand {background:linear-gradient(135deg,#007a33,#005c26);color:#ffd51f;}
.portal-login-card h2 {color:#003f1a;}
.portal-login-subtitle,.portal-login-register {color:#657068;}
.portal-login-register a {color:#007a33;}
.portal-login-card .action-button.primary-button {
  background:linear-gradient(135deg,#007a33,#005c26);
  color:#fff;
}
.portal-login-card .action-button.primary-button:hover {
  background:linear-gradient(135deg,#006a2e,#003f1a);
}
.portal-login-card .field-control input:focus,
.portal-login-card .field-control select:focus {
  border-color:#007a33;
  box-shadow:0 0 0 3px rgba(0,122,51,.10);
}
.portal-login-card .section-heading {color:#005c26;}
.portal-login-card .section-heading:after {background:#dce8df;}
html[data-rems-theme="dark"] .portal-viewport {
  background:
    radial-gradient(circle at 85% 12%,rgba(255,213,31,.05),transparent 40%),
    linear-gradient(112deg,#001b0c 0%,#003f1a 64%,#005c26 100%);
}
html[data-rems-theme="dark"] .portal-banner {background:transparent;}
html[data-rems-theme="dark"] .portal-nav-actions .portal-nav-active,
html[data-rems-theme="dark"] .portal-nav-actions .portal-nav-signup.portal-nav-active {
  background:#007a33;border-color:#2c9a39;color:#fff;
}
html[data-rems-theme="dark"] .portal-login-card .action-button.primary-button {
  background:linear-gradient(135deg,#007a33,#005c26);border-color:#2c9a39;
}

/* Complete the rectangle and circle past the left-panel boundary.
   Keep the beige login/registration card layered above the decoration. */
@media (min-width:1001px) {
    .portal-hero-grid {
        position:relative;
        isolation:isolate;
    }
    .portal-banner {
        overflow:visible;
    }
    .portal-banner::before,
    .portal-banner::after,
    .portal-banner-art,
    .portal-banner-art::after {
        pointer-events:none;
    }
    .portal-login-card {
        position:relative;
        z-index:1;
    }
}


/* Extra-light warm beige for BOTH Sign In and Create Account.
   Almost white, with only a subtle cream tint; all behavior unchanged. */
html:not([data-rems-theme="dark"]) .portal-login-card,
html:not([data-rems-theme="dark"]) .portal-login-card.portal-signup-card {
    background: linear-gradient(145deg,
        #fffefc 0%,
        #fefcf8 55%,
        #fcf8f0 100%);
    border-color: #eeeae2;
}


/* Create Account: small institutional-email reminder directly under Email. */
.portal-signup-card .signup-email-note{display:block;margin:0 0 8px 2px;color:#68786d;font-size:11px;line-height:1.5;}
html[data-rems-theme="dark"] .portal-signup-card .signup-email-note{color:#c1d0c2;}

/* CREATE ACCOUNT — optional profile picture picker. */
.portal-login-card .signup-photo-control {
    display:flex;
    align-items:center;
    gap:22px;
    padding:17px 20px;
    border:1px solid #dbe8df;
    border-radius:18px;
    background:rgba(255,255,255,.70);
}
.portal-login-card .signup-photo-circle {
    flex:0 0 94px;
    width:94px;
    height:94px;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
    border:2px solid #cfe8d6;
    border-radius:50%;
    color:#007a33;
    background:#edf7ef;
    font-size:34px;
}
.portal-login-card .signup-photo-circle img {
    display:block;
    width:100%;
    height:100%;
    object-fit:cover;
}
.portal-login-card .signup-photo-circle img[hidden] { display:none; }
.portal-login-card .signup-photo-info {
    display:flex;
    flex-direction:column;
    align-items:flex-start;
    gap:9px;
    min-width:0;
}
.portal-login-card .signup-photo-info input[type=file] {
    position:absolute;
    width:1px;
    height:1px;
    padding:0;
    overflow:hidden;
    opacity:0;
    clip-path:inset(50%);
}
.portal-login-card .signup-photo-actions {display:flex;align-items:center;flex-wrap:wrap;gap:10px;}
.portal-login-card .signup-photo-pick {
    display:inline-flex;
    justify-content:center;
    align-items:center;
    gap:8px;
    min-height:42px;
    padding:9px 15px;
    border:1px solid #b6d9c0;
    border-radius:11px;
    background:#fff;
    color:#006b30;
    font-size:12px;
    font-weight:850;
    cursor:pointer;
}
.portal-login-card .signup-photo-pick:hover {background:#eef7f0;border-color:#9bcaa9;}
.portal-login-card .signup-photo-info:has(input:focus-visible) .signup-photo-pick {
    outline:3px solid rgba(0,122,51,.30);
    outline-offset:2px;
}
.portal-login-card .signup-photo-clear {
    border:0;
    background:transparent;
    color:#a04c33;
    font-size:12px;
    font-weight:850;
    cursor:pointer;
}
.portal-login-card .signup-photo-clear[hidden] {display:none;}
.portal-login-card .signup-photo-help,
.portal-login-card .signup-photo-filename {
    font-size:11px;
    line-height:1.5;
    color:#75857a;
    overflow-wrap:anywhere;
}
.portal-login-card .signup-photo-filename {font-weight:800;color:#006b30;}
html[data-rems-theme="dark"] .portal-login-card .signup-photo-control {
    background:rgba(255,255,255,.04);
    border-color:#4a6651;
}
html[data-rems-theme="dark"] .portal-login-card .signup-photo-circle {
    background:#1b3d2d;
    border-color:#4d735b;
    color:#d4e7d9;
}
html[data-rems-theme="dark"] .portal-login-card .signup-photo-pick {
    background:#21432c;
    color:#e3f7e6;
    border-color:#587b61;
}
html[data-rems-theme="dark"] .portal-login-card .signup-photo-help {color:#c1d0c2;}
html[data-rems-theme="dark"] .portal-login-card .signup-photo-filename {color:#c2f2ce;}
@media(max-width:500px) {
    .portal-login-card .signup-photo-control {gap:12px;padding:13px;}
    .portal-login-card .signup-photo-circle {flex-basis:67px;width:67px;height:67px;font-size:25px;}
    .portal-login-card .signup-photo-pick {font-size:11px;min-height:37px;padding:8px 11px;}
}


/* ASU-REMS logo: single image used in public navigation and the sign-in / registration card. */
.portal-navbar .portal-logo-icon,
.portal-login-card .portal-login-brand {
    background: #ffffff !important;
    border: 1px solid rgba(0, 92, 38, .18);
    border-radius: 50% !important;
    padding: 3px;
    overflow: hidden;
    box-shadow: 0 5px 16px rgba(0, 63, 26, .08);
}
.portal-navbar .portal-logo-icon img,
.portal-login-card .portal-login-brand img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 50%;
    flex: none;
}
html[data-rems-theme="dark"] .portal-navbar .portal-logo-icon,
html[data-rems-theme="dark"] .portal-login-card .portal-login-brand {
    background:#ffffff !important;
    border-color:rgba(255, 213, 31, .45);
}

/* Keep the ASU-REMS public navigation visible while scrolling.
   This affects positioning only; existing colors and controls remain unchanged. */
.portal-navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    width: 100%;
    z-index: 1000;
}

/* Allow one natural page scrollbar, with content below the fixed navbar. */
html:has(.portal-navbar) {
    overflow-x: hidden !important;
    overflow-y: auto !important;
}
body:has(.portal-navbar) {
    height: auto !important;
    min-height: 100% !important;
    overflow-x: hidden !important;
    overflow-y: visible !important;
    padding-top: 82px;
}

@media (max-width: 1000px) {
    body:has(.portal-navbar) { padding-top: 75px; }
}
@media (max-width: 600px) {
    body:has(.portal-navbar) { padding-top: 69px; }
}

</style>

<header class="portal-navbar" aria-label="ASU-REMS public navigation">
    <a class="portal-logo" href="<?= BASE_URL; ?>/login.php" aria-label="ASU-REMS home">
        <span class="portal-logo-icon"><img src="<?= htmlspecialchars($asuRemsLogoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="ASU-REMS logo" loading="eager" decoding="async"></span>
        <span class="portal-logo-copy"><strong>ASU-REMS</strong><small>Aklan State University</small></span>
    </a>
    <nav class="portal-nav-actions" aria-label="Public pages">
        <button type="button" id="portalThemeToggle" data-rems-theme-toggle class="portal-theme-toggle" aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false"><i class="bi bi-moon-fill" aria-hidden="true"></i></button>
        <a class="portal-nav-signup <?= $view === 'signup' ? 'portal-nav-active' : ''; ?>" href="<?= BASE_URL; ?>/login.php?view=signup" <?= $view === 'signup' ? 'aria-current="page"' : ''; ?>>Create Account</a>
        <a class="portal-nav-login <?= $view !== 'signup' ? 'portal-nav-active' : ''; ?>" href="<?= BASE_URL; ?>/login.php?view=login" <?= $view !== 'signup' ? 'aria-current="page"' : ''; ?>>Sign In</a>
        <a class="portal-nav-about" href="<?= BASE_URL; ?>/about.php">About</a>
    </nav>
</header>
<main class="portal-viewport" id="portalHome">
    <div class="portal-hero-grid">
        <section class="portal-banner" aria-label="Research and Extension Management System">
            <div class="portal-banner-art" aria-hidden="true"></div>
            <div class="portal-banner-body">
                <div class="portal-banner-headline">
                    <h1>
                        <span>Research &amp;</span>
                        <span class="portal-title-gold">Extension</span>
                        <span>Management</span>
                        <span>System</span>
                    </h1>
                </div>
            </div>
        </section>
        <section class="portal-login-card <?= $view === 'signup' ? 'portal-signup-card' : ''; ?>" aria-labelledby="portalFormTitle">
            <div class="portal-login-inner">
                <div class="portal-login-brand"><img src="<?= htmlspecialchars($asuRemsLogoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="ASU-REMS logo" loading="eager" decoding="async"></div>
                <h2 id="portalFormTitle"><?= $view === 'signup' ? 'Create Account' : 'Sign In'; ?></h2>
                <?php if ($view !== 'signup'): ?>
                    <p class="portal-login-subtitle">Access your research and extension workspace using your account credentials.</p>
                <?php endif; ?>
                <?php if ($flash): ?>
                    <div class="alert-box <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-danger'; ?>"><i class="bi bi-info-circle"></i><span><?= e($flash['message']); ?></span></div>
                <?php endif; ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert-box alert-danger"><i class="bi bi-exclamation-circle"></i><span><?= e($error); ?></span></div>
                <?php endforeach; ?>
                <?php if ($success !== ''): ?>
                    <div class="alert-box alert-success"><i class="bi bi-check-circle"></i><span><?= e($success); ?></span></div>
                <?php endif; ?>
                <?php if ($view === 'signup'): ?>
                    <!-- Existing registration fields and server-side processing are unchanged. -->
                    <form method="POST" action="<?= BASE_URL; ?>/login.php?view=signup" enctype="multipart/form-data" autocomplete="on">
                                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
                                        <input type="hidden" name="action" value="signup">
                    
                                        <div class="section-heading"><i class="bi bi-shield-lock"></i> Account Details</div>
                                        <div class="field-group">
                                            <label class="field-label" for="email">Email</label>
                                            <small id="signupEmailNote" class="signup-email-note">Please use your official school email address.</small>
                                            <div class="field-control"><i class="bi bi-envelope"></i><input id="email" name="email" type="email" maxlength="190" placeholder="Email address" autocomplete="email" aria-describedby="signupEmailNote" value="<?= e(posted('email')); ?>" required></div>
                                        </div>
                                        <div class="field-grid">
                                            <div class="field-group">
                                                <label class="field-label" for="signup_password">Password</label>
                                                <div class="field-control"><i class="bi bi-lock"></i><input id="signup_password" name="password" type="password" minlength="8" maxlength="72" placeholder="Password" autocomplete="new-password" required><button type="button" class="password-toggle" data-toggle-password="signup_password" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
                                            </div>
                                            <div class="field-group">
                                                <label class="field-label" for="confirm_password">Confirm Password</label>
                                                <div class="field-control"><i class="bi bi-shield-lock"></i><input id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="72" placeholder="Confirm password" autocomplete="new-password" required><button type="button" class="password-toggle" data-toggle-password="confirm_password" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
                                            </div>
                                        </div>
                    
                                        <div class="section-heading"><i class="bi bi-person-vcard"></i> Personal Information</div>
                                        <div class="signup-photo-group field-group">
                                            <label class="field-label" for="profile_photo">Profile Picture</label>
                                            <div class="signup-photo-control">
                                                <div class="signup-photo-circle" aria-hidden="true">
                                                    <i class="bi bi-person-fill" id="signupPhotoIcon"></i>
                                                    <img id="signupPhotoPreview" alt="" hidden>
                                                </div>
                                                <div class="signup-photo-info">
                                                    <div class="signup-photo-actions">
                                                        <label class="signup-photo-pick" for="profile_photo"><i class="bi bi-upload" aria-hidden="true"></i> Choose Picture</label>
                                                        <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" aria-describedby="profilePhotoHelp">
                                                        <button class="signup-photo-clear" id="signupPhotoClear" type="button" hidden>Remove</button>
                                                    </div>
                                                    <span id="profilePhotoHelp" class="signup-photo-help">JPG, PNG, or WEBP · Maximum 2 MB. This photo will be used as your account avatar.</span>
                                                    <span id="profilePhotoFilename" class="signup-photo-filename" aria-live="polite"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label" for="title">Title</label>
                                            <div class="field-control"><i class="bi bi-person"></i><select id="title" name="title" required><option value="">Select</option><?php foreach ($titles as $option): ?><option value="<?= e($option); ?>" <?= posted('title') === $option ? 'selected' : ''; ?>><?= e($option); ?></option><?php endforeach; ?></select></div>
                                        </div>
                                        <!-- FULL-WIDTH NAME FIELDS: one textbox per row -->
                                        <div class="name-fields">
                                        <div class="field-group">
                                            <label class="field-label" for="first_name">First Name</label>
                                            <div class="field-control"><i class="bi bi-person"></i><input id="first_name" name="first_name" placeholder="Enter your first name" maxlength="100" value="<?= e(posted('first_name')); ?>" required></div>
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label" for="middle_name">Middle Name</label>
                                            <div class="field-control"><i class="bi bi-person"></i><input id="middle_name" name="middle_name" placeholder="Enter your middle name (optional)" maxlength="100" value="<?= e(posted('middle_name')); ?>"></div>
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label" for="last_name">Last Name</label>
                                            <div class="field-control"><i class="bi bi-person"></i><input id="last_name" name="last_name" placeholder="Enter your last name" maxlength="100" value="<?= e(posted('last_name')); ?>" required></div>
                                        </div>
                                        </div><!-- /.name-fields -->
                                        <div class="field-group">
                                            <label class="field-label" for="extension">Name Extension</label>
                                            <div class="field-control"><i class="bi bi-person-badge"></i><select id="extension" name="extension"><?php foreach ($extensions as $option): ?><option value="<?= e($option); ?>" <?= posted('extension') === $option ? 'selected' : ''; ?>><?= $option === '' ? 'None' : e($option); ?></option><?php endforeach; ?></select></div>
                                        </div>
                                        <div class="field-grid three">
                                            <div class="field-group"><label class="field-label" for="birth_date">Date of Birth</label><div class="field-control"><i class="bi bi-calendar-event"></i><input id="birth_date" name="birth_date" type="date" max="<?= date('Y-m-d'); ?>" value="<?= e(posted('birth_date')); ?>" required></div></div>
                                            <div class="field-group"><label class="field-label" for="civil_status">Civil Status</label><div class="field-control"><i class="bi bi-people"></i><select id="civil_status" name="civil_status" required><option value="">Select</option><?php foreach ($civilStatuses as $option): ?><option value="<?= e($option); ?>" <?= posted('civil_status') === $option ? 'selected' : ''; ?>><?= e($option); ?></option><?php endforeach; ?></select></div></div>
                                            <div class="field-group"><label class="field-label" for="gender">Gender</label><div class="field-control"><i class="bi bi-person"></i><select id="gender" name="gender" required><option value="" disabled hidden <?= posted('gender') === '' ? 'selected' : ''; ?>>Select</option><?php foreach ($genderOptions as $option): ?><option value="<?= e($option); ?>" <?= posted('gender') === $option ? 'selected' : ''; ?>><?= e($option); ?></option><?php endforeach; ?></select></div></div>
                                        </div>
                                        <div class="field-group"><label class="field-label" for="citizenship">Citizenship</label><div class="field-control"><i class="bi bi-person-check"></i><input id="citizenship" name="citizenship" placeholder="e.g., Filipino" maxlength="100" value="<?= e(posted('citizenship')); ?>" required></div></div>
                    
                                        <div class="section-heading"><i class="bi bi-geo-alt"></i> Address and Contact</div>
                                        <div class="field-grid">
                                            <div class="field-group"><label class="field-label" for="country">Country</label><div class="field-control"><i class="bi bi-globe"></i><input id="country" name="country" maxlength="100" placeholder="Country" value="<?= e(posted('country') ?: 'Philippines'); ?>" required></div></div>
                                            <div class="field-group"><label class="field-label" for="region">Region</label><div class="field-control"><i class="bi bi-map"></i><input id="region" name="region" maxlength="100" placeholder="Region" value="<?= e(posted('region')); ?>" required></div></div>
                                            <div class="field-group"><label class="field-label" for="province">Province</label><div class="field-control"><i class="bi bi-map"></i><input id="province" name="province" maxlength="100" placeholder="Province" value="<?= e(posted('province')); ?>" required></div></div>
                                            <div class="field-group"><label class="field-label" for="city">Town / City</label><div class="field-control"><i class="bi bi-buildings"></i><input id="city" name="city" maxlength="100" placeholder="Town or city" value="<?= e(posted('city')); ?>" required></div></div>
                                            <div class="field-group"><label class="field-label" for="barangay">Barangay</label><div class="field-control"><i class="bi bi-geo"></i><input id="barangay" name="barangay" maxlength="100" placeholder="Barangay" value="<?= e(posted('barangay')); ?>" required></div></div>
                                            <div class="field-group"><label class="field-label" for="zip_code">Zip Code</label><div class="field-control"><i class="bi bi-mailbox"></i><input id="zip_code" name="zip_code" maxlength="20" placeholder="Zip code" value="<?= e(posted('zip_code')); ?>" required></div></div>
                                        </div>
                                        <div class="field-group"><label class="field-label" for="street_address">Street / Subdivision</label><div class="field-control"><i class="bi bi-house"></i><input id="street_address" name="street_address" maxlength="190" placeholder="House no., street, subdivision" value="<?= e(posted('street_address')); ?>" required></div></div>
                                        <div class="field-group"><label class="field-label" for="contact_number">Contact Number</label><div class="field-control"><i class="bi bi-telephone"></i><input id="contact_number" name="contact_number" type="tel" maxlength="25" placeholder="e.g., 09XXXXXXXXX" value="<?= e(posted('contact_number')); ?>" required></div></div>
                    
                                        <div class="section-heading"><i class="bi bi-person-gear"></i> Role-Based Access</div>
                                        <div class="field-group">
                                            <label class="field-label" for="role">Role</label>
                                            <div class="field-control"><i class="bi bi-people"></i><select id="role" name="role" required><option value="">Select a role</option><?php foreach ($allowedRoles as $value => $label): ?><option value="<?= e($value); ?>" <?= posted('role') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                                        </div>
                                        <button type="submit" class="action-button primary-button">Submit Registration</button>
                                    </form>
                <?php else: ?>
                    <form method="POST" action="<?= BASE_URL; ?>/login.php?view=login">
                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
                        <input type="hidden" name="action" value="login">
                        <div class="field-group">
                            <label class="field-label" for="portal_login">Email or Username</label>
                            <div class="field-control"><i class="bi bi-person"></i><input type="text" id="portal_login" name="login" autocomplete="username" placeholder="Enter your email or username" value="<?= e($_POST['login'] ?? ''); ?>" required></div>
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="portal_password">Password</label>
                            <div class="field-control"><i class="bi bi-lock"></i><input type="password" id="portal_password" name="password" autocomplete="current-password" placeholder="Enter your password" required><button type="button" class="password-toggle" data-toggle-password="portal_password" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
                        </div>
                        <button class="action-button primary-button" type="submit">Sign In</button>
                    </form>
                    <p class="portal-login-register">Don't have an account? <a href="<?= BASE_URL; ?>/login.php?view=signup">Create Account</a></p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>


<script>
document.addEventListener('DOMContentLoaded',function(){

 document.querySelectorAll('[data-toggle-password]').forEach(function(button){
  button.addEventListener('click',function(){
   const input=document.getElementById(button.getAttribute('data-toggle-password'));
   const icon=button.querySelector('i');if(!input)return;
   const hidden=input.type==='password';input.type=hidden?'text':'password';
   if(icon)icon.className=hidden?'bi bi-eye-slash':'bi bi-eye';
   button.setAttribute('aria-label',hidden?'Hide password':'Show password');
  });
 });

 const photoInput=document.getElementById('profile_photo');
 if (photoInput) {
     const preview=document.getElementById('signupPhotoPreview');
     const icon=document.getElementById('signupPhotoIcon');
     const clear=document.getElementById('signupPhotoClear');
     const filename=document.getElementById('profilePhotoFilename');
     let pictureUrl=null;

     const clearPhoto=function(){
         if (pictureUrl) {URL.revokeObjectURL(pictureUrl);pictureUrl=null;}
         photoInput.value='';
         preview.removeAttribute('src');
         preview.hidden=true;
         icon.hidden=false;
         clear.hidden=true;
         filename.textContent='';
     };
     photoInput.addEventListener('change',function(){
         const photo=photoInput.files && photoInput.files[0];
         if (!photo) {clearPhoto();return;}
         const accepted=['image/jpeg','image/png','image/webp'];
         if (!accepted.includes(photo.type)||photo.size<1||photo.size>2*1024*1024) {
             clearPhoto();
             alert('Please choose a JPG, PNG, or WEBP picture up to 2 MB.');
             return;
         }
         if (pictureUrl) URL.revokeObjectURL(pictureUrl);
         pictureUrl=URL.createObjectURL(photo);
         preview.src=pictureUrl;
         preview.hidden=false;
         icon.hidden=true;
         clear.hidden=false;
         filename.textContent=photo.name;
     });
     clear.addEventListener('click',clearPhoto);
     window.addEventListener('pagehide',function(){if(pictureUrl)URL.revokeObjectURL(pictureUrl);});
 }
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
