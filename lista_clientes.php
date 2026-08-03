<?php
// ============================================================
// 1. FUNCIONES AUXILIARES (compartidas con el registro)
// ============================================================
function escapar($valor) {
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

// ============================================================
// 2. CONFIGURACIÓN Y CONEXIÓN A LA BD
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
// 3. PROCESAR ELIMINACIÓN (cambio de estado) si se recibe acción
// ============================================================
$mensaje = '';
$tipo_mensaje = ''; // 'exito' o 'error'

if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];
    try {
        // Cambiar el estado a 'inactivo' (eliminación lógica)
        $sql = "UPDATE clientes SET estado = 'inactivo' WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() > 0) {
            $mensaje = "Cliente desactivado correctamente.";
            $tipo_mensaje = 'exito';
        } else {
            $mensaje = "El cliente no existe o ya estaba inactivo.";
            $tipo_mensaje = 'error';
        }
    } catch (PDOException $e) {
        $mensaje = "Error al desactivar: " . $e->getMessage();
        $tipo_mensaje = 'error';
    }
    // Redirigir para evitar reenvío de la acción al recargar
    header("Location: lista_clientes.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
    exit;
}

// Si hay mensaje en GET, lo mostramos
if (isset($_GET['mensaje'])) {
    $mensaje = htmlspecialchars($_GET['mensaje']);
    $tipo_mensaje = $_GET['tipo'] ?? 'info';
}

// ============================================================
// 4. CONSULTAR LISTA DE CLIENTES (excepto inactivos)
// ============================================================
$sql = "SELECT id, codigo_cliente, nombres, apellidos, estado, telefono
        FROM clientes
        WHERE estado != 'inactivo'
        ORDER BY id DESC";
$stmt = $pdo->query($sql);
$clientes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de clientes | Gimnasio</title>
    


    <style>
        /* ===== ESTILOS (mismos que en registro) ===== */
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
        .contenedor { max-width: 1100px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .cabecera { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .cabecera h1 { margin: 0; color: #2c3e50; }
        .cabecera a { background: #007bff; color: #fff; padding: 8px 16px; text-decoration: none; border-radius: 4px; }
        .cabecera a:hover { background: #0069d9; }
        .alerta { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alerta-exito { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alerta-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alerta-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; color: #495057; }
        tr:hover { background: #f1f3f5; }
        .acciones { display: flex; gap: 5px; flex-wrap: wrap; }
        .btn { padding: 5px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.85em; display: inline-block; }
        .btn-ver { background: #17a2b8; color: #fff; }
        .btn-ver:hover { background: #138496; }
        .btn-editar { background: #ffc107; color: #212529; }
        .btn-editar:hover { background: #e0a800; }
        .btn-eliminar { background: #dc3545; color: #fff; }
        .btn-eliminar:hover { background: #c82333; }
        .btn-nuevo { background: #28a745; color: #fff; padding: 8px 16px; text-decoration: none; border-radius: 4px; }
        .btn-nuevo:hover { background: #218838; }
        .estado { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.8em; font-weight: bold; }
        .estado-activo { background: #d4edda; color: #155724; }
        .estado-suspendido { background: #fff3cd; color: #856404; }
        .estado-retirado { background: #f8d7da; color: #721c24; }
        .vacio { text-align: center; padding: 40px; color: #6c757d; }
        @media (max-width: 600px) { .cabecera { flex-direction: column; align-items: stretch; gap: 10px; } }
    </style>
</head>
<body>
<div class="contenedor">
    <div class="cabecera">
        <h1>Lista de clientes</h1>
        <a href="index.php" class="btn-nuevo">+ Nuevo cliente</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alerta alerta-<?= $tipo_mensaje ?>">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if (count($clientes) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre completo</th>
                    <th>Teléfono</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td><?= escapar($cliente['codigo_cliente']) ?></td>
                        <td><?= escapar($cliente['nombres'] . ' ' . $cliente['apellidos']) ?></td>
                        <td><?= escapar($cliente['telefono']) ?></td>
                        <td>
                            <span class="estado estado-<?= $cliente['estado'] ?>">
                                <?= ucfirst(escapar($cliente['estado'])) ?>
                            </span>
                        </td>
                        <td>
                            <div class="acciones">
                                <a href="ver_cliente.php?id=<?= $cliente['id'] ?>" class="btn btn-ver">Ver</a>
                                <a href="editar_cliente.php?id=<?= $cliente['id'] ?>" class="btn btn-editar">Editar</a>
                                <a href="lista_clientes.php?eliminar=<?= $cliente['id'] ?>" class="btn btn-eliminar" 
                                   onclick="return confirm('¿Estás seguro de desactivar este cliente?')">Eliminar</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="vacio">No hay clientes registrados activos.</p>
    <?php endif; ?>
</div>
</body>
</html>