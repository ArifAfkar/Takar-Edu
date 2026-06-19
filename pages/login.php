<?php
/* =======================================================
    SESSION CONFIGURATION
======================================================= */

/* ---------- Secure Cookie Parameters ---------- */
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

/* ---------- Session Initialization ---------- */
session_start();

/* =======================================================
    CSRF TOKEN
======================================================= */

/* ---------- Token Generation ---------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =======================================================
    LOGIN SESSION VALIDATION
======================================================= */

/* ---------- Authenticated User Check ---------- */
if (isset($_SESSION['login_id'])) {
    header('Location: home.php');
    exit;
}

/* =======================================================
    ACCOUNT STATUS MESSAGE
======================================================= */

/* ---------- Default Message ---------- */
$inactiveMessage = '';

/* ---------- Inactive Account Detection ---------- */
if (isset($_GET['inactive']) && $_GET['inactive'] == 1) {
    $inactiveMessage = 'Akun Anda telah dinonaktifkan. Hubungi administrator.';
}
?>


<!DOCTYPE html>
<html lang="id">

<head>

    <!-- =======================================================
        GLOBAL HEADER / ASSETS
    ======================================================= -->
    <?php require_once '../includes/header.php'; ?>

    <!-- ---------- Page Title ---------- -->
    <title>Login - Takar-Edu</title>

</head>

