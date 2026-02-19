<?php

function e($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inicial(string $t): string {
  return mb_strtoupper(mb_substr($t, 0, 1, 'UTF-8'), 'UTF-8');
}


$DB_HOST = "localhost";
$DB_USER = "thais";
$DB_PASS = "";          
$DB_NAME = "fotografia_thais";

/* ===== CONEXIÓN ===== */
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
  die("Error de conexión: " . e($mysqli->connect_error));
}
$mysqli->set_charset("utf8mb4");

/* ===== TABLAS (WHITELIST) ===== */
$tablas = [];
$res = $mysqli->query("SHOW TABLES");
while ($t = $res->fetch_row()) $tablas[] = $t[0];
$res->free();

/* ===== OBTENER PK ===== */
function primaryKey(mysqli $db, string $schema, string $tabla): ?string {
  $sql = "
    SELECT k.COLUMN_NAME
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS t
    JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
      ON t.CONSTRAINT_NAME = k.CONSTRAINT_NAME
    WHERE t.CONSTRAINT_TYPE = 'PRIMARY KEY'
      AND t.TABLE_SCHEMA = ?
      AND t.TABLE_NAME = ?
    LIMIT 1";
  $st = $db->prepare($sql);
  $st->bind_param("ss", $schema, $tabla);
  $st->execute();
  $st->bind_result($pk);
  $res = $st->fetch() ? $pk : null;
  $st->close();
  return $res;
}

/* ===== POST · BORRAR ===== */
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (
    ($_POST['accion'] ?? '') === 'borrar' &&
    in_array($_POST['tabla'] ?? '', $tablas, true)
  ) {
    $tabla = $_POST['tabla'];
    $pk = primaryKey($mysqli, $DB_NAME, $tabla);

    if ($pk) {
      $id = $_POST['id'];
      $tablaSQL = "`$tabla`";
      $pkSQL = "`$pk`";

      $sql = "DELETE FROM $tablaSQL WHERE $pkSQL = ?";
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param("s", $id);

      if (!$stmt->execute()) {
        $msg = "❌ No se puede borrar (relación con otros datos)";
      } else {
        $msg = "✅ Registro eliminado";
      }
      $stmt->close();
    }
  }
  header("Location: ?tabla=".$_POST['tabla']."&msg=".urlencode($msg));
  exit;
}

/* ===== GET ===== */
$tablaSel = $_GET['tabla'] ?? '';
$tablaValida = in_array($tablaSel, $tablas, true) ? $tablaSel : null;

$columnas = [];
$filas = [];

if ($tablaValida) {
  $res = $mysqli->query("SELECT * FROM `$tablaValida` LIMIT 200");
  $meta = $res->fetch_fields();
  foreach ($meta as $c) $columnas[] = $c->name;
  while ($f = $res->fetch_assoc()) $filas[] = $f;
  $res->free();

  $pk = primaryKey($mysqli, $DB_NAME, $tablaValida);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Panel · Thais Esteve</title>
<style>
html,body{margin:0;height:100%;font-family:sans-serif}
body{display:flex;background:#f5f6fa}
nav{width:260px;background:#2f5d50;color:#fff;padding:20px;display:flex;flex-direction:column;gap:12px}
nav a{background:#fff;color:#2f5d50;padding:14px;border-radius:8px;text-decoration:none;font-weight:700;display:flex;gap:12px;align-items:center}
nav a.activa{outline:3px solid #8aa07a}
main{flex:1;padding:30px}
.inicial{width:36px;height:36px;background:#2f5d50;color:#fff;border-radius:8px;display:flex;align-items:center;justify-content:center}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden}
th{background:#2f5d50;color:#fff;padding:12px;text-align:left}
td{padding:10px;border-bottom:1px solid #eee}
tr:nth-child(even){background:#f9f9f9}
button{background:#c0392b;color:#fff;border:none;padding:8px 12px;border-radius:6px;cursor:pointer}
.msg{margin-bottom:15px;font-weight:700}
</style>
</head>
<body>

<nav>
  <h3>📸 Admin</h3>
  <?php foreach ($tablas as $t): ?>
    <a href="?tabla=<?= e($t) ?>" class="<?= $tablaValida === $t ? 'activa' : '' ?>">
      <span class="inicial"><?= e(inicial($t)) ?></span>
      <?= e($t) ?>
    </a>
  <?php endforeach; ?>
</nav>

<main>
<?php if (!$tablaValida): ?>
  <h2>Panel de control</h2>
  <p>Selecciona una tabla del menú.</p>
<?php else: ?>
  <h2>Tabla: <?= e($tablaValida) ?></h2>
  <?php if (!empty($_GET['msg'])): ?>
    <div class="msg"><?= e($_GET['msg']) ?></div>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <?php foreach ($columnas as $c): ?><th><?= e($c) ?></th><?php endforeach; ?>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($filas as $f): ?>
      <tr>
        <?php foreach ($columnas as $c): ?>
          <td><?= e($f[$c]) ?></td>
        <?php endforeach; ?>
        <td>
          <form method="POST" onsubmit="return confirm('¿Borrar registro?')">
            <input type="hidden" name="accion" value="borrar">
            <input type="hidden" name="tabla" value="<?= e($tablaValida) ?>">
            <input type="hidden" name="id" value="<?= e($f[$pk]) ?>">
            <button>🗑 Borrar</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</main>

</body>
</html>

