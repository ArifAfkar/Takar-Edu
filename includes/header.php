<!-- =======================================================
    GLOBAL HEAD CONFIGURATION
======================================================= -->

<!-- =======================================================
    META CONFIGURATION
======================================================= -->

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<!-- =======================================================
    SIDEBAR PRELOAD STATE SYSTEM
======================================================= -->
<script>
(function () {

    try {

        const sidebarCollapsed =
            localStorage.getItem("sidebarCollapsed") === "true";

        if (sidebarCollapsed) {

            document.documentElement.classList.add(
                "sidebar-collapsed-init"
            );

        }

    } catch (e) {

        console.warn("Sidebar preload failed:", e);

    }

})();
</script>

<!-- =======================================================
    SIDEBAR PRELOAD FALLBACK STYLE
======================================================= -->
<style>
@media (min-width: 1024px) {

    .sidebar-collapsed-init #sidebar {
        width: 5rem !important;
    }

    .sidebar-collapsed-init #mainContent {
        margin-left: 5rem !important;
    }

    .sidebar-collapsed-init .sidebar-text {
        display: none !important;
    }

    .sidebar-collapsed-init #sidebar a {
        justify-content: center !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        gap: 0 !important;
    }

}
</style>

<!-- =======================================================
    GLOBAL CSS LIBRARIES
======================================================= -->

<!-- ---------- Main Global Styles ---------- -->
<link
    rel="stylesheet"
    href="../assets/css/app.css?v=<?= time(); ?>"
>

<!-- ---------- CKEditor 5 ---------- -->
<link
    rel="stylesheet"
    href="../assets/ckeditor/ckeditor5/ckeditor5.css"
>

<!-- ---------- KaTeX ---------- -->
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css"
>

<!-- =======================================================
    FONT CONFIGURATION
======================================================= -->
<link
    rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
>

<!-- =======================================================
    GLOBAL JAVASCRIPT LIBRARIES
======================================================= -->

<!-- ---------- Tailwind CSS ---------- -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- ---------- jsPDF ---------- -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<!-- ---------- jsPDF AutoTable ---------- -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<!-- ---------- Lucide Icons ---------- -->
<script src="https://unpkg.com/lucide@latest"></script>

<!-- ---------- SortableJS ---------- -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<!-- ---------- SweetAlert2 ---------- -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- ---------- KaTeX ---------- -->
<script
    defer
    src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"
></script>

<script
    defer
    src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"
></script>

<!-- =======================================================
    CKEDITOR 5 SELF HOSTED
======================================================= -->
<script type="module">

    import {

        ClassicEditor,

        Essentials,
        Paragraph,
        Heading,

        Bold,
        Italic,
        Underline,
        Strikethrough,

        Link,

        List,

        BlockQuote,

        Table,
        TableToolbar,

        ImageUpload,
        ImageBlock,
        ImageInline,
        ImageToolbar,
        ImageCaption,
        ImageStyle,
        ImageTextAlternative,

        FontSize,
        FontColor,
        FontBackgroundColor,

        Highlight,

        Alignment,

        Subscript,
        Superscript,

        HorizontalLine,

        RemoveFormat

    } from '../assets/ckeditor/ckeditor5/ckeditor5.js';

    window.CKEDITOR = {

        ClassicEditor,

        Essentials,
        Paragraph,
        Heading,

        Bold,
        Italic,
        Underline,
        Strikethrough,

        Link,

        List,

        BlockQuote,

        Table,
        TableToolbar,

        ImageUpload,
        ImageBlock,
        ImageInline,
        ImageToolbar,
        ImageCaption,
        ImageStyle,
        ImageTextAlternative,

        FontSize,
        FontColor,
        FontBackgroundColor,

        Highlight,

        Alignment,

        Subscript,
        Superscript,

        HorizontalLine,

        RemoveFormat

    };

</script>