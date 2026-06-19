<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */

require_once '../config/auth.php';
require_once '../config/db_connect.php';

/* =======================================================
    SESSION USER DATA
======================================================= */

$login_id = intval($_SESSION['login_id']);
$name = $_SESSION['login_name'];
$user_type = intval($_SESSION['login_user_type']);

/* =======================================================
    FLASH MESSAGE DATA
======================================================= */

$message = $_SESSION['profile_message'] ?? '';
$messageType = $_SESSION['profile_message_type'] ?? '';

unset(
    $_SESSION['profile_message'],
    $_SESSION['profile_message_type']
);

/* =======================================================
    PROFILE DATA MODE
======================================================= */
/* ---------- AJAX Profile Data ---------- */
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <!-- =======================================================
        GLOBAL HEAD CONFIGURATION
    ======================================================= -->
    <?php require_once '../includes/header.php'; ?>

    <title>Profil Saya - TakarEdu</title>
</head>

<body>

    <!-- =======================================================
        GLOBAL NAVBAR + SIDEBAR
    ======================================================= -->
    <?php require_once '../includes/nav_bar.php'; ?>

    <!-- =======================================================
        MAIN CONTENT AREA
    ======================================================= -->
    <main id="mainContent" class="main-content">

        <!-- =======================================================
            PAGE CONTAINER
        ======================================================= -->
        <section class="page-container">

            <!-- ---------- Back Button ---------- -->
            <div class="mb-4">

                <a href="home.php" class="back-button">
                    <i data-lucide="arrow-left" class="w-3 h-3"></i>
                    Kembali ke Beranda
                </a>

            </div>

            <!-- =======================================================
                PROFILE CONTAINER
            ======================================================= -->
            <div class="profile-container">

                <!-- =======================================================
                    PAGE TITLE
                ======================================================= -->
                <h1 class="page-title">
                    Profil Saya
                </h1>

                <p class="page-description mb-8">
                    Kelola foto profil akun Anda.
                </p>

                <!-- =======================================================
                    PROFILE CONTENT GRID
                ======================================================= -->
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-10 items-center">

                    <!-- =======================================================
                        PROFILE IMAGE
                    ======================================================= -->
                    <div class="lg:col-span-2 flex justify-center">

                        <!-- ---------- Dynamic Profile Preview ---------- -->
                        <div
                            id="profilePreviewContainer"
                            class="profile-preview"
                        >
                            <div
                                id="profilePreview"
                                class="w-full h-full flex items-center justify-center"
                            >
                                <i
                                    data-lucide="user"
                                    class="w-20 h-20 text-white"
                                ></i>
                            </div>
                        </div>

                    </div>

                    <!-- =======================================================
                        PROFILE INFO + ACTION
                    ======================================================= -->
                    <div class="lg:col-span-3">

                        <!-- ---------- User Information Grid ---------- -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">

                            <!-- Name -->
                            <div class="profile-info-card">
                                <label class="profile-info-label">
                                    Nama
                                </label>

                                <p id="profileName" class="profile-info-value">
                                    Memuat...
                                </p>
                            </div>

                            <!-- Username -->
                            <div class="profile-info-card">
                                <label class="profile-info-label">
                                    Username
                                </label>

                                <p id="profileUsername" class="profile-info-value">
                                    Memuat...
                                </p>
                            </div>

                            <!-- Role -->
                            <div class="profile-info-card">
                                <label class="profile-info-label">
                                    Role
                                </label>

                                <p id="profileRole" class="profile-info-value">
                                    Memuat...
                                </p>
                            </div>

                            <!-- Account status -->
                            <div class="profile-info-card">
                                <label class="profile-info-label">
                                    Status Akun
                                </label>

                                <p id="profileStatus" class="profile-info-value">
                                    Memuat...
                                </p>
                            </div>

                        </div>

                        <!-- ---------- Upload Form ---------- -->
                        <form
                            method="POST"
                            action="../ajax/profile/save_profile.php"
                            enctype="multipart/form-data"
                            class="space-y-5"
                        >
                            <div>
                                <label class="form-label">
                                    Upload Foto Baru
                                </label>

                                <input
                                    type="file"
                                    id="profileImageInput"
                                    name="profile_image"
                                    accept=".jpg,.jpeg,.png"
                                    class="file-upload"
                                >
                            </div>

                            <div class="flex flex-wrap gap-4">
                                <button type="submit" class="form-btn form-btn-primary">
                                    <i data-lucide="save" class="w-4 h-4"></i>
                                    Simpan Foto
                                </button>
                            </div>
                        </form>

                        <!-- ---------- Delete Photo Form ---------- -->
                        <form
                            id="deleteProfileForm"
                            method="POST"
                            action="../ajax/profile/delete_profile.php"
                            class="hidden mt-4"
                        >
                            <button type="submit" class="form-btn form-btn-danger">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                Hapus Foto
                            </button>
                        </form>

                        <!-- File upload note -->
                        <p class="file-note">
                            Format: JPG, JPEG, PNG. Maksimal 2MB.
                        </p>

                    </div>

                </div>

            </div>

        </section>

    </main>

    <script>
    document.addEventListener("DOMContentLoaded", function () {

        /* =======================================================
            INITIALIZE LUCIDE ICONS
        ======================================================= */
        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }

        /* =======================================================
            ELEMENT REFERENCES
        ======================================================= */
        const input = document.getElementById("profileImageInput");
        const profilePreview = document.getElementById("profilePreview");
        const deleteForm = document.getElementById("deleteProfileForm");

        /* =======================================================
            SWEETALERT HELPERS
        ======================================================= */
        function getResponsiveSwal(icon, title, text) {

            const isMobile = window.innerWidth < 640;

            return {
                icon,
                title,
                text,
                width: isMobile ? "90%" : "32rem",
                padding: isMobile ? "1.25rem" : "2rem",
                buttonsStyling: false,
                reverseButtons: false,
                focusCancel: true,

                customClass: {
                    popup: `
                        rounded-2xl
                        shadow-2xl
                        px-6 py-6
                        sm:px-8 sm:py-8
                    `,
                    title: `
                        text-lg sm:text-2xl
                        font-bold
                        text-gray-800
                    `,
                    htmlContainer: `
                        text-sm sm:text-base
                        text-gray-600
                    `,
                    actions: `
                        flex flex-row justify-center gap-3 mt-6
                    `,
                    confirmButton: `
                        inline-flex items-center justify-center
                        min-w-[120px]
                        px-5 py-3
                        rounded-xl
                        font-semibold
                        text-white
                        bg-red-600 hover:bg-red-700
                        transition
                        shadow-sm
                    `,
                    cancelButton: `
                        inline-flex items-center justify-center
                        min-w-[120px]
                        px-5 py-3
                        rounded-xl
                        font-semibold
                        text-white
                        bg-gray-500 hover:bg-gray-600
                        transition
                        shadow-sm
                    `
                }
            };

        }

        /* ---------- Toast Helper ---------- */
        function showToast(icon, title) {

            Swal.fire({
                toast: true,
                position: "top-end",
                icon,
                title,
                showConfirmButton: false,
                timer: 2200,
                timerProgressBar: true,
                customClass: {
                    popup: "rounded-2xl shadow-xl",
                    title: "font-semibold text-gray-800 text-sm sm:text-base"
                }
            });

        }

        /* ---------- Loading Helper ---------- */
        function showLoading(title = "Memproses...") {

            Swal.fire({
                title,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                customClass: {
                    popup: "rounded-2xl shadow-xl"
                }
            });

        }

        /* ---------- Inline Alert Helper ---------- */
        function showInlineAlert(message, type = "error") {

            const existingAlert = document.getElementById("profileInlineAlert");

            if (existingAlert) {
                existingAlert.remove();
            }

            const alertBox = document.createElement("div");
            alertBox.id = "profileInlineAlert";

            alertBox.className = `
                mb-6 px-4 py-3 rounded-xl text-sm font-medium border
                ${type === "success"
                    ? "bg-green-50 text-green-700 border-green-200"
                    : "bg-red-50 text-red-700 border-red-200"}
            `;

            alertBox.textContent = message;

            const title = document.querySelector("h1");

            if (title) {
                title.insertAdjacentElement("afterend", alertBox);
            }

            setTimeout(() => {
                alertBox.remove();
            }, 5000);

        }

        /* =======================================================
            DELETE PROFILE CONFIRMATION
        ======================================================= */
        if (deleteForm) {

            deleteForm.addEventListener("submit", function (e) {

                e.preventDefault();

                Swal.fire({
                    ...getResponsiveSwal(
                        "warning",
                        "Hapus Foto Profil?",
                        "Foto profil Anda akan dihapus permanen."
                    ),
                    showCancelButton: true,
                    confirmButtonText: "Ya, Hapus",
                    cancelButtonText: "Batal"
                }).then((result) => {

                    if (result.isConfirmed) {

                        showLoading("Menghapus foto...");

                        HTMLFormElement.prototype.submit.call(deleteForm);

                    }

                });

            });

        }

        /* =======================================================
            PROFILE DATA LOADER
        ======================================================= */
        async function loadProfileData() {

            try {

                const response = await fetch("../ajax/profile/get_profile.php", {
                    credentials: "same-origin"
                });

                const result = await response.json();

                if (!result.status) {

                    showInlineAlert(
                        result.msg || "Gagal memuat data profil.",
                        "error"
                    );

                    return;
                }

                const user = result.user;

                /* ---------- Basic Info ---------- */
                document.getElementById("profileName").textContent =
                    user.name || "-";

                document.getElementById("profileUsername").textContent =
                    user.username || "-";

                document.getElementById("profileRole").textContent =
                    parseInt(user.user_type) === 1
                        ? "Administrator"
                        : parseInt(user.user_type) === 2
                            ? "Pengajar"
                            : "Siswa";

                /* ---------- Status ---------- */
                const statusElement = document.getElementById("profileStatus");

                if (parseInt(user.status) === 1) {

                    statusElement.textContent = "Aktif";
                    statusElement.className =
                        "profile-status profile-status-active";

                } else {

                    statusElement.textContent = "Nonaktif";
                    statusElement.className =
                        "profile-status profile-status-inactive";

                }

                /* ---------- Profile Image ---------- */
                if (user.profile_image) {

                    profilePreview.innerHTML = `
                        <img
                            src="../${user.profile_image}"
                            alt="Foto Profil"
                            class="w-full h-full object-cover rounded-full"
                        >
                    `;

                    deleteForm.classList.remove("hidden");

                } else {

                    profilePreview.innerHTML = `
                        <i
                            data-lucide="user"
                            class="w-20 h-20 text-white"
                        ></i>
                    `;

                    deleteForm.classList.add("hidden");

                }

                if (typeof lucide !== "undefined") {
                    lucide.createIcons();
                }

            } catch (error) {

                Swal.fire(
                    getResponsiveSwal(
                        "error",
                        "Kesalahan Sistem",
                        "Terjadi kesalahan saat memuat profil."
                    )
                );

            }

        }

        /* =======================================================
            IMAGE PREVIEW AND VALIDATION
        ======================================================= */
        if (input) {

            input.addEventListener("change", function (event) {

                const file = event.target.files[0];

                if (!file) return;

                const maxSize = 2 * 1024 * 1024;

                /* ---------- Size Validation ---------- */
                if (file.size > maxSize) {

                    showInlineAlert(
                        "Ukuran file maksimal adalah 2MB.",
                        "error"
                    );

                    input.value = "";
                    return;
                }

                /* ---------- Type Validation ---------- */
                const allowedTypes = [
                    "image/jpeg",
                    "image/png"
                ];

                if (!allowedTypes.includes(file.type)) {

                    showInlineAlert(
                        "Gunakan format JPG, JPEG, atau PNG.",
                        "error"
                    );

                    input.value = "";
                    return;
                }

                /* ---------- Preview ---------- */
                const reader = new FileReader();

                reader.onload = function (e) {

                    profilePreview.innerHTML = `
                        <img
                            src="${e.target.result}"
                            alt="Preview"
                            class="w-full h-full object-cover rounded-full"
                        >
                    `;

                };

                reader.readAsDataURL(file);

            });

        }

        /* =======================================================
            FLASH MESSAGE HANDLER
        ======================================================= */
        const flashMessage = <?= json_encode($message) ?>;
        const flashType = <?= json_encode($messageType) ?>;

        if (flashMessage) {

            if (flashType === "success") {

                /* ---------- Success Flash ---------- */
                showToast("success", flashMessage);

            } else {

                /* ---------- Error Flash ---------- */
                showInlineAlert(flashMessage, "error");

            }

        }

        /* =======================================================
            INITIALIZATION
        ======================================================= */
        loadProfileData();

    });
    </script>

</body>
</html>