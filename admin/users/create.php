<?php



require_once __DIR__ . '/../../includes/auth.php';



requireRole('admin');



$user = currentUser();



$pageTitle = 'Add User';

$activePage = 'users';



$errors = [];





/*

|--------------------------------------------------------------------------

| GET ACTIVE COLLEGES

|--------------------------------------------------------------------------

*/



$collegeStmt = $pdo->query("

    SELECT

        college_id,

        college_name

    FROM colleges

    WHERE status = 'Active'

    ORDER BY college_name

");



$colleges = $collegeStmt->fetchAll();





/*

|--------------------------------------------------------------------------

| FORM SUBMISSION

|--------------------------------------------------------------------------

*/



if ($_SERVER['REQUEST_METHOD'] === 'POST') {



    /*

    |--------------------------------------------------------------------------

    | CSRF VALIDATION

    |--------------------------------------------------------------------------

    */



    if (

        !verifyCsrfToken(

            $_POST['csrf_token'] ?? ''

        )

    ) {



        $errors[] =

            'Invalid request. Please refresh and try again.';

    }





    /*

    |--------------------------------------------------------------------------

    | GET VALUES

    |--------------------------------------------------------------------------

    */



    $firstName =

        trim($_POST['first_name'] ?? '');



    $middleName =

        trim($_POST['middle_name'] ?? '');



    $lastName =

        trim($_POST['last_name'] ?? '');



    $username =

        trim($_POST['username'] ?? '');



    $email =

        trim($_POST['email'] ?? '');



    $role =

        trim($_POST['role'] ?? '');



    $collegeId =

        !empty($_POST['college_id'])

            ? (int)$_POST['college_id']

            : null;



    $password =

        $_POST['password'] ?? '';



    $confirmPassword =

        $_POST['confirm_password'] ?? '';





    /*

    |--------------------------------------------------------------------------

    | VALID ROLES

    |--------------------------------------------------------------------------

    |

    | Admin can create:

    |

    | - Administrator

    | - Faculty / Researcher

    | - Research Processing Staff (RPS)

    | - Extension Coordinator

    |

    */



    $validRoles = [

        'admin',

        'faculty',

        'rps',

        'extension_coordinator'

    ];





    /*

    |--------------------------------------------------------------------------

    | VALIDATION

    |--------------------------------------------------------------------------

    */



    if ($firstName === '') {



        $errors[] =

            'First name is required.';

    }





    if ($lastName === '') {



        $errors[] =

            'Last name is required.';

    }





    if ($username === '') {



        $errors[] =

            'Username is required.';

    }





    /*

    |--------------------------------------------------------------------------

    | USERNAME FORMAT

    |--------------------------------------------------------------------------

    */



    if (

        $username !== ''

        &&

        !preg_match(

            '/^[A-Za-z0-9._-]{4,50}$/',

            $username

        )

    ) {



        $errors[] =

            'Username must be 4 to 50 characters and may contain letters, numbers, dot, underscore, or dash only.';

    }





    /*

    |--------------------------------------------------------------------------

    | EMAIL

    |--------------------------------------------------------------------------

    */



    if (

        !filter_var(

            $email,

            FILTER_VALIDATE_EMAIL

        )

    ) {



        $errors[] =

            'Please enter a valid email address.';

    }





    /*

    |--------------------------------------------------------------------------

    | ROLE

    |--------------------------------------------------------------------------

    */



    if (

        !in_array(

            $role,

            $validRoles,

            true

        )

    ) {



        $errors[] =

            'Please select a valid user role.';

    }





    /*

    |--------------------------------------------------------------------------

    | PASSWORD

    |--------------------------------------------------------------------------

    */



    if (

        strlen($password) < 8

    ) {



        $errors[] =

            'Password must be at least 8 characters.';

    }





    if (

        $password !==

        $confirmPassword

    ) {



        $errors[] =

            'Password confirmation does not match.';

    }





    /*

    |--------------------------------------------------------------------------

    | ROLE-SPECIFIC COLLEGE ASSIGNMENT

    |--------------------------------------------------------------------------

    |

    | RPS and Administrator accounts do not require

    | a college assignment.

    |

    */



    if (

        $role === 'admin'

        ||

        $role === 'rps'

    ) {



        $collegeId = null;

    }





    /*

    |--------------------------------------------------------------------------

    | CHECK DUPLICATES

    |--------------------------------------------------------------------------

    */



    if (!$errors) {



        $check = $pdo->prepare("

            SELECT user_id

            FROM users

            WHERE username = ?

               OR email = ?

            LIMIT 1

        ");



        $check->execute([

            $username,

            $email

        ]);





        if ($check->fetch()) {



            $errors[] =

                'Username or email already exists.';



        } else {



            /*

            |--------------------------------------------------------------------------

            | PASSWORD HASH

            |--------------------------------------------------------------------------

            */



            $hash =

                password_hash(

                    $password,

                    PASSWORD_DEFAULT

                );





            /*

            |--------------------------------------------------------------------------

            | CREATE USER

            |--------------------------------------------------------------------------

            */



            $stmt = $pdo->prepare("

                INSERT INTO users

                (

                    first_name,

                    middle_name,

                    last_name,

                    username,

                    email,

                    password,

                    role,

                    college_id,

                    status

                )

                VALUES

                (

                    ?, ?, ?, ?, ?, ?, ?, ?,

                    'Active'

                )

            ");





            $stmt->execute([

                $firstName,

                $middleName !== ''

                    ? $middleName

                    : null,

                $lastName,

                $username,

                $email,

                $hash,

                $role,

                $collegeId

            ]);





            /*

            |--------------------------------------------------------------------------

            | SUCCESS MESSAGE

            |--------------------------------------------------------------------------

            */



            $roleLabels = [



                'admin' =>

                    'Administrator',



                'faculty' =>

                    'Faculty / Researcher',



                'rps' =>

                    'Research Processing Staff (RPS)',



                'extension_coordinator' =>

                    'Extension Coordinator'

            ];





            $createdRoleLabel =

                $roleLabels[$role]

                ?? 'User';





            setFlash(

                'success',

                $createdRoleLabel .

                ' account created successfully.'

            );





            redirect(

                BASE_URL .

                '/admin/users/index.php'

            );

        }

    }

}





/*

|--------------------------------------------------------------------------

| USER INITIALS

|--------------------------------------------------------------------------

*/



$initials = '';



if (!empty($user['first_name'])) {



    $initials .= strtoupper(

        substr(

            $user['first_name'],

            0,

            1

        )

    );

}



if (!empty($user['last_name'])) {



    $initials .= strtoupper(

        substr(

            $user['last_name'],

            0,

            1

        )

    );

}





/*

|--------------------------------------------------------------------------

| LOAD HEADER + SIDEBAR

|--------------------------------------------------------------------------

*/



require __DIR__ . '/../../includes/header.php';



require __DIR__ . '/../../includes/sidebar.php';



?>





<style>



/* =========================================================

   ADD USER PAGE

========================================================= */



.add-user-wrapper {

    max-width: 1050px;

    margin: 0 auto;

}





/* =========================================================

   PAGE TITLE AREA

========================================================= */



.add-user-heading {

    display: flex;

    align-items: center;

    justify-content: space-between;



    gap: 20px;



    margin-bottom: 22px;

}





.add-user-heading-left {

    display: flex;

    align-items: center;



    gap: 14px;

}





.add-user-heading-icon {

    width: 48px;

    height: 48px;



    min-width: 48px;



    display: flex;

    align-items: center;

    justify-content: center;



    border-radius: 12px;



    background: #e8f5eb;



    color: var(--asu-green);



    font-size: 21px;

}





.add-user-heading h1 {

    margin: 0 0 4px;



    font-size: 25px;

    font-weight: 700;



    color: #1b211d;

}





.add-user-heading p {

    margin: 0;



    color: #747c76;



    font-size: 12px;

}







/* =========================================================

   FORM CARD

========================================================= */



.add-user-card {

    overflow: hidden;



    background: #ffffff;



    border:

        1px solid

        var(--asu-border);



    border-radius: 16px;



    box-shadow:

        0 5px 18px

        rgba(30, 64, 34, 0.04);

}





.add-user-card-header {

    display: flex;

    align-items: center;



    gap: 13px;



    padding: 20px 24px;



    border-bottom:

        1px solid #edf1ec;



    background:

        #ffffff;

}





.add-user-card-header-icon {

    width: 38px;

    height: 38px;



    min-width: 38px;



    display: flex;

    align-items: center;

    justify-content: center;



    border-radius: 9px;



    background: #eef7ef;



    color: var(--asu-green);



    font-size: 17px;

}





.add-user-card-header h2 {

    margin: 0 0 2px;



    font-size: 15px;

    font-weight: 700;

}





.add-user-card-header p {

    margin: 0;



    font-size: 11px;



    color: #818982;

}







/* =========================================================

   FORM BODY

========================================================= */



.add-user-card-body {

    padding: 25px;

}





.form-section {

    margin-bottom: 30px;

}





.form-section:last-child {

    margin-bottom: 0;

}





.form-section-title {

    display: flex;

    align-items: center;



    gap: 9px;



    margin-bottom: 16px;



    padding-bottom: 11px;



    border-bottom:

        1px solid #eef2ed;



    color: #273029;



    font-size: 12px;

    font-weight: 700;



    text-transform: uppercase;



    letter-spacing: 0.04em;

}





.form-section-title i {

    color: var(--asu-green);



    font-size: 15px;

}







/* =========================================================

   LABELS

========================================================= */



.add-user-card .form-label {

    margin-bottom: 7px;



    color: #414943;



    font-size: 12px;



    font-weight: 600;

}





.required-mark {

    color: #bd3131;



    margin-left: 2px;

}







/* =========================================================

   INPUTS

========================================================= */



.add-user-card .form-control,

.add-user-card .form-select {

    min-height: 45px;



    padding:

        9px

        13px;



    background: #fbfcfb;



    border:

        1px solid #d8e1d7;



    border-radius: 9px;



    color: #232923;



    font-size: 13px;



    transition:

        border-color 0.18s ease,

        box-shadow 0.18s ease,

        background 0.18s ease;

}





.add-user-card .form-control:focus,

.add-user-card .form-select:focus {

    background: #ffffff;



    border-color:

        #76ac82;



    box-shadow:

        0 0 0

        3px

        rgba(8, 117, 44, 0.08);

}





.add-user-card .input-group-text {

    border:

        1px solid #d8e1d7;



    background:

        #f4f8f4;



    color:

        var(--asu-green);



    border-radius:

        9px 0 0 9px;

}





.add-user-card

.input-group

.form-control {

    border-left: 0;



    border-radius:

        0 9px 9px 0;

}





.form-help {

    display: block;



    margin-top: 6px;



    color: #939a94;



    font-size: 10px;

}







/* =========================================================

   ROLE INFORMATION

========================================================= */



.role-information {

    display: flex;

    align-items: flex-start;



    gap: 10px;



    margin-top: 10px;



    padding: 11px 13px;



    border-radius: 9px;



    background: #f5f9f5;



    color: #697169;



    font-size: 10px;



    line-height: 1.5;

}





.role-information i {

    margin-top: 1px;



    color: var(--asu-green);



    font-size: 13px;

}







/* =========================================================

   PASSWORD BOX

========================================================= */



.password-note {

    display: flex;

    align-items: flex-start;



    gap: 10px;



    margin-top: 14px;



    padding: 12px 14px;



    border-left:

        3px solid

        var(--asu-yellow);



    border-radius: 7px;



    background: #fffbea;



    color: #685d25;



    font-size: 10px;



    line-height: 1.55;

}





.password-note i {

    margin-top: 1px;



    font-size: 13px;

}







/* =========================================================

   ACTION BAR

========================================================= */



.add-user-actions {

    display: flex;

    justify-content: flex-end;

    align-items: center;



    gap: 10px;



    margin-top: 27px;



    padding-top: 20px;



    border-top:

        1px solid #edf1ec;

}





.add-user-actions .btn {

    min-width: 120px;



    min-height: 42px;

}







/* =========================================================

   ERROR BOX

========================================================= */



.form-errors {

    margin-bottom: 20px;



    padding: 16px 18px;



    border:

        1px solid #f1c7c7;



    border-radius: 11px;



    background: #fff5f5;



    color: #822e2e;

}





.form-errors-header {

    display: flex;

    align-items: center;



    gap: 8px;



    margin-bottom: 8px;



    font-size: 12px;

    font-weight: 700;

}





.form-errors ul {

    margin:

        0;



    padding-left:

        20px;



    font-size:

        11px;



    line-height:

        1.7;

}







/* =========================================================

   RESPONSIVE

========================================================= */



@media (max-width: 768px) {



    .add-user-heading {

        align-items: flex-start;

    }





    .add-user-card-body {

        padding: 20px;

    }





    .add-user-actions {

        flex-direction: column-reverse;

    }





    .add-user-actions .btn {

        width: 100%;

    }



}



</style>







<div class="main-page">





    <!-- =====================================================

         TOPBAR

    ====================================================== -->



    <header class="asu-topbar">





        <div class="topbar-left">





            <button

                id="sidebarToggle"

                type="button"

                class="sidebar-toggle"

                aria-label="Open navigation"

            >



                <i class="bi bi-list"></i>



            </button>





            <div class="system-brand-copy">

                <strong>Research &amp; Extension Management System</strong>

                <span>Aklan State University</span>

            </div>





        </div>







        <div class="topbar-user">





            <div class="topbar-user-text">





                <strong>



                    <?= e(

                        fullName($user)

                    ); ?>



                </strong>





                <span>



                    <?= e(

                        roleLabel(

                            $user['role']

                        )

                    ); ?>



                </span>





            </div>







            <div class="user-avatar">



                <?= e($initials); ?>



            </div>





        </div>





    </header>







    <!-- =====================================================

         CONTENT

    ====================================================== -->



    <main class="page-content">





        <div class="add-user-wrapper">





            <!-- =================================================

                 PAGE HEADING

            ================================================== -->



            <div class="add-user-heading">





                <div class="add-user-heading-left">





                    <div class="add-user-heading-icon">



                        <i class="bi bi-person-plus"></i>



                    </div>





                    <div>





                        <h1>



                            Add User



                        </h1>



                        <p>

                            Create a new authorized ASU-REMS user account.

                        </p>





                    </div>





                </div>







                <a

                    href="<?= BASE_URL; ?>/admin/users/index.php"

                    class="btn btn-outline-asu"

                >



                    <i class="bi bi-arrow-left me-1"></i>



                    Back to Users



                </a>





            </div>







            <!-- =================================================

                 ERRORS

            ================================================== -->



            <?php if ($errors): ?>





                <div class="form-errors">





                    <div class="form-errors-header">



                        <i class="bi bi-exclamation-circle"></i>



                        Please check the following:



                    </div>





                    <ul>





                        <?php foreach (

                            $errors

                            as

                            $error

                        ): ?>





                            <li>



                                <?= e($error); ?>



                            </li>





                        <?php endforeach; ?>





                    </ul>





                </div>





            <?php endif; ?>







            <!-- =================================================

                 MAIN CARD

            ================================================== -->



            <section class="add-user-card">





                <!-- HEADER -->



                <div class="add-user-card-header">





                    <div class="add-user-card-header-icon">



                        <i class="bi bi-person-vcard"></i>



                    </div>





                    <div>





                        <h2>



                            Account Information



                        </h2>





                    </div>





                </div>







                <!-- BODY -->



                <div class="add-user-card-body">





                    <form

                        method="POST"

                        autocomplete="off"

                    >





                        <input

                            type="hidden"

                            name="csrf_token"

                            value="<?= e(

                                generateCsrfToken()

                            ); ?>"

                        >







                        <!-- =====================================

                             PERSONAL INFORMATION

                        ====================================== -->



                        <div class="form-section">





                            <div class="form-section-title">



                                <i class="bi bi-person"></i>



                                Personal Information



                            </div>







                            <div class="row g-3">





                                <!-- FIRST NAME -->



                                <div class="col-lg-4 col-md-6">





                                    <label

                                        for="first_name"

                                        class="form-label"

                                    >



                                        First Name



                                        <span class="required-mark">

                                            *

                                        </span>



                                    </label>





                                    <input

                                        type="text"

                                        id="first_name"

                                        name="first_name"

                                        class="form-control"

                                        maxlength="100"

                                        placeholder="Enter first name"

                                        value="<?= e(

                                            $_POST['first_name']

                                            ?? ''

                                        ); ?>"

                                        required

                                    >





                                </div>







                                <!-- MIDDLE NAME -->



                                <div class="col-lg-4 col-md-6">





                                    <label

                                        for="middle_name"

                                        class="form-label"

                                    >



                                        Middle Name



                                    </label>





                                    <input

                                        type="text"

                                        id="middle_name"

                                        name="middle_name"

                                        class="form-control"

                                        maxlength="100"

                                        placeholder="Optional"

                                        value="<?= e(

                                            $_POST['middle_name']

                                            ?? ''

                                        ); ?>"

                                    >





                                </div>







                                <!-- LAST NAME -->



                                <div class="col-lg-4 col-md-6">





                                    <label

                                        for="last_name"

                                        class="form-label"

                                    >



                                        Last Name



                                        <span class="required-mark">

                                            *

                                        </span>



                                    </label>





                                    <input

                                        type="text"

                                        id="last_name"

                                        name="last_name"

                                        class="form-control"

                                        maxlength="100"

                                        placeholder="Enter last name"

                                        value="<?= e(

                                            $_POST['last_name']

                                            ?? ''

                                        ); ?>"

                                        required

                                    >





                                </div>





                            </div>





                        </div>







                        <!-- =====================================

                             LOGIN INFORMATION

                        ====================================== -->



                        <div class="form-section">





                            <div class="form-section-title">



                                <i class="bi bi-shield-lock"></i>



                                Login Information



                            </div>







                            <div class="row g-3">





                                <!-- USERNAME -->



                                <div class="col-md-6">





                                    <label

                                        for="username"

                                        class="form-label"

                                    >



                                        Username



                                        <span class="required-mark">

                                            *

                                        </span>



                                    </label>





                                    <div class="input-group">





                                        <span class="input-group-text">



                                            <i class="bi bi-person-circle"></i>



                                        </span>





                                        <input

                                            type="text"

                                            id="username"

                                            name="username"

                                            class="form-control"

                                            minlength="4"

                                            maxlength="50"

                                            placeholder="Enter username"

                                            value="<?= e(

                                                $_POST['username']

                                                ?? ''

                                            ); ?>"

                                            required

                                        >





                                    </div>

