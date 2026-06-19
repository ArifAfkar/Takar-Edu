<?php
/* =======================================================
    SESSION USER DATA
======================================================= */

/* ---------- Active Login Session ---------- */
$name = $_SESSION['login_name'];
$user_type = intval($_SESSION['login_user_type']);
$login_id = intval($_SESSION['login_id']);

/* =======================================================
    GLOBAL USER ACCOUNT STATUS + PROFILE IMAGE
======================================================= */

/* ---------- Default Account Data ---------- */
$accountStatus = 1;
$userProfileImage = null;

/* ---------- Latest Account Data ---------- */
$userQuery = $conn->query("
    SELECT status, profile_image
    FROM users
    WHERE id = {$login_id}
    LIMIT 1
");

if ($userQuery && $userQuery->num_rows > 0) {

    $userData = $userQuery->fetch_assoc();

    /* ---------- Account Status ---------- */
    if (isset($userData['status'])) {
        $accountStatus = intval($userData['status']);
    }

    /* ---------- Profile Image ---------- */
    if (!empty($userData['profile_image'])) {
        $userProfileImage = '../' . ltrim($userData['profile_image'], '/');
    }

}

/* =======================================================
    STATUS LABEL CONFIGURATION
======================================================= */

/* ---------- Status Text, Icon, and Color ---------- */
$statusText = $accountStatus === 1
    ? 'Aktif'
    : 'Nonaktif';

$statusIcon = $accountStatus === 1
    ? 'badge-check'
    : 'badge-x';

$statusColor = $accountStatus === 1
    ? 'text-blue-600'
    : 'text-red-600';

/* =======================================================
    EXTRA USER INFORMATION DEFAULT
======================================================= */

/* ---------- Default Extra Information ---------- */
$extraInfoLabel = '';
$extraInfoValue = '-';

/* =======================================================
    ADMINISTRATOR EXTRA INFO
======================================================= */
if ($user_type === 1) {

    /* ---------- Full System Access ---------- */
    $extraInfoLabel = 'Akses';
    $extraInfoValue = 'Penuh Sistem';

}

/* =======================================================
    TEACHER EXTRA INFO
======================================================= */
elseif ($user_type === 2) {

    /* ---------- Default Teacher Label ---------- */
    $extraInfoLabel = 'Mapel';

    /* ---------- Teacher Subject Query ---------- */
    $teacherQuery = $conn->query("
        SELECT
            t.subject_id,
            s.subject_name
        FROM teachers t
        LEFT JOIN subjects s
            ON t.subject_id = s.id
        WHERE t.user_id = {$login_id}
        LIMIT 1
    ");

    if ($teacherQuery && $teacherQuery->num_rows > 0) {

        $teacherData = $teacherQuery->fetch_assoc();

        /* ---------- Teacher Subject Value ---------- */
        if (!empty($teacherData['subject_name'])) {
            $extraInfoValue = htmlspecialchars(
                $teacherData['subject_name']
            );
        }

    }

}

/* =======================================================
    STUDENT EXTRA INFO
======================================================= */
elseif ($user_type === 3) {

    /* ---------- Default Student Label ---------- */
    $extraInfoLabel = 'Kelas';

    /* ---------- Student Class Query ---------- */
    $studentQuery = $conn->query("
        SELECT
            s.class_id,
            c.class_name
        FROM students s
        LEFT JOIN classes c
            ON s.class_id = c.id
        WHERE s.user_id = {$login_id}
        LIMIT 1
    ");

    if ($studentQuery && $studentQuery->num_rows > 0) {

        $studentData = $studentQuery->fetch_assoc();

        /* ---------- Student Class Value ---------- */
        if (!empty($studentData['class_name'])) {
            $extraInfoValue = htmlspecialchars(
                $studentData['class_name']
            );
        }

    }

}
?>


<!-- =======================================================
    TOP NAVBAR
======================================================= -->
<nav class="top-navbar">

    <!-- ---------- Left Section ---------- -->
    <div class="navbar-left">

        <!-- Sidebar toggle -->
        <button
            id="sidebarToggle"
            class="navbar-toggle"
        >
            <i data-lucide="menu" class="w-4 sm:w-5 h-4 sm:h-5"></i>
        </button>

        <!-- Logo -->
        <div class="navbar-brand">

            <div class="navbar-brand-icon">
                <i data-lucide="scale" class="w-4 sm:w-5 h-4 sm:h-5"></i>
            </div>

            <span class="navbar-brand-text">
                Takar-Edu
            </span>

        </div>

    </div>

    <!-- ---------- Right Section ---------- -->
    <div class="navbar-right">

        <!-- =======================================================
            USER DROPDOWN SYSTEM
        ======================================================= -->
        <div class="relative">

            <!-- ---------- Dropdown Toggle ---------- -->
            <button
                id="userDropdownToggle"
                type="button"
                class="user-dropdown-toggle"
            >

                <!-- User avatar -->
                <?php if (!empty($userProfileImage)): ?>

                    <img
                        src="<?= htmlspecialchars($userProfileImage) ?>"
                        alt="Profile"
                        class="user-avatar w-8 sm:w-9 h-8 sm:h-9 shadow-sm"
                    >

                <?php else: ?>

                    <div class="user-avatar-placeholder">
                        <i data-lucide="user" class="w-5 h-5 text-white"></i>
                    </div>

                <?php endif; ?>

                <!-- User name and role -->
                <div class="user-info-desktop">

                    <span class="text-white font-bold text-sm sm:text-base whitespace-nowrap truncate">
                        <?= htmlspecialchars($name) ?>
                    </span>

                    <span class="text-blue-100 text-xs font-medium whitespace-nowrap">
                        <?php
                        if ($user_type === 1) {
                            echo "Administrator";
                        } elseif ($user_type === 2) {
                            echo "Pengajar";
                        } else {
                            echo "Siswa";
                        }
                        ?>
                    </span>

                </div>

                <!-- Chevron icon -->
                <i
                    data-lucide="chevron-down"
                    class="w-3.5 sm:w-4 h-3.5 sm:h-4 text-blue-100 shrink-0"
                ></i>

            </button>

            <!-- ---------- Dropdown Menu ---------- -->
            <div
                id="userDropdownMenu"
                class="hidden user-dropdown"
            >

                <!-- ---------- Dropdown Header ---------- -->
                <div class="user-dropdown-header">

                    <div class="user-dropdown-profile">

                        <!-- Profile image -->
                        <?php if (!empty($userProfileImage)): ?>

                            <img
                                src="<?= htmlspecialchars($userProfileImage) ?>"
                                alt="Profile"
                                class="user-dropdown-avatar"
                            >

                        <?php else: ?>

                            <div class="user-dropdown-avatar-placeholder">
                                <i data-lucide="user" class="w-7 h-7"></i>
                            </div>

                        <?php endif; ?>

                        <!-- User information -->
                        <div>

                            <span class="user-name">
                                <?= htmlspecialchars($name) ?>
                            </span>

                            <span class="user-role">
                                <?php
                                if ($user_type === 1) echo "Administrator";
                                elseif ($user_type === 2) echo "Pengajar";
                                else echo "Siswa";
                                ?>
                            </span>

                        </div>

                    </div>

                </div>

                <!-- ---------- Dropdown Body ---------- -->
                <div class="user-dropdown-body space-y-3">

                    <!-- Account status -->
                    <div class="user-dropdown-info">

                        <i
                            data-lucide="<?= $statusIcon ?>"
                            class="w-4 h-4 <?= $statusColor ?>"
                        ></i>

                        <span>
                            Status akun:
                            <strong><?= $statusText ?></strong>
                        </span>

                    </div>

                    <!-- Extra info -->
                    <?php if ($user_type !== 1): ?>

                        <div class="user-dropdown-info">

                            <i
                                data-lucide="info"
                                class="w-4 h-4 text-purple-600"
                            ></i>

                            <span>
                                <?= $extraInfoLabel ?>:
                                <strong><?= $extraInfoValue ?></strong>
                            </span>

                        </div>

                    <?php endif; ?>

                    <!-- Access type -->
                    <div class="user-dropdown-info">

                        <?php if ($user_type === 1): ?>

                            <i
                                data-lucide="shield-check"
                                class="w-4 h-4 text-green-600"
                            ></i>

                            <span>Akses penuh sistem</span>

                        <?php elseif ($user_type === 2): ?>

                            <i
                                data-lucide="clipboard-list"
                                class="w-4 h-4 text-green-600"
                            ></i>

                            <span>Pengelola kuis & evaluasi</span>

                        <?php else: ?>

                            <i
                                data-lucide="book-open-check"
                                class="w-4 h-4 text-green-600"
                            ></i>

                            <span>Peserta evaluasi</span>

                        <?php endif; ?>

                    </div>

                </div>

                <!-- ---------- Dropdown Footer ---------- -->
                <div class="user-dropdown-footer">

                    <!-- Edit profile -->
                    <a
                        href="profile.php"
                        class="profile-shortcut-btn mb-2"
                    >
                        <i data-lucide="image-up" class="w-4 h-4"></i>
                        Edit Profil
                    </a>

                    <!-- Logout -->
                    <a
                        href="#"
                        id="dropdownLogoutBtn"
                        class="logout-btn"
                    >
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                        Logout
                    </a>

                </div>

            </div>

        </div>

    </div>

</nav>


<!-- =======================================================
    SIDEBAR
======================================================= -->
<aside id="sidebar" class="sidebar">

    <!-- ---------- Sidebar Navigation ---------- -->
    <nav class="sidebar-nav space-y-2">

        <!-- Home -->
        <a href="home.php" class="sidebar-link">
            <i data-lucide="home" class="sidebar-icon"></i>
            <span class="sidebar-text">Beranda</span>
        </a>

        <!-- ---------- Role-Based Sidebar Menu ---------- -->
        <?php if ($user_type === 1): ?>

            <!-- ---------- Admin Menu ---------- -->

            <!-- Teacher -->
            <a href="teacher.php" class="sidebar-link">
                <i data-lucide="users" class="sidebar-icon"></i>
                <span class="sidebar-text">Pengajar</span>
            </a>

            <!-- Student -->
            <a href="student.php" class="sidebar-link">
                <i data-lucide="graduation-cap" class="sidebar-icon"></i>
                <span class="sidebar-text">Siswa</span>
            </a>

            <!-- Class -->
            <a href="class.php" class="sidebar-link">
                <i data-lucide="school" class="sidebar-icon"></i>
                <span class="sidebar-text">Kelas</span>
            </a>

            <!-- Subject -->
            <a href="subject.php" class="sidebar-link">
                <i data-lucide="library-big" class="sidebar-icon"></i>
                <span class="sidebar-text">Mapel</span>
            </a>

            <!-- Wacana -->
            <a href="wacana.php" class="sidebar-link">
                <i data-lucide="book-open-text" class="sidebar-icon"></i>
                <span class="sidebar-text">Wacana</span>
            </a>

            <!-- PhET -->
            <a href="phet.php" class="sidebar-link">
                <i data-lucide="atom" class="sidebar-icon"></i>
                <span class="sidebar-text">PhET</span>
            </a>

            <!-- Teacher distribution -->
            <a href="teacher_distribution.php" class="sidebar-link">
                <i data-lucide="network" class="sidebar-icon"></i>
                <span class="sidebar-text">Distribusi Pengajar</span>
            </a>

            <!-- Quiz -->
            <a href="quiz.php" class="sidebar-link">
                <i data-lucide="book-open" class="sidebar-icon"></i>
                <span class="sidebar-text">Kuis</span>
            </a>

            <!-- History -->
            <a href="history.php" class="sidebar-link">
                <i data-lucide="chart-column" class="sidebar-icon"></i>
                <span class="sidebar-text">Hasil</span>
            </a>

            <!-- Help -->
            <a href="help.php" class="sidebar-link">
                <i data-lucide="circle-help" class="sidebar-icon"></i>
                <span class="sidebar-text">Bantuan</span>
            </a>

            <!-- About -->
            <a href="about.php" class="sidebar-link">
                <i data-lucide="info" class="sidebar-icon"></i>
                <span class="sidebar-text">Tentang</span>
            </a>

        <?php elseif ($user_type === 2): ?>

            <!-- ---------- Teacher Menu ---------- -->

            <!-- Student -->
            <a href="student.php" class="sidebar-link">
                <i data-lucide="graduation-cap" class="sidebar-icon"></i>
                <span class="sidebar-text">Siswa</span>
            </a>

            <!-- Wacana -->
            <a href="wacana.php" class="sidebar-link">
                <i data-lucide="book-open-text" class="sidebar-icon"></i>
                <span class="sidebar-text">Wacana</span>
            </a>

            <!-- PhET -->
            <a href="phet.php" class="sidebar-link">
                <i data-lucide="atom" class="sidebar-icon"></i>
                <span class="sidebar-text">PhET</span>
            </a>

            <!-- Quiz -->
            <a href="quiz.php" class="sidebar-link">
                <i data-lucide="book-open" class="sidebar-icon"></i>
                <span class="sidebar-text">Kuis</span>
            </a>

            <!-- History -->
            <a href="history.php" class="sidebar-link">
                <i data-lucide="chart-column" class="sidebar-icon"></i>
                <span class="sidebar-text">Hasil</span>
            </a>

            <!-- Help -->
            <a href="help.php" class="sidebar-link">
                <i data-lucide="circle-help" class="sidebar-icon"></i>
                <span class="sidebar-text">Bantuan</span>
            </a>

            <!-- About -->
            <a href="about.php" class="sidebar-link">
                <i data-lucide="info" class="sidebar-icon"></i>
                <span class="sidebar-text">Tentang</span>
            </a>

        <?php elseif ($user_type === 3): ?>

            <!-- ---------- Student Menu ---------- -->

            <!-- Quiz -->
            <a href="student_quiz_list.php" class="sidebar-link">
                <i data-lucide="book-open-check" class="sidebar-icon"></i>
                <span class="sidebar-text">Kuis Saya</span>
            </a>

            <!-- History -->
            <a href="history.php" class="sidebar-link">
                <i data-lucide="chart-column" class="sidebar-icon"></i>
                <span class="sidebar-text">Riwayat</span>
            </a>

            <!-- Help -->
            <a href="help.php" class="sidebar-link">
                <i data-lucide="circle-help" class="sidebar-icon"></i>
                <span class="sidebar-text">Bantuan</span>
            </a>

            <!-- About -->
            <a href="about.php" class="sidebar-link">
                <i data-lucide="info" class="sidebar-icon"></i>
                <span class="sidebar-text">Tentang</span>
            </a>

        <?php endif; ?>

        <!-- Logout -->
        <a
            href="#"
            id="sidebarLogoutBtn"
            class="sidebar-link sidebar-logout-link"
        >
            <i data-lucide="log-out" class="sidebar-icon"></i>
            <span class="sidebar-text">Logout</span>
        </a>

    </nav>

</aside>

<!-- =======================================================
    MOBILE BACKDROP
======================================================= -->
<div
    id="sidebarBackdrop"
    class="hidden sidebar-backdrop lg:hidden"
></div>


<script>
document.addEventListener("DOMContentLoaded", () => {

    /* =======================================================
        ELEMENT REFERENCES
    ======================================================= */
    const sidebar = document.getElementById("sidebar");
    const toggleBtn = document.getElementById("sidebarToggle");
    const mainContent = document.getElementById("mainContent");
    const sidebarBackdrop = document.getElementById("sidebarBackdrop");

    const userDropdownToggle = document.getElementById("userDropdownToggle");
    const userDropdownMenu = document.getElementById("userDropdownMenu");

    /* =======================================================
        DEVICE CHECK
    ======================================================= */
    const isMobileScreen = () => window.innerWidth < 1024;

    /* =======================================================
        SIDEBAR STATE
    ======================================================= */
    let sidebarCollapsed = false;
    let mobileSidebarOpen = false;

    try {
        sidebarCollapsed =
            localStorage.getItem("sidebarCollapsed") === "true";
    } catch (e) {
        // Ignore localStorage errors
    }

    /* =======================================================
        SAVE SIDEBAR STATE
    ======================================================= */
    function saveSidebarState() {

        try {
            localStorage.setItem(
                "sidebarCollapsed",
                sidebarCollapsed
            );
        } catch (e) {
            // Ignore localStorage errors
        }

    }

    /* =======================================================
        APPLY DESKTOP SIDEBAR STATE
    ======================================================= */
    function applyDesktopSidebar() {

        if (!sidebar || !mainContent) return;

        sidebar.classList.remove(
            "w-20",
            "w-24",
            "w-56",
            "w-64"
        );

        mainContent.classList.remove(
            "ml-0",
            "ml-64",
            "ml-24",
            "lg:ml-20",
            "lg:ml-24",
            "lg:ml-64"
        );

        /* ---------- Remove mobile hidden state ---------- */
        sidebar.classList.remove("-translate-x-full");

        /* ---------- Remove preload helper class ---------- */
        document.documentElement.classList.remove(
            "sidebar-collapsed-init"
        );

        const sidebarLinks = document.querySelectorAll("#sidebar a");
        const sidebarTexts = document.querySelectorAll(".sidebar-text");
        const sidebarIcons = document.querySelectorAll("#sidebar a i");

        if (sidebarCollapsed) {

            /* =======================================================
                COLLAPSED DESKTOP SIDEBAR
            ======================================================= */

            sidebar.style.width = "6rem";

            /* ---------- Hide Text ---------- */
            sidebarTexts.forEach(el => {
                el.classList.add("hidden");
            });

            /* ---------- Center Links ---------- */
            sidebarLinks.forEach(link => {

                link.classList.remove(
                    "justify-start",
                    "gap-4",
                    "px-4"
                );

                link.classList.add(
                    "justify-center",
                    "px-2",
                    "py-3"
                );

            });

            /* ---------- Main Content ---------- */
            mainContent.style.marginLeft = "6rem";

        } else {

            /* =======================================================
                EXPANDED DESKTOP SIDEBAR
            ======================================================= */

            sidebar.style.width = "15rem";

            /* ---------- Show Text ---------- */
            sidebarTexts.forEach(el => {
                el.classList.remove("hidden");
            });

            /* ---------- Normal Links ---------- */
            sidebarLinks.forEach(link => {

                link.classList.remove(
                    "justify-center",
                    "px-2"
                );

                link.classList.add(
                    "justify-start",
                    "gap-4",
                    "px-4"
                );

            });

            /* ---------- Normal Icons ---------- */
            sidebarIcons.forEach(icon => {

                icon.classList.remove(
                    "w-6",
                    "h-6"
                );

                icon.classList.add(
                    "w-5",
                    "h-5"
                );

            });

            /* ---------- Main Content ---------- */
            mainContent.style.marginLeft = "15rem";

        }

    }

    /* =======================================================
        APPLY MOBILE SIDEBAR STATE
    ======================================================= */
    function applyMobileSidebar() {

        if (!sidebar || !mainContent) return;

        /* ---------- Always full width on mobile ---------- */
        sidebar.classList.remove(
            "w-20",
            "w-24",
            "w-56",
            "w-64"
        );

        sidebar.style.width = "15rem";
        mainContent.style.marginLeft = "0";

        document.querySelectorAll(".sidebar-text").forEach(el => {
            el.classList.remove("hidden");
        });

        document.querySelectorAll("#sidebar a").forEach(link => {
            link.classList.remove("justify-center");
            link.classList.add(
                "justify-start",
                "gap-4",
                "px-4"
            );
        });

        mainContent.classList.remove(
            "lg:ml-20",
            "lg:ml-24",
            "lg:ml-64"
        );

        mainContent.classList.add("ml-0");

        if (mobileSidebarOpen) {

            sidebar.classList.remove("-translate-x-full");
            sidebarBackdrop.classList.remove("hidden");
            document.body.classList.add("sidebar-mobile-open");

        } else {

            sidebar.classList.add("-translate-x-full");
            sidebarBackdrop.classList.add("hidden");
            document.body.classList.remove("sidebar-mobile-open");

        }

    }

    /* =======================================================
        GLOBAL SCREEN APPLY
    ======================================================= */
    function applyResponsiveSidebar() {

        if (isMobileScreen()) {
            applyMobileSidebar();
        } else {
            mobileSidebarOpen = false;
            sidebarBackdrop.classList.add("hidden");
            document.body.classList.remove("sidebar-mobile-open");
            applyDesktopSidebar();
        }

    }

    /* =======================================================
        TOGGLE SIDEBAR
    ======================================================= */
    function toggleSidebar() {

        if (isMobileScreen()) {

            mobileSidebarOpen = !mobileSidebarOpen;
            applyMobileSidebar();

        } else {

            sidebarCollapsed = !sidebarCollapsed;
            saveSidebarState();
            applyDesktopSidebar();

        }

    }

    /* =======================================================
        TOGGLE BUTTON EVENT
    ======================================================= */
    if (toggleBtn) {

        toggleBtn.addEventListener("click", toggleSidebar);

    }

    /* =======================================================
        BACKDROP CLOSE
    ======================================================= */
    if (sidebarBackdrop) {

        sidebarBackdrop.addEventListener("click", () => {

            mobileSidebarOpen = false;
            applyMobileSidebar();

        });

    }

    /* =======================================================
        WINDOW RESIZE
    ======================================================= */
    window.addEventListener("resize", applyResponsiveSidebar);

    /* =======================================================
        INITIAL LOAD
    ======================================================= */
    applyResponsiveSidebar();

    /* =======================================================
        USER DROPDOWN SYSTEM
    ======================================================= */
    if (userDropdownToggle && userDropdownMenu) {

        userDropdownToggle.addEventListener("click", (e) => {

            e.stopPropagation();
            userDropdownMenu.classList.toggle("hidden");

        });

        document.addEventListener("click", (e) => {

            if (
                !userDropdownToggle.contains(e.target) &&
                !userDropdownMenu.contains(e.target)
            ) {
                userDropdownMenu.classList.add("hidden");
            }

        });

    }

    /* =======================================================
        ACTIVE SIDEBAR LINK
    ======================================================= */
    const currentPage = window.location.pathname.split("/").pop();

    document.querySelectorAll("#sidebar a").forEach(link => {

        const href = link.getAttribute("href");

        if (href === currentPage) {

            link.classList.add(
                "bg-blue-50",
                "text-blue-600",
                "font-semibold"
            );

        }

    });

    /* =======================================================
        INIT LUCIDE ICONS
    ======================================================= */
    if (window.lucide) {
        lucide.createIcons();
    }

    /* =======================================================
        LOGOUT ALERT SYSTEM
    ======================================================= */
    const dropdownLogoutBtn = document.getElementById("dropdownLogoutBtn");
    const sidebarLogoutBtn  = document.getElementById("sidebarLogoutBtn");

    function handleLogout() {

        Swal.fire({

            icon: "warning",
            title: "Logout dari akun?",
            text: "Anda akan keluar dari sesi saat ini dan perlu login kembali untuk mengakses dashboard.",

            width: window.innerWidth < 640 ? "90%" : "34rem",
            padding: window.innerWidth < 640 ? "1.25rem" : "2rem",

            showCancelButton: true,
            confirmButtonText: "Ya, Logout",
            cancelButtonText: "Batal",

            buttonsStyling: false,
            reverseButtons: false,
            focusConfirm: false,

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

        }).then((result) => {

            if (result.isConfirmed) {

                Swal.fire({

                    toast: true,
                    position: "top-end",
                    icon: "success",
                    title: "Berhasil logout",
                    showConfirmButton: false,
                    timer: 1600,
                    timerProgressBar: true,

                    customClass: {
                        popup: `
                            rounded-xl
                            shadow-lg
                        `,
                        title: `
                            text-sm
                            font-semibold
                        `
                    }

                });

                setTimeout(() => {
                    window.location.href = "../auth/logout.php";
                }, 1500);

            }

        });

    }

    if (dropdownLogoutBtn) {
        dropdownLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault();
            handleLogout();
        });
    }

    if (sidebarLogoutBtn) {
        sidebarLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault();
            handleLogout();
        });
    }

});
</script>