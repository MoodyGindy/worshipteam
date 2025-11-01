<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use QuizGame\Database;

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Get the action from URL
$action = $pathParts[count($pathParts) - 1] ?? '';

try {
    switch ($action) {
        case 'create-game':
            if ($method === 'POST') {
                createGame($db);
            }
            break;

        case 'get-game':
            if ($method === 'GET') {
                getGame($db);
            }
            break;

        case 'get-questions':
            if ($method === 'GET') {
                getQuestions($db);
            }
            break;

        case 'get-leaderboard':
            if ($method === 'GET') {
                getLeaderboard($db);
            }
            break;

        case 'get-stats':
            if ($method === 'GET') {
                getStats($db);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function createGame($db) {
    $gameCode = strtoupper(substr(md5(uniqid()), 0, 6));

    $sql = "INSERT INTO games (game_code, status) VALUES (?, 'lobby')";
    $db->query($sql, [$gameCode]);

    $gameId = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'gameId' => $gameId,
        'gameCode' => $gameCode
    ]);
}

function getGame($db) {
    $gameCode = $_GET['code'] ?? '';

    if (!$gameCode) {
        http_response_code(400);
        echo json_encode(['error' => 'Game code required']);
        return;
    }

    $game = $db->fetchOne(
        "SELECT * FROM games WHERE game_code = ?",
        [$gameCode]
    );

    if (!$game) {
        http_response_code(404);
        echo json_encode(['error' => 'Game not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'game' => $game
    ]);
}

function getQuestions($db) {
    $limit = $_GET['limit'] ?? 20;

    $questions = $db->fetchAll(
        "SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer, category, points, time_limit
         FROM questions
         ORDER BY RAND()
         LIMIT ?",
        [(int)$limit]
    );

    echo json_encode([
        'success' => true,
        'questions' => $questions
    ]);
}

function getLeaderboard($db) {
    $gameCode = $_GET['code'] ?? '';

    if (!$gameCode) {
        http_response_code(400);
        echo json_encode(['error' => 'Game code required']);
        return;
    }

    $leaderboard = $db->fetchAll(
        "SELECT p.player_name, p.total_score,
                (SELECT COUNT(*) FROM answers WHERE player_id = p.id AND is_correct = 1) as correct_answers,
                (SELECT COUNT(*) FROM answers WHERE player_id = p.id) as total_answers
         FROM players p
         JOIN games g ON p.game_id = g.id
         WHERE g.game_code = ?
         ORDER BY p.total_score DESC",
        [$gameCode]
    );

    echo json_encode([
        'success' => true,
        'leaderboard' => $leaderboard
    ]);
}

function getStats($db) {
    $gameCode = $_GET['code'] ?? '';

    if (!$gameCode) {
        http_response_code(400);
        echo json_encode(['error' => 'Game code required']);
        return;
    }

    $stats = $db->fetchOne(
        "SELECT
            COUNT(DISTINCT p.id) as total_players,
            COALESCE(SUM(a.is_correct), 0) as total_correct,
            COUNT(a.id) as total_answers
         FROM games g
         LEFT JOIN players p ON g.id = p.game_id
         LEFT JOIN answers a ON p.id = a.player_id
         WHERE g.game_code = ?
         GROUP BY g.id",
        [$gameCode]
    );

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}
