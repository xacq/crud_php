<?php
// ============================================================
// 1. FUNCIONES AUXILIARES
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
// 3. OBTENER ID Y CONSULTAR CLIENTE
// ============================================================
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$cliente = null;
$error = '';

if ($id > 0) {
    $sql = "SELECT * FROM clientes WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        $error = "El cliente solicitado no existe.";
    } elseif ($cliente['estado'] === 'inactivo') {
        $error = "Este cliente se encuentra inactivo y no puede ser visualizado.";
    }
} else {
    $error = "ID de cliente no válido.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del cliente | Gimnasio</title>
    <style>
        /* ===== ESTILOS (consistentes con lista y registro) ===== */
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
        .contenedor { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .cabecera { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .cabecera h1 { margin: 0; color: #2c3e50; }
        .acciones-superior { display: flex; gap: 10px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9em; display: inline-block; }
        .btn-volver { background: #6c757d; color: #fff; }
        .btn-volver:hover { background: #5a6268; }
        .btn-editar { background: #ffc107; color: #212529; }
        .btn-editar:hover { background: #e0a800; }
        .alerta { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alerta-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alerta-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

        /* Detalles del cliente */
        .ficha { display: flex; gap: 30px; flex-wrap: wrap; }
        .foto { flex: 0 0 200px; text-align: center; }
        .foto img { max-width: 100%; border-radius: 8px; border: 1px solid #dee2e6; }
        .foto .sin-foto { display: flex; align-items: center; justify-content: center; width: 200px; height: 200px; background: #e9ecef; border-radius: 8px; color: #6c757d; font-size: 0.9em; border: 1px dashed #ced4da; }
        .datos { flex: 1; }
        .grupo { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .campo { margin-bottom: 10px; }
        .campo label { font-weight: bold; display: block; color: #495057; font-size: 0.9em; margin-bottom: 2px; }
        .campo .valor { padding: 5px 0; border-bottom: 1px solid #f1f3f5; }
        .campo-completo { grid-column: 1 / -1; }
        .estado { display: inline-block; padding: 3px 12px; border-radius: 20px; font-weight: bold; }
        .estado-activo { background: #d4edda; color: #155724; }
        .estado-suspendido { background: #fff3cd; color: #856404; }
        .estado-retirado { background: #f8d7da; color: #721c24; }
        .estado-inactivo { background: #e2e3e5; color: #383d41; }
        @media (max-width: 600px) {
            .ficha { flex-direction: column; align-items: center; }
            .foto { flex: 1 1 auto; }
            .grupo { grid-template-columns: 1fr; }
            .cabecera { flex-direction: column; align-items: stretch; gap: 10px; }
            .acciones-superior { justify-content: center; }
        }
    </style>
</head>
<body>
<div class="contenedor">
    <div class="cabecera">
        <h1>Detalles del cliente</h1>
        <div class="acciones-superior">
            <a href="lista_clientes.php" class="btn btn-volver">← Volver a la lista</a>
            <?php if ($cliente && $cliente['estado'] !== 'inactivo'): ?>
                <a href="editar_cliente.php?id=<?= $cliente['id'] ?>" class="btn btn-editar">✎ Editar</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alerta alerta-error">
            <?= escapar($error) ?>
        </div>
    <?php elseif ($cliente): ?>
        <div class="ficha">
            <!-- Fotografía -->
            <div class="foto">
                <?php if (!empty($cliente['fotografia']) && file_exists(__DIR__ . '/' . $cliente['fotografia'])): ?>
                    <img src="<?= escapar($cliente['fotografia']) ?>" alt="Foto de <?= escapar($cliente['nombres']) ?>" style="max-width:200px; max-height:200px;">
                <?php else: ?>
                    <div class="sin-foto">No hay  fotografía</div>
                <?php endif; ?>
            </div>

            <!-- Datos personales -->
            <div class="datos">
                <div class="grupo">
                    <div class="campo">
                        <label>Código de cliente</label>
                        <div class="valor"><strong><?= escapar($cliente['codigo_cliente']) ?></strong></div>
                    </div>
                    <div class="campo">
                        <label>Estado</label>
                        <div class="valor">
                            <span class="estado estado-<?= $cliente['estado'] ?>">
                                <?= ucfirst(escapar($cliente['estado'])) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grupo">
                    <div class="campo">
                        <label>Nombres</label>
                        <div class="valor"><?= escapar($cliente['nombres']) ?></div>
                    </div>
                    <div class="campo">
                        <label>Apellidos</label>
                        <div class="valor"><?= escapar($cliente['apellidos']) ?></div>
                    </div>
                </div>

                <div class="grupo">
                    <div class="campo">
                        <label>Tipo de documento</label>
                        <div class="valor"><?= escapar($cliente['tipo_documento']) ?></div>
                    </div>
                    <div class="campo">
                        <label>Número de documento</label>
                        <div class="valor"><?= escapar($cliente['numero_documento']) ?></div>
                    </div>
                </div>

                <div class="grupo">
                    <div class="campo">
                        <label>Fecha de nacimiento</label>
                        <div class="valor"><?= $cliente['fecha_nacimiento'] ? escapar($cliente['fecha_nacimiento']) : 'No especificada' ?></div>
                    </div>
                    <div class="campo">
                        <label>Sexo</label>
                        <div class="valor"><?= escapar($cliente['sexo'] ?? 'No especificado') ?></div>
                    </div>
                </div>

                <div class="grupo">
                    <div class="campo">
                        <label>Teléfono principal</label>
                        <div class="valor"><?= escapar($cliente['telefono']) ?></div>
                    </div>
                    <div class="campo">
                        <label>Teléfono secundario</label>
                        <div class="valor"><?= $cliente['telefono_secundario'] ? escapar($cliente['telefono_secundario']) : 'No especificado' ?></div>
                    </div>
                </div>

                <div class="grupo">
                    <div class="campo">
                        <label>Correo electrónico</label>
                        <div class="valor"><?= $cliente['correo'] ? escapar($cliente['correo']) : 'No especificado' ?></div>
                    </div>
                    <div class="campo">
                        <label>Ciudad</label>
                        <div class="valor"><?= $cliente['ciudad'] ? escapar($cliente['ciudad']) : 'No especificada' ?></div>
                    </div>
                </div>

                <div class="campo campo-completo">
                    <label>Dirección</label>
                    <div class="valor"><?= $cliente['direccion'] ? escapar($cliente['direccion']) : 'No especificada' ?></div>
                </div>

                <h3 style="margin-top: 25px; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">Contacto de emergencia</h3>
                <div class="grupo">
                    <div class="campo">
                        <label>Nombre</label>
                        <div class="valor"><?= $cliente['contacto_emergencia_nombre'] ? escapar($cliente['contacto_emergencia_nombre']) : 'No especificado' ?></div>
                    </div>
                    <div class="campo">
                        <label>Parentesco</label>
                        <div class="valor"><?= $cliente['contacto_emergencia_parentesco'] ? escapar($cliente['contacto_emergencia_parentesco']) : 'No especificado' ?></div>
                    </div>
                    <div class="campo">
                        <label>Teléfono</label>
                        <div class="valor"><?= $cliente['contacto_emergencia_telefono'] ? escapar($cliente['contacto_emergencia_telefono']) : 'No especificado' ?></div>
                    </div>
                </div>

                <?php if (!empty($cliente['observaciones'])): ?>
                    <div class="campo campo-completo">
                        <label>Observaciones</label>
                        <div class="valor" style="white-space: pre-wrap;"><?= escapar($cliente['observaciones']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>