</div>







                                <!-- EMAIL -->



                                <div class="col-md-6">





                                    <label

                                        for="email"

                                        class="form-label"

                                    >



                                        Email Address



                                        <span class="required-mark">

                                            *

                                        </span>



                                    </label>





                                    <div class="input-group">





                                        <span class="input-group-text">



                                            <i class="bi bi-envelope"></i>



                                        </span>





                                        <input

                                            type="email"

                                            id="email"

                                            name="email"

                                            class="form-control"

                                            maxlength="150"

                                            placeholder="name@example.com"

                                            value="<?= e(

                                                $_POST['email']

                                                ?? ''

                                            ); ?>"

                                            required

                                        >





                                    </div>





                                </div>





                            </div>





                        </div>







                        <!-- =====================================

                             ROLE & ASSIGNMENT

                        ====================================== -->



                        <div class="form-section">





                            <div class="form-section-title">



                                <i class="bi bi-person-gear"></i>



                                Role & Institutional Assignment



                            </div>







                            <div class="row g-3">





                                <!-- ROLE -->



                                <div class="col-md-6">





                                    <label

                                        for="role"

                                        class="form-label"

                                    >



                                        User Role



                                        <span class="required-mark">

                                            *

                                        </span>



                                    </label>





                                    <select

                                        id="role"

                                        name="role"

                                        class="form-select"

                                        required

                                    >





                                        <option value="">



                                            Select Role



                                        </option>





                                        <option

                                            value="admin"

                                            <?= (

                                                $_POST['role']

                                                ?? ''

                                            ) === 'admin'

                                                ? 'selected'

                                                : ''; ?>

                                        >



                                            Administrator



                                        </option>





                                        <option

                                            value="faculty"

                                            <?= (

                                                $_POST['role']

                                                ?? ''

                                            ) === 'faculty'

                                                ? 'selected'

                                                : ''; ?>

                                        >



                                            Faculty / Researcher



                                        </option>





                                        <option

                                            value="rps"

                                            <?= (

                                                $_POST['role']

                                                ?? ''

                                            ) === 'rps'

                                                ? 'selected'

                                                : ''; ?>

                                        >



                                            Research Processing Staff (RPS)



                                        </option>





                                        <option

                                            value="extension_coordinator"

                                            <?= (

                                                $_POST['role']

                                                ?? ''

                                            ) ===

                                            'extension_coordinator'

                                                ? 'selected'

                                                : ''; ?>

                                        >



                                            Extension Coordinator



                                        </option>





                                    </select>







                                    <div class="role-information">



                                        <i class="bi bi-info-circle"></i>



                                        <span id="roleDescription">



                                            The selected role determines

                                            which ASU-REMS modules and

                                            functions the user may access.



                                        </span>



                                    </div>





                                </div>







                                <!-- COLLEGE -->



                                <div class="col-md-6">





                                    <label

                                        for="college_id"

                                        class="form-label"

                                    >



                                        College



                                    </label>





                                    <select

                                        id="college_id"

                                        name="college_id"

                                        class="form-select"

                                    >





                                        <option value="">



                                            Not Assigned



                                        </option>







                                        <?php foreach (

                                            $colleges

                                            as

                                            $college

                                        ): ?>





                                            <option

                                                value="<?= (int)$college['college_id']; ?>"

                                                <?= (

                                                    (int)(

                                                        $_POST['college_id']

                                                        ?? 0

                                                    )

                                                    ===

                                                    (int)$college['college_id']

                                                )

                                                    ? 'selected'

                                                    : ''; ?>

                                            >



                                                <?= e(

                                                    $college['college_name']

                                                ); ?>



                                            </option>





                                        <?php endforeach; ?>





                                    </select>





                                    <span

                                        class="form-help"

                                        id="collegeHelp"

                                    >



                                        College assignment may be

                                        updated later if necessary.



                                    </span>





                                </div>





                            </div>





                        </div>







                        <!-- =====================================

                             PASSWORD

                        ====================================== -->



                        <div class="form-section">





                            <div class="form-section-title">



                                <i class="bi bi-key"></i>



                                Account Password



                            </div>







                            <div class="row g-3">





                                <!-- PASSWORD -->



                                <div class="col-md-6">





                                    <label

                                        for="password"

                                        class="form-label"

                                    >



                                        Temporary Password



                                        <span class="required-mark">

                                            *

                                        </span>



                                    </label>





                                    <div class="input-group">





                                        <span class="input-group-text">



                                            <i class="bi bi-lock"></i>



                                        </span>





                                        <input

                                            type="password"

                                            id="password"

                                            name="password"

                                            class="form-control"

                                            minlength="8"

                                            autocomplete="new-password"

                                            placeholder="Minimum 8 characters"

                                            required

                                        >





                                    </div>





                                </div>







                                <!-- CONFIRM -->



                                <div class="col-md-6">





                                    <label

                                        for="confirm_password"

                                        class="form-label"

                                    >



                                        Confirm Password



                                        <span class="required-mark">

                                            *

                                        </span>



                                    </label>





                                    <div class="input-group">





                                        <span class="input-group-text">



                                            <i class="bi bi-shield-check"></i>



                                        </span>





                                        <input

                                            type="password"

                                            id="confirm_password"

                                            name="confirm_password"

                                            class="form-control"

                                            minlength="8"

                                            autocomplete="new-password"

                                            placeholder="Re-enter password"

                                            required

                                        >





                                    </div>





                                </div>





                            </div>





                        </div>







                        <!-- =====================================

                             ACTION BUTTONS

                        ====================================== -->



                        <div class="add-user-actions">

