<?php

// Set session cookie parameters BEFORE starting session
// Use session_set_cookie_params() instead of ini_set() to avoid warnings
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use QuizGame\Database;

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove base path if present (e.g., /worshipTeam/api/admin-login -> admin-login)
// Find the position of 'api' in the path
$pathParts = explode('/', trim($path, '/'));
$apiIndex = array_search('api', $pathParts);

if ($apiIndex !== false && $apiIndex < count($pathParts) - 1) {
    // Get everything after 'api'
    $action = $pathParts[$apiIndex + 1] ?? '';
} else {
    // Fallback: get the last part
    $action = $pathParts[count($pathParts) - 1] ?? '';
}

// Clean up action (remove query string if present)
$action = explode('?', $action)[0];

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

        // Admin endpoints
        case 'admin-login':
            if ($method === 'POST') {
                adminLogin($db);
            }
            break;

        case 'admin-questions':
            if ($method === 'GET') {
                requireAdminAuth();
                getAdminQuestions($db);
            } elseif ($method === 'POST') {
                requireAdminAuth();
                createQuestion($db);
            } elseif ($method === 'PUT') {
                requireAdminAuth();
                updateQuestion($db);
            } elseif ($method === 'DELETE') {
                requireAdminAuth();
                deleteQuestion($db);
            }
            break;

        case 'admin-logout':
            if ($method === 'POST') {
                adminLogout();
            }
            break;

        case 'admin-check':
            if ($method === 'GET') {
                checkAdminAuth();
            }
            break;

        case 'admin-forgot-password':
            if ($method === 'POST') {
                forgotPassword($db);
            }
            break;

        case 'admin-reset-password':
            if ($method === 'POST') {
                resetPassword($db);
            }
            break;

        case 'admin-change-password':
            if ($method === 'POST') {
                requireAdminAuth();
                changePassword($db);
            }
            break;

        default:
            // Debug info for 404 errors
            http_response_code(404);
            echo json_encode([
                'error' => 'Endpoint not found',
                'debug' => [
                    'action' => $action,
                    'method' => $method,
                    'path' => $path,
                    'pathParts' => $pathParts,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not set'
                ]
            ]);
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
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
            return;
        }
        
        $playerId = $input['playerId'] ?? null;
        $questionId = $input['questionId'] ?? null;
        $answer = $input['answer'] ?? null;
        $responseTime = $input['responseTime'] ?? 0;

        // Validate inputs
        if (!$playerId || !$questionId || !$answer) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Missing required fields',
                'debug' => [
                    'playerId' => $playerId,
                    'questionId' => $questionId,
                    'answer' => $answer
                ]
            ]);
            return;
        }

        // Validate player exists
        $player = $db->fetchOne("SELECT id FROM players WHERE id = ?", [$playerId]);
        if (!$player) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Player not found']);
            return;
        }

        // Get correct answer
        $question = $db->fetchOne(
            "SELECT correct_answer, points FROM questions WHERE id = ?",
            [$questionId]
        );

        if (!$question) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Question not found']);
            return;
        }

        // Normalize answer comparison
        $submittedAnswer = strtoupper(trim($answer));
        $correctAnswer = strtoupper(trim($question['correct_answer']));
        $isCorrect = ($submittedAnswer === $correctAnswer);

        // Explicitly cast boolean to integer (0 or 1) for database insertion
        // This prevents MySQL from receiving an empty string when $isCorrect is false
        $isCorrectInt = $isCorrect ? 1 : 0;

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

        // Use integer value for database insertion
        $db->query($sql, [$playerId, $questionId, $answer, $isCorrectInt, $pointsEarned, $responseTime]);

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
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
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

// ==================== ADMIN FUNCTIONS ====================

