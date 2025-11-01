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

        case 'join-game':
            if ($method === 'POST') {
                joinGame($db);
            }
            break;

        case 'submit-answer':
            if ($method === 'POST') {
                submitAnswer($db);
            }
            break;

        case 'get-current-question':
            if ($method === 'GET') {
                getCurrentQuestion($db);
            }
            break;

        case 'set-current-question':
            if ($method === 'POST') {
                setCurrentQuestion($db);
            }
            break;

        case 'get-game-updates':
            if ($method === 'GET') {
                getGameUpdates($db);
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

// New polling-based endpoints

function joinGame($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $gameCode = $input['gameCode'] ?? '';
    $playerName = $input['playerName'] ?? '';

    if (!$gameCode || !$playerName) {
        http_response_code(400);
        echo json_encode(['error' => 'Game code and player name required']);
        return;
    }

    // Get game
    $game = $db->fetchOne("SELECT id FROM games WHERE game_code = ?", [$gameCode]);
    if (!$game) {
        http_response_code(404);
        echo json_encode(['error' => 'Game not found']);
        return;
    }

    // Create session ID
    $sessionId = md5(uniqid($playerName, true));

    // Check if player already exists (by name in same game)
    $existingPlayer = $db->fetchOne(
        "SELECT id, session_id FROM players WHERE game_id = ? AND player_name = ?",
        [$game['id'], $playerName]
    );

    if ($existingPlayer) {
        // Update session ID
        $db->query("UPDATE players SET session_id = ? WHERE id = ?", [$sessionId, $existingPlayer['id']]);
        $playerId = $existingPlayer['id'];
    } else {
        // Create new player
        $db->query(
            "INSERT INTO players (game_id, player_name, session_id) VALUES (?, ?, ?)",
            [$game['id'], $playerName, $sessionId]
        );
        $playerId = $db->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'playerId' => $playerId,
        'sessionId' => $sessionId,
        'gameId' => $game['id']
    ]);
}

function submitAnswer($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $playerId = $input['playerId'] ?? null;
    $questionId = $input['questionId'] ?? null;
    $answer = $input['answer'] ?? null;
    $responseTime = $input['responseTime'] ?? 0;

    if (!$playerId || !$questionId || !$answer) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    // Get correct answer
    $question = $db->fetchOne(
        "SELECT correct_answer, points FROM questions WHERE id = ?",
        [$questionId]
    );

    if (!$question) {
        http_response_code(404);
        echo json_encode(['error' => 'Question not found']);
        return;
    }

    // Normalize answer comparison
    $submittedAnswer = strtoupper(trim($answer));
    $correctAnswer = strtoupper(trim($question['correct_answer']));
    $isCorrect = ($submittedAnswer === $correctAnswer);

    // Calculate points
    $pointsEarned = 0;
    if ($isCorrect) {
        $timeBonus = max(0, 1 - ($responseTime / 30));
        $pointsEarned = round($question['points'] * (0.5 + 0.5 * $timeBonus));
    }

    // Save answer
    $sql = "INSERT INTO answers (player_id, question_id, selected_answer, is_correct, points_earned, response_time)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            selected_answer = VALUES(selected_answer),
            is_correct = VALUES(is_correct),
            points_earned = VALUES(points_earned),
            response_time = VALUES(response_time)";

    $db->query($sql, [$playerId, $questionId, $answer, $isCorrect, $pointsEarned, $responseTime]);

    // Update total score
    $db->query(
        "UPDATE players SET total_score = (
            SELECT COALESCE(SUM(points_earned), 0) FROM answers WHERE player_id = ?
        ) WHERE id = ?",
        [$playerId, $playerId]
    );

    echo json_encode([
        'success' => true,
        'isCorrect' => $isCorrect,
        'pointsEarned' => $pointsEarned,
        'correctAnswer' => $question['correct_answer']
    ]);
}

