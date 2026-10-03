-- =========================================================
-- BARANGAY HEALTH CENTER SURVEY MANAGEMENT SYSTEM
-- COMPLETE DATABASE
--
-- Includes:
--   1. Original Barangay Survey database structure
--   2. Act 5 - Set A resident information fields
--   3. Gender (Male / Female)
--   4. Resident children
--   5. Resident update history
--   6. Before/After change storage
--   7. Staff / Resident update tracking
--   8. Resident archive status
--   9. Surveys
--  10. Survey questions
--  11. Survey choices
--  12. Survey responses
--  13. Survey results
--  14. Login history
--  15. Sample staff account
--  16. Five sample residents
--  17. Sample survey and questions
--
-- IMPORT THIS FILE ONCE IN PHPMYADMIN
-- =========================================================


-- =========================================================
-- DATABASE
-- =========================================================

CREATE DATABASE IF NOT EXISTS barangay_survey_db;

USE barangay_survey_db;


-- =========================================================
-- TABLE: residents
-- =========================================================

CREATE TABLE IF NOT EXISTS residents (

    resident_id INT AUTO_INCREMENT PRIMARY KEY,

    resident_number VARCHAR(20) NOT NULL UNIQUE,

    first_name VARCHAR(50) NOT NULL,

    last_name VARCHAR(50) NOT NULL,

    middle_name VARCHAR(50),

    extension_name VARCHAR(10),

    gender ENUM(
        'Male',
        'Female'
    ) DEFAULT NULL,

    civil_status ENUM(
        'Single',
        'Married',
        'Widowed',
        'Separated',
        'Divorced'
    ) DEFAULT NULL,

    email VARCHAR(100),

    contact_number VARCHAR(20),

    address VARCHAR(150),

    birthday DATE DEFAULT NULL,

    age INT DEFAULT NULL,

    occupation VARCHAR(100),

    employer VARCHAR(100),

    employer_address VARCHAR(150),

    father_name VARCHAR(100),

    mother_name VARCHAR(100),

    spouse_name VARCHAR(100),

    spouse_occupation VARCHAR(100),

    spouse_employer VARCHAR(100),

    reference1_name VARCHAR(100),

    reference1_signature VARCHAR(100),

    reference2_name VARCHAR(100),

    reference2_signature VARCHAR(100),

    photo VARCHAR(255) DEFAULT NULL,

    status ENUM(
        'active',
        'archived'
    ) NOT NULL DEFAULT 'active',

    password VARCHAR(255) NOT NULL,

    is_first_login TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

);


-- =========================================================
-- TABLE: resident_children
-- =========================================================

CREATE TABLE IF NOT EXISTS resident_children (

    child_id INT AUTO_INCREMENT PRIMARY KEY,

    resident_id INT NOT NULL,

    child_name VARCHAR(100) NOT NULL,

    age INT DEFAULT NULL,

    CONSTRAINT fk_resident_children_resident

        FOREIGN KEY (resident_id)

        REFERENCES residents(resident_id)

        ON DELETE CASCADE

);


-- =========================================================
-- TABLE: resident_update_history
--
-- Stores BEFORE and AFTER information whenever a resident
-- record is updated.
-- =========================================================

CREATE TABLE IF NOT EXISTS resident_update_history (

    history_id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    resident_id INT NOT NULL,

    updated_by_type ENUM(
        'resident',
        'staff'
    ) NOT NULL,

    updated_by_id INT DEFAULT NULL,

    update_section VARCHAR(100) NOT NULL,

    old_data LONGTEXT DEFAULT NULL,

    new_data LONGTEXT DEFAULT NULL,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (history_id),

    INDEX idx_history_resident_id (
        resident_id
    ),

    INDEX idx_history_updated_at (
        updated_at
    ),

    INDEX idx_history_updated_by (
        updated_by_type,
        updated_by_id
    ),

    CONSTRAINT fk_resident_update_history_resident

        FOREIGN KEY (resident_id)

        REFERENCES residents(resident_id)

        ON DELETE CASCADE

        ON UPDATE CASCADE

);


