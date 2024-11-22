<?php
require_once 'config.php';
require_once 'bot.php';

$config = require 'config.php';
$bot = new TelegramBot($config);
$bot->handleRequest();