<button

                                type="submit"

                                class="btn btn-asu"

                            >



                                <i class="bi bi-person-check me-1"></i>



                                Create User



                            </button>





                        </div>





                    </form>





                </div>





            </section>

</div>





    </main>





</div>





<script>



document.addEventListener(

    'DOMContentLoaded',

    function () {



        const role =

            document.getElementById(

                'role'

            );



        const college =

            document.getElementById(

                'college_id'

            );



        const collegeHelp =

            document.getElementById(

                'collegeHelp'

            );



        const roleDescription =

            document.getElementById(

                'roleDescription'

            );





        if (

            !role

            ||

            !college

        ) {

            return;

        }





        function updateRoleFields() {



            const selectedRole =

                role.value;





            /*

             * ROLE DESCRIPTION

             */



            const descriptions = {



                admin:

                    'Administrator manages system users, accounts, settings, reports, and overall ASU-REMS administration.',



                faculty:

                    'Faculty / Researcher creates, submits, revises, and tracks research records.',



                rps:

                    'RPS processes research submissions, encodes UREC/IACUC, URRC and In-House results, and processes Notice to Proceed and permits.',



                extension_coordinator:

                    'Extension Coordinator manages and processes extension-related activities and records.'

            };





            if (roleDescription) {



                roleDescription.textContent =

                    descriptions[

                        selectedRole

                    ]

                    ||

                    'The selected role determines which ASU-REMS modules and functions the user may access.';

            }





            /*

             * ADMIN AND RPS DO NOT REQUIRE

             * COLLEGE ASSIGNMENT

             */



            if (

                selectedRole === 'admin'

                ||

                selectedRole === 'rps'

            ) {



                college.value = '';



                college.disabled = true;





                if (collegeHelp) {



                    collegeHelp.textContent =

                        selectedRole === 'rps'

                            ? 'RPS accounts are assigned to the Research Office and do not require a college.'

                            : 'Administrator accounts do not require a college assignment.';

                }



            } else {



                college.disabled = false;





                if (collegeHelp) {



                    collegeHelp.textContent =

                        'College assignment may be updated later if necessary.';

                }

            }

        }





        role.addEventListener(

            'change',

            updateRoleFields

        );





        updateRoleFields();

    }

);



