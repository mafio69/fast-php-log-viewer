<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>fast-php-log-viewer</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <script nonce="<?= CSP_NONCE ?>">
        window.FPLV_CONFIG = {
            editorUrl: <?= json_encode(EDITOR_URL) ?>
        };
    </script>
</head>
<body class="h-screen overflow-hidden crt-text crt-bg">

<div id="app" v-cloak>
    <v-app></v-app>
</div>

<script src="js/store.js"></script>
<script src="js/components/SetupWizard.js"></script>
<script src="js/components/Sidebar.js"></script>
<script src="js/components/SSHModal.js"></script>
<script src="js/components/Toolbar.js"></script>
<script src="js/components/DataTable.js"></script>
<script src="js/components/VApp.js"></script>
<script src="js/app.js"></script>
</body>
</html>