function requireAdminAuth() {
    if (!isset($_SESSION['admin_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

function checkAdminAuth() {
    if (isset($_SESSION['admin_id'])) {
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'username' => $_SESSION['admin_username'] ?? 'admin'
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'authenticated' => false
        ]);
    }
}

function adminLogin($db) {
    try {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => 'Invalid JSON: ' . json_last_error_msg()
            ]);
            return;
        }
        
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if (!$username || !$password) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'اسم المستخدم وكلمة المرور مطلوبان']);
            return;
        }

        // Check if admins table exists
        try {
            $tableCheck = $db->fetchOne("SHOW TABLES LIKE 'admins'");
            if (!$tableCheck) {
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'error' => 'جدول المشرفين غير موجود. يرجى تشغيل admin_setup.sql أولاً.'
                ]);
                return;
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'error' => 'خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage()
            ]);
            return;
        }

        // Get admin from database
        try {
            $admin = $db->fetchOne(
                "SELECT id, username, password_hash FROM admins WHERE username = ?",
                [$username]
            );
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'error' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()
            ]);
            return;
        }

        if (!$admin) {
            // Auto-create admin user if it doesn't exist (for first-time setup)
            if ($username === 'admin' && $password === 'admin123') {
                try {
                    // Create default admin with correct hash
                    $defaultHash = password_hash('admin123', PASSWORD_DEFAULT);
                    $db->query(
                        "INSERT INTO admins (username, password_hash, email) VALUES (?, ?, ?)",
                        ['admin', $defaultHash, 'moody.gindy@gmail.com']
                    );
                    
                    // Retry login
                    $admin = $db->fetchOne(
                        "SELECT id, username, password_hash FROM admins WHERE username = ?",
                        [$username]
                    );
                    
                    if ($admin) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'تم إنشاء المستخدم المشرف تلقائياً',
                            'token' => session_id(),
                            'username' => $admin['username']
                        ]);
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        return;
                    }
                } catch (Exception $createError) {
                    // Fall through to error message below
                }
            }
            
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'اسم المستخدم أو كلمة المرور غير صحيح']);
            return;
        }

        // Check if password_hash is valid - fix if corrupted
        if (empty($admin['password_hash']) || strlen($admin['password_hash']) < 10) {
            // Auto-fix corrupted hash for default admin
            if ($username === 'admin' && $password === 'admin123') {
                try {
                    $newHash = password_hash('admin123', PASSWORD_DEFAULT);
                    $db->query(
                        "UPDATE admins SET password_hash = ? WHERE id = ?",
                        [$newHash, $admin['id']]
                    );
                    $admin['password_hash'] = $newHash;
                } catch (Exception $fixError) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'error' => 'خطأ في إصلاح كلمة المرور: ' . $fixError->getMessage()
                    ]);
                    return;
                }
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'error' => 'كلمة المرور غير صحيحة في قاعدة البيانات. يرجى استخدام reset_admin_password.php لإصلاحها.'
                ]);
                return;
            }
        }

        // Verify password
        if (!password_verify($password, $admin['password_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'اسم المستخدم أو كلمة المرور غير صحيح']);
            return;
        }

        // Store admin info in session (session already started at top of file)
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];

        echo json_encode([
            'success' => true,
            'token' => session_id(),
            'username' => $admin['username']
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'خطأ في الخادم: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

function adminLogout() {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
    echo json_encode(['success' => true]);
}

function getAdminQuestions($db) {
    $category = $_GET['category'] ?? null;
    $search = $_GET['search'] ?? null;

    $sql = "SELECT id, question_text, option_a, option_b, option_c, option_d, 
                   correct_answer, category, points, time_limit, created_at
            FROM questions WHERE 1=1";
    $params = [];

    if ($category && $category !== 'all') {
        $sql .= " AND category = ?";
        $params[] = $category;
    }

    if ($search) {
        $sql .= " AND (question_text LIKE ? OR option_a LIKE ? OR option_b LIKE ? 
                      OR option_c LIKE ? OR option_d LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    $sql .= " ORDER BY id DESC";

    $questions = $db->fetchAll($sql, $params);

    echo json_encode([
        'success' => true,
        'questions' => $questions
    ]);
}

function createQuestion($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    $questionText = $input['question_text'] ?? '';
    $optionA = $input['option_a'] ?? '';
    $optionB = $input['option_b'] ?? '';
    $optionC = $input['option_c'] ?? '';
    $optionD = $input['option_d'] ?? '';
    $correctAnswer = strtoupper($input['correct_answer'] ?? '');
    $category = $input['category'] ?? 'general';
    $points = (int)($input['points'] ?? 100);
    $timeLimit = (int)($input['time_limit'] ?? 30);

    // Validate
    if (!$questionText || !$optionA || !$optionB || !$optionC || !$optionD || !$correctAnswer) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'جميع الحقول مطلوبة']);
        return;
    }

    if (!in_array($correctAnswer, ['A', 'B', 'C', 'D'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'الإجابة الصحيحة يجب أن تكون A, B, C, أو D']);
        return;
    }

    if (!in_array($category, ['music', 'bible', 'general', 'sports'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'فئة غير صالحة']);
        return;
    }

    try {
        $db->query(
            "INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, 
                                   correct_answer, category, points, time_limit)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$questionText, $optionA, $optionB, $optionC, $optionD, $correctAnswer, $category, $points, $timeLimit]
        );

        $questionId = $db->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'تم إضافة السؤال بنجاح',
            'questionId' => $questionId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'حدث خطأ: ' . $e->getMessage()]);
    }
}

