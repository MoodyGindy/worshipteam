const API_URL = 'https://kdsc.fun/worshipteam/api';

let gameCode = null;
let questions = [];
let currentQuestionIndex = 0;
let timerInterval = null;
let currentQuestion = null;
let pollingInterval = null;
let lastUpdateTime = 0;

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

        // Start polling for updates
        startPolling();

        // Setup event listeners
        document.getElementById('startButton').addEventListener('click', startGame);
        document.getElementById('showAnswerButton').addEventListener('click', showCorrectAnswer);
        document.getElementById('nextQuestionButton').addEventListener('click', nextQuestion);
        document.getElementById('showWinnersButton').addEventListener('click', showWinners);

        // Move action buttons before leaderboard
        const questionScreen = document.getElementById('questionScreen');
        const leaderboardContainer = document.querySelector('.leaderboard');
        const showAnswerButton = document.getElementById('showAnswerButton');
        if (questionScreen && leaderboardContainer && showAnswerButton && showAnswerButton.parentElement) {
            questionScreen.insertBefore(showAnswerButton.parentElement, leaderboardContainer);
        }

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
        
        console.log('Loaded questions:', questions.length);
    } catch (error) {
        console.error('Error loading questions:', error);
    }
}

function startPolling() {
    // Poll every 2 seconds for game updates (player count, new answers)
    pollingInterval = setInterval(async () => {
        try {
            const response = await fetch(`${API_URL}/get-game-updates?code=${gameCode}&lastCheck=${lastUpdateTime}`);
            const data = await response.json();

            if (data.success) {
                // Update player count
                updatePlayerCount(data.totalPlayers);

                // If new answers submitted, update leaderboard
                if (data.newAnswers > 0) {
                    updateLeaderboard();
                }

                lastUpdateTime = data.lastUpdate;
            }
        } catch (error) {
            console.error('Error polling for updates:', error);
        }
    }, 2000); // Poll every 2 seconds

    // Initial update
    updatePlayerCount(0);
}

function updatePlayerCount(count) {
    document.getElementById('playersCount').textContent = count;
}

async function startGame() {
    try {
        // Update game status to 'playing'
        await fetch(`${API_URL}/get-game?code=${gameCode}`);

        // Hide lobby, show question screen
        document.getElementById('lobbyScreen').classList.add('hidden');
        document.getElementById('questionScreen').classList.remove('hidden');
        document.getElementById('questionScreen').classList.add('show-flex');

        // Show first question
        nextQuestion();
    } catch (error) {
        console.error('Error starting game:', error);
        alert('خطأ في بدء اللعبة');
    }
}

async function nextQuestion() {
    if (currentQuestionIndex >= questions.length) {
        showWinners();
        return;
    }

    currentQuestion = questions[currentQuestionIndex];
    currentQuestionIndex++;

    console.log(`=== Moving to Question ${currentQuestionIndex} ===`);
    console.log('Question ID:', currentQuestion.id);

    // Set current question in database (this makes it available to players)
    try {
        const response = await fetch(`${API_URL}/set-current-question`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                gameCode: gameCode,
                questionId: currentQuestion.id,
                questionNumber: currentQuestionIndex
            })
        });

        const data = await response.json();
        if (!data.success) {
            console.error('Failed to set current question');
            return;
        }
    } catch (error) {
        console.error('Error setting current question:', error);
        alert('خطأ في إرسال السؤال');
        return;
    }

    // Update host UI
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

    // Normalize correct answer
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

    // Highlight correct answer
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

        data.leaderboard.slice(0, 5).forEach((player, index) => {
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
        // Clear current question
        await fetch(`${API_URL}/set-current-question`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                gameCode: gameCode,
                questionId: null,
                questionNumber: 0
            })
        });

        // Update game status to finished
        // (You may want to add an API endpoint for this)

        // Get final leaderboard
        const response = await fetch(`${API_URL}/get-leaderboard?code=${gameCode}`);
        const data = await response.json();
        const winners = data.leaderboard.slice(0, 3);

        // Hide question screen, show winners screen
        document.getElementById('questionScreen').classList.add('hidden');
        document.getElementById('winnersScreen').classList.remove('hidden');
        document.getElementById('winnersScreen').classList.add('show-flex');

        // Stop polling
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }

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
