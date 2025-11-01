const API_URL = 'https://kdsc.fun/worshipteam/worshipteam/api';

let gameCode = null;
let playerId = null;
let sessionId = null;
let playerName = null;
let currentQuestionId = null;
let lastQuestionId = null; // Track last question to detect new ones
let selectedAnswer = null;
let totalScore = 0;
let questionStartTime = null;
let timerInterval = null;
let pollingInterval = null;
let hasAnsweredCurrentQuestion = false;

// Initialize
function init() {
    // Get game code from URL
    const urlParams = new URLSearchParams(window.location.search);
    gameCode = urlParams.get('code');

    if (gameCode) {
        document.getElementById('gameCodeInput').value = gameCode;
    }

    // Setup event listeners
    document.getElementById('joinButton').addEventListener('click', joinGame);
    document.getElementById('submitButton').addEventListener('click', submitAnswer);

    // Setup option buttons
    document.querySelectorAll('.option-button').forEach(button => {
        button.addEventListener('click', selectOption);
    });
}

async function joinGame() {
    const nameInput = document.getElementById('playerNameInput').value.trim();
    const codeInput = document.getElementById('gameCodeInput').value.trim();

    if (!nameInput) {
        alert('الرجاء إدخال اسمك');
        return;
    }

    if (!codeInput) {
        alert('الرجاء إدخال رمز اللعبة');
        return;
    }

    playerName = nameInput;
    gameCode = codeInput;

    try {
        // Join game via API
        const response = await fetch(`${API_URL}/join-game`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                gameCode: gameCode,
                playerName: playerName
            })
        });

        const data = await response.json();

        if (data.success) {
            playerId = data.playerId;
            sessionId = data.sessionId;

            console.log('✅ Joined game successfully:', {
                playerId: playerId,
                sessionId: sessionId,
                gameCode: gameCode
            });

            // Show waiting screen
            document.getElementById('joinScreen').classList.add('hidden');
            document.getElementById('waitingScreen').classList.remove('hidden');
            document.getElementById('waitingScreen').classList.add('show-flex');

            // Start polling for questions
            startPolling();
        } else {
            alert('فشل الانضمام إلى اللعبة: ' + (data.error || 'خطأ غير معروف'));
        }
    } catch (error) {
        console.error('Error joining game:', error);
        alert('حدث خطأ في الانضمام إلى اللعبة');
    }
}

function startPolling() {
    // Poll every 1.5 seconds for new questions
    pollingInterval = setInterval(async () => {
        try {
            const response = await fetch(`${API_URL}/get-current-question?code=${gameCode}&playerId=${playerId}`);
            const data = await response.json();

            if (data.success) {
                if (data.hasQuestion) {
                    // Check if this is a new question
                    const questionId = data.question.id;
                    
                    if (questionId !== lastQuestionId) {
                        // New question!
                        console.log('🎯 NEW QUESTION DETECTED:', questionId);
                        lastQuestionId = questionId;
                        currentQuestionId = questionId;
                        handleNewQuestion(data);
                    } else if (data.alreadyAnswered && !hasAnsweredCurrentQuestion) {
                        // Player already answered this question (maybe refreshed page)
                        hasAnsweredCurrentQuestion = true;
                        if (data.answerResult) {
                            showAnswerResult(data.answerResult);
                        }
                    }
                } else {
                    // No question available (waiting for host)
                    if (data.status === 'lobby') {
                        // Still in lobby, keep waiting
                        console.log('⏳ Waiting for game to start...');
                    }
                }
            }
        } catch (error) {
            console.error('Error polling for question:', error);
        }
    }, 1500); // Poll every 1.5 seconds
}