<body>

    <!-- =======================================================
        PAGE WRAPPER
    ======================================================= -->
    <div class="page-center">

        <!-- =======================================================
            LOGIN CONTAINER
        ======================================================= -->
        <main class="auth-container">

            <!-- =======================================================
                LOGIN CARD
            ======================================================= -->
            <div class="auth-card">

                <!-- ---------- Card Header ---------- -->
                <div class="auth-header">

                    <!-- Platform title -->
                    <h1 class="auth-brand-title">
                        Takar-Edu
                    </h1>

                    <!-- Platform subtitle -->
                    <p class="auth-brand-subtitle">
                        Platform Evaluasi Pembelajaran Modern
                    </p>

                </div>

                <!-- ---------- Card Body ---------- -->
                <div class="auth-body">

                    <!-- Page title -->
                    <h2 class="auth-title">
                        Login Akun
                    </h2>

                    <!-- Login error -->
                    <div
                        id="loginError"
                        class="login-error hidden"
                    ></div>

                    <!-- ---------- Account Status Alert ---------- -->
                    <?php if (!empty($inactiveMessage)): ?>

                        <div class="alert alert-danger alert-center">
                            <?= htmlspecialchars($inactiveMessage) ?>
                        </div>

                    <?php endif; ?>

                    <!-- =======================================================
                        LOGIN FORM
                    ======================================================= -->
                    <form id="loginForm" method="POST">

                        <!-- CSRF token -->
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                        >

                        <!-- ---------- Username Section ---------- -->
                        <div class="form-group">

                            <!-- Username label -->
                            <label class="form-label">
                                Username
                            </label>

                            <!-- Username input -->
                            <input
                                type="text"
                                name="username"
                                required
                                autocomplete="username"
                                class="form-input auth-input"
                                autofocus
                            >

                        </div>

                        <!-- ---------- Password Section ---------- -->
                        <div class="form-group">

                            <!-- Password label -->
                            <label class="form-label">
                                Password
                            </label>

                            <!-- Password wrapper -->
                            <div class="password-wrapper">

                                <!-- Password input -->
                                <input
                                    type="password"
                                    name="password"
                                    id="passwordField"
                                    required
                                    autocomplete="current-password"
                                    class="form-input password-input auth-input"
                                >

                                <!-- Password toggle button -->
                                <button
                                    type="button"
                                    id="togglePassword"
                                    aria-label="Toggle password visibility"
                                    class="password-toggle"
                                >
                                    <i
                                        data-lucide="eye"
                                        id="eyeIcon"
                                        class="password-toggle-icon"
                                    ></i>
                                </button>

                            </div>

                        </div>

                        <!-- Submit button -->
                        <button
                            type="submit"
                            id="loginButton"
                            class="btn-primary auth-submit-btn"
                        >
                            Login
                        </button>

                    </form>

                </div>

            </div>

        </main>

    </div>


    <script>
    /* =======================================================
        INITIALIZE LUCIDE ICONS
    ======================================================= */
    lucide.createIcons();

    /* =======================================================
        RESPONSIVE SWEETALERT2 CONFIG
    ======================================================= */
    function getResponsiveSwalConfig(icon, title, text) {

        const isMobile = window.innerWidth < 640;

        return {
            icon,
            title,
            text,

            /* ---------- Layout ---------- */
            width: isMobile ? "85%" : "32rem",
            padding: isMobile ? "1.25rem" : "2rem",

            /* ---------- Button ---------- */
            confirmButtonColor: "#2563eb",
            buttonsStyling: true,

            /* ---------- Styling ---------- */
            customClass: {
                popup: "rounded-2xl",
                title: isMobile
                    ? "text-lg font-bold"
                    : "text-2xl font-bold",
                htmlContainer: isMobile
                    ? "text-sm"
                    : "text-base",
                confirmButton: isMobile
                    ? "text-sm px-4 py-2 rounded-xl"
                    : "text-base px-5 py-2 rounded-xl"
            }
        };

    }

    /* =======================================================
        PASSWORD VISIBILITY TOGGLE
    ======================================================= */
    document.getElementById("togglePassword").addEventListener("click", function () {

        // Required elements
        const passwordField = document.getElementById("passwordField");
        const eyeIcon = document.getElementById("eyeIcon");

        // Toggle visibility
        if (passwordField.type === "password") {
            passwordField.type = "text";
            eyeIcon.setAttribute("data-lucide", "eye-off");
        } else {
            passwordField.type = "password";
            eyeIcon.setAttribute("data-lucide", "eye");
        }

        this.setAttribute(
            "aria-label",
            passwordField.type === "password"
                ? "Tampilkan password"
                : "Sembunyikan password"
        );

        // Re-render icon
        lucide.createIcons();

    });

    /* =======================================================
        LOGIN FORM SUBMISSION HANDLER
    ======================================================= */
    document.getElementById("loginForm").addEventListener("submit", function (e) {

        /* ---------- Prevent default ---------- */
        e.preventDefault();

        /* ---------- References ---------- */
        const form = this;
        const btn = document.getElementById("loginButton");

        const loginError = document.getElementById("loginError");

        /* ---------- Reset inline error ---------- */
        loginError.classList.add("hidden");
        loginError.textContent = "";

        /* =======================================================
            SANITIZATION
        ======================================================= */

        // Trim username
        form.username.value = form.username.value.trim();

        /* =======================================================
            BUTTON LOADING STATE
        ======================================================= */

        btn.disabled = true;
        btn.innerHTML = `
            <span class="inline-flex items-center gap-2">
                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                        fill="none"
                    ></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                    ></path>
                </svg>
                Mohon tunggu...
            </span>
        `;

        /* =======================================================
            SEND LOGIN REQUEST
        ======================================================= */
        fetch("../auth/login_auth.php", {
            method: "POST",
            body: new FormData(form),
            credentials: "same-origin"
        })

        /* =======================================================
            RAW RESPONSE
        ======================================================= */
        .then(response => response.text())

        /* =======================================================
            PROCESS RESULT
        ======================================================= */
        .then(resp => {

            const result = resp.trim();

            /* =======================================================
                SUCCESS LOGIN
            ======================================================= */
            if (result === "1") {

                Swal.fire({
                    toast: true,
                    position: "top-end",
                    icon: "success",
                    title: "Login berhasil",
                    showConfirmButton: false,
                    timer: 1500,
                    timerProgressBar: true,
                    customClass: {
                        popup: "rounded-2xl shadow-xl",
                        title: "font-semibold text-gray-800"
                    }
                }).then(() => {
                    window.location.href = "home.php";
                });

                return;
            }

            /* =======================================================
                ERROR MESSAGE MAPPING
            ======================================================= */
            let message = "Username atau password salah.";

            switch (result) {

                case "3":
                    message = "Akun Anda telah dinonaktifkan. Hubungi administrator.";
                    break;

                case "4":
                    message = "Terjadi kesalahan sistem.";
                    break;

                case "5":
                    message = "Permintaan tidak valid.";
                    break;

                case "6":
                    message = "Terlalu banyak percobaan login. Coba lagi dalam 1 menit.";
                    break;

            }

            /* =======================================================
                SHOW LOGIN FAILURE
            ======================================================= */
            const loginError = document.getElementById("loginError");

            if (result === "2" || result === "3") {

                /* =======================================================
                    INLINE ERROR (PASSWORD SALAH / AKUN NONAKTIF)
                ======================================================= */
                loginError.textContent = message;
                loginError.classList.remove("hidden");

            } else {

                /* =======================================================
                    SWEETALERT ERROR (RATE LIMIT / SYSTEM)
                ======================================================= */
                Swal.fire(
                    getResponsiveSwalConfig(
                        "error",
                        "Login Gagal",
                        message
                    )
                );

            }

            /* =======================================================
                RESET BUTTON
            ======================================================= */
            btn.disabled = false;
            btn.innerHTML = "Login";

        })

        /* =======================================================
            NETWORK / SERVER ERROR
        ======================================================= */
        .catch(() => {

            Swal.fire(
                getResponsiveSwalConfig(
                    "error",
                    "Koneksi Gagal",
                    "Terjadi kesalahan koneksi ke server."
                )
            );

            btn.disabled = false;
            btn.innerText = "Login";

        });

    });
    </script>

</body>
</html>