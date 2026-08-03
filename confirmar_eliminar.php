<?php
// ============================================================
// Funciones auxiliares
// ============================================================
function escapar($valor) {
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

// ============================================================
// Configuración y conexión
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'gimnasio_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// ============================================================
// Obtener cliente
// ============================================================
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$cliente = null;
$error = '';

if ($id > 0) {
    $sql = "SELECT id, codigo_cliente, nombres, apellidos, telefono, estado FROM clientes WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        $error = "El cliente no existe.";
    } elseif ($cliente['estado'] === 'inactivo') {
        $error = "Este cliente ya está inactivo.";
    }
} else {
    $error = "ID no válido.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar eliminación | Gimnasio</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
        .contenedor { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .cabecera { margin-bottom: 20px; }
        .cabecera h1 { color: #dc3545; }
        .alerta { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alerta-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .cliente-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .cliente-info p { margin: 5px 0; }
        .acciones { display: flex; gap: 15px; justify-content: flex-end; }
        .btn { padding: 10px 25px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 1em; }
        .btn-cancelar { background: #6c757d; color: #fff; }
        .btn-cancelar:hover { background: #5a6268; }
        .btn-eliminar { background: #dc3545; color: #fff; }
        .btn-eliminar:hover { background: #c82333; }
    </style>
</head>
<body>
<div class="contenedor">
    <div class="cabecera">
        <h1>⚠️ Confirmar desactivación</h1>
        <p>Estás a punto de desactivar (eliminar lógicamente) a este cliente. Esta acción no se puede deshacer fácilmente.</p>
    </div>

    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= escapar($error) ?></div>
        <div class="acciones">
            <a href="lista_clientes.php" class="btn btn-cancelar">← Volver a la lista</a>
        </div>
    <?php elseif ($cliente): ?>
        <div class="cliente-info">
            <p><strong>Código:</strong> <?= escapar($cliente['codigo_cliente']) ?></p>
            <p><strong>Nombre:</strong> <?= escapar($cliente['nombres'] . ' ' . $cliente['apellidos']) ?></p>
            <p><strong>Teléfono:</strong> <?= escapar($cliente['telefono']) ?></p>
            <p><strong>Estado actual:</strong> <?= ucfirst(escapar($cliente['estado'])) ?></p>
        </div>

        <form action="eliminar_cliente.php" method="post">
            <input type="hidden" name="id" value="<?= $cliente['id'] ?>">
            <div class="acciones">
                <a href="lista_clientes.php" class="btn btn-cancelar">Cancelar</a>
                <button type="submit" class="btn btn-eliminar" onclick="return confirm('¿Confirmas la desactivación de este cliente?')">Sí, desactivar</button>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>