function handleNewQuestion(data) {
    console.log('═══════════════════════════════════════');
    console.log('🎯🎯🎯 NEW QUESTION 🎯🎯🎯');
    console.log('Question Number:', data.questionNumber);
    console.log('Question ID:', data.question.id);
    console.log('═══════════════════════════════════════');

    // Reset state
    selectedAnswer = null;
    hasAnsweredCurrentQuestion = false;
    questionStartTime = Date.now();

    // FORCE SHOW question screen
    const questionScreen = document.getElementById('questionScreen');
    if (questionScreen) {
        questionScreen.removeAttribute('class');
        questionScreen.style.cssText = 'display: flex !important; visibility: visible !important; opacity: 1 !important; z-index: 99999 !important; position: relative !important; flex-direction: column !important;';
    }

    // FORCE HIDE other screens
    const allScreens = ['waitingScreen', 'resultScreen', 'joinScreen', 'winnersScreen'];
    allScreens.forEach(screenId => {
        const screen = document.getElementById(screenId);
        if (screen) {
            screen.style.cssText = 'display: none !important; visibility: hidden !important; opacity: 0 !important; z-index: -1 !important;';
            screen.removeAttribute('class');
        }
    });

    // Update UI
    const currentQElement = document.getElementById('currentQuestion');
    if (currentQElement) {
        currentQElement.textContent = data.questionNumber;
    }

    const totalQElement = document.getElementById('totalQuestions');
    if (totalQElement) {
        totalQElement.textContent = '20';
    }

    // Set question text (if there's a question text element)
    const questionTextElement = document.getElementById('questionText');
    if (questionTextElement) {
        questionTextElement.textContent = data.question.question_text;
    }

    // Set options
    const optionA = document.getElementById('optionA');
    const optionB = document.getElementById('optionB');
    const optionC = document.getElementById('optionC');
    const optionD = document.getElementById('optionD');

    if (optionA && data.question.options.A) optionA.textContent = `أ) ${data.question.options.A}`;
    if (optionB && data.question.options.B) optionB.textContent = `ب) ${data.question.options.B}`;
    if (optionC && data.question.options.C) optionC.textContent = `ج) ${data.question.options.C}`;
    if (optionD && data.question.options.D) optionD.textContent = `د) ${data.question.options.D}`;

    // Reset buttons
    document.querySelectorAll('.option-button').forEach(button => {
        button.classList.remove('selected', 'correct', 'wrong');
        button.disabled = false;
        button.style.opacity = '1';
        button.style.pointerEvents = 'auto';
    });

    const submitBtn = document.getElementById('submitButton');
    if (submitBtn) {
        submitBtn.disabled = true;
    }

    // Start timer
    startTimer(data.question.timeLimit || 30);

    console.log('✅ Question displayed successfully');
}

function selectOption(event) {
    if (selectedAnswer || hasAnsweredCurrentQuestion) return; // Already selected or answered

    const button = event.currentTarget;
    selectedAnswer = button.dataset.answer;

    // Clear previous selection
    document.querySelectorAll('.option-button').forEach(btn => {
        btn.classList.remove('selected');
    });

    // Mark selected
    button.classList.add('selected');

    // Enable submit button
    document.getElementById('submitButton').disabled = false;
}

