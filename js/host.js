const API_URL = 'https://kdsc.fun/worshipteam/worshipteam/api';
const WS_URL = 'wss://kdsc.fun:8080'; // Use WSS (secure WebSocket) for HTTPS sites

let ws = null;
let gameCode = null;
let questions = [];
let currentQuestionIndex = 0;
let timerInterval = null;
let currentQuestion = null;

// Initialize the game
async function init() {
    try {
        // Create a new game
        const response = await fetch(`${API_URL}/create-game`, {
            method: 'POST'
        });

        const data = await response.json();
        gameCode = data.gameCode;

        // Display game code
        document.getElementById('gameCode').textContent = gameCode;

        // Generate QR code
        const joinUrl = `${window.location.origin}/worshipteam/player.html?code=${gameCode}`;
        document.getElementById('joinUrl').textContent = joinUrl;

        new QRCode(document.getElementById('qrCode'), {
            text: joinUrl,
            width: 300,
            height: 300,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });

        // Load questions
        await loadQuestions();

        // Connect to WebSocket
        connectWebSocket();

        // Setup event listeners
        document.getElementById('startButton').addEventListener('click', startGame);
        document.getElementById('showAnswerButton').addEventListener('click', showCorrectAnswer);
        document.getElementById('nextQuestionButton').addEventListener('click', nextQuestion);
        document.getElementById('showWinnersButton').addEventListener('click', showWinners);

    } catch (error) {
        console.error('Initialization error:', error);
        alert('حدث خطأ في تهيئة اللعبة');
    }
}

async function loadQuestions() {
    try {
        const response = await fetch(`${API_URL}/get-questions?limit=20`);
        const data = await response.json();
        questions = data.questions;
        document.getElementById('totalQuestions').textContent = questions.length;
        
        // Verify questions have correct_answer
        console.log('Loaded questions:', questions.length);
        if (questions.length > 0) {
            console.log('Sample question:', {
                id: questions[0].id,
                has_correct_answer: 'correct_answer' in questions[0],
                correct_answer: questions[0].correct_answer
            });
        }
    } catch (error) {
        console.error('Error loading questions:', error);
    }
}

function connectWebSocket() {
    ws = new WebSocket(WS_URL);

    ws.onopen = () => {
        console.log('Connected to WebSocket server');
        
        // Register as host with the server
        ws.send(JSON.stringify({
            type: 'register_host',
            gameCode: gameCode
        }));
        
        ws.isHost = true;
        ws.gameCode = gameCode;
    };

    ws.onmessage = (event) => {
        try {
            const message = JSON.parse(event.data);
            console.log('Received message:', message);
            handleWebSocketMessage(message);
        } catch (error) {
            console.error('Error parsing message:', error, event.data);
        }
    };

    ws.onerror = (error) => {
        console.error('WebSocket error:', error);
    };

    ws.onclose = () => {
        console.log('WebSocket connection closed');
        setTimeout(connectWebSocket, 3000);
    };
}

function handleWebSocketMessage(message) {
    console.log('Handling message type:', message.type);
    switch (message.type) {
        case 'player_joined':
            console.log('Player joined:', message.playerName, 'Total:', message.totalPlayers);
            updatePlayerCount(message.totalPlayers);
            break;
        case 'answer_submitted':
            console.log('Answer submitted, updating leaderboard');
            updateLeaderboard();
            break;
        case 'question_broadcast':
            // Question was broadcast to players
            console.log('Question broadcasted to players');
            break;
        default:
            console.log('Unknown message type:', message.type);
    }
}

function updatePlayerCount(count) {
    document.getElementById('playersCount').textContent = count;
}

function startGame() {
    if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({
            type: 'start_game',
            gameCode: gameCode
        }));

        // Hide lobby, show question screen
        document.getElementById('lobbyScreen').classList.add('hidden');
        document.getElementById('questionScreen').classList.remove('hidden');
        document.getElementById('questionScreen').classList.add('show-flex');

        // Show first question
        nextQuestion();
    }
}

function nextQuestion() {
    if (currentQuestionIndex >= questions.length) {
        showWinners();
        return;
    }

    currentQuestion = questions[currentQuestionIndex];
    currentQuestionIndex++;

    console.log(`=== Moving to Question ${currentQuestionIndex} ===`);
    console.log('Question ID:', currentQuestion.id);
    console.log('WebSocket state:', ws ? ws.readyState : 'null', '(OPEN=1)');

    // IMPORTANT: Send question to server FIRST before updating UI
    // This ensures players receive the question as quickly as possible
    if (ws && ws.readyState === WebSocket.OPEN) {
        const message = {
            type: 'next_question',
            gameCode: gameCode,
            questionNumber: currentQuestionIndex,
            questionId: currentQuestion.id
        };
        console.log('Sending to server:', message);
        ws.send(JSON.stringify(message));
    } else {
        console.error('WebSocket not ready! State:', ws ? ws.readyState : 'null');
        alert('WebSocket connection lost! Please refresh the page.');
        return;
    }

    // Then update host UI
    document.getElementById('currentQuestion').textContent = currentQuestionIndex;
    document.getElementById('category').textContent = getCategoryName(currentQuestion.category);
    document.getElementById('questionText').textContent = currentQuestion.question_text;

    // Display options
    document.getElementById('optionA').textContent = `أ) ${currentQuestion.option_a}`;
    document.getElementById('optionB').textContent = `ب) ${currentQuestion.option_b}`;
    document.getElementById('optionC').textContent = `ج) ${currentQuestion.option_c}`;
    document.getElementById('optionD').textContent = `د) ${currentQuestion.option_d}`;

    // Reset option styles
    document.querySelectorAll('.option').forEach(opt => {
        opt.classList.remove('correct', 'wrong');
    });

    // Hide/show buttons
    document.getElementById('showAnswerButton').classList.remove('hidden');
    document.getElementById('nextQuestionButton').classList.add('hidden');
    document.getElementById('showWinnersButton').classList.add('hidden');

    // Start timer
    startTimer(currentQuestion.time_limit);
}

