CREATE DATABASE telegram_bot;

USE telegram_bot;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id BIGINT NOT NULL UNIQUE,
    username VARCHAR(255),
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