</script>





<?php



require __DIR__ . '/../../includes/footer.php';



?>



<style>

/* =========================================================

   ADD USER — UNIFORM ADMIN INTERFACE

========================================================= */



/* Overall typography */

.main-page,

.main-page button,

.main-page input,

.main-page select,

.main-page textarea,

.main-page a {

    font-family: "Century Gothic", sans-serif !important;

}



/* Background */

.main-page {

    background:

        linear-gradient(

            135deg,

            #dff1e3 0%,

            #e8f0e3 34%,

            #f1eee2 68%,

            #f4e6d5 100%

        ) !important;

}



.page-content {

    min-height: calc(100vh - 76px);

    padding: 24px 28px 30px !important;

    background:

        radial-gradient(circle at 90% 5%, rgba(255,213,31,.10), transparent 25%),

        radial-gradient(circle at 8% 7%, rgba(0,122,51,.07), transparent 27%),

        linear-gradient(

            135deg,

            #dff1e3 0%,

            #e8f0e3 34%,

            #f1eee2 68%,

            #f4e6d5 100%

        ) !important;

}



/* =========================================================

   TOPBAR — SAME AS ADMIN DASHBOARD

========================================================= */



.asu-topbar {

    min-height: 76px !important;

    padding: 0 30px !important;

    background: rgba(255,255,255,.97) !important;

    border-bottom: 1px solid rgba(0,122,51,.12) !important;

    box-shadow: 0 10px 26px rgba(16,24,16,.035) !important;

    backdrop-filter: blur(10px);

}



