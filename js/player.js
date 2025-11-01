const API_URL = 'http://localhost:8888/worshipteam/api';
const WS_URL = 'ws://localhost:8080';

let ws = null;
let gameCode = null;
let playerId = null;
let sessionId = null;
let playerName = null; // Store player name for reconnection
let currentQuestionId = null;
let selectedAnswer = null;
let totalScore = 0;
let questionStartTime = null;
let timerInterval = null;
let reconnectAttempts = 0;
let maxReconnectAttempts = 5;
let isReconnecting = false;

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

function joinGame() {
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

    // Store for reconnection
    playerName = nameInput;
    gameCode = codeInput;

    // Connect to WebSocket
    connectWebSocket(playerName);
}

function connectWebSocket(name) {
    if (isReconnecting) {
        console.log('⏳ Reconnection already in progress...');
        return;
    }

    console.log('🔌 Connecting to WebSocket server...');
    ws = new WebSocket(WS_URL);

    ws.onopen = () => {
        console.log('✅✅✅ WebSocket CONNECTED ✅✅✅');
        reconnectAttempts = 0; // Reset on successful connection
        isReconnecting = false;

        // Send join message
        const joinMessage = {
            type: 'join',
            gameCode: gameCode,
            playerName: name
        };
        
        ws.send(JSON.stringify(joinMessage));
        console.log('📤 Sent join message:', joinMessage);
        
        // If we already have a playerId (reconnecting), try to restore state
        if (playerId) {
            console.log('⚠️  Reconnected - playerId:', playerId, 'sessionId:', sessionId);
        }
    };

    ws.onmessage = (event) => {
        try {
            const message = JSON.parse(event.data);
            console.log('═══════════════════════════════════════');
            console.log('📨 Player received WebSocket message:', message.type);
            console.log('Full message:', JSON.stringify(message, null, 2));
            console.log('═══════════════════════════════════════');
            handleWebSocketMessage(message);
        } catch (error) {
            console.error('❌ Error parsing WebSocket message:', error, event.data);
        }
    };

    ws.onerror = (error) => {
        console.error('❌ WebSocket error:', error);
        // Don't alert on error - let onclose handle reconnection
    };

    ws.onclose = (event) => {
        console.log('═══════════════════════════════════════');
        console.log('⚠️⚠️⚠️ WebSocket connection CLOSED ⚠️⚠️⚠️');
        console.log('Close code:', event.code);
        console.log('Close reason:', event.reason || 'No reason provided');
        console.log('Was clean:', event.wasClean);
        console.log('Current playerId:', playerId);
        console.log('Current gameCode:', gameCode);
        console.log('═══════════════════════════════════════');

        // Only reconnect if we have gameCode and playerName (i.e., we were in a game)
        if (gameCode && playerName && reconnectAttempts < maxReconnectAttempts) {
            reconnectAttempts++;
            isReconnecting = true;
            const delay = Math.min(1000 * reconnectAttempts, 10000); // Exponential backoff, max 10s
            console.log(`⏳ Reconnecting in ${delay}ms... (Attempt ${reconnectAttempts}/${maxReconnectAttempts})`);
            
            setTimeout(() => {
                console.log('🔄 Attempting to reconnect...');
                connectWebSocket(playerName);
            }, delay);
        } else {
            if (reconnectAttempts >= maxReconnectAttempts) {
                console.error('❌ Max reconnection attempts reached. Please refresh the page.');
                alert('فقد الاتصال بالخادم. يرجى تحديث الصفحة.');
            } else {
                console.log('⏸️  Not reconnecting (no gameCode/playerName or max attempts reached)');
            }
        }
    };
}

