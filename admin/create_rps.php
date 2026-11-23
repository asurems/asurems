<?php



/**

 * ============================================================

 * ASU-REMS

 * CREATE RPS ACCOUNT

 * ============================================================

 *

 * Admin-only page for creating

 * Research Processing Staff (RPS) accounts.

 */



require_once __DIR__ . '/../config/app.php';

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../includes/functions.php';





/* ============================================================

   LOGIN CHECK

   ============================================================ */



if (empty($_SESSION['user_id'])) {



    redirect(

        BASE_URL . '/login.php'

    );

}





/* ============================================================

   ADMIN ONLY

   ============================================================ */



if (

    ($_SESSION['role'] ?? '')

    !== 'admin'

) {



    http_response_code(403);



    exit(

        'Access denied. Administrator account required.'

    );

}





/* ============================================================

   PAGE DATA

   ============================================================ */



$pageTitle = 'Create RPS Account';



$errors = [];



$success = '';



$form = [



    'first_name' => '',



    'middle_name' => '',



    'last_name' => '',



    'username' => '',



    'email' => ''

];





/* ============================================================

   GET ROLE COLUMN INFORMATION

   ============================================================ */



function getRoleColumnInfo(

    PDO $pdo

): array {



    $stmt =

        $pdo->query(

            "SHOW COLUMNS

             FROM users

             LIKE 'role'"

        );





    $column =

        $stmt->fetch(

            PDO::FETCH_ASSOC

        );





    return $column ?: [];

}





/* ============================================================

   GET ENUM VALUES

   ============================================================ */



function getEnumValues(

    string $type

): array {



    if (

        !str_starts_with(

            strtolower($type),

            'enum('

        )

    ) {



        return [];

    }





    preg_match_all(

        "/'((?:[^'\\\\]|\\\\.)*)'/",

        $type,

        $matches

    );





    $values = [];





    foreach (

        $matches[1] ?? []

        as $value

    ) {



        $values[] =

            str_replace(
                ["\\'", "\\\\"],
                ["'", "\\"],
                $value
            );

    }





    return $values;

}





/* ============================================================

   CHECK IF RPS ROLE IS SUPPORTED

   ============================================================ */



$roleColumn =

    getRoleColumnInfo($pdo);





if (!$roleColumn) {



    die(

        'The users.role column could not be found.'

    );

}





$roleType =

    strtolower(

        (string) $roleColumn['Type']

    );





$isEnum =

    str_starts_with(

        $roleType,

        'enum('

    );





$enumValues =

    $isEnum

        ? getEnumValues($roleType)

        : [];





/*

 * If role is VARCHAR/TEXT,

 * RPS can already be stored.

 *

 * If ENUM,

 * RPS must be one of its allowed values.

 */



$rpsRoleSupported =

    !$isEnum

    ||

    in_array(

        'rps',

        $enumValues,

        true

    );





/* ============================================================

   POST PROCESS

   ============================================================ */



