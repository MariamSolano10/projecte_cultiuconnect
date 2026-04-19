<?php
require_once __DIR__ . '/../config/db_config.php';
?>
<!doctype html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 â€” Error intern</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/estils_globals.css">
</head>
<body class="pagina-login">
    <div class="login-contenidor">
        <div class="login-logo">
            <span>CultiuConnect</span>
        </div>
        <div class="formulari-login formulari-login--center card-accent-gris">
            <h1 class="login-titol">500 â€” Error intern del servidor</h1>
            <p class="text-suau--mb-m">
                Hi ha hagut un problema inesperat. Torna-ho a intentar.
            </p>
            <a href="<?= BASE_URL ?>index.php" class="boto-login sense-subratllat">
                Tornar al panell
            </a>
        </div>
    </div>
</body>
</html>