function handleWebSocketMessage(message) {
    console.log(`🔄 Handling message type: ${message.type}`);
    
    // CRITICAL: If it's a new_question, ALWAYS process it immediately, regardless of current state
    if (message.type === 'new_question') {
        console.log('🚨🚨🚨 NEW QUESTION DETECTED - FORCING IMMEDIATE PROCESSING 🚨🚨🚨');
        console.log('Current state before reset:', {
            currentQuestionId: currentQuestionId,
            selectedAnswer: selectedAnswer,
            hasTimer: !!timerInterval
        });
        
        // FORCE reset everything first
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        selectedAnswer = null;
        
        // Then handle the question
        handleNewQuestion(message);
        return; // Exit early - don't process anything else
    }
    
    switch (message.type) {
        case 'joined':
            console.log('→ Calling handleJoined');
            handleJoined(message);
            break;
        case 'game_started':
            console.log('→ Calling handleGameStarted');
            handleGameStarted();
            break;
        case 'answer_result':
            console.log('→ Calling handleAnswerResult');
            handleAnswerResult(message);
            break;
        case 'game_ended':
            console.log('→ Calling handleGameEnded');
            handleGameEnded(message);
            break;
        default:
            console.warn('⚠ Unknown message type:', message.type);
    }
}

function handleJoined(message) {
    playerId = message.playerId;
    sessionId = message.sessionId;

    // Show waiting screen
    document.getElementById('joinScreen').classList.add('hidden');
    document.getElementById('waitingScreen').classList.remove('hidden');
    document.getElementById('waitingScreen').classList.add('show-flex');
}

function handleGameStarted() {
    // Game is starting
    console.log('Game started!');
}

function handleNewQuestion(message) {
    console.log('═══════════════════════════════════════');
    console.log('🎯🎯🎯🎯🎯 NEW QUESTION - ABSOLUTE PRIORITY 🎯🎯🎯🎯🎯');
    console.log('Previous Question ID:', currentQuestionId);
    console.log('New Question Number:', message.questionNumber);
    console.log('New Question ID:', message.questionId || message.questionNumber);
    console.log('Has Options:', !!message.options);
    
    // IMMEDIATE: Get question screen and FORCE it visible NOW
    const questionScreen = document.getElementById('questionScreen');
    if (questionScreen) {
        // Nuclear option - wipe everything and force show
        questionScreen.removeAttribute('class');
        questionScreen.style.cssText = 'display: flex !important; visibility: visible !important; opacity: 1 !important; z-index: 99999 !important; position: relative !important; flex-direction: column !important;';
        console.log('🚀 Question screen FORCED visible immediately');
    }
    
    console.log('═══════════════════════════════════════');
    
    // Stop timer if running
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
        console.log('✓ Timer stopped');
    }
    
    // Update question ID
    const newQuestionId = message.questionId || message.questionNumber;
    const oldQuestionId = currentQuestionId;
    currentQuestionId = newQuestionId;
    selectedAnswer = null;
    questionStartTime = Date.now();

    if (!message.options) {
        console.error('❌ CRITICAL ERROR: No options in message!', message);
        alert('Error: Question received without options. Please refresh.');
        return;
    }
    
    console.log(`✓ Question changed: ${oldQuestionId} → ${newQuestionId}`);

    // Update UI
    const currentQElement = document.getElementById('currentQuestion');
    if (currentQElement) {
        currentQElement.textContent = message.questionNumber;
    }

    const totalQElement = document.getElementById('totalQuestions');
    if (totalQElement) {
        totalQElement.textContent = '20';
    }

    // Set options - check if elements exist
    const optionA = document.getElementById('optionA');
    const optionB = document.getElementById('optionB');
    const optionC = document.getElementById('optionC');
    const optionD = document.getElementById('optionD');

    if (optionA && message.options.A) optionA.textContent = `أ) ${message.options.A}`;
    if (optionB && message.options.B) optionB.textContent = `ب) ${message.options.B}`;
    if (optionC && message.options.C) optionC.textContent = `ج) ${message.options.C}`;
    if (optionD && message.options.D) optionD.textContent = `د) ${message.options.D}`;

    // Reset buttons - IMPORTANT: Clear all previous state
    document.querySelectorAll('.option-button').forEach(button => {
        button.classList.remove('selected', 'correct', 'wrong');
        button.disabled = false;
        // Make sure buttons are visible and enabled
        button.style.opacity = '1';
        button.style.pointerEvents = 'auto';
    });

    const submitBtn = document.getElementById('submitButton');
    if (submitBtn) {
        submitBtn.disabled = true;
    }

    // FORCE HIDE ALL OTHER SCREENS IMMEDIATELY
    const allScreens = ['waitingScreen', 'resultScreen', 'joinScreen', 'winnersScreen'];
    allScreens.forEach(screenId => {
        const screen = document.getElementById(screenId);
        if (screen) {
            screen.style.cssText = 'display: none !important; visibility: hidden !important; opacity: 0 !important; z-index: -1 !important;';
            screen.removeAttribute('class');
            console.log(`✓✓✓ Force hidden: ${screenId}`);
        }
    });

    // FORCE SHOW question screen (already done above, but ensure it stays)
    if (!questionScreen) {
        console.error('❌❌❌ CRITICAL: Question screen element missing!');
        alert('FATAL ERROR: Question screen not found. Page may be corrupted. Please refresh.');
        return;
    }
    
    // Final check and log
    const finalStyle = window.getComputedStyle(questionScreen);
    console.log('Final state check:', {
        display: finalStyle.display,
        visibility: finalStyle.visibility,
        opacity: finalStyle.opacity,
        zIndex: finalStyle.zIndex
    });
    
    if (finalStyle.display === 'flex' && finalStyle.visibility === 'visible') {
        console.log('✅✅✅✅✅ QUESTION SCREEN CONFIRMED VISIBLE ✅✅✅✅✅');
    } else {
        console.error('❌❌❌ DISPLAY ISSUE DETECTED - Retrying...');
        questionScreen.style.cssText = 'display: flex !important; visibility: visible !important; opacity: 1 !important; z-index: 99999 !important;';
    }

    // Start timer
    startTimer(message.timeLimit || 30);
    
    // Final confirmation
    console.log('═══════════════════════════════════════');
    console.log('✅✅✅✅✅ NEW QUESTION COMPLETE ✅✅✅✅✅');
    console.log('Question #' + message.questionNumber + ' should now be visible');
    console.log('═══════════════════════════════════════');
}