function updateQuestion($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $questionId = $input['id'] ?? null;

    if (!$questionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف السؤال مطلوب']);
        return;
    }

    $questionText = $input['question_text'] ?? '';
    $optionA = $input['option_a'] ?? '';
    $optionB = $input['option_b'] ?? '';
    $optionC = $input['option_c'] ?? '';
    $optionD = $input['option_d'] ?? '';
    $correctAnswer = strtoupper($input['correct_answer'] ?? '');
    $category = $input['category'] ?? 'general';
    $points = (int)($input['points'] ?? 100);
    $timeLimit = (int)($input['time_limit'] ?? 30);

    // Validate
    if (!$questionText || !$optionA || !$optionB || !$optionC || !$optionD || !$correctAnswer) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'جميع الحقول مطلوبة']);
        return;
    }

    if (!in_array($correctAnswer, ['A', 'B', 'C', 'D'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'الإجابة الصحيحة يجب أن تكون A, B, C, أو D']);
        return;
    }

    try {
        $db->query(
            "UPDATE questions SET 
                question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?,
                correct_answer = ?, category = ?, points = ?, time_limit = ?
             WHERE id = ?",
            [$questionText, $optionA, $optionB, $optionC, $optionD, $correctAnswer, $category, $points, $timeLimit, $questionId]
        );

        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث السؤال بنجاح'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'حدث خطأ: ' . $e->getMessage()]);
    }
}

function deleteQuestion($db) {
    $questionId = $_GET['id'] ?? null;

    if (!$questionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف السؤال مطلوب']);
        return;
    }

    try {
        $db->query("DELETE FROM questions WHERE id = ?", [$questionId]);

        echo json_encode([
            'success' => true,
            'message' => 'تم حذف السؤال بنجاح'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'حدث خطأ: ' . $e->getMessage()]);
    }
}

function forgotPassword($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';

    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'البريد الإلكتروني مطلوب']);
        return;
    }

    // Find admin by email
    $admin = $db->fetchOne(
        "SELECT id, username, email FROM admins WHERE email = ?",
        [$email]
    );

    // Always return success message for security (don't reveal if email exists)
    $successMessage = 'إذا كان البريد الإلكتروني موجوداً في النظام، سيتم إرسال رابط إعادة التعيين إليه.';

    if ($admin) {
        // Generate reset token
        $resetToken = bin2hex(random_bytes(32)); // 64 character token
        $resetTokenExpires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

        // Save token to database
        $db->query(
            "UPDATE admins SET reset_token = ?, reset_token_expires = ? WHERE id = ?",
            [$resetToken, $resetTokenExpires, $admin['id']]
        );

        // Send reset email
        $resetLink = getResetLink($resetToken);
        sendPasswordResetEmail($admin['email'], $admin['username'], $resetLink);
    }

    echo json_encode([
        'success' => true,
        'message' => $successMessage
    ]);
}