function startTimer(duration) {
    let timeLeft = duration;
    const timerElement = document.getElementById('timer');

    clearInterval(timerInterval);

    timerInterval = setInterval(() => {
        timeLeft--;
        timerElement.textContent = timeLeft;

        if (timeLeft <= 5) {
            timerElement.classList.add('warning');
        } else {
            timerElement.classList.remove('warning');
        }

        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            showCorrectAnswer();
        }
    }, 1000);
}

function showCorrectAnswer() {
    // Stop the timer if it's still running
    clearInterval(timerInterval);
    
    // Check if we have a current question
    if (!currentQuestion) {
        console.error('No current question to show answer for');
        return;
    }

    // Normalize correct answer (convert to uppercase, trim whitespace)
    let correctAnswer = String(currentQuestion.correct_answer || '').trim().toUpperCase();
    
    console.log('Showing correct answer. Question:', currentQuestion.id, 'Correct Answer:', correctAnswer);
    
    if (!correctAnswer || !['A', 'B', 'C', 'D'].includes(correctAnswer)) {
        console.error('Invalid correct_answer value:', currentQuestion.correct_answer);
        return;
    }

    const optionMap = {
        'A': 'optionA',
        'B': 'optionB',
        'C': 'optionC',
        'D': 'optionD'
    };

    // Reset all options first
    Object.keys(optionMap).forEach(key => {
        const element = document.getElementById(optionMap[key]);
        if (element) {
            element.classList.remove('correct', 'wrong');
        }
    });

    // Highlight correct answer (check if element exists)
    const correctElement = document.getElementById(optionMap[correctAnswer]);
    if (correctElement) {
        correctElement.classList.add('correct');
        console.log('Marked correct answer:', correctAnswer);
    } else {
        console.error('Could not find correct answer element:', optionMap[correctAnswer]);
    }

    // Show wrong answers
    Object.keys(optionMap).forEach(key => {
        if (key !== correctAnswer) {
            const element = document.getElementById(optionMap[key]);
            if (element) {
                element.classList.add('wrong');
            }
        }
    });

    // Update leaderboard
    updateLeaderboard();

    // Hide "Show Answer" button, show "Next Question" button
    const showAnswerBtn = document.getElementById('showAnswerButton');
    if (showAnswerBtn) {
        showAnswerBtn.classList.add('hidden');
    }
    
    if (currentQuestionIndex < questions.length) {
        const nextBtn = document.getElementById('nextQuestionButton');
        if (nextBtn) {
            nextBtn.classList.remove('hidden');
        }
    } else {
        const winnersBtn = document.getElementById('showWinnersButton');
        if (winnersBtn) {
            winnersBtn.classList.remove('hidden');
        }
    }
}

async function updateLeaderboard() {
    try {
        const response = await fetch(`${API_URL}/get-leaderboard?code=${gameCode}`);
        const data = await response.json();

        const leaderboardList = document.getElementById('leaderboardList');
        leaderboardList.innerHTML = '';

        data.leaderboard.slice(0, 10).forEach((player, index) => {
            const item = document.createElement('div');
            item.className = 'leaderboard-item';
            item.innerHTML = `
                <span>${index + 1}. ${player.player_name}</span>
                <span>${player.total_score} نقطة</span>
            `;
            leaderboardList.appendChild(item);
        });
    } catch (error) {
        console.error('Error updating leaderboard:', error);
    }
}

async function showWinners() {
    try {
        // Send game ended message
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                type: 'end_game',
                gameCode: gameCode
            }));
        }

        // Get final leaderboard
        const response = await fetch(`${API_URL}/get-leaderboard?code=${gameCode}`);
        const data = await response.json();
        const winners = data.leaderboard.slice(0, 3);

        // Hide question screen, show winners screen
        document.getElementById('questionScreen').classList.add('hidden');
        document.getElementById('winnersScreen').classList.remove('hidden');
        document.getElementById('winnersScreen').classList.add('show-flex');

        // Display winners
        if (winners[0]) {
            document.getElementById('firstName').textContent = winners[0].player_name;
            document.getElementById('firstScore').textContent = winners[0].total_score;
        }

        if (winners[1]) {
            document.getElementById('secondName').textContent = winners[1].player_name;
            document.getElementById('secondScore').textContent = winners[1].total_score;
        }

        if (winners[2]) {
            document.getElementById('thirdName').textContent = winners[2].player_name;
            document.getElementById('thirdScore').textContent = winners[2].total_score;
        }
    } catch (error) {
        console.error('Error showing winners:', error);
    }
}

function getCategoryName(category) {
    const categories = {
        'music': '🎵 موسيقى',
        'bible': '📖 الكتاب المقدس',
        'general': '🌍 معلومات عامة',
        'sports': '⚽ رياضة'
    };
    return categories[category] || category;
}

// Start the application
window.addEventListener('DOMContentLoaded', init);
