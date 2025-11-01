-- Complete Database Setup for Quiz Game
-- Run this file in phpMyAdmin or MySQL command line

-- Create database
CREATE DATABASE IF NOT EXISTS worshipteam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user (optional - you can skip this and use root)
CREATE USER IF NOT EXISTS 'worshipteam'@'localhost' IDENTIFIED BY 'worshipteam';
GRANT ALL PRIVILEGES ON worshipteam.* TO 'worshipteam'@'localhost';
FLUSH PRIVILEGES;

-- Use the database
USE worshipteam;

-- Games table: tracks each quiz session
CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_code VARCHAR(10) UNIQUE NOT NULL,
    status ENUM('lobby', 'playing', 'finished') DEFAULT 'lobby',
    current_question INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questions table: stores quiz questions in Arabic
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    category ENUM('music', 'bible', 'general', 'sports') NOT NULL,
    points INT DEFAULT 100,
    time_limit INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Players table: tracks participants in each game
CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    player_name VARCHAR(100) NOT NULL,
    total_score INT DEFAULT 0,
    session_id VARCHAR(100) UNIQUE NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    INDEX idx_game_score (game_id, total_score DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Answers table: records each answer submission
CREATE TABLE IF NOT EXISTS answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    points_earned INT DEFAULT 0,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_time DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_question (player_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better performance
CREATE INDEX idx_game_code ON games(game_code);
CREATE INDEX idx_game_status ON games(status);
CREATE INDEX idx_question_category ON questions(category);
CREATE INDEX idx_player_session ON players(session_id);

-- Insert Sample Questions

-- Music Questions (الموسيقى)
INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_answer, category, points, time_limit) VALUES
('من هو ملك الموسيقى؟', 'مايكل جاكسون', 'إلفيس بريسلي', 'فريدي ميركوري', 'فرانك سيناترا', 'B', 'music', 100, 30),
('كم عدد الأوتار في الجيتار الكلاسيكي؟', '4 أوتار', '5 أوتار', '6 أوتار', '7 أوتار', 'C', 'music', 100, 30),
('ما هي الآلة الموسيقية التي تعرف بملكة الآلات؟', 'البيانو', 'الكمان', 'الأورغن', 'الهارب', 'C', 'music', 100, 30),
('من هو مؤلف السيمفونية التاسعة؟', 'موزارت', 'بيتهوفن', 'باخ', 'تشايكوفسكي', 'B', 'music', 100, 30),
('ما هو أعلى صوت غنائي نسائي؟', 'ألتو', 'سوبرانو', 'ميزو سوبرانو', 'كونترالتو', 'B', 'music', 100, 30);

-- Bible Questions (الكتاب المقدس)
INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_answer, category, points, time_limit) VALUES
('كم عدد أسفار الكتاب المقدس؟', '66 سفراً', '73 سفراً', '77 سفراً', '80 سفراً', 'A', 'bible', 100, 30),
('من هو أول ملك لإسرائيل؟', 'داود', 'شاول', 'سليمان', 'صموئيل', 'B', 'bible', 100, 30),
('كم عدد تلاميذ المسيح؟', '10', '11', '12', '13', 'C', 'bible', 100, 30),
('أين ولد يسوع المسيح؟', 'الناصرة', 'بيت لحم', 'القدس', 'كفر ناحوم', 'B', 'bible', 100, 30),
('من كتب سفر الرؤيا؟', 'يوحنا', 'بطرس', 'بولس', 'يعقوب', 'A', 'bible', 100, 30),
('كم يوماً صام يسوع في البرية؟', '30 يوماً', '40 يوماً', '50 يوماً', '60 يوماً', 'B', 'bible', 100, 30),
('من هو أخ موسى؟', 'يشوع', 'هارون', 'كالب', 'يثرون', 'B', 'bible', 100, 30),
('في أي نهر تعمد يسوع؟', 'نهر النيل', 'نهر الفرات', 'نهر الأردن', 'نهر دجلة', 'C', 'bible', 100, 30);

-- General Information Questions (معلومات عامة)
INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_answer, category, points, time_limit) VALUES
('ما هي عاصمة فرنسا؟', 'لندن', 'برلين', 'باريس', 'روما', 'C', 'general', 100, 30),
('كم عدد قارات العالم؟', '5 قارات', '6 قارات', '7 قارات', '8 قارات', 'C', 'general', 100, 30),
('ما هو أكبر كوكب في المجموعة الشمسية؟', 'الأرض', 'زحل', 'المشتري', 'أورانوس', 'C', 'general', 100, 30),
('من اخترع المصباح الكهربائي؟', 'نيكولا تسلا', 'توماس إديسون', 'ألكسندر جراهام بيل', 'بنجامين فرانكلين', 'B', 'general', 100, 30),
('كم عدد أيام السنة الكبيسة؟', '364 يوماً', '365 يوماً', '366 يوماً', '367 يوماً', 'C', 'general', 100, 30);

-- Sports Questions (الرياضة)
INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_answer, category, points, time_limit) VALUES
('كم عدد لاعبي كرة القدم في كل فريق؟', '9 لاعبين', '10 لاعبين', '11 لاعباً', '12 لاعباً', 'C', 'sports', 100, 30),
('في أي مدينة أقيمت أول دورة أولمبية حديثة؟', 'باريس', 'أثينا', 'لندن', 'روما', 'B', 'sports', 100, 30),
('كم عدد الحلقات في شعار الأولمبياد؟', '3 حلقات', '4 حلقات', '5 حلقات', '6 حلقات', 'C', 'sports', 100, 30),
('ما هي أسرع رياضة في العالم؟', 'كرة القدم', 'التنس', 'كرة الريشة', 'الهوكي', 'C', 'sports', 100, 30),
('كم عدد الأشواط في مباراة الملاكمة الاحترافية؟', '10 أشواط', '12 شوطاً', '15 شوطاً', '20 شوطاً', 'B', 'sports', 100, 30),
('من هو أكثر لاعب تسجيلاً للأهداف في تاريخ كأس العالم؟', 'بيليه', 'مارادونا', 'ميروسلاف كلوزه', 'رونالدو', 'C', 'sports', 100, 30);

-- Done!
SELECT 'Database setup complete!' AS Status;
SELECT COUNT(*) AS 'Total Questions' FROM questions;