.topbar-left {

    display: flex !important;

    align-items: center !important;

    gap: 14px !important;

}



.system-name {

    display: none !important;

}



.system-brand-copy {

    position: relative;

    padding-left: 18px;

    line-height: 1.15;

}



.system-brand-copy::before {

    content: "";

    position: absolute;

    left: 0;

    top: 50%;

    width: 3px;

    height: 38px;

    transform: translateY(-50%);

    border-radius: 999px;

    background: #007a33;

}



.system-brand-copy strong {

    display: block;

    color: #101810;

    font-size: 17px;

    font-weight: 800;

    letter-spacing: -.015em;

}



.system-brand-copy span {

    display: block;

    margin-top: 4px;

    color: #657068;

    font-size: 13px;

    font-weight: 400;

}



.topbar-user-text strong {

    font-size: 15px !important;

    line-height: 1.1 !important;

}



.topbar-user-text span {

    margin-top: 4px !important;

    font-size: 12px !important;

}



.asu-topbar .user-avatar {

    width: 46px !important;

    height: 46px !important;

    min-width: 46px !important;

    border-radius: 50% !important;

    background: #ffd51f !important;

    color: #003f1a !important;

    font-size: 15px !important;

    font-weight: 900 !important;

    box-shadow: 0 8px 18px rgba(255,213,31,.25) !important;

}



