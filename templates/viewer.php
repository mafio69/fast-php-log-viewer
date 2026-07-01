<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ LOG VIEWER</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='12' fill='%23000'/><text x='32' y='46' text-anchor='middle' font-size='38' font-family='monospace' fill='%2300ff00'>⚡</text></svg>">
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
