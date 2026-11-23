
/*
 * ASU-REMS shared application JavaScript
 * Sidebar open/close is handled by includes/sidebar.php.
 */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const alerts =
            document.querySelectorAll(
                '.auto-dismiss'
            );

        alerts.forEach(
            function (alertElement) {

                setTimeout(
                    function () {

                        if (
                            typeof bootstrap
                            !==
                            'undefined'
                        ) {

                            const alert =
                                bootstrap.Alert
                                    .getOrCreateInstance(
                                        alertElement
                                    );

                            alert.close();
                        }

                    },
                    4000
                );

            }
        );

    }
);
