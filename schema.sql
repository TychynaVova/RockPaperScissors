-- --------------------------------------------------------

--
-- Структура таблицы `gameusersbot`
--

CREATE TABLE IF NOT EXISTS `gameusersbot2` (
  `chat_id` bigint(20) NOT NULL,
  `score` int(11) DEFAULT 0,
  `username` varchar(255) NOT NULL,
  `state` enum('idle','creating_tournament','waiting_for_choice','playing_with_bot','waiting_for_choice_result') DEFAULT 'idle',
  `current_tournament` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


--
-- Структура таблицы `gametournaments`
--

CREATE TABLE IF NOT EXISTS `gametournaments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `status` enum('active','completed') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `chat_id` (`chat_id`),
  CONSTRAINT `gametournaments_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `gameusersbot` (`chat_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


--
-- Структура таблицы `gamematches`
--

CREATE TABLE IF NOT EXISTS `gamematches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `player1_chat_id` bigint(20) NOT NULL,
  `player2_chat_id` bigint(20) DEFAULT NULL,
  `result` enum('Игрок, который создал игру выиграл','Вы выиграли','Ничья') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `player1_choice` varchar(20) NOT NULL,
  `player2_choice` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