function resetPassword($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';
    $newPassword = $input['password'] ?? '';

    if (!$token || !$newPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'الرمز وكلمة المرور مطلوبان']);
        return;
    }

    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل']);
        return;
    }

    // Find admin by token
    $admin = $db->fetchOne(
        "SELECT id, reset_token, reset_token_expires FROM admins WHERE reset_token = ? AND reset_token_expires > NOW()",
        [$token]
    );

    if (!$admin) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'الرمز غير صحيح أو منتهي الصلاحية. يرجى طلب رابط جديد.']);
        return;
    }

    // Hash new password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password and clear reset token
    $db->query(
        "UPDATE admins SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?",
        [$passwordHash, $admin['id']]
    );

    echo json_encode([
        'success' => true,
        'message' => 'تم تغيير كلمة المرور بنجاح'
    ]);
}

function getResetLink($token) {
    // Get the base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    // Remove /api from path if present
    $path = str_replace('/api', '', $path);
    
    return "$protocol://$host$path/admin/reset-password.html?token=$token";
}

function sendPasswordResetEmail($email, $username, $resetLink) {
    $to = $email;
    $subject = 'إعادة تعيين كلمة مرور المشرف - Quiz Game';
    
    // HTML email
    $message = '
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                direction: rtl;
                text-align: right;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background: #f9f9f9;
            }
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                border-radius: 10px 10px 0 0;
                text-align: center;
            }
            .content {
                background: white;
                padding: 30px;
                border-radius: 0 0 10px 10px;
            }
            .button {
                display: inline-block;
                padding: 15px 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                margin: 20px 0;
                font-weight: bold;
            }
            .footer {
                text-align: center;
                margin-top: 20px;
                color: #666;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔑 إعادة تعيين كلمة المرور</h1>
            </div>
            <div class="content">
                <p>مرحباً <strong>' . htmlspecialchars($username) . '</strong>,</p>
                <p>لقد طلبت إعادة تعيين كلمة المرور لحساب المشرف.</p>
                <p>انقر على الزر أدناه لإعادة تعيين كلمة المرور:</p>
                <p style="text-align: center;">
                    <a href="' . htmlspecialchars($resetLink) . '" class="button">إعادة تعيين كلمة المرور</a>
                </p>
                <p>أو انسخ الرابط التالي والصقه في المتصفح:</p>
                <p style="background: #f0f0f0; padding: 10px; border-radius: 5px; word-break: break-all;">
                    ' . htmlspecialchars($resetLink) . '
                </p>
                <p><strong>ملاحظة:</strong> هذا الرابط صالح لمدة ساعة واحدة فقط.</p>
                <p>إذا لم تطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذا البريد الإلكتروني.</p>
            </div>
            <div class="footer">
                <p>هذا بريد إلكتروني تلقائي، يرجى عدم الرد عليه.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Email headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Quiz Game Admin <noreply@' . $_SERVER['HTTP_HOST'] . '>',
        'Reply-To: noreply@' . $_SERVER['HTTP_HOST'],
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Send email
    $result = mail($to, $subject, $message, implode("\r\n", $headers));
    
    // Log if email sending failed (for debugging)
    if (!$result) {
        error_log("Failed to send password reset email to: $email");
    }
    
    return $result;
}

function changePassword($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';

    if (!$currentPassword || !$newPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'كلمة المرور الحالية والجديدة مطلوبتان']);
        return;
    }

    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل']);
        return;
    }

    // Get current admin
    $adminId = $_SESSION['admin_id'] ?? null;
    if (!$adminId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'غير مصرح']);
        return;
    }

    // Get admin's current password hash
    $admin = $db->fetchOne(
        "SELECT password_hash FROM admins WHERE id = ?",
        [$adminId]
    );

    if (!$admin) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'المستخدم غير موجود']);
        return;
    }

    // Verify current password
    if (!password_verify($currentPassword, $admin['password_hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'كلمة المرور الحالية غير صحيحة']);
        return;
    }

    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $db->query(
        "UPDATE admins SET password_hash = ? WHERE id = ?",
        [$newPasswordHash, $adminId]
    );

    echo json_encode([
        'success' => true,
        'message' => 'تم تغيير كلمة المرور بنجاح'
    ]);
}