/* =========================================================

   PAGE WIDTH

========================================================= */



.add-user-wrapper {

    max-width: 1180px !important;

    margin: 0 auto !important;

}



/* =========================================================

   PAGE HERO / HEADING

========================================================= */



.add-user-heading {

    position: relative;

    overflow: hidden;

    min-height: 126px;

    margin-bottom: 20px !important;

    padding: 24px 30px !important;

    border-radius: 20px;

    display: flex !important;

    align-items: center !important;

    justify-content: space-between !important;

    gap: 22px !important;

    background:

        linear-gradient(

            112deg,

            #005c26 0%,

            #007a33 64%,

            #258a36 100%

        );

    box-shadow: 0 18px 42px rgba(0,90,40,.16);

}



.add-user-heading::after {

    content: "";

    position: absolute;

    right: -75px;

    top: -90px;

    width: 500px;

    height: 330px;

    transform: rotate(-9deg);

    border-radius: 42px;

    background:

        linear-gradient(

            135deg,

            rgba(255,213,31,.16),

            rgba(255,255,255,.035)

        );

    pointer-events: none;

}



.add-user-heading > * {

    position: relative;

    z-index: 2;

}



.add-user-heading-left {

    gap: 14px !important;

}



.add-user-heading-icon {

    width: 52px !important;

    height: 52px !important;

    min-width: 52px !important;

    border-radius: 15px !important;

    background: #ffd51f !important;

    color: #003f1a !important;

    font-size: 22px !important;

    box-shadow: 0 10px 22px rgba(255,213,31,.20);

}



.add-user-heading h1 {

    margin: 0 !important;

    color: #ffffff !important;

    font-size: 31px !important;

    line-height: 1.05 !important;

    font-weight: 900 !important;

    letter-spacing: -.035em !important;

}



.add-user-heading p {

    display: none !important;

}



/* Back button centered perfectly */

.add-user-heading .btn-outline-asu {

    min-width: 126px !important;

    height: 44px !important;

    min-height: 44px !important;

    padding: 0 16px !important;

    display: inline-flex !important;

    align-items: center !important;

    justify-content: center !important;

    gap: 7px !important;

    border: 1px solid rgba(255,255,255,.36) !important;

    border-radius: 12px !important;

    background: rgba(255,255,255,.10) !important;

    color: #ffffff !important;

    font-size: 11px !important;

    font-weight: 800 !important;

    line-height: 1 !important;

}



.add-user-heading .btn-outline-asu i {

    margin: 0 !important;

    font-size: 14px !important;

    line-height: 1 !important;

}



.add-user-heading .btn-outline-asu:hover {

    background: #ffffff !important;

    color: #005c26 !important;

}



/* =========================================================

   MAIN FORM CARD

========================================================= */



.add-user-card {

    border: 1px solid rgba(0,122,51,.13) !important;

    border-radius: 18px !important;

    background: rgba(255,255,255,.96) !important;

    box-shadow: 0 16px 38px rgba(16,24,16,.06) !important;

}



.add-user-card-header {

    min-height: 66px;

    padding: 14px 18px !important;

    gap: 11px !important;

    border-bottom: 1px solid rgba(0,122,51,.09) !important;

    background: rgba(255,255,255,.84) !important;

}



