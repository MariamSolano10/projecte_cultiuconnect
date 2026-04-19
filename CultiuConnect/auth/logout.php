<?php
/**
 * auth/logout.php — Tancament de sessió amb l'estètica de CultiuConnect.
 */
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/db.php';

// helpers.php crida cc_enforce_auth_i_rols() automàticament.
// El logout �?S pàgina pública (cc_es_public retorna true), per tant no redirigirà.
require_once __DIR__ . '/../includes/helpers.php';

// 1. Destruir sessió completament
cc_logout_hard();

// 2. Iniciar sessió neta per poder usar flash messages si cal
cc_session_start();

// 3. Generar la vista
$titol_pagina = 'Sortint... — CultiuConnect';
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titol_pagina) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/estils_globals.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <meta http-equiv="refresh" content="2;url=<?= BASE_URL ?>auth/login.php">
</head>

<body class="pagina-login">
    <div class="login-contenidor">

        <div class="login-logo">
            <i class="fas fa-seedling" aria-hidden="true"></i>
            <span>CultiuConnect</span>
        </div>

        <div class="formulari-login formulari-login--center card-accent-or">
            <i class="fas fa-right-from-bracket icona-login-sortida"></i>

            <h1 class="login-titol login-titol--verd">Sessió tancada</h1>
            <p class="text-suau--mb-l">
                Has sortit correctament de la plataforma.
            </p>

            <a href="<?= BASE_URL ?>auth/login.php" class="boto-login sense-subratllat">
                Anar al Login ara
            </a>
        </div>

        <p class="login-peu">
            © <?= date('Y') ?> CultiuConnect — Projecte 2n DAW
        </p>

    </div>
</body>

</html>


