<?php

error_reporting(E_ALL);


require_once 'TelegramApi.php';
require_once 'Logger.php';
$config = require_once 'config.php';

$logger = new Logger('log.txt', $config['logEnabled'], Logger::LOG_LEVEL_NONE);
$telegram = new TelegramAPI($config['botToken'], $config['dbHost'], $config['dbUser'], $config['dbPass'], $config['dbName']);

$update = json_decode(file_get_contents('php://input'), true);
$logger->logInfo('Received update: ' . print_r($update, true));

// Эмодзи
$rock = "\u{1FAA8}"; // Камень
$scissors = "\u{2702}\u{FE0F}"; // Ножницы
$paper = "\u{1F4C4}"; // Бумага

$gameEmoji = "\u{1F3AE}"; // Играть с ботом
$createMatchEmoji = "\u{2694}"; // Создать поединок
$findMatchEmoji = "\u{1F50D}"; // Найти поединок
$leaderboardEmoji = "\u{1F3C6}"; // Проверить таблицу лидеров
$rulesEmoji = "\u{1F4D6}"; // Правила работы с ботом
$back = "\u{21A9}"; //стрелка назад

$disappointedFace = "\u{1F61E}"; // Грусный смайл.
$list = "\u{2714}"; //для листа

$firstPlaceEmoji = "\u{1F947}"; // 1-е место
$secondPlaceEmoji = "\u{1F948}"; // 2-е место
$thirdPlaceEmoji = "\u{1F949}"; // 3-е место
//

$keyboard_menu = [
    'inline_keyboard' => [
        [['text' => $gameEmoji . " Играть с ботом", 'callback_data' => 'play_with_bot']],
        [['text' => $createMatchEmoji . " Создать поединок", 'callback_data' => 'create_match']],
        [['text' => $findMatchEmoji . " Найти поединок", 'callback_data' => 'find_match']],
        [['text' => $leaderboardEmoji . " Проверить таблицу лидеров", 'callback_data' => 'check_leaderboard']],
        [['text' => $rulesEmoji . " Правила работы с ботом", 'callback_data' => 'check_rules']]
    ]
];

$keyboard_game = [
    'inline_keyboard' => [
        [
            ['text' => $rock . ' Камень', 'callback_data' => 'Камень'],
            ['text' => $scissors . ' Ножницы', 'callback_data' => 'Ножницы'],
            ['text' => $paper . ' Бумага', 'callback_data' => 'Бумага']
        ],
        [
            ['text' => $back . ' В меню', 'callback_data' => 'menu'],
        ]
    ]
];

$return_menu  = [
    'inline_keyboard' => [
        [
            ['text' => $back . ' В меню', 'callback_data' => 'menu'],
        ]
    ]
];

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'];
    $username = $message['from']['username'];

    if ($text === '/start') {
        $telegram->createUser($chatId, $username);
        $telegram->setUserState($chatId, 'idle');
        $telegram->sendMessage($chatId, 'Добро пожаловать в игровой бот! Выберите действие:', $keyboard_menu);
        $logger->logInfo("User $chatId started the bot");
    } else {
        $state = $telegram->getUserState($chatId);
        $logger->logDebug("State user $chatId: $state");
        if (isValidTournamentName($text)) {
            $tournamentName = $text;
            $tournamentCreated = $telegram->createTournament($chatId, $tournamentName);
            $logger->logDebug("Tournament name $tournamentName created: " . ($tournamentCreated ? 'success' : 'failure'));

            if ($tournamentCreated) {
                $telegram->setUserState($chatId, 'waiting_for_choice');
                $tournamentId = $telegram->getLastTournamentId($chatId);
                $logger->logDebug("Tournament number: $tournamentId");
                $telegram->createMatch($tournamentId, $chatId);
                $telegram->setUserCurrentTournament($chatId, $tournamentId);
                $telegram->sendMessage($chatId, 'Поединок создан успешно! Сделайте свой выбор:', $keyboard_game);
                $logger->logInfo("Tournament $tournamentId created for user $chatId");
            } else {
                $telegram->sendMessage($chatId, 'Произошла ошибка при создании поединка. Попробуйте еще раз.', $keyboard_menu);
                $logger->logError("Error creating tournament for user $chatId");
            }
        } else {
            $text = "Имя поединка не соответствует требованиям.
Наименование поединка может содержать:
    $list любую букву (латинская, кириллическая, и т.д.);
    $list любую цифру;
    $list любой знак припинания;
    $list любой специальный символ (например, валютные знаки, математические символы);
    $list любой пробельный символ (включает пробел, табуляцию и другие пробельные символы);
    $list длина строки от 1 до 15 символов.

Введите другое имя поединка.
            ";
            $telegram->sendMessage($chatId, $text, $keyboard_menu);

            $logger->logError("Invalid tournament name provided by user $chatId: $text");
        }
    }
}