.add-user-card-header-icon {

    width: 38px !important;

    height: 38px !important;

    min-width: 38px !important;

    border-radius: 11px !important;

    background: rgba(0,122,51,.09) !important;

    color: #007a33 !important;

    font-size: 16px !important;

}



.add-user-card-header h2 {

    margin: 0 !important;

    color: #101810 !important;

    font-family: "Century Gothic", sans-serif !important;

    font-size: 15px !important;

    line-height: 1.15 !important;

    font-weight: 700 !important;

}



.add-user-card-header p {

    display: none !important;

}



.add-user-card-body {

    padding: 22px !important;

}



/* =========================================================

   FORM SECTIONS

========================================================= */



.form-section {

    margin-bottom: 24px !important;

}



.form-section-title {

    margin-bottom: 14px !important;

    padding-bottom: 10px !important;

    gap: 8px !important;

    color: #273029 !important;

    font-family: "Century Gothic", sans-serif !important;

    font-size: 11px !important;

    line-height: 1.2 !important;

    font-weight: 700 !important;

    letter-spacing: .035em !important;

}



.form-section-title i {

    font-size: 14px !important;

}



/* =========================================================

   FORM FIELDS

========================================================= */



.add-user-card .form-label {

    margin-bottom: 6px !important;

    color: #414943 !important;

    font-size: 11px !important;

    font-weight: 600 !important;

}



.add-user-card .form-control,

.add-user-card .form-select {

    min-height: 44px !important;

    height: 44px !important;

    padding: 8px 12px !important;

    border: 1px solid #d8e1d7 !important;

    border-radius: 10px !important;

    background: #fbfcfb !important;

    color: #232923 !important;

    font-size: 12px !important;

}



.add-user-card .form-control:focus,

.add-user-card .form-select:focus {

    background: #ffffff !important;

    border-color: #76ac82 !important;

    box-shadow: 0 0 0 3px rgba(8,117,44,.08) !important;

}



.add-user-card .input-group-text {

    min-height: 44px !important;

    padding-left: 12px !important;

    padding-right: 12px !important;

    border-color: #d8e1d7 !important;

    background: #f4f8f4 !important;

    color: #007a33 !important;

    font-size: 13px !important;

}



.form-help {

    margin-top: 5px !important;

    font-size: 9.5px !important;

}



/* =========================================================

   INFORMATION BOXES

========================================================= */



.role-information {

    margin-top: 9px !important;

    padding: 10px 12px !important;

    border-radius: 9px !important;

    background: rgba(0,122,51,.045) !important;

    font-size: 9.5px !important;

    line-height: 1.45 !important;

}



.password-note {

    margin-top: 12px !important;

    padding: 10px 12px !important;

    border-left: 3px solid #ffd51f !important;

    border-radius: 8px !important;

    background: #fffbea !important;

    font-size: 9.5px !important;

    line-height: 1.5 !important;

}



/* =========================================================

   ACTIONS

========================================================= */



.add-user-actions {

    gap: 9px !important;

    margin-top: 22px !important;

    padding-top: 16px !important;

}



.add-user-actions .btn {

    min-width: 118px !important;

    height: 42px !important;

    min-height: 42px !important;

    padding: 0 15px !important;

    display: inline-flex !important;

    align-items: center !important;

    justify-content: center !important;

    gap: 6px !important;

    border-radius: 11px !important;

    font-size: 11px !important;

    font-weight: 800 !important;

    line-height: 1 !important;

}



.add-user-actions .btn i {

    margin: 0 !important;

    line-height: 1 !important;

}



/* =========================================================

   ERROR PANEL

========================================================= */



.form-errors {

    margin-bottom: 18px !important;

    padding: 13px 15px !important;

    border-radius: 11px !important;

    font-size: 10px !important;

}



.form-errors-header {

    margin-bottom: 6px !important;

    font-size: 11px !important;

}



.form-errors ul {

    font-size: 10px !important;

    line-height: 1.55 !important;

}



/* Hide duplicate local footer if any remains */

.content-footer {

    display: none !important;

}



/* =========================================================

   RESPONSIVE

========================================================= */



@media (max-width: 900px) {

    .system-brand-copy strong {

        font-size: 15px !important;

    }



    .system-brand-copy span {

        font-size: 11px !important;

    }

}



@media (max-width: 768px) {

    .asu-topbar {

        padding: 0 16px !important;

    }



    .system-brand-copy strong {

        font-size: 13px !important;

    }



    .system-brand-copy span {

        font-size: 10px !important;

    }



    .page-content {

        padding: 18px 14px 24px !important;

    }



    .add-user-heading {

        min-height: auto !important;

        align-items: flex-start !important;

        flex-direction: column !important;

        padding: 22px 18px !important;

        border-radius: 18px !important;

    }



    .add-user-heading h1 {

        font-size: 27px !important;

    }



    .add-user-heading .btn-outline-asu {

        width: 100% !important;

    }



    .add-user-card-body {

        padding: 18px !important;

    }

}

</style>





<style>

/* =========================================================

   ADD USER — PLAIN PAGE HEADING

========================================================= */



.add-user-heading {

    position: static !important;

    overflow: visible !important;

    min-height: 0 !important;

    margin-bottom: 18px !important;

    padding: 0 !important;

    border-radius: 0 !important;

    background: transparent !important;

    box-shadow: none !important;

    display: flex !important;

    align-items: center !important;

    justify-content: space-between !important;

    gap: 18px !important;

}



.add-user-heading::after {

    display: none !important;

}



.add-user-heading-left {

    display: flex !important;

    align-items: center !important;

    gap: 12px !important;

}



