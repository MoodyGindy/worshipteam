<?php

namespace QuizGame;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class QuizGameServer implements MessageComponentInterface {
    protected $clients;
    protected $games;
    protected $db;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->games = [];
        $this->db = Database::getInstance();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        if (!$data || !isset($data['type'])) {
            return;
        }

        switch ($data['type']) {
            case 'join':
                $this->handleJoin($from, $data);
                break;
            case 'register_host':
                $this->handleRegisterHost($from, $data);
                break;
            case 'answer':
                $this->handleAnswer($from, $data);
                break;
            case 'next_question':
                $this->handleNextQuestion($from, $data);
                break;
            case 'start_game':
                $this->handleStartGame($from, $data);
                break;
            case 'end_game':
                $this->handleEndGame($from, $data);
                break;
        }
    }

    private function handleRegisterHost($conn, $data) {
        $gameCode = $data['gameCode'] ?? '';
        
        if (!$gameCode) {
            return;
        }

        // Mark this connection as host
        $conn->isHost = true;
        $conn->gameCode = $gameCode;

        echo "Host registered for game: $gameCode ({$conn->resourceId})\n";
    }

    private function handleJoin($conn, $data) {
        $gameCode = $data['gameCode'] ?? '';
        $playerName = $data['playerName'] ?? '';
        $sessionId = uniqid('player_', true);

        // Store player in database
        $sql = "INSERT INTO players (game_id, player_name, session_id)
                SELECT id, ?, ? FROM games WHERE game_code = ? LIMIT 1";
        $this->db->query($sql, [$playerName, $sessionId, $gameCode]);

        $playerId = $this->db->lastInsertId();

        // Store connection info
        $conn->playerId = $playerId;
        $conn->gameCode = $gameCode;
        $conn->sessionId = $sessionId;

        echo "Player joined: $playerName (game: $gameCode, id: $playerId)\n";

        // Send confirmation to player
        $conn->send(json_encode([
            'type' => 'joined',
            'playerId' => $playerId,
            'playerName' => $playerName,
            'sessionId' => $sessionId
        ]));

        // Broadcast to host
        $totalPlayers = $this->getPlayerCount($gameCode);
        echo "Broadcasting player_joined to host. Total players: $totalPlayers\n";
        $this->broadcastToHost($gameCode, [
            'type' => 'player_joined',
            'playerName' => $playerName,
            'totalPlayers' => $totalPlayers
        ]);
    }

    private function handleAnswer($conn, $data) {
        try {
            echo "\n=== ANSWER SUBMISSION ===\n";
            $playerId = $conn->playerId ?? null;
            $questionId = $data['questionId'] ?? null;
            $answer = $data['answer'] ?? null;
            $responseTime = $data['responseTime'] ?? 0;

            echo "Player ID: " . ($playerId ?? 'NULL') . "\n";
            echo "Question ID: " . ($questionId ?? 'NULL') . "\n";
            echo "Answer: " . ($answer ?? 'NULL') . "\n";
            echo "Connection ID: {$conn->resourceId}\n";
            echo "Connection gameCode: " . ($conn->gameCode ?? 'NOT SET') . "\n";

            if (!$playerId || !$questionId || !$answer) {
                echo "❌ Invalid answer data: playerId=$playerId, questionId=$questionId, answer=$answer\n";
                echo "=== END ANSWER SUBMISSION (ERROR) ===\n\n";
                return;
            }

            // Get correct answer
            $question = $this->db->fetchOne(
                "SELECT correct_answer, points FROM questions WHERE id = ?",
                [$questionId]
            );

            if (!$question) {
                echo "❌ Question not found: $questionId\n";
                echo "=== END ANSWER SUBMISSION (ERROR) ===\n\n";
                return;
            }

            // Normalize answer comparison (trim whitespace and convert to uppercase)
            $submittedAnswer = strtoupper(trim($answer));
            $correctAnswer = strtoupper(trim($question['correct_answer']));
            $isCorrect = ($submittedAnswer === $correctAnswer);
            
            echo "Answer check: Submitted='$submittedAnswer', Correct='$correctAnswer', Match=" . ($isCorrect ? 'YES ✓' : 'NO ✗') . "\n";

            // Calculate points (faster answers get more points)
            $pointsEarned = 0;
            if ($isCorrect) {
                $timeBonus = max(0, 1 - ($responseTime / 30)); // 30 seconds max
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

            $this->db->query($sql, [$playerId, $questionId, $answer, $isCorrect, $pointsEarned, $responseTime]);
            echo "✓ Answer saved to database\n";

            // Update total score
            $this->db->query(
                "UPDATE players SET total_score = (
                    SELECT COALESCE(SUM(points_earned), 0) FROM answers WHERE player_id = ?
                ) WHERE id = ?",
                [$playerId, $playerId]
            );
            echo "✓ Score updated\n";

            echo "Answer submitted: Player $playerId, Question $questionId, Answer $answer, Correct: " . ($isCorrect ? 'Yes ✓' : 'No ✗') . ", Points: $pointsEarned\n";

            // Send result to player - CRITICAL: Don't let this fail
            try {
                $resultMessage = json_encode([
                    'type' => 'answer_result',
                    'isCorrect' => $isCorrect,
                    'pointsEarned' => $pointsEarned,
                    'correctAnswer' => $question['correct_answer']
                ]);
                
                $conn->send($resultMessage);
                echo "✓ Answer result sent to player\n";
            } catch (\Exception $sendError) {
                echo "❌ ERROR sending answer_result to player: " . $sendError->getMessage() . "\n";
                // Don't rethrow - connection might still be usable
            }

            // Notify host that an answer was submitted
            $gameCode = $conn->gameCode ?? '';
            if ($gameCode) {
                try {
                    $this->broadcastToHost($gameCode, [
                        'type' => 'answer_submitted',
                        'totalPlayers' => $this->getPlayerCount($gameCode)
                    ]);
                    echo "✓ Host notified\n";
                } catch (\Exception $hostError) {
                    echo "⚠️  Could not notify host: " . $hostError->getMessage() . "\n";
                }
            } else {
                echo "⚠️  No gameCode on connection - cannot notify host\n";
            }
            
            echo "=== END ANSWER SUBMISSION (SUCCESS) ===\n\n";
            
        } catch (\Exception $e) {
            echo "═══════════════════════════════════════\n";
            echo "❌❌❌ FATAL ERROR in handleAnswer ❌❌❌\n";
            echo "Error: {$e->getMessage()}\n";
            echo "File: {$e->getFile()}:{$e->getLine()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";
            echo "═══════════════════════════════════════\n";
            // Don't close connection - try to keep it alive
        }
    }

    private function handleNextQuestion($conn, $data) {
        $gameCode = $data['gameCode'] ?? '';
        $questionNumber = $data['questionNumber'] ?? 0;
        $questionId = $data['questionId'] ?? null;

        echo "\n=== NEXT QUESTION REQUEST ===\n";
        echo "Game Code: $gameCode\n";
        echo "Question Number: $questionNumber\n";
        echo "Question ID: " . ($questionId ?? 'NULL') . "\n";

        // Update game state (non-blocking, but do it quickly)
        $this->db->query(
            "UPDATE games SET current_question = ? WHERE game_code = ?",
            [$questionNumber, $gameCode]
        );

        // Get question by ID if provided (preferred - faster and more reliable)
        if ($questionId) {
            $question = $this->db->fetchOne(
                "SELECT * FROM questions WHERE id = ?",
                [$questionId]
            );
            if (!$question) {
                echo "ERROR: Question ID $questionId not found!\n";
                return;
            }
        } else {
            // Fallback: get by offset (slower)
            $question = $this->db->fetchOne(
                "SELECT * FROM questions ORDER BY id LIMIT 1 OFFSET ?",
                [$questionNumber - 1]
            );
            if (!$question) {
                echo "ERROR: Question at offset " . ($questionNumber - 1) . " not found!\n";
                return;
            }
        }

        // Prepare question data
        $questionData = [
            'type' => 'new_question',
            'questionNumber' => $questionNumber,
            'questionId' => $question['id'],
            'options' => [
                'A' => $question['option_a'],
                'B' => $question['option_b'],
                'C' => $question['option_c'],
                'D' => $question['option_d']
            ],
            'timeLimit' => $question['time_limit']
        ];
        
        echo "Question found: ID={$question['id']}, Text=\"{$question['question_text']}\"\n";
        echo "Broadcasting to players immediately...\n";
        
        // Broadcast question to all players IMMEDIATELY (synchronously)
        $this->broadcastToGame($gameCode, $questionData);
        
        echo "Question broadcast complete!\n";
        echo "===============================\n\n";

        // Send confirmation to host (optional - host already has the question)
        $this->broadcastToHost($gameCode, [
            'type' => 'question_broadcast',
            'questionNumber' => $questionNumber,
            'questionId' => $question['id']
        ]);
    }

    private function handleStartGame($conn, $data) {
        $gameCode = $data['gameCode'] ?? '';

        $this->db->query(
            "UPDATE games SET status = 'playing' WHERE game_code = ?",
            [$gameCode]
        );

        $this->broadcastToGame($gameCode, [
            'type' => 'game_started'
        ]);
    }

    private function handleEndGame($conn, $data) {
        $gameCode = $data['gameCode'] ?? '';

        $this->db->query(
            "UPDATE games SET status = 'finished' WHERE game_code = ?",
            [$gameCode]
        );

        // Get top 3 winners
        $winners = $this->db->fetchAll(
            "SELECT player_name, total_score
             FROM players p
             JOIN games g ON p.game_id = g.id
             WHERE g.game_code = ?
             ORDER BY total_score DESC
             LIMIT 3",
            [$gameCode]
        );

        $this->broadcastToGame($gameCode, [
            'type' => 'game_ended',
            'winners' => $winners
        ]);
    }

    private function broadcastToGame($gameCode, $message) {
        $sentCount = 0;
        $failedCount = 0;
        $messageJson = json_encode($message);
        
        echo "Broadcasting message type '{$message['type']}' to game '$gameCode'...\n";
        echo "Total connected clients: " . count($this->clients) . "\n";
        
        foreach ($this->clients as $client) {
            $clientGameCode = $client->gameCode ?? 'NOT SET';
            $isHost = isset($client->isHost) && $client->isHost;
            $hasPlayerId = isset($client->playerId);
            
            echo "  Checking client {$client->resourceId}: gameCode='$clientGameCode', isHost=" . ($isHost ? 'YES' : 'NO') . ", hasPlayerId=" . ($hasPlayerId ? 'YES' : 'NO') . "\n";
            
            // Send to all players in this game (but not hosts)
            if ($clientGameCode === $gameCode && !$isHost) {
                try {
                    $client->send($messageJson);
                    $sentCount++;
                    echo "  ✓✓✓ SENT to player {$client->resourceId} (playerId: " . ($client->playerId ?? 'N/A') . ")\n";
                } catch (\Exception $e) {
                    $failedCount++;
                    echo "  ✗✗✗ ERROR sending to player {$client->resourceId}: " . $e->getMessage() . "\n";
                }
            } else {
                if ($clientGameCode !== $gameCode) {
                    echo "  → Skipped (wrong game: '$clientGameCode' != '$gameCode')\n";
                }
                if ($isHost) {
                    echo "  → Skipped (is host)\n";
                }
            }
        }
        
        echo "═══════════════════════════════════════\n";
        echo "✓✓✓ Broadcast complete: $sentCount sent, $failedCount failed\n";
        echo "═══════════════════════════════════════\n";
        
        if ($sentCount === 0) {
            echo "🚨🚨🚨 WARNING: NO PLAYERS RECEIVED THE MESSAGE! 🚨🚨🚨\n";
            echo "This means players are not connected or have wrong gameCode!\n";
        }
    }

    private function broadcastToHost($gameCode, $message) {
        foreach ($this->clients as $client) {
            if (isset($client->isHost) && $client->isHost && isset($client->gameCode) && $client->gameCode === $gameCode) {
                $client->send(json_encode($message));
            }
        }
    }

    private function getPlayerCount($gameCode) {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM players p
             JOIN games g ON p.game_id = g.id
             WHERE g.game_code = ?",
            [$gameCode]
        );
        return $result['count'] ?? 0;
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "═══════════════════════════════════════\n";
        echo "❌ ERROR on connection {$conn->resourceId}:\n";
        echo "   Message: {$e->getMessage()}\n";
        echo "   File: {$e->getFile()}:{$e->getLine()}\n";
        echo "   Stack trace:\n";
        echo $e->getTraceAsString();
        echo "\n═══════════════════════════════════════\n";
        
        // Don't close on error - let it try to continue
        // Only close if it's a critical error
        if (strpos($e->getMessage(), 'fatal') !== false || strpos($e->getMessage(), 'Fatal') !== false) {
            echo "⚠️  Closing connection due to fatal error\n";
            $conn->close();
        } else {
            echo "⚠️  Error handled, connection kept open\n";
        }
    }
}