if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $data = $callbackQuery['data'];

    $state = $telegram->getUserState($chatId);
    $logger->logDebug("State user $chatId: $state");
    switch ($data) {
        case 'menu':
            $telegram->setUserState($chatId, 'idle');
            $telegram->sendMessage($chatId, 'Добро пожаловать в игровой бот! Выберите действие:', $keyboard_menu);
            break;
        case 'play_with_bot':
            $telegram->setUserState($chatId, 'playing_with_bot');
            $telegram->sendMessage($chatId, 'Сделайте свой выбор:', $keyboard_game);
            $logger->logInfo("User $chatId chose to play with the bot");
            break;
        case 'create_match':
            $telegram->setUserState($chatId, 'creating_tournament');
            $telegram->sendMessage($chatId, 'Введите имя поединка:');
            $logger->logInfo("User $chatId chose to create a match");
            break;
        case 'find_match':
            $tournaments = $telegram->getAvailableTournaments();
            if (empty($tournaments)) {
                $telegram->sendMessage($chatId, 'Нет доступных поединков.', $keyboard_menu);
                $logger->logInfo("No available tournaments for user $chatId");
            } else {
                $keyboard = [
                    'inline_keyboard' => array_chunk($tournaments, 1)
                ];
                $telegram->sendMessage($chatId, 'Выберите поединок:', $keyboard);
                $logger->logInfo("Available tournaments sent to user $chatId");
            }
            break;
        case 'check_leaderboard':
            $leaderboard = $telegram->getLeaderboard();
            $telegram->sendMessage($chatId, $leaderboard, $keyboard_menu);
            $logger->logInfo("Leaderboard sent to user $chatId");
            break;
        case 'check_rules':
            $rulesText = "
<b>Правила работы с ботом:</b>

1. Используйте команду /start для начала.
2. Выберите действие в основном меню.
3. Для игры с ботом выберите 'Играть с ботом' и сделайте свой выбор.
4. Для создания поединка выберите 'Создать поединок' и введите имя поединка.
5. Для участия в поединке выберите 'Найти поединок' и выберите поединок из списка.
6. Результаты матчей и турнирная таблица доступны в разделе 'Проверить таблицу лидеров'

<b>Правила работы с ботом:</b>
1. <u>Игровой процесс:</u>
    - Вы можете играть с ботом в игру \"Камень, Ножницы, Бумага\" или участвовать в поединках с другими пользователями.
2. <u>Система начисления очков:</u>
    <b>Игра с ботом:</b>
    - За победу: +2 очка
    - За проигрыш: -1 очко
    - За ничью: 0 очков
    <b>Поединок:</b>
    - За победу: +2 очка
    - За проигрыш: -1 очко
    - За ничью: 0 очков
    - За организацию поединка: -1 очко (снимается с игрока, создавшего поединок)
3. <u>Минимальное количество очков:</u>
    - Очки не могут быть меньше 0. Если после проигрыша результат становится отрицательным, очки устанавливаются на 0.
4. <u>Создание поединка:</u>
    - Наименование может содержать:
        $list любую букву (латинская, кириллическая, и т.д.);
        $list любую цифру;
        $list любой знак припинания;
        $list любой специальный символ (например, валютные знаки, математические символы);
        $list любой пробельный символ (включает пробел, табуляцию и другие пробельные символы);
        $list длина строки от 1 до 15 символов.
    - Первый игрок создает поединок, делает свой выбор и теряет 1 очко за организацию поединка.
    - Второй игрок присоединяется к поединку и также делает свой выбор.
    - Очки начисляются в соответствии с результатом игры.
5. <u>Ограничение по числу поединков:</u>
    - Один игрок может создать не более 3 поединков.

<b>Приятной игры!</b>
                ";
            $telegram->sendMessage($chatId, $rulesText, $keyboard_menu);
            $logger->logInfo("Rules sent to user $chatId");
            break;
        case 'Камень':
        case 'Ножницы':
        case 'Бумага':
            if ($state === 'playing_with_bot') {
                $userChoice = $data;
                $computerChoice = getComputerChoice();
                $result = determineWinner($userChoice, $computerChoice);
                $score = $telegram->getUserScore($chatId);

                if ($result === "Вы выиграли!") {
                    $score += 2;
                } elseif ($result === "Вы проиграли!") {
                    $score -= 1;
                }

                $score = max(0, $score);
                $telegram->updateUserScore($chatId, $score);
                $telegram->sendMessage($chatId, "Ваш выбор: $userChoice\nВыбор компьютера: $computerChoice\nРезультат: $result\nВаши очки: $score", $keyboard_game);
                $telegram->setUserState($chatId, 'playing_with_bot', true);
            } elseif ($state === 'waiting_for_choice') {
                $tournamentId = $telegram->getUserCurrentTournament($chatId);
                file_put_contents('logDebug.txt', "Current tournament: $tournamentId\n", FILE_APPEND);
                if ($tournamentId) {
                    $telegram->updateMatchResult($tournamentId, $chatId, $data, null, null);
                    $telegram->sendMessage($chatId, "Ваш выбор сохранен. Ожидайте выбор второго игрока.", $return_menu);
                    $telegram->setUserState($chatId, 'idle');
                }
            }
            break;
        case (preg_match('/^tournament_\d+$/', $data) ? $data : null):
            $tournamentId = str_replace('tournament_', '', $data);

            $keyboard_game_response = [
                'inline_keyboard' => [
                    [
                        ['text' => $rock . 'Камень', 'callback_data' => "choice_" . $tournamentId . "_Камень"],
                        ['text' => $scissors . 'Ножницы', 'callback_data' => "choice_" . $tournamentId . "_Ножницы"],
                        ['text' => $paper . 'Бумага', 'callback_data' => "choice_" . $tournamentId . "_Бумага"]
                    ],
                ],
            ];

            $telegram->sendMessage($chatId, "Вы выбрали поединок с ID: $tournamentId. Сделайте свой выбор:", $keyboard_game_response);
            $telegram->setUserState($chatId, 'waiting_for_choice_result');
            $telegram->setUserCurrentTournament($chatId, $tournamentId);
            $logger->logInfo("User $chatId chose tournament $tournamentId");
            break;
        case (preg_match('/^choice_(\d+)_(Камень|Ножницы|Бумага)$/', $data) ? $data : null):
            if ($state === 'waiting_for_choice_result') {
                list($prefix, $tournamentId, $userChoice) = explode('_', $data);
                $logger->logDebug("Waiting for choice result: prefix=$prefix, tournamentId=$tournamentId, userChoice=$userChoice");
                if ($prefix === 'choice') {
                    $telegram->updateMatchResult($tournamentId, null, null, $chatId, $userChoice);
                    $telegram->sendMessage($chatId, "Ваш выбор сохранен. Результат игры будет объявлен когда ваш поединок будет сыгран.");
                    $telegram->setUserState($chatId, 'idle');
                }
            }
            break;
        default:
            $telegram->sendMessage($chatId, 'Не понимаю твою команду.' . $disappointedFace, $keyboard_menu);
            $logger->logInfo("Unknown command from user $chatId: $data");
            break;
    }
}

/**
 * Summary of getComputerChoice
 * @return string
 */
function getComputerChoice()
{
    $choices = ['Камень', 'Ножницы', 'Бумага'];
    return $choices[array_rand($choices)];
}

/**
 * Summary of determineWinner
 * @param mixed $userChoice
 * @param mixed $computerChoice
 * @return string
 */
function determineWinner($userChoice, $computerChoice)
{
    if ($userChoice === $computerChoice) {
        return 'Ничья!';
    }

    if (
        ($userChoice === 'Камень' && $computerChoice === 'Ножницы') ||
        ($userChoice === 'Ножницы' && $computerChoice === 'Бумага') ||
        ($userChoice === 'Бумага' && $computerChoice === 'Камень')
    ) {
        return 'Вы выиграли!';
    } else {
        return 'Вы проиграли!';
    }
}

/**
 * Summary of isValidTournamentName
 * @param mixed $name
 * @return bool|int
 */
function isValidTournamentName($name)
{
    $pattern = '/^[\p{L}\p{N}\p{P}\p{S}\s]{1,15}$/u';
    return preg_match($pattern, $name);
}