.add-user-heading-icon {

    width: 44px !important;

    height: 44px !important;

    min-width: 44px !important;

    border-radius: 12px !important;

    background: rgba(0,122,51,.09) !important;

    color: #007a33 !important;

    font-size: 19px !important;

    box-shadow: none !important;

}



.add-user-heading h1 {

    margin: 0 0 3px !important;

    color: #101810 !important;

    font-size: 26px !important;

    line-height: 1.05 !important;

    font-weight: 800 !important;

    letter-spacing: -.025em !important;

}



.add-user-heading p {

    display: block !important;

    margin: 0 !important;

    color: #657068 !important;

    font-size: 11px !important;

    line-height: 1.4 !important;

}



/* Back to Users — simple, compact, same interface family */

.add-user-heading .btn-outline-asu {

    min-width: auto !important;

    height: 40px !important;

    min-height: 40px !important;

    padding: 0 13px !important;

    display: inline-flex !important;

    align-items: center !important;

    justify-content: center !important;

    gap: 6px !important;

    border: 1px solid rgba(0,122,51,.18) !important;

    border-radius: 10px !important;

    background: rgba(255,255,255,.72) !important;

    color: #00692c !important;

    font-size: 10px !important;

    font-weight: 800 !important;

    line-height: 1 !important;

    box-shadow: none !important;

}



.add-user-heading .btn-outline-asu:hover {

    background: rgba(0,122,51,.06) !important;

    border-color: rgba(0,122,51,.28) !important;

    color: #005c26 !important;

}



/* Password note removed */

.password-note {

    display: none !important;

}



@media (max-width: 768px) {

    .add-user-heading {

        align-items: flex-start !important;

        flex-direction: column !important;

    }



    .add-user-heading .btn-outline-asu {

        width: 100% !important;

    }

}

</style>





<style>

/* =========================================================

   POLISHED SEPARATE INPUT BOXES

   Keep the original icon-box + input-box design

========================================================= */



/* Standard standalone fields */

.add-user-card .form-control,

.add-user-card .form-select {

    min-height: 44px !important;

    height: 44px !important;

    padding: 8px 13px !important;

    border: 1px solid #d7e0d8 !important;

    border-radius: 11px !important;

    background: #fbfcfb !important;

    color: #232923 !important;

    font-size: 12px !important;

    box-shadow: none !important;

}



.add-user-card .form-control:focus,

.add-user-card .form-select:focus {

    position: relative;

    z-index: 3;

    background: #ffffff !important;

    border-color: #76ac82 !important;

    box-shadow: 0 0 0 3px rgba(8,117,44,.07) !important;

}



/* ---------------------------------------------------------

   INPUT GROUP

   Keep TWO visible boxes, but make them line up perfectly.

--------------------------------------------------------- */



.add-user-card .input-group {

    display: flex !important;

    align-items: stretch !important;

    flex-wrap: nowrap !important;

}



/* Left icon box */

.add-user-card .input-group-text {

    width: 46px !important;

    min-width: 46px !important;

    height: 44px !important;

    min-height: 44px !important;



    padding: 0 !important;



    display: inline-flex !important;

    align-items: center !important;

    justify-content: center !important;



    border: 1px solid #d7e0d8 !important;

    border-right: 1px solid #d7e0d8 !important;



    border-radius: 11px 0 0 11px !important;



    background: #f4f8f4 !important;

    color: #007a33 !important;



    font-size: 14px !important;

    line-height: 1 !important;



    box-shadow: none !important;

}



/* Right input box */

.add-user-card .input-group > .form-control,

.add-user-card .input-group > .form-select {

    height: 44px !important;

    min-height: 44px !important;



    margin-left: -1px !important;



    padding: 8px 13px !important;



    border: 1px solid #d7e0d8 !important;

    border-radius: 0 11px 11px 0 !important;



    background: #fbfcfb !important;



    box-shadow: none !important;

}



/* Make the connecting seam clean */

.add-user-card .input-group:focus-within .input-group-text {

    border-color: #76ac82 !important;

    background: #f1f8f2 !important;

}



.add-user-card .input-group > .form-control:focus,

.add-user-card .input-group > .form-select:focus {

    z-index: 4 !important;

    margin-left: -1px !important;



    border-color: #76ac82 !important;

    border-radius: 0 11px 11px 0 !important;



    background: #ffffff !important;



    box-shadow:

        0 0 0 3px rgba(8,117,44,.07) !important;

}



/* Placeholder tone */

.add-user-card .form-control::placeholder {

    color: #8a948d !important;

    opacity: 1 !important;

}



/* Keep all field heights visually identical */

.add-user-card .input-group,

.add-user-card .input-group-text,

.add-user-card .input-group > .form-control,

.add-user-card .input-group > .form-select,

.add-user-card > .form-control,

.add-user-card > .form-select {

    box-sizing: border-box !important;

}



/* Slightly cleaner labels */

.add-user-card .form-label {

    margin-bottom: 6px !important;

}



/* Mobile */

@media (max-width: 768px) {

    .add-user-card .form-control,

    .add-user-card .form-select,

    .add-user-card .input-group-text,

    .add-user-card .input-group > .form-control,

    .add-user-card .input-group > .form-select {

        height: 43px !important;

        min-height: 43px !important;

    }



    .add-user-card .input-group-text {

        width: 44px !important;

        min-width: 44px !important;

    }

}

</style>





<style>

/* Final action bar after removing Cancel */

.add-user-actions {

    justify-content: flex-end !important;

}

</style>