async function submitAnswer() {
    if (!selectedAnswer || !currentQuestionId || hasAnsweredCurrentQuestion) {
        console.error('Cannot submit:', {
            selectedAnswer: selectedAnswer,
            currentQuestionId: currentQuestionId,
            hasAnswered: hasAnsweredCurrentQuestion
        });
        return;
    }

    const responseTime = (Date.now() - questionStartTime) / 1000;

    console.log('═══════════════════════════════════════');
    console.log('📤 SUBMITTING ANSWER');
    console.log('Question ID:', currentQuestionId);
    console.log('Selected Answer:', selectedAnswer);
    console.log('Response Time:', responseTime, 'seconds');
    console.log('═══════════════════════════════════════');

    // Mark as answered immediately to prevent double submission
    hasAnsweredCurrentQuestion = true;

    // Disable all buttons
    document.querySelectorAll('.option-button').forEach(btn => {
        btn.disabled = true;
    });
    document.getElementById('submitButton').disabled = true;

    // Stop timer
    clearInterval(timerInterval);

    try {
        // Submit answer via API
        const response = await fetch(`${API_URL}/submit-answer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                playerId: playerId,
                questionId: currentQuestionId,
                answer: selectedAnswer,
                responseTime: responseTime
            })
        });

        const data = await response.json();

        if (data.success) {
            // Show answer result immediately
            showAnswerResult({
                isCorrect: data.isCorrect,
                pointsEarned: data.pointsEarned,
                correctAnswer: data.correctAnswer,
                selectedAnswer: selectedAnswer
            });

            totalScore += data.pointsEarned;
            console.log(`✓ Total score: ${totalScore} points`);
        } else {
            console.error('Failed to submit answer:', data.error);
            alert('فشل إرسال الإجابة');
            // Re-enable buttons on error
            hasAnsweredCurrentQuestion = false;
            document.querySelectorAll('.option-button').forEach(btn => {
                btn.disabled = false;
            });
            document.getElementById('submitButton').disabled = false;
        }
    } catch (error) {
        console.error('Error submitting answer:', error);
        alert('حدث خطأ في إرسال الإجابة');
        // Re-enable buttons on error
        hasAnsweredCurrentQuestion = false;
        document.querySelectorAll('.option-button').forEach(btn => {
            btn.disabled = false;
        });
        document.getElementById('submitButton').disabled = false;
    }
}

function showAnswerResult(result) {
    console.log('═══════════════════════════════════════');
    console.log('✅ Answer result:', result);
    console.log('Is Correct:', result.isCorrect);
    console.log('Points Earned:', result.pointsEarned);
    console.log('═══════════════════════════════════════');

    const optionMap = {
        'A': 'optionA',
        'B': 'optionB',
        'C': 'optionC',
        'D': 'optionD'
    };

    // Mark correct answer (green)
    const correctElement = document.getElementById(optionMap[result.correctAnswer]);
    if (correctElement) {
        correctElement.classList.add('correct');
    }

    // Mark selected answer as wrong if incorrect (red)
    if (!result.isCorrect && result.selectedAnswer) {
        const selectedElement = document.getElementById(optionMap[result.selectedAnswer]);
        if (selectedElement) {
            selectedElement.classList.add('wrong');
        }
    }

    // Keep question screen visible - ready for next question
    const questionScreen = document.getElementById('questionScreen');
    if (questionScreen) {
        questionScreen.classList.remove('hidden');
        questionScreen.classList.add('show-flex');
        questionScreen.style.setProperty('display', 'flex', 'important');
    }

    console.log('✓✓✓ Answer feedback shown. Waiting for next question...');
    
    // Update score display
    updatePlayerRank();
}

async function updatePlayerRank() {
    try {
        const response = await fetch(`${API_URL}/get-leaderboard?code=${gameCode}`);
        const data = await response.json();

        // Find player by playerId
        const playerIndex = data.leaderboard.findIndex(p => {
            // We need to match by playerId, but leaderboard might not have it
            // So we'll just update if there's a rank element
            return true; // For now, just show rank if element exists
        });
        
        const rankElement = document.getElementById('currentRank');
        if (rankElement && data.leaderboard.length > 0) {
            // Try to find player by score (not perfect, but works)
            const playerRank = data.leaderboard.findIndex(p => Math.abs(p.total_score - totalScore) < 10);
            if (playerRank !== -1) {
                rankElement.textContent = `المركز: ${playerRank + 1}`;
            }
        }
    } catch (error) {
        console.error('Error updating rank:', error);
    }
}

function startTimer(duration) {
    let timeLeft = duration;
    const timerBar = document.getElementById('timerBar');

    clearInterval(timerInterval);

    timerInterval = setInterval(() => {
        timeLeft--;
        const percentage = (timeLeft / duration) * 100;
        if (timerBar) {
            timerBar.style.width = percentage + '%';

            if (percentage <= 20) {
                timerBar.classList.add('warning');
            } else {
                timerBar.classList.remove('warning');
            }
        }

        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            // Auto-submit if not already submitted
            if (!hasAnsweredCurrentQuestion && selectedAnswer) {
                submitAnswer();
            }
        }
    }, 1000);
}

// Check for game end by polling status
async function checkGameStatus() {
    try {
        const response = await fetch(`${API_URL}/get-game?code=${gameCode}`);
        const data = await response.json();

        if (data.success && data.game.status === 'finished') {
            // Game ended, show winners
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
            showWinners();
        }
    } catch (error) {
        console.error('Error checking game status:', error);
    }
}

async function showWinners() {
    try {
        const response = await fetch(`${API_URL}/get-leaderboard?code=${gameCode}`);
        const data = await response.json();

        // Hide other screens
        document.getElementById('questionScreen').classList.add('hidden');
        document.getElementById('waitingScreen').classList.add('hidden');

        // Show winners screen
        document.getElementById('winnersScreen').classList.remove('hidden');
        document.getElementById('winnersScreen').classList.add('show-flex');

        // Display final score
        const finalScoreElement = document.getElementById('finalScore');
        if (finalScoreElement) {
            finalScoreElement.textContent = totalScore;
        }

        // Display player's final rank
        const playerRank = data.leaderboard.findIndex(p => Math.abs(p.total_score - totalScore) < 10);
        const finalRankElement = document.getElementById('finalRank');
        if (finalRankElement && playerRank !== -1) {
            finalRankElement.textContent = `المركز النهائي: ${playerRank + 1}`;
        }

        // Display top 3 winners
        const winnersList = document.getElementById('winnersList');
        if (winnersList) {
            winnersList.innerHTML = '';
            const winners = data.leaderboard.slice(0, 3);
            const medals = ['🥇', '🥈', '🥉'];
            
            winners.forEach((winner, index) => {
                const item = document.createElement('div');
                item.className = 'winner-item';
                item.innerHTML = `
                    <span>${medals[index]} ${winner.player_name}</span>
                    <span>${winner.total_score} نقطة</span>
                `;
                winnersList.appendChild(item);
            });
        }
    } catch (error) {
        console.error('Error showing winners:', error);
    }
}

// Start the application
window.addEventListener('DOMContentLoaded', init);
