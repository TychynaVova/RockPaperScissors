<?php

error_reporting(E_ALL);

class TelegramAPI {
    private $apiUrl;
    private $db;
    private $logEnabled;

    /**
     * Summary of __construct
     * @param mixed $botToken
     * @param mixed $dbHost
     * @param mixed $dbUser
     * @param mixed $dbPass
     * @param mixed $dbName
     * @param mixed $logEnabled
     */
    public function __construct($botToken, $dbHost, $dbUser, $dbPass, $dbName, $logEnabled = false) {
        $this->apiUrl = "https://api.telegram.org/bot" . $botToken . "/";
        $this->db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        $this->logEnabled = $logEnabled;

        if ($this->db->connect_error) {
            $this->logError("Connection failed: " . $this->db->connect_error);
            die("Connection failed: " . $this->db->connect_error);
        }
    }

    /**
     * Summary of sendMessage
     * @param mixed $chatId
     * @param mixed $text
     * @param mixed $replyMarkup
     * @param mixed $log
     * @return mixed
     */
    public function sendMessage($chatId, $text, $replyMarkup = null, $log = false) {
        $url = $this->apiUrl . "sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
    
        if ($this->logEnabled || $log) {
            $this->logDebug('sendMessage data: ' . print_r($data, true));
        }
    
        return $this->makeRequest($url, $data);
    }

    /**
     * Summary of getUpdates
     * @param mixed $offset
     * @param mixed $log
     * @return mixed
     */
    public function getUpdates($offset = 0, $log = false) {
        $url = $this->apiUrl . "getUpdates";
        $data = [
            'offset' => $offset
        ];
    
        if ($this->logEnabled || $log) {
            $this->logDebug('getUpdates data: ' . print_r($data, true));
        }
    
        return $this->makeRequest($url, $data);
    }