function selectOption(event) {
    if (selectedAnswer) return; // Already selected

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

function submitAnswer() {
    if (!selectedAnswer || !currentQuestionId) {
        console.error('Cannot submit: selectedAnswer=', selectedAnswer, 'currentQuestionId=', currentQuestionId);
        return;
    }

    const responseTime = (Date.now() - questionStartTime) / 1000;

    console.log('═══════════════════════════════════════');
    console.log('📤 SUBMITTING ANSWER');
    console.log('Question ID:', currentQuestionId);
    console.log('Selected Answer:', selectedAnswer);
    console.log('Response Time:', responseTime, 'seconds');
    console.log('WebSocket State:', ws ? ws.readyState : 'null', '(OPEN=1)');
    console.log('═══════════════════════════════════════');

    // Send answer via WebSocket
    const answerMessage = {
        type: 'answer',
        questionId: currentQuestionId,
        answer: selectedAnswer,
        responseTime: responseTime
    };
    
    if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify(answerMessage));
        console.log('✓ Answer sent to server:', answerMessage);
    } else {
        console.error('✗ WebSocket not ready! Cannot send answer.');
        alert('Connection lost! Please refresh the page.');
        return;
    }

    // Disable all buttons
    document.querySelectorAll('.option-button').forEach(btn => {
        btn.disabled = true;
    });
    document.getElementById('submitButton').disabled = true;

    // Stop timer
    clearInterval(timerInterval);
    
    console.log('✓ Answer submitted. Waiting for result from server...');
    console.log('⚠️  NOTE: Stay on question screen - will receive new question when host clicks "Next Question"');
}

