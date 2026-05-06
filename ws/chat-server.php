<?php
/**
 * WebSocket server (Ratchet).
 *
 * Cara jalankan (terminal terpisah):
 *   php ws/chat-server.php
 *
 * Dia akan listen di port WS dari config.php (default 8080),
 * bind ke 0.0.0.0 supaya HP/laptop lain di WiFi yang sama bisa konek.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../helpers/room_playing.php';

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;

$config = require __DIR__ . '/../config.php';

class ChatServer implements MessageComponentInterface
{
    private const VALID_ROOMS = [1, 2, 3, 4, 5];

    public SplObjectStorage $clients;
    private array $userMap = [];
    private array $connectionMeta = [];
    private array $pendingOwnerDisconnect = [];
    /** Map roomId => bool: apakah room sedang main game. */
    private array $roomPlaying = [];
    /**
     * Map user_id => ['nama' => string, 'foto' => string, 'context' => string,
     *                 'room_id' => int, 'conns' => int]
     * Kita hitung jumlah koneksi per user (bisa multi-tab) agar user hanya
     * dianggap offline saat semua tab-nya tertutup.
     */
    private array $onlineUsers = [];
    private mysqli $db;

    public function __construct(array $dbCfg)
    {
        $this->clients = new SplObjectStorage();
        $this->db = mysqli_connect($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
        if (!$this->db) {
            throw new RuntimeException('WS DB connect failed: ' . mysqli_connect_error());
        }
        mysqli_set_charset($this->db, 'utf8mb4');
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        echo "[+] Connect #{$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        if (!is_array($data)) return;

        if (isset($data['user_id'])) {
            $this->userMap[$from->resourceId] = (int)$data['user_id'];
        }

        if (($data['type'] ?? '') === 'identify_connection' && isset($data['user_id'])) {
            $uid = (int)$data['user_id'];
            $this->connectionMeta[$from->resourceId] = [
                'context' => (string)($data['context'] ?? 'unknown'),
                'role'    => $data['role'] ?? null,
                'room_id' => (int)($data['room_id'] ?? 0),
                'user_id' => $uid,
            ];

            // ===== PRESENCE: daftar user online =====
            if ($uid > 0) {
                if (!isset($this->onlineUsers[$uid])) {
                    $this->onlineUsers[$uid] = [
                        'id'      => $uid,
                        'nama'    => (string)($data['nama'] ?? ('User#' . $uid)),
                        'foto'    => (string)($data['foto'] ?? 'img/default.png'),
                        'context' => (string)($data['context'] ?? 'unknown'),
                        'room_id' => (int)($data['room_id'] ?? 0),
                        'conns'   => 1,
                    ];
                } else {
                    $this->onlineUsers[$uid]['conns']++;
                    // refresh metadata terbaru (mis. user pindah dari lobby ke room)
                    if (!empty($data['nama'])) $this->onlineUsers[$uid]['nama'] = (string)$data['nama'];
                    if (!empty($data['foto'])) $this->onlineUsers[$uid]['foto'] = (string)$data['foto'];
                    $this->onlineUsers[$uid]['context'] = (string)($data['context'] ?? $this->onlineUsers[$uid]['context']);
                    $this->onlineUsers[$uid]['room_id'] = (int)($data['room_id'] ?? $this->onlineUsers[$uid]['room_id']);
                }
                $this->broadcastPresence();
            }

            if (($data['role'] ?? '') === 'owner' && (int)($data['room_id'] ?? 0) > 0) {
                $key = $uid . ':' . (int)$data['room_id'];
                if (isset($this->pendingOwnerDisconnect[$key])) {
                    Loop::cancelTimer($this->pendingOwnerDisconnect[$key]);
                    unset($this->pendingOwnerDisconnect[$key]);
                }
            }

            // Kirim snapshot presence langsung ke klien yang baru identify
            $from->send($this->presencePayload());
        }

        if (($data['type'] ?? '') === 'request_presence') {
            $from->send($this->presencePayload());
            return;
        }

        // ===== Tracking status "room sedang main" =====
        $msgType = (string)($data['type'] ?? '');
        if ($msgType === 'game_started') {
            $rid = (int)($data['room_id'] ?? 0);
            if (in_array($rid, self::VALID_ROOMS, true)) {
                $this->roomPlaying[$rid] = true;
                room_playing_set($rid);
            }
        } elseif ($msgType === 'game_ended' || $msgType === 'game_reset') {
            $rid = (int)($data['room_id'] ?? 0);
            if (in_array($rid, self::VALID_ROOMS, true)) {
                unset($this->roomPlaying[$rid]);
                room_playing_clear($rid);
            }
        }

        // ===== Tolak permintaan izin masuk saat owner sedang main =====
        if ($msgType === 'minta_izin_masuk') {
            $rid = (int)($data['room_id'] ?? 0);
            if ($rid > 0 && !empty($this->roomPlaying[$rid])) {
                $reply = json_encode([
                    'type'    => 'room_busy_playing',
                    'room_id' => $rid,
                    'nama'    => (string)($data['nama'] ?? ''),
                ]);
                if (is_string($reply)) $from->send($reply);
                return; // jangan broadcast — owner tidak perlu diganggu
            }
        }

        if (!empty($data['msg'])) {
            $userId = (int)($data['user_id'] ?? 0);
            $isi    = substr(trim((string)$data['msg']), 0, 1000);
            $target = (string)($data['target_room'] ?? 'lobby');

            if ($userId > 0 && $isi !== '') {
                if ($target === 'room') {
                    $roomId = (int)($data['room_id'] ?? 0);
                    if (in_array($roomId, self::VALID_ROOMS, true)) {
                        $stmt = mysqli_prepare($this->db,
                            "INSERT INTO pesan_room (room_id, user_id, isi_pesan) VALUES (?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "iis", $roomId, $userId, $isi);
                        mysqli_stmt_execute($stmt);
                    }
                } else {
                    $stmt = mysqli_prepare($this->db,
                        "INSERT INTO pesan (user_id, isi_pesan) VALUES (?, ?)");
                    mysqli_stmt_bind_param($stmt, "is", $userId, $isi);
                    mysqli_stmt_execute($stmt);
                }
            }
        }

        foreach ($this->clients as $client) $client->send($msg);
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $rid    = $conn->resourceId;
        $meta   = $this->connectionMeta[$rid] ?? null;
        $userId = $this->userMap[$rid] ?? 0;

        if (is_array($meta)
            && $meta['context'] === 'room'
            && $meta['role'] === 'owner'
            && $meta['room_id'] > 0
            && $userId > 0
        ) {
            $roomId = $meta['room_id'];
            $key = $userId . ':' . $roomId;
            if (!isset($this->pendingOwnerDisconnect[$key])) {
                $this->pendingOwnerDisconnect[$key] = Loop::addTimer(2.0, function () use ($roomId, $userId, $key) {
                    unset($this->pendingOwnerDisconnect[$key]);
                    // Reset status "sedang main" karena owner sudah lepas room.
                    unset($this->roomPlaying[$roomId]);
                    room_playing_clear($roomId);
                    $stmt = mysqli_prepare($this->db,
                        "UPDATE room_status SET is_occupied=0, owner_id=NULL WHERE id=? AND owner_id=?");
                    if ($stmt) { mysqli_stmt_bind_param($stmt, "ii", $roomId, $userId); mysqli_stmt_execute($stmt); }

                    $stmt2 = mysqli_prepare($this->db,
                        "UPDATE users SET presence_status='lobby', presence_room_id=NULL,
                         presence_last_seen=NOW() WHERE id=?");
                    if ($stmt2) { mysqli_stmt_bind_param($stmt2, "i", $userId); mysqli_stmt_execute($stmt2); }

                    $payload = json_encode(['type' => 'room_vacant', 'room_id' => $roomId]);
                    if (is_string($payload)) {
                        foreach ($this->clients as $client) $client->send($payload);
                    }
                });
            }
        }

        // ===== PRESENCE: kurangi counter, hapus jika 0 =====
        if ($userId > 0 && isset($this->onlineUsers[$userId])) {
            $this->onlineUsers[$userId]['conns']--;
            if ($this->onlineUsers[$userId]['conns'] <= 0) {
                unset($this->onlineUsers[$userId]);
            }
            $this->broadcastPresence();
        }

        $this->clients->detach($conn);
        unset($this->userMap[$rid], $this->connectionMeta[$rid]);
        echo "[-] Close #{$rid}\n";
    }

    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        echo "[!] Error: {$e->getMessage()}\n";
        $conn->close();
    }

    private function presencePayload(): string
    {
        $users = array_values(array_map(function ($u) {
            return [
                'id'      => $u['id'],
                'nama'    => $u['nama'],
                'foto'    => $u['foto'],
                'context' => $u['context'],
                'room_id' => $u['room_id'],
            ];
        }, $this->onlineUsers));

        usort($users, fn($a, $b) => strcasecmp($a['nama'], $b['nama']));

        return (string)json_encode([
            'type'  => 'presence',
            'count' => count($users),
            'users' => $users,
        ]);
    }

    private function broadcastPresence(): void
    {
        $payload = $this->presencePayload();
        foreach ($this->clients as $client) $client->send($payload);
    }
}

$server = IoServer::factory(
    new HttpServer(new WsServer(new ChatServer($config['db']))),
    (int)$config['ws_port'],
    '0.0.0.0'
);

echo "WS server listening on 0.0.0.0:{$config['ws_port']}\n";
$server->run();