    /**
     * Summary of getUserScore
     * @param mixed $chatId
     * @param mixed $log
     * @return mixed
     */
    public function getUserScore($chatId, $log = false) {
        $stmt = $this->db->prepare("SELECT score FROM gameusersbot WHERE chat_id = ?");
        $stmt->bind_param("i", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $score = $row ? $row['score'] : 0;
        
        if ($this->logEnabled || $log) {
            $this->logDebug("getUserScore chatId: $chatId, score: $score");
        }
        
        return $score;
    }

    /**
     * Summary of updateUserScore
     * @param mixed $chatId
     * @param mixed $score
     * @param mixed $log
     * @return void
     */
    public function updateUserScore($chatId, $score, $log = false) {
        $stmt = $this->db->prepare("UPDATE gameusersbot SET score = ? WHERE chat_id = ?");
        $stmt->bind_param("ii", $score, $chatId);
        $stmt->execute();
        $stmt->close();
        
        if ($this->logEnabled || $log) {
            $this->logDebug("updateUserScore chatId: $chatId, newScore: $score");
        }
    }

    /**
     * Summary of createUser
     * @param mixed $chatId
     * @param mixed $username
     * @param mixed $log
     * @return void
     */
    public function createUser($chatId, $username, $log = false) {
        $query = "INSERT INTO gameusersbot (chat_id, username) VALUES (?, ?) 
                  ON DUPLICATE KEY UPDATE username = VALUES(username)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('is', $chatId, $username);
        $stmt->execute();
        $stmt->close();
        
        if ($this->logEnabled || $log) {
            $this->logDebug("createUser chatId: $chatId, username: $username");
        }
    }

    /**
     * Summary of makeRequest
     * @param mixed $url
     * @param mixed $data
     * @return mixed
     */
    private function makeRequest($url, $data) {
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return json_decode($result, true);
    }
    
    /**
     * Summary of getLeaderboard
     * @param mixed $log
     * @return string
     */
    public function getLeaderboard($log = false) {
        $query = "SELECT username, score FROM gameusersbot ORDER BY score DESC LIMIT 3";
        $result = $this->db->query($query);
    
        if ($this->db->error) {
            $this->logError("SQL Error: " . $this->db->error);
        }
    
        if ($result->num_rows > 0) {
            $leaderboard = [];
            $place = 1;
            while ($row = $result->fetch_assoc()) {
                $emoji = '';
                switch ($place) {
                    case 1:
                        $emoji = "\u{1F947}"; // 🥇 1-е место
                        break;
                    case 2:
                        $emoji = "\u{1F948}"; // 🥈 2-е место
                        break;
                    case 3:
                        $emoji = "\u{1F949}"; // 🥉 3-е место
                        break;
                }
                $leaderboard[] = "$emoji Место $place: {$row['username']} - {$row['score']} балл";
                $place++;
            }
            
            if ($this->logEnabled || $log) {
                $this->logDebug("Leaderboard Data: " . print_r($leaderboard, true));
            }
            
            return implode("\n", $leaderboard);
        } else {
            return "Нет данных о пользователях.";
        }
    }

    /**
     * Summary of createTournament
     * @param mixed $chatId
     * @param mixed $tournamentName
     * @param mixed $log
     * @return bool
     */
    public function createTournament($chatId, $tournamentName, $log = false) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM gametournaments WHERE chat_id = ? AND status = 'active'");
        $stmt->bind_param("i", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = $row['count'];
        $stmt->close();
    
        if ($count > 3) {
            return false;
        }
    
        $score = $this->getUserScore($chatId);
        if ($score < 1) {
            return false;
        }
    
        $stmt = $this->db->prepare("INSERT INTO gametournaments (chat_id, name) VALUES (?, ?)");
        $stmt->bind_param("is", $chatId, $tournamentName);
        $stmt->execute();
        $stmt->close();
    
        $this->updateUserScore($chatId, $score - 1);
        
        if ($this->logEnabled || $log) {
            $this->logDebug("createTournament chatId: $chatId, tournamentName: $tournamentName");
        }
        
        return true;
    }
    
    /**
 * Summary of getAvailableTournaments
 * @param mixed $chatId
 * @param mixed $log
 * @return string[][]
 */
public function getAvailableTournaments($chatId, $log = false) {
    // Получаем текущий турнир пользователя
    $currentTournamentId = $this->getUserCurrentTournament($chatId);

    // Запрос на получение доступных турниров, исключая те, в которых пользователь участвует
    $query = "SELECT id, name FROM gametournaments 
              WHERE status = 'active' 
              AND id <> ? 
              ORDER BY created_at ASC 
              LIMIT 10";
    $stmt = $this->db->prepare($query);
    $stmt->bind_param("i", $currentTournamentId);
    $stmt->execute();
    $result = $stmt->get_result();

    $tournaments = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tournaments[] = [
                'text' => "Турнир: {$row['name']}",
                'callback_data' => "tournament_{$row['id']}"
            ];
        }
    }

    if ($this->logEnabled || $log) {
        $this->logDebug("getAvailableTournaments: " . print_r($tournaments, true));
    }