if (

    $_SERVER['REQUEST_METHOD']

    === 'POST'

) {



    /* --------------------------------------------------------

       CSRF

       -------------------------------------------------------- */



    if (

        !verifyCsrfToken(

            $_POST['csrf_token'] ?? ''

        )

    ) {



        $errors[] =

            'Invalid request. Please refresh and try again.';

    }





    $action =

        $_POST['action'] ?? '';





    /* ========================================================

       ENABLE RPS ROLE

       ======================================================== */



    if (

        $action === 'enable_rps_role'

        &&

        !$errors

    ) {



        try {



            /*

             * We only need ALTER TABLE

             * if role is an ENUM.

             */



            if ($isEnum) {



                if (

                    !in_array(

                        'rps',

                        $enumValues,

                        true

                    )

                ) {



                    $newValues =

                        $enumValues;





                    $newValues[] =

                        'rps';





                    /*

                     * Quote ENUM values safely.

                     */



                    $quotedValues = [];





                    foreach (

                        $newValues

                        as $value

                    ) {



                        $quotedValues[] =

                            $pdo->quote(

                                $value

                            );

                    }





                    /*

                     * Preserve NULL / NOT NULL.

                     */



                    $nullSql =



                        strtoupper(

                            $roleColumn['Null']

                            ?? 'NO'

                        ) === 'YES'



                        ? 'NULL'



                        : 'NOT NULL';





                    /*

                     * Preserve default value if present.

                     */



                    $defaultSql = '';





                    if (

                        $roleColumn['Default']

                        !== null

                    ) {



                        $defaultSql =

                            ' DEFAULT '

                            . $pdo->quote(

                                $roleColumn['Default']

                            );

                    }





                    $sql = "



                        ALTER TABLE users



                        MODIFY COLUMN role



                        ENUM(

                            " .

                            implode(

                                ', ',

                                $quotedValues

                            )

                            . "

                        )



                        {$nullSql}



                        {$defaultSql}



                    ";





                    $pdo->exec($sql);

                }

            }





            $success =

                'RPS role has been enabled successfully.';





            /*

             * Reload current column information.

             */



            $roleColumn =

                getRoleColumnInfo($pdo);





            $roleType =

                strtolower(

                    (string)

                    $roleColumn['Type']

                );





            $isEnum =

                str_starts_with(

                    $roleType,

                    'enum('

                );





            $enumValues =

                $isEnum

                    ? getEnumValues(

                        $roleType

                    )

                    : [];





            $rpsRoleSupported =

                !$isEnum

                ||

                in_array(

                    'rps',

                    $enumValues,

                    true

                );





        } catch (Throwable $e) {



            error_log(

                'Enable RPS Role Error: '

                . $e->getMessage()

            );





            $errors[] =

                'Unable to enable the RPS role.';

        }

    }





    /* ========================================================

       CREATE RPS ACCOUNT

       ======================================================== */



    if (

        $action === 'create_rps'

        &&

        !$errors

    ) {



        $form['first_name'] =

            trim(

                $_POST['first_name']

                ?? ''

            );





        $form['middle_name'] =

            trim(

                $_POST['middle_name']

                ?? ''

            );





        $form['last_name'] =

            trim(

                $_POST['last_name']

                ?? ''

            );





        $form['username'] =

            trim(

                $_POST['username']

                ?? ''

            );





        $form['email'] =

            trim(

                $_POST['email']

                ?? ''

            );





        $password =

            $_POST['password']

            ?? '';





        $confirmPassword =

            $_POST['confirm_password']

            ?? '';





        /* ----------------------------------------------------

           ROLE SUPPORT

           ---------------------------------------------------- */



        if (!$rpsRoleSupported) {



            $errors[] =

                'The database does not yet allow the RPS role.';

        }





        /* ----------------------------------------------------

           FIRST NAME

           ---------------------------------------------------- */



        if (

            $form['first_name']

            === ''

        ) {



            $errors[] =

                'First name is required.';

        }





        /* ----------------------------------------------------

           LAST NAME

           ---------------------------------------------------- */



        if (

            $form['last_name']

            === ''

        ) {



            $errors[] =

                'Last name is required.';

        }





        /* ----------------------------------------------------

           USERNAME

           ---------------------------------------------------- */



        if (

            $form['username']

            === ''

        ) {



            $errors[] =

                'Username is required.';

        }





        if (

            !preg_match(

                '/^[A-Za-z0-9._-]{4,50}$/',

                $form['username']

            )

        ) {



            $errors[] =

                'Username must be 4 to 50 characters and may contain letters, numbers, dot, underscore, or dash only.';

        }





        /* ----------------------------------------------------

           EMAIL

           ---------------------------------------------------- */



        if (

            !filter_var(

                $form['email'],

                FILTER_VALIDATE_EMAIL

            )

        ) {



            $errors[] =

                'Valid email is required.';

        }





        /* ----------------------------------------------------

           PASSWORD

           ---------------------------------------------------- */



        if (

            strlen($password)

            < 8

        ) {



            $errors[] =

                'Password must be at least 8 characters.';

        }





        if (

            $password

            !== $confirmPassword

        ) {



            $errors[] =

                'Password confirmation does not match.';

        }





        /* ====================================================

           CREATE ACCOUNT

           ==================================================== */



        if (!$errors) {



            try {



                /*

                 * Duplicate username/email check.

                 */



                $check =

                    $pdo->prepare("

                        SELECT user_id



                        FROM users



                        WHERE

                            username = ?



                            OR email = ?



                        LIMIT 1

                    ");





                $check->execute([



                    $form['username'],



                    $form['email']

                ]);





                if ($check->fetch()) {



                    $errors[] =

                        'Username or email already exists.';



                } else {



                    /*

                     * Same password security used

                     * by your existing register.php.

                     */



                    $hash =

                        password_hash(

                            $password,

                            PASSWORD_DEFAULT

                        );





                    /*

                     * RPS does not need to belong

                     * to a college for now.

                     */



                    $stmt =

                        $pdo->prepare("

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

                                ?, ?, ?, ?, ?,

                                ?, 'rps', NULL,

                                'Active'

                            )

                        ");





                    $stmt->execute([



                        $form['first_name'],



                        $form['middle_name']

                        !== ''

                            ? $form['middle_name']

                            : null,



                        $form['last_name'],



                        $form['username'],



                        $form['email'],



                        $hash

                    ]);





                    $success =

                        'RPS account created successfully.';





                    /*

                     * Clear form.

                     */



                    $form = [



                        'first_name' => '',



                        'middle_name' => '',



                        'last_name' => '',



                        'username' => '',



                        'email' => ''

                    ];

                }





            } catch (Throwable $e) {



                error_log(

                    'Create RPS Account Error: '

                    . $e->getMessage()

                );





                $errors[] =

                    'Unable to create the RPS account.';

            }

        }

    }

}



?>

<!DOCTYPE html>



<html lang="en">



<head>



    <meta charset="UTF-8">



    <meta

        name="viewport"

        content="width=device-width, initial-scale=1"

    >



    <title>

        Create RPS Account

    </title>



    <style>



        * {

            box-sizing: border-box;

        }



        body {

            margin: 0;

            padding: 40px 20px;

            font-family:

                "Century Gothic",

                sans-serif;

            background: #f4f7f5;

            color: #17201a;

        }



        .container {

            width: 100%;

            max-width: 720px;

            margin: auto;

        }



        .card {

            background: #ffffff;

            border: 1px solid #e0e7e2;

            border-radius: 16px;

            padding: 28px;

            box-shadow:

                0 8px 30px

                rgba(0, 0, 0, .06);

        }



        h1 {

            margin:

                0

                0

                8px;

        }



        .subtitle {

            margin:

                0

                0

                25px;

            color: #69756d;

            font-size: 14px;

            line-height: 1.6;

        }



        .alert {

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 12px;

            font-size: 13px;

        }



        .alert-success {

            background: #edf8f0;

            color: #25653a;

            border: 1px solid #cbe5d2;

        }



        .alert-danger {

            background: #fff1f1;

            color: #933b3b;

            border: 1px solid #efcece;

        }



        .alert-warning {

            background: #fff8df;

            color: #745b00;

            border: 1px solid #eee0a6;

        }



        .grid {

            display: grid;

            grid-template-columns:

                1fr

                1fr;

            gap: 15px;

        }



        .field {

            margin-bottom: 15px;

        }



        label {

            display: block;

            margin-bottom: 6px;

            font-size: 12px;

            font-weight: 700;

        }



        input {

            width: 100%;

            height: 46px;

            border: 1px solid #dce5df;

            border-radius: 10px;

            padding: 0 13px;

            outline: none;

        }



        input:focus {

            border-color: #087b37;

            box-shadow:

                0 0 0 3px

                rgba(8, 123, 55, .08);

        }



        button {

            min-height: 46px;

            border: 0;

            border-radius: 10px;

            padding: 0 18px;

            cursor: pointer;

            font-weight: 700;

        }



        .primary {

            width: 100%;

            background: #087b37;

            color: #ffffff;

        }



        .warning-button {

            background: #f4ca24;

            color: #17341f;

        }



        .role-box {

            padding: 14px;

            background: #f6faf7;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 13px;

        }



        .back {

            display: inline-block;

            margin-top: 18px;

            color: #087b37;

            text-decoration: none;

            font-weight: 700;

            font-size: 13px;

        }



        @media (

            max-width: 600px

        ) {



            .grid {

                grid-template-columns:

                    1fr;

                gap: 0;

            }

        }



    </style>



</head>



<body>



<div class="container">



    <div class="card">



        <h1>

            Create RPS Account

        </h1>



        <p class="subtitle">



            Create an authorized Research

            Processing Staff account.



        </p>





        <?php if ($success !== ''): ?>



            <div class="

                alert

                alert-success

            ">



                <?= e($success); ?>



            </div>



        <?php endif; ?>





        <?php foreach (

            $errors

            as $error

        ): ?>



            <div class="

                alert

                alert-danger

            ">



                <?= e($error); ?>



            </div>



        <?php endforeach; ?>





        <div class="role-box">



            Database role type:



            <strong>

                <?= e(

                    $roleColumn['Type']

                    ?? 'Unknown'

                ); ?>

            </strong>



            <br><br>



            RPS role status:



            <strong>



                <?= $rpsRoleSupported

                    ? 'Enabled'

                    : 'Not Yet Enabled'; ?>



            </strong>



        </div>





        <?php if (

            !$rpsRoleSupported

        ): ?>



            <div class="

                alert

                alert-warning

            ">



                Your users table uses an ENUM

                for roles, but <strong>rps</strong>

                is not yet included.



            </div>





            <form method="POST">



                <input

                    type="hidden"

                    name="csrf_token"

                    value="<?= e(

                        generateCsrfToken()

                    ); ?>"

                >



                <input

                    type="hidden"

                    name="action"

                    value="enable_rps_role"

                >





                <button

                    type="submit"

                    class="warning-button"

                >



                    Enable RPS Role



                </button>



            </form>





        <?php else: ?>





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



                <input

                    type="hidden"

                    name="action"

                    value="create_rps"

                >





                <div class="grid">



                    <div class="field">



                        <label>

                            First Name

                        </label>



                        <input

                            type="text"

                            name="first_name"

                            value="<?= e(

                                $form['first_name']

                            ); ?>"

                            required

                        >



                    </div>





                    <div class="field">



                        <label>

                            Last Name

                        </label>



                        <input

                            type="text"

                            name="last_name"

                            value="<?= e(

                                $form['last_name']

                            ); ?>"

                            required

                        >



                    </div>



                </div>





                <div class="field">



                    <label>

                        Middle Name

                    </label>



                    <input

                        type="text"

                        name="middle_name"

                        value="<?= e(

                            $form['middle_name']

                        ); ?>"

                    >



                </div>





                <div class="grid">



                    <div class="field">



                        <label>

                            Username

                        </label>



                        <input

                            type="text"

                            name="username"

                            minlength="4"

                            maxlength="50"

                            value="<?= e(

                                $form['username']

                            ); ?>"

                            required

                        >



                    </div>





                    <div class="field">



                        <label>

                            Email

                        </label>



                        <input

                            type="email"

                            name="email"

                            value="<?= e(

                                $form['email']

                            ); ?>"

                            required

                        >



                    </div>



                </div>





                <div class="grid">



                    <div class="field">



                        <label>

                            Password

                        </label>



                        <input

                            type="password"

                            name="password"

                            minlength="8"

                            required

                        >



                    </div>





                    <div class="field">



                        <label>

                            Confirm Password

                        </label>



                        <input

                            type="password"

                            name="confirm_password"

                            minlength="8"

                            required

                        >



                    </div>



                </div>





                <button

                    type="submit"

                    class="primary"

                >



                    Create RPS Account



                </button>



            </form>



        <?php endif; ?>





        <a

            class="back"

            href="<?= BASE_URL; ?>/dashboard.php"

        >



            ← Back to Dashboard



        </a>



    </div>



</div>



</body>



</html>