function getCurrentQuestion($db) {
    $gameCode = $_GET['code'] ?? '';
    $playerId = $_GET['playerId'] ?? null;

    if (!$gameCode) {
        http_response_code(400);
        echo json_encode(['error' => 'Game code required']);
        return;
    }

    // Get game
    $game = $db->fetchOne("SELECT * FROM games WHERE game_code = ?", [$gameCode]);
    if (!$game) {
        http_response_code(404);
        echo json_encode(['error' => 'Game not found']);
        return;
    }

    // If no current question
    if (!$game['current_question'] || $game['status'] !== 'playing') {
        echo json_encode([
            'success' => true,
            'hasQuestion' => false,
            'status' => $game['status']
        ]);
        return;
    }

    // Get question details
    $question = $db->fetchOne(
        "SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer, category, points, time_limit
         FROM questions WHERE id = ?",
        [$game['current_question']]
    );

    if (!$question) {
        echo json_encode([
            'success' => true,
            'hasQuestion' => false
        ]);
        return;
    }

    // Check if player already answered
    $alreadyAnswered = false;
    $answerResult = null;
    if ($playerId) {
        $existingAnswer = $db->fetchOne(
            "SELECT selected_answer, is_correct, points_earned FROM answers WHERE player_id = ? AND question_id = ?",
            [$playerId, $question['id']]
        );
        if ($existingAnswer) {
            $alreadyAnswered = true;
            $answerResult = [
                'isCorrect' => (bool)$existingAnswer['is_correct'],
                'pointsEarned' => $existingAnswer['points_earned'],
                'selectedAnswer' => $existingAnswer['selected_answer'],
                'correctAnswer' => $question['correct_answer']
            ];
        }
    }

    // Calculate question number from answers count for this game
    // This is approximate but works for display
    $answeredCount = $db->fetchOne(
        "SELECT COUNT(DISTINCT a.question_id) as count
         FROM answers a
         JOIN players p ON a.player_id = p.id
         JOIN games g ON p.game_id = g.id
         WHERE g.game_code = ?",
        [$gameCode]
    )['count'] ?? 0;
    
    // Question number is count + 1 (since we're showing the next question)
    $questionNumber = $answeredCount + 1;

    echo json_encode([
        'success' => true,
        'hasQuestion' => true,
        'questionNumber' => $questionNumber,
        'question' => [
            'id' => $question['id'],
            'question_text' => $question['question_text'],
            'options' => [
                'A' => $question['option_a'],
                'B' => $question['option_b'],
                'C' => $question['option_c'],
                'D' => $question['option_d']
            ],
            'timeLimit' => $question['time_limit'],
            'points' => $question['points']
        ],
        'alreadyAnswered' => $alreadyAnswered,
        'answerResult' => $answerResult
    ]);
}

function setCurrentQuestion($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $gameCode = $input['gameCode'] ?? '';
    $questionId = $input['questionId'] ?? null;
    $questionNumber = $input['questionNumber'] ?? 0;

    if (!$gameCode) {
        http_response_code(400);
        echo json_encode(['error' => 'Game code required']);
        return;
    }

    // Update game state
    if ($questionId) {
        // Starting a new question - store question ID and number
        // We'll use a JSON field or just store number separately
        // For now, we'll store question number in a comment or calculate from answers
        // Actually, let's add it to the response and calculate from game state
        $db->query(
            "UPDATE games SET current_question = ?, status = 'playing', updated_at = NOW() WHERE game_code = ?",
            [$questionId, $gameCode]
        );
        
        // Store question number in a temporary way - use JSON in a comment or add field later
        // For now, we'll just return it in the response
    } else {
        // Clearing question (between questions or end of game)
        $db->query(
            "UPDATE games SET current_question = NULL, updated_at = NOW() WHERE game_code = ?",
            [$gameCode]
        );
    }

    echo json_encode([
        'success' => true,
        'questionNumber' => $questionNumber,
        'questionId' => $questionId
    ]);
}

function getGameUpdates($db) {
    $gameCode = $_GET['code'] ?? '';
    $lastCheck = $_GET['lastCheck'] ?? '0';

    if (!$gameCode) {
        http_response_code(400);
        echo json_encode(['error' => 'Game code required']);
        return;
    }

    // Get game with players count
    $game = $db->fetchOne(
        "SELECT g.*, COUNT(DISTINCT p.id) as player_count
         FROM games g
         LEFT JOIN players p ON g.id = p.game_id
         WHERE g.game_code = ?
         GROUP BY g.id",
        [$gameCode]
    );

    if (!$game) {
        http_response_code(404);
        echo json_encode(['error' => 'Game not found']);
        return;
    }

    // Get recent answers (since last check)
    $answersCount = 0;
    if ($lastCheck && $lastCheck !== '0') {
        $timestamp = date('Y-m-d H:i:s', (int)$lastCheck / 1000);
        $answersCount = $db->fetchOne(
            "SELECT COUNT(DISTINCT a.player_id) as count
             FROM answers a
             JOIN players p ON a.player_id = p.id
             JOIN games g ON p.game_id = g.id
             WHERE g.game_code = ? AND a.answered_at > ?",
            [$gameCode, $timestamp]
        )['count'] ?? 0;
    }

    echo json_encode([
        'success' => true,
        'totalPlayers' => (int)$game['player_count'],
        'newAnswers' => $answersCount,
        'status' => $game['status'],
        'currentQuestion' => $game['current_question'],
        'lastUpdate' => time() * 1000 // Current timestamp in milliseconds
    ]);
}