    return $tournaments;
}


    /**
     * Summary of setUserState
     * @param mixed $chatId
     * @param mixed $state
     * @param mixed $log
     * @return void
     */
    public function setUserState($chatId, $state, $log = false) {
        $stmt = $this->db->prepare("UPDATE gameusersbot SET state = ? WHERE chat_id = ?");
        $stmt->bind_param("si", $state, $chatId);
        $stmt->execute();
        if ($stmt->error) {
            $this->logDebug("Error updating state: " . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        
        if ($this->logEnabled || $log) {
            $this->logDebug("setUserState chatId: $chatId, state: $state");
        }
    }

    /**
     * Summary of getUserState
     * @param mixed $chatId
     * @param mixed $log
     * @return mixed
     */
    public function getUserState($chatId, $log = false) {
        $stmt = $this->db->prepare("SELECT state FROM gameusersbot WHERE chat_id = ?");
        $stmt->bind_param("i", $chatId);
        $stmt->execute();
        $stmt->bind_result($state);
        $stmt->fetch();
        $stmt->close();
        
        if ($this->logEnabled || $log) {
            $this->logDebug("getUserState chatId: $chatId, state: $state");
        }
        
        return $state;
    }
    
    /**
     * Summary of setUserCurrentTournament
     * @param mixed $chatId
     * @param mixed $tournamentId
     * @param mixed $log
     * @return void
     */
    public function setUserCurrentTournament($chatId, $tournamentId, $log = false) {
        $stmt = $this->db->prepare("UPDATE gameusersbot SET current_tournament = ? WHERE chat_id = ?");
        $stmt->bind_param("ii", $tournamentId, $chatId);
        $stmt->execute();
        $stmt->close();
        
        if ($this->logEnabled || $log) {
            $this->logDebug("setUserCurrentTournament chatId: $chatId, tournamentId: $tournamentId");
        }
    }

    /**
     * Summary of getUserCurrentTournament
     * @param mixed $chatId
     * @param mixed $log
     * @return mixed
     */
    public function getUserCurrentTournament($chatId, $log = false) {
        $stmt = $this->db->prepare("SELECT current_tournament FROM gameusersbot WHERE chat_id = ?");
        $stmt->bind_param("i", $chatId);
        $stmt->execute();
        $stmt->bind_result($tournamentId);
        $stmt->fetch();
        $stmt->close();
        
        if ($this->logEnabled || $log) {
            $this->logDebug("getUserCurrentTournament chatId: $chatId, tournamentId: $tournamentId");
        }
        
        return $tournamentId;
    }

    /**
     * Summary of getLastTournamentId
     * @param mixed $chatId
     * @return mixed
     */
    public function getLastTournamentId($chatId) { 
        $stmt = $this->db->prepare("SELECT id FROM gametournaments WHERE chat_id = ? ORDER BY created_at DESC LIMIT 1"); 
        $stmt->bind_param("i", $chatId); 
        $stmt->execute(); 
        $stmt->bind_result($tournamentId); 
        $stmt->fetch(); 
        $stmt->close(); 
        return $tournamentId; 
    }

    /**
     * Summary of createMatch
     * @param mixed $tournamentId
     * @param mixed $player1ChatId
     * @return void
     */
    public function createMatch($tournamentId, $player1ChatId) { 
        $stmt = $this->db->prepare("INSERT INTO gamematches (tournament_id, player1_chat_id, result) VALUES (?, ?, NULL)"); 
        $stmt->bind_param("ii", $tournamentId, $player1ChatId); 
        $stmt->execute(); 
        $stmt->close(); 
    }

    /**
     * Summary of updateMatchResult
     * @param mixed $tournamentId
     * @param mixed $player1ChatId
     * @param mixed $player1Choice
     * @param mixed $player2ChatId
     * @param mixed $player2Choice
     * @return void
     */
    public function updateMatchResult($tournamentId, $player1ChatId = null, $player1Choice = null, $player2ChatId = null, $player2Choice = null) { 
        if ($player1ChatId && $player1Choice) { 
            // Обновление данных первого игрока 
            $stmt = $this->db->prepare(" 
                UPDATE gamematches  
                SET player1_choice = ?  
                WHERE tournament_id = ? AND player1_chat_id = ? 
            "); 
            $stmt->bind_param("sii", $player1Choice, $tournamentId, $player1ChatId); 
        } elseif ($player2ChatId && $player2Choice) { 
            // Получаем выбор первого игрока для определения результата 
            $stmt = $this->db->prepare(" 
                SELECT player1_choice, player1_chat_id  
                FROM gamematches  
                WHERE tournament_id = ? 
            "); 
            $stmt->bind_param("i", $tournamentId); 
            $stmt->execute(); 
            $stmt->bind_result($player1Choice, $player1ChatId); 
            $stmt->fetch(); 
            $stmt->close(); 
     
            if (!$player1Choice) { 
                file_put_contents('logDebug.txt', "Error: player1_choice is null for tournament_id = $tournamentId\n", FILE_APPEND); 
                return; 
            } 
     
            // Определяем результат игры 
            $result = $this->determineResult($player1Choice, $player2Choice);
             
            // Обновление данных второго игрока и результата 
            $stmt = $this->db->prepare(" 
                UPDATE gamematches  
                SET player2_chat_id = ?, player2_choice = ?, result = ?  
                WHERE tournament_id = ? AND player1_chat_id = ? 
            "); 
            $stmt->bind_param("issii", $player2ChatId, $player2Choice, $result, $tournamentId, $player1ChatId); 
             
            switch ($result) { 
                case 'draw': 
                    // Никто из игроков не получает балов, игроку player1ChatId необходимо вернуть 1 балл 
                    $this->updateUserScore($player1ChatId, $this->getUserScore($player1ChatId) + 1); 
                    break; 
                case 'Player 1 wins': 
                    // Игроку player1ChatId добавить 1 балов за создание турнира и 2 бала за выигрыш 
                    $this->updateUserScore($player1ChatId, $this->getUserScore($player1ChatId) + 3); 
                    break; 
                case 'Player 2 wins': 
                    // Игроку player2ChatId добавить 2 бала за выигрыш 
                    $this->updateUserScore($player2ChatId, $this->getUserScore($player2ChatId) + 2); 
                    break; 
            }
            $this->sendResultNotification($tournamentId, $player1ChatId, $player2ChatId, $result); 
            $this->updateTournamentStatus($tournamentId, 'completed');

        } else { 
            file_put_contents('logDebug.txt', "Error: Insufficient data provided for updating match result.\n", FILE_APPEND); 
            return; 
        } 
     
        $stmt->execute();
        if ($stmt->error) { 
            file_put_contents('logDebug.txt', "Error updating match result: " . htmlspecialchars($stmt->error) . "\n", FILE_APPEND); 
        } else { 
            file_put_contents('logDebug.txt', "Match result updated successfully: tournament_id = $tournamentId, player1_chat_id = $player1ChatId, player2_chat_id = $player2ChatId, player1_choice = $player1Choice, player2_choice = $player2Choice, result = $result\n", FILE_APPEND); 
        } 
        $stmt->close(); 
    }

    /**
     * Summary of determineResult
     * @param mixed $player1Choice
     * @param mixed $player2Choice
     * @return string
     */
    private function determineResult($player1Choice, $player2Choice) { 
        // Логика определения победителя 
        if ($player1Choice === $player2Choice) { 
            return 'Ничья'; 
        } 
        if ( 
            ($player1Choice === 'Камень' && $player2Choice === 'Ножницы') || 
            ($player1Choice === 'Ножницы' && $player2Choice === 'Бумага') || 
            ($player1Choice === 'Бумага' && $player2Choice === 'Камень') 
        ) { 
            return "Игрок, который создал игру выиграл"; 
        } 
        return 'Вы выиграли'; 
    } 
 
    /**
     * Summary of sendResultNotification
     * @param mixed $tournamentId
     * @param mixed $player1ChatId
     * @param mixed $player2ChatId
     * @param mixed $result
     * @return void
     */
    private function sendResultNotification($tournamentId, $player1ChatId, $player2ChatId, $result) { 
        $this->sendMessage($player1ChatId, "Результат поединка: $tournamentId: $result"); 
        $this->sendMessage($player2ChatId, "Результат поединка: $tournamentId: $result"); 
    } 
 
    /**
     * Summary of updateTournamentStatus
     * @param mixed $tournamentId
     * @param mixed $status
     * @return bool
     */
    public function updateTournamentStatus($tournamentId, $status) { 
        $stmt = $this->db->prepare("UPDATE gametournaments SET status = ? WHERE id = ?"); 
        $stmt->bind_param("si", $status, $tournamentId); 
        return $stmt->execute(); 
    }

    /**
     * Summary of logDebug
     * @param mixed $message
     * @return void
     */
    private function logDebug($message) {
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
    }

    /**
     * Summary of logError
     * @param mixed $message
     * @return void
     */
    private function logError($message) {
        file_put_contents('error.log', date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
    }
}

