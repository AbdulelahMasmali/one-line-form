<?php
// backend.php
// Place this file in the same folder as your index.html / index.php
// Configuration - change if your MySQL credentials differ
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'one_line_app';

// Return JSON helper
function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Optional: enable mysqli exceptions for easier error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $mysqli->set_charset('utf8mb4');
} catch (Exception $e) {
    // Don't reveal internal details in production; return friendly message
    json_out(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()], 500);
}

// GET -> return rows
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $rows = [];
        $res = $mysqli->query('SELECT id, name, age, status, created_at FROM people ORDER BY id DESC');
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free();
        json_out(['success' => true, 'rows' => $rows]);
    } catch (Exception $e) {
        json_out(['success' => false, 'message' => 'Failed to fetch rows: ' . $e->getMessage()], 500);
    }
}

// POST actions: add, toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $age  = intval($_POST['age'] ?? 0);

        if ($name === '' || $age <= 0) {
            json_out(['success' => false, 'message' => 'Invalid name or age'], 400);
        }

        try {
            $stmt = $mysqli->prepare('INSERT INTO people (name, age, status) VALUES (?, ?, 0)');
            $stmt->bind_param('si', $name, $age);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            $row = $mysqli->query('SELECT id, name, age, status, created_at FROM people WHERE id = ' . intval($newId))->fetch_assoc();
            json_out(['success' => true, 'person' => $row]);
        } catch (Exception $e) {
            json_out(['success' => false, 'message' => 'Insert failed: ' . $e->getMessage()], 500);
        }
    }

    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) json_out(['success' => false, 'message' => 'Invalid id'], 400);

        try {
            // Atomic toggle
            $stmt = $mysqli->prepare('UPDATE people SET status = 1 - status WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            $row = $mysqli->query('SELECT status FROM people WHERE id = ' . intval($id))->fetch_assoc();
            if ($row === null) json_out(['success' => false, 'message' => 'Row not found'], 404);
            json_out(['success' => true, 'status' => intval($row['status'])]);
        } catch (Exception $e) {
            json_out(['success' => false, 'message' => 'Toggle failed: ' . $e->getMessage()], 500);
        }
    }

    json_out(['success' => false, 'message' => 'Unknown action'], 400);
}

// If other methods used:
json_out(['success' => false, 'message' => 'Unsupported method'], 405);
