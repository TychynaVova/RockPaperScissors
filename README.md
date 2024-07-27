# Telegram Game Bot

Этот проект представляет собой Telegram бота, который позволяет пользователям играть в игру, создавать поединки и отслеживать свои результаты. Бот реализует логику игры "Камень, ножницы, бумага" и предоставляет возможность управления через команды и кнопки.


## Версия

![version](https://img.shields.io/badge/version-1.0.0-blue)

## Функциональность

- **Регистрация пользователей**: Бот автоматически регистрирует пользователей при первом взаимодействии.
- **Создание поединков**: Пользователи могут создавать поединков и приглашать других участников.
- **Игра "Камень, ножницы, бумага"**: Бот позволяет играть в эту игру как с ботом, так и с другими пользователями.
- **Отслеживание результатов**: Ведется учет результатов игр и поединков.
- **Логирование**: Вся активность бота и пользователей логируется для упрощения отладки и мониторинга.

## Установка

### Требования

- PHP 7.4.33 или выше
- MySQL
- [Telegram Bot API](https://core.telegram.org/bots/api)

### Шаги установки

1. **Клонирование репозитория**

    ```sh
    git clone https://github.com/yourusername/telegram-game-bot.git
    cd telegram-game-bot
    ```

2. **Установка зависимостей**

    Проект не использует внешние библиотеки, так что дополнительных установок не требуется.

3. **Настройка конфигурации**

    Переименуйте файл `config.sample.php` в `config.php` и установите ваши параметры:

    ```php
    return [
        'botToken' => 'YOUR_TELEGRAM_BOT_TOKEN',
        'dbHost' => 'localhost',
        'dbUser' => 'YOUR_DB_USER',
        'dbPass' => 'YOUR_DB_PASSWORD',
        'dbName' => 'YOUR_DB_NAME',
        'logEnabled' => true,
    ];
    ```

4. **Создание базы данных**

    Создайте базу данных и выполните SQL скрипт для создания таблиц:

    ```sql
    -- --------------------------------------------------------

    --
    -- Структура таблицы `gameusersbot`
    --

    CREATE TABLE IF NOT EXISTS `gameusersbot` (
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
    ```

5. **Запуск сервера**

    Убедитесь, что ваш веб-сервер (например, Apache или Nginx) настроен и запущен. Настройте веб-хуки в Telegram для получения обновлений от вашего бота.

## Структура проекта

- `index.php` - Основной файл для обработки запросов и взаимодействия с Telegram API.
- `TelegramAPI.php` - Класс для взаимодействия с Telegram Bot API и базой данных.
- `Logger.php` - Класс для логирования активности.
- `config.php` - Файл конфигурации для установки параметров базы данных и бота.

## Пример использования

1. Отправьте команду `/start` боту для начала взаимодействия.
2. Используйте меню бота для создания поединков, просмотра рейтингов и начала игр.

## Будущие задачи

- **Многоязычная поддержка**: Добавление возможности выбора языка при взаимодействии с ботом.
- **Создание турниров**: Создание круговых турниров на 10 играков. Минимальные требования для игры в турниры.
- **Оптимизация кода**: Улучшение производительности и читаемости кода.

## Вклад

Если у вас есть предложения или улучшения, пожалуйста, создайте issue или отправьте pull request. Вы также можете связаться со мной через Telegram: [@tychina](https://t.me/tychina) или по электронной почте: [tychinavova@gmail.com](mailto:tychinavova@gmail.com).