-- =========================================================
-- TABLE: staff
-- =========================================================

CREATE TABLE IF NOT EXISTS staff (

    staff_id INT AUTO_INCREMENT PRIMARY KEY,

    username VARCHAR(50) NOT NULL UNIQUE,

    full_name VARCHAR(100) NOT NULL,

    email VARCHAR(100),

    password VARCHAR(255) NOT NULL,

    role VARCHAR(30) DEFAULT 'staff',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- =========================================================
-- TABLE: surveys
-- =========================================================

CREATE TABLE IF NOT EXISTS surveys (

    survey_id INT AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(150) NOT NULL,

    description TEXT,

    created_by INT NOT NULL,

    start_date DATE NOT NULL,

    end_date DATE NOT NULL,

    status ENUM(
        'active',
        'inactive'
    ) DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_surveys_staff

        FOREIGN KEY (created_by)

        REFERENCES staff(staff_id)

);


-- =========================================================
-- TABLE: survey_questions
-- =========================================================

CREATE TABLE IF NOT EXISTS survey_questions (

    question_id INT AUTO_INCREMENT PRIMARY KEY,

    survey_id INT NOT NULL,

    question_text VARCHAR(255) NOT NULL,

    question_type ENUM(
        'multiple_choice',
        'yes_no',
        'rating',
        'short_answer'
    ) NOT NULL,

    is_required TINYINT(1) DEFAULT 1,

    question_order INT DEFAULT 0,

    CONSTRAINT fk_questions_survey

        FOREIGN KEY (survey_id)

        REFERENCES surveys(survey_id)

        ON DELETE CASCADE

);


-- =========================================================
-- TABLE: survey_choices
-- =========================================================

CREATE TABLE IF NOT EXISTS survey_choices (

    choice_id INT AUTO_INCREMENT PRIMARY KEY,

    question_id INT NOT NULL,

    choice_text VARCHAR(150) NOT NULL,

    choice_order INT DEFAULT 0,

    CONSTRAINT fk_choices_question

        FOREIGN KEY (question_id)

        REFERENCES survey_questions(question_id)

        ON DELETE CASCADE

);


-- =========================================================
-- TABLE: responses
-- =========================================================

CREATE TABLE IF NOT EXISTS responses (

    response_id INT AUTO_INCREMENT PRIMARY KEY,

    survey_id INT NOT NULL,

    resident_id INT NOT NULL,

    resident_name VARCHAR(100) NOT NULL,

    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_responses_survey

        FOREIGN KEY (survey_id)

        REFERENCES surveys(survey_id)

        ON DELETE CASCADE,

    CONSTRAINT fk_responses_resident

        FOREIGN KEY (resident_id)

        REFERENCES residents(resident_id)

        ON DELETE CASCADE,

    UNIQUE KEY unique_submission (
        survey_id,
        resident_id
    )

);


-- =========================================================
-- TABLE: survey_results
-- =========================================================

CREATE TABLE IF NOT EXISTS survey_results (

    result_id INT AUTO_INCREMENT PRIMARY KEY,

    response_id INT NOT NULL,

    question_id INT NOT NULL,

    choice_id INT NULL,

    answer_text VARCHAR(255) NULL,

    CONSTRAINT fk_results_response

        FOREIGN KEY (response_id)

        REFERENCES responses(response_id)

        ON DELETE CASCADE,

    CONSTRAINT fk_results_question

        FOREIGN KEY (question_id)

        REFERENCES survey_questions(question_id)

        ON DELETE CASCADE,

    CONSTRAINT fk_results_choice

        FOREIGN KEY (choice_id)

        REFERENCES survey_choices(choice_id)

        ON DELETE SET NULL

);


-- =========================================================
-- TABLE: login_history
-- =========================================================

CREATE TABLE IF NOT EXISTS login_history (

    log_id INT AUTO_INCREMENT PRIMARY KEY,

    user_type ENUM(
        'resident',
        'staff'
    ) NOT NULL,

    user_id INT NOT NULL,

    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- =========================================================
-- SAMPLE STAFF ACCOUNT
-- =========================================================
--
-- Username:
--     admin
--
-- Password:
--     admin123
--
-- =========================================================

INSERT INTO staff (
    username,
    full_name,
    email,
    password,
    role
)

SELECT
    'admin',
    'Barangay Health Staff',
    'staff@example.com',
    '$2b$10$mF8MNjFO6F1s.nbadeDc5.h9teXtBeuSGv2zBWKOH/.3AKQPksCsm',
    'admin'

WHERE NOT EXISTS (

    SELECT 1
    FROM staff
    WHERE username = 'admin'

);


-- =========================================================
-- SAMPLE RESIDENT 1
-- =========================================================
-- Gender: Male
-- =========================================================

INSERT INTO residents (
    resident_number,
    first_name,
    last_name,
    email,
    contact_number,
    address,
    gender,
    password,
    is_first_login
)

SELECT
    '2026-0001',
    'Justin Lian',
    'Enriquez',
    'lianjustin91@gmail.com',
    '09752509652',
    'Sulucan, Bocaue, Bulacan',
    'Male',
    '$2b$10$mGrOfVoC0/ORly7Z4iHH0OPlksjcOmNqPP9hh4vkrXXPJnQfq4LMm',
    1

WHERE NOT EXISTS (

    SELECT 1
    FROM residents
    WHERE resident_number = '2026-0001'

);


-- =========================================================
-- SAMPLE RESIDENT 2
-- =========================================================
-- Gender: Female
-- =========================================================

INSERT INTO residents (
    resident_number,
    first_name,
    last_name,
    email,
    contact_number,
    address,
    gender,
    password,
    is_first_login
)

SELECT
    '2026-0002',
    'Cielo Marie',
    'Estolloso',
    'cieloestolloso09@gmail.com',
    '09763008362',
    'Iba-Ibayo, Hagonoy, Bulacan',
    'Female',
    '$2b$10$Vd25nNypemf8GlUJX.pAsehs9fdQUS/7oZvtBEyU7dleDslVSinpe',
    1

WHERE NOT EXISTS (

    SELECT 1
    FROM residents
    WHERE resident_number = '2026-0002'

);


-- =========================================================
-- SAMPLE RESIDENT 3
-- =========================================================
-- Gender: Female
-- =========================================================

INSERT INTO residents (
    resident_number,
    first_name,
    last_name,
    email,
    contact_number,
    address,
    gender,
    password,
    is_first_login
)

SELECT
    '2026-0003',
    'Mary Pauleen',
    'Salvador',
    'pauleensalvador@gmail.com',
    '09690640080',
    'Malolos, Bulacan',
    'Female',
    '$2b$10$wz1gKG5NezDiO9kCo3cMveQBx1ju78z7M4plgT349Uuazy9oa.Tr.',
    1

WHERE NOT EXISTS (

    SELECT 1
    FROM residents
    WHERE resident_number = '2026-0003'

);


-- =========================================================
-- SAMPLE RESIDENT 4
-- =========================================================
-- Gender: Female
-- =========================================================

INSERT INTO residents (
    resident_number,
    first_name,
    last_name,
    email,
    contact_number,
    address,
    gender,
    password,
    is_first_login
)

SELECT
    '2026-0004',
    'Kylie Denise',
    'Marasigan',
    'kyliedenise12@gmail.com',
    '09235476895',
    'San Isidro 1, Paombong, Bulacan',
    'Female',
    '$2b$10$Va7OVF/mb56F6KFD99w5FegKMXJd3g51ohmsvz12JI2AJdqnhN0hu',
    1

WHERE NOT EXISTS (

    SELECT 1
    FROM residents
    WHERE resident_number = '2026-0004'

);


-- =========================================================
-- SAMPLE RESIDENT 5
-- =========================================================
-- Gender: Male
-- =========================================================

INSERT INTO residents (
    resident_number,
    first_name,
    last_name,
    email,
    contact_number,
    address,
    gender,
    password,
    is_first_login
)

SELECT
    '2026-0005',
    'Aaron Gabriel',
    'Ranes',
    'aarongabrielranes@gmail.com',
    '09690924629',
    'Malis, Guiguinto, Bulacan',
    'Male',
    '$2b$10$qPNa1js1imq7pYTuQF2AluEX3BwQRYr5BXeZfaAiJbzR55z8.vrw2',
    1

WHERE NOT EXISTS (

    SELECT 1
    FROM residents
    WHERE resident_number = '2026-0005'

);


-- =========================================================
-- SAMPLE SURVEY
-- =========================================================

INSERT INTO surveys (
    title,
    description,
    created_by,
    start_date,
    end_date,
    status
)

SELECT
    'Barangay Health Services Feedback',
    'Help us improve our health services by answering this short survey.',
    1,
    '2026-07-01',
    '2026-12-31',
    'active'

WHERE NOT EXISTS (

    SELECT 1
    FROM surveys
    WHERE title =
        'Barangay Health Services Feedback'

);


-- =========================================================
-- SAMPLE SURVEY QUESTION 1
-- =========================================================

INSERT INTO survey_questions (
    survey_id,
    question_text,
    question_type,
    is_required,
    question_order
)

SELECT
    survey_id,
    'How satisfied are you with the health center services?',
    'rating',
    1,
    1

FROM surveys

WHERE title =
    'Barangay Health Services Feedback'

AND NOT EXISTS (

    SELECT 1
    FROM survey_questions sq
    WHERE sq.survey_id = surveys.survey_id
    AND sq.question_order = 1

);


-- =========================================================
-- SAMPLE SURVEY QUESTION 2
-- =========================================================

INSERT INTO survey_questions (
    survey_id,
    question_text,
    question_type,
    is_required,
    question_order
)

SELECT
    survey_id,
    'Have you availed of a free check-up in the past 6 months?',
    'yes_no',
    1,
    2

FROM surveys

WHERE title =
    'Barangay Health Services Feedback'

AND NOT EXISTS (

    SELECT 1
    FROM survey_questions sq
    WHERE sq.survey_id = surveys.survey_id
    AND sq.question_order = 2

);


-- =========================================================
-- SAMPLE SURVEY QUESTION 3
-- =========================================================

INSERT INTO survey_questions (
    survey_id,
    question_text,
    question_type,
    is_required,
    question_order
)

SELECT
    survey_id,
    'Which service do you use most often?',
    'multiple_choice',
    1,
    3

FROM surveys

WHERE title =
    'Barangay Health Services Feedback'

AND NOT EXISTS (

    SELECT 1
    FROM survey_questions sq
    WHERE sq.survey_id = surveys.survey_id
    AND sq.question_order = 3

);


-- =========================================================
-- SAMPLE SURVEY QUESTION 4
-- =========================================================

INSERT INTO survey_questions (
    survey_id,
    question_text,
    question_type,
    is_required,
    question_order
)

SELECT
    survey_id,
    'Any suggestions to improve our services?',
    'short_answer',
    0,
    4

FROM surveys

WHERE title =
    'Barangay Health Services Feedback'

AND NOT EXISTS (

    SELECT 1
    FROM survey_questions sq
    WHERE sq.survey_id = surveys.survey_id
    AND sq.question_order = 4

);


-- =========================================================
-- SAMPLE SURVEY CHOICES
-- QUESTION 1 - RATING
-- =========================================================

INSERT INTO survey_choices (
    question_id,
    choice_text,
    choice_order
)

SELECT
    question_id,
    '1 - Very Dissatisfied',
    1

FROM survey_questions

WHERE question_order = 1

AND NOT EXISTS (

    SELECT 1
    FROM survey_choices sc
    WHERE sc.question_id =
        survey_questions.question_id
    AND sc.choice_order = 1

);


INSERT INTO survey_choices (
    question_id,
    choice_text,
    choice_order
)

SELECT
    question_id,
    '2 - Dissatisfied',
    2

FROM survey_questions

WHERE question_order = 1

AND NOT EXISTS (

    SELECT 1
    FROM survey_choices sc
    WHERE sc.question_id =
        survey_questions.question_id
    AND sc.choice_order = 2

);


INSERT INTO survey_choices (
    question_id,
    choice_text,
    choice_order
)

SELECT
    question_id,
    '3 - Neutral',
    3

FROM survey_questions

WHERE question_order = 1

AND NOT EXISTS (

    SELECT 1
    FROM survey_choices sc
    WHERE sc.question_id =
        survey_questions.question_id
    AND sc.choice_order = 3

);


INSERT INTO survey_choices (
    question_id,
    choice_text,
    choice_order
)

SELECT
    question_id,
    '4 - Satisfied',
    4

FROM survey_questions

WHERE question_order = 1

AND NOT EXISTS (

    SELECT 1
    FROM survey_choices sc
    WHERE sc.question_id =
        survey_questions.question_id
    AND sc.choice_order = 4

);


INSERT INTO survey_choices (
    question_id,
    choice_text,
    choice_order
)

SELECT
    question_id,
    '5 - Very Satisfied',
    5

FROM survey_questions

WHERE question_order = 1

AND NOT EXISTS (

    SELECT 1
    FROM survey_choices sc
    WHERE sc.question_id =
        survey_questions.question_id
    AND sc.choice_order = 5

);


-- =========================================================
-- SAMPLE SURVEY CHOICES
-- QUESTION 2 - YES / NO
-- =========================================================

INSERT INTO survey_choices (
    question_id,
    choice_text,
    choice_order
)

SELECT
    question_id,
    'Yes',
    1

FROM survey_questions

WHERE question_order = 2

AND NOT EXISTS (

    SELECT 1
    FROM survey_choices sc
    WHERE sc.question_id =
        survey_questions.question_id
    AND sc.choice_order = 1

);


INSERT INTO survey_choices (
    question_id,
    choice_text,
    choice_order
)

SELECT
    question_id,
    'No',
    2

FROM survey_questions

WHERE question_order = 2

AND NOT EXISTS (

    SELECT 1
    FROM survey_choices sc
    WHERE sc.question_id =
        survey_questions.question_id
    AND sc.choice_order = 2

);


-- =========================================================
-- SAMPLE SURVEY CHOICES
-- QUESTION 3 - SERVICES
-- =========================================================

INSERT INTO survey_choices (
    question_id,
    choice_text,
    choice_order
)

SELECT
    question_id,
    'Consultation',
    1

FROM survey_questions

WHERE question_order = 3

AND NOT EXISTS (

    SELECT 1
    FROM survey_choices sc
    WHERE sc.question_id =
        survey_questions.question_id
    AND sc.choice_order = 1

);


INSERT INTO survey_choices (
    question_id,
    choice_text,
    choice_order
)

SELECT
    question_id,
    'Vaccination',
    2

FROM survey_questions

WHERE question_order = 3

AND NOT EXISTS (

    SELECT 1
    FROM survey_choices sc
    WHERE sc.question_id =
        survey_questions.question_id
    AND sc.choice_order = 2

);


INSERT INTO survey_choices (
    question_id,
    choice_text,
    choice_order
)

SELECT
    question_id,
    'Dental Checkup',
    3

FROM survey_questions

WHERE question_order = 3

AND NOT EXISTS (

    SELECT 1
    FROM survey_choices sc
    WHERE sc.question_id =
        survey_questions.question_id
    AND sc.choice_order = 3

);


INSERT INTO survey_choices (
    question_id,
    choice_text,
    choice_order
)

SELECT
    question_id,
    'Maternal Care',
    4

FROM survey_questions

WHERE question_order = 3

AND NOT EXISTS (

    SELECT 1
    FROM survey_choices sc
    WHERE sc.question_id =
        survey_questions.question_id
    AND sc.choice_order = 4

);


-- =========================================================
-- END OF DATABASE
-- =========================================================