<?php
// ============================================================
// Función para redirigir con mensaje
// ============================================================
function redirigirConMensaje($mensaje, $tipo = 'exito') {
    header("Location: lista_clientes.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo);
    exit;
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
// Procesar eliminación
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int) $_POST['id'];

    // Verificar que el cliente existe y no está inactivo
    $stmt = $pdo->prepare("SELECT estado FROM clientes WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        redirigirConMensaje("El cliente no existe.", "error");
    } elseif ($cliente['estado'] === 'inactivo') {
        redirigirConMensaje("El cliente ya estaba inactivo.", "error");
    }

    // Cambiar estado a 'inactivo'
    $sql = "UPDATE clientes SET estado = 'inactivo' WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);

    if ($stmt->rowCount() > 0) {
        redirigirConMensaje("Cliente desactivado correctamente.", "exito");
    } else {
        redirigirConMensaje("No se pudo desactivar el cliente.", "error");
    }
} else {
    // Si se accede directamente sin POST, redirigir a la lista
    header("Location: lista_clientes.php");
    exit;
}