<?php
// /app/api/sales/list.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/require_login.php';
require_once __DIR__ . '/../../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

function jexit($ok, $data_or_error, int $code = 200) {
    http_response_code($code);
    $payload = ['ok' => $ok];
    if ($ok) {
        $payload['data'] = $data_or_error;
    } else {
        $payload['error'] = $data_or_error;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $params = [];
    $sql = "SELECT 
                s.id, s.created_at, s.total, s.arca_status,
                c.name AS customer_name,
                u.full_name AS user_name,
                b.name AS branch_name
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN branches b ON s.branch_id = b.id
            WHERE 1=1";

    if (!empty($_GET['date_from'])) {
        $sql .= " AND s.created_at >= ?";
        $params[] = $_GET['date_from'] . ' 00:00:00';
    }
    if (!empty($_GET['date_to'])) {
        $sql .= " AND s.created_at <= ?";
        $params[] = $_GET['date_to'] . ' 23:59:59';
    }
    if (!empty($_GET['branch_id'])) {
        $sql .= " AND s.branch_id = ?";
        $params[] = (int)$_GET['branch_id'];
    }
    if (!empty($_GET['user_id'])) {
        $sql .= " AND s.user_id = ?";
        $params[] = (int)$_GET['user_id'];
    }

    $sql .= " ORDER BY s.id DESC LIMIT 200"; // Límite para evitar sobrecargas

    $sales = DB::all($sql, $params);

    jexit(true, $sales);

} catch (Throwable $e) {
    jexit(false, 'Error interno del servidor: ' . $e->getMessage(), 500);
}