function handleAnswerResult(message) {
    console.log('═══════════════════════════════════════');
    console.log('✅ Answer result received:', message);
    console.log('Is Correct:', message.isCorrect);
    console.log('Points Earned:', message.pointsEarned);
    console.log('Current Question ID:', currentQuestionId);
    console.log('═══════════════════════════════════════');
    
    const isCorrect = message.isCorrect;
    const pointsEarned = message.pointsEarned;
    const correctAnswer = message.correctAnswer;

    totalScore += pointsEarned;
    console.log(`Total score updated: ${totalScore} points`);

    // Show correct/wrong on buttons (keep question screen visible - no result screen)
    const optionMap = {
        'A': 'optionA',
        'B': 'optionB',
        'C': 'optionC',
        'D': 'optionD'
    };

    // Mark correct answer (green)
    const correctElement = document.getElementById(optionMap[correctAnswer]);
    if (correctElement) {
        correctElement.classList.add('correct');
        console.log(`✓ Marked correct answer: ${correctAnswer}`);
    } else {
        console.error(`✗ Could not find element for correct answer: ${correctAnswer}`);
    }

    // Mark selected answer as wrong if incorrect (red)
    if (!isCorrect && selectedAnswer) {
        const selectedElement = document.getElementById(optionMap[selectedAnswer]);
        if (selectedElement) {
            selectedElement.classList.add('wrong');
            console.log(`✗ Marked wrong answer: ${selectedAnswer}`);
        } else {
            console.error(`✗ Could not find element for selected answer: ${selectedAnswer}`);
        }
    }

    // IMPORTANT: Keep question screen visible and ready for next question
    // The question screen MUST stay visible so new questions can replace it
    const questionScreen = document.getElementById('questionScreen');
    if (questionScreen) {
        // Ensure question screen is still visible (in case something hid it)
        questionScreen.classList.remove('hidden');
        questionScreen.classList.add('show-flex');
        questionScreen.style.setProperty('display', 'flex', 'important');
        console.log('✓ Question screen kept visible - ready for next question');
    }

    // DON'T show result screen - just show feedback on the question itself
    // This way new questions can always replace the current question immediately
    console.log(`✓✓✓ Answer feedback shown: ${isCorrect ? 'CORRECT ✓' : 'WRONG ✗'}, Points: ${pointsEarned}`);
    console.log('⚠️  Waiting for host to send next question...');
    console.log('═══════════════════════════════════════');
    
    // Update score display if there's a score element visible
    updatePlayerRank();
}

// REMOVED: showResultScreen function - we don't show result screen anymore
// New questions always take priority and feedback is shown on the question itself

async function updatePlayerRank() {
    try {
        const response = await fetch(`${API_URL}/get-leaderboard?code=${gameCode}`);
        const data = await response.json();

        const playerIndex = data.leaderboard.findIndex(p => p.player_name === sessionId);
        if (playerIndex !== -1) {
            document.getElementById('currentRank').textContent = `المركز: ${playerIndex + 1}`;
        }
    } catch (error) {
        console.error('Error updating rank:', error);
    }
}

async function handleGameEnded(message) {
    const winners = message.winners;

    // Hide other screens
    document.getElementById('questionScreen').classList.add('hidden');
    document.getElementById('resultScreen').classList.add('hidden');

    // Show winners screen
    document.getElementById('winnersScreen').classList.remove('hidden');
    document.getElementById('winnersScreen').classList.add('show-flex');

    // Display final score
    document.getElementById('finalScore').textContent = totalScore;

    // Get player's final rank
    try {
        const response = await fetch(`${API_URL}/get-leaderboard?code=${gameCode}`);
        const data = await response.json();

        const playerIndex = data.leaderboard.findIndex(p => p.total_score === totalScore);
        if (playerIndex !== -1) {
            document.getElementById('finalRank').textContent = `المركز النهائي: ${playerIndex + 1}`;
        }
    } catch (error) {
        console.error('Error getting final rank:', error);
    }

    // Display winners
    const winnersList = document.getElementById('winnersList');
    winnersList.innerHTML = '';

    winners.forEach((winner, index) => {
        const medals = ['🥇', '🥈', '🥉'];
        const item = document.createElement('div');
        item.className = 'winner-item';
        item.innerHTML = `
            <span>${medals[index]} ${winner.player_name}</span>
            <span>${winner.total_score} نقطة</span>
        `;
        winnersList.appendChild(item);
    });
}

function startTimer(duration) {
    let timeLeft = duration;
    const timerBar = document.getElementById('timerBar');

    clearInterval(timerInterval);

    timerInterval = setInterval(() => {
        timeLeft--;
        const percentage = (timeLeft / duration) * 100;
        timerBar.style.width = percentage + '%';

        if (percentage <= 20) {
            timerBar.classList.add('warning');
        } else {
            timerBar.classList.remove('warning');
        }

        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            // Auto-submit if not already submitted
            if (!document.getElementById('submitButton').disabled && selectedAnswer) {
                submitAnswer();
            }
        }
    }, 1000);
}

// Start the application
window.addEventListener('DOMContentLoaded', init);
