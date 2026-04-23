<?php

// Catat waktu mulai seakurat mungkin (dalam mikrodetik)
$startTime = microtime(true);

// Set Zona Waktu ke WIB (GMT+7)
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data  = json_decode($input, true);

// --- LOG AWAL: Catat SEMUA request masuk ke chat_data.json (sebelum validasi) ---
$receiveLog = json_encode([
    "time"       => date('Y-m-d H:i:s'),
    "stage"      => "incoming_request",
    "status"     => $data ? "json_parsed_ok" : "json_parse_failed",
    "raw_input"  => $input,
    "parsed"     => $data,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
file_put_contents('chat_data.json', $receiveLog . PHP_EOL . "---" . PHP_EOL, FILE_APPEND | LOCK_EX);

// Jika body bukan JSON valid, tolak dan catat alasannya
if (!$data) {
    http_response_code(400);
    $errLog = json_encode([
        "time"    => date('Y-m-d H:i:s'),
        "stage"   => "validation_failed",
        "reason"  => "Body bukan JSON valid atau kosong",
        "raw"     => $input,
    ], JSON_UNESCAPED_UNICODE);
    file_put_contents('chat_data.json', $errLog . PHP_EOL . "---" . PHP_EOL, FILE_APPEND | LOCK_EX);
    echo json_encode(["status" => "error", "message" => "Invalid or empty JSON body"]);
    exit;
}

// Ambil field-field dari payload
$dataEvent   = $data['data_event']      ?? '';
$roomStatus  = $data['room']['status']  ?? '';
$roomId      = $data['room']['id']      ?? '';
$userText    = $data['text']            ?? '';
$senderName  = $data['sender']['name']  ?? 'Unknown';

// Variabel tracking
$geminiTimeMs  = 0;
$qontakTimeMs  = 0;
$totalTimeMs   = 0;
$geminiErrorMsg = null;
$aiText        = '';

// --- LOG KONDISI ROUTING ---
$shouldProcess = (
    $dataEvent  === 'receive_message_from_customer' &&
    $roomStatus === 'unassigned' &&
    !empty($roomId)
);

$routeLog = json_encode([
    "time"                => date('Y-m-d H:i:s'),
    "stage"               => "routing_check",
    "data_event"          => $dataEvent,
    "room_status"         => $roomStatus,
    "room_id"             => $roomId,
    "sender"              => $senderName,
    "user_text"           => $userText,
    "will_process"        => $shouldProcess,
    "skip_reason"         => !$shouldProcess ? [
        "event_match"   => ($dataEvent  === 'receive_message_from_customer'),
        "status_match"  => ($roomStatus === 'unassigned'),
        "room_id_valid" => !empty($roomId),
    ] : null,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
file_put_contents('chat_data.json', $routeLog . PHP_EOL . "---" . PHP_EOL, FILE_APPEND | LOCK_EX);


if ($shouldProcess) {

    // =========================================================
    // 1. SISTEM MEMORY / HISTORY (sessions.json)
    // =========================================================
    $sessionFile = 'sessions.json';
    $sessions    = [];

    if (file_exists($sessionFile)) {
        $sessionData = file_get_contents($sessionFile);
        $sessions    = json_decode($sessionData, true) ?: [];
    }

    $history   = $sessions[$roomId] ?? [];
    $history[] = [
        "role"  => "user",
        "parts" => [["text" => $userText]],
    ];

    // Batasi history ke 10 pesan terakhir
    if (count($history) > 10) {
        $history = array_slice($history, -10);
    }

    // Log history yang akan dikirim ke Gemini
    $historyLog = json_encode([
        "time"            => date('Y-m-d H:i:s'),
        "stage"           => "history_loaded",
        "room_id"         => $roomId,
        "history_count"   => count($history),
        "history_preview" => $history,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents('chat_data.json', $historyLog . PHP_EOL . "---" . PHP_EOL, FILE_APPEND | LOCK_EX);


    // =========================================================
    // 2. HIT API GEMINI
    // =========================================================
    $geminiStart  = microtime(true);
    // Masukkan Gemini API Key Anda. Sebaiknya menggunakan environment variable atau file config yang diignore git.
    $geminiApiKey = 'YOUR_GEMINI_API_KEY';
    $geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent";
    $geminiPayload = json_encode([
        "contents" => $history,
        "systemInstruction" => [
            "parts" => [[
                "text" => "Kamu adalah asisten chatbot dari QBLStore. QBLStore menjual Game Key (Steam, Epic, dll), Peripheral (Mouse, Keyboard, Audio), dan Konsol. 
Tone bicaramu: Friendly, Gaming banget, Gen Z, santai, tapi tetap informatif. 
Gunakan slang: 'Gaskeun', 'GGWP', 'Sikat', 'No Debat', 'Mantul'. 

ATURAN FORMATTING TEKS (SANGAT PENTING):
Karena pesan ini dikirim via WhatsApp, kamu DILARANG KERAS menggunakan Markdown standar. 
- Untuk huruf TEBAL, WAJIB gunakan HANYA SATU BINTANG: *teks* (Jangan pernah gunakan bintang dua).
- Untuk huruf MIRING, WAJIB gunakan UNDERSCORE: _teks_.
- Jangan gunakan tanda pagar (#) untuk judul.

Berikut adalah database harga QBLStore saat ini:

[KATEGORI: GAME KEY & VOUCHER]
- Steam Wallet IDR 100k: Rp 105.000
- Steam Wallet IDR 250k: Rp 260.000
- Valorant Points (1000 VP): Rp 150.000
- Game Key - GTA V (Premium Edition): Rp 150.000
- Game Key - Cyberpunk 2077 (Steam): Rp 450.000

[KATEGORI: PERIPHERAL]
- Mouse - Razer Viper Mini: Rp 350.000
- Keyboard - Rexus Daixa Mechanical: Rp 350.000
- Headset - HyperX Cloud II: Rp 1.100.000

[KATEGORI: KONSOL & HANDHELD]
- PS5 Slim Disc Edition: Rp 8.500.000
- Steam Deck OLED 512GB: Rp 10.500.000

Jika user bilang setuju/mau beli (misal: 'sikat', 'gas', 'boleh'), ingat kembali item apa yang baru saja kalian diskusikan, lalu arahkan user untuk menyelesaikan pembayaran (kasih nomor rekening bank/e-wallet fiktif untuk testing)."
            ]]
        ],
    ]);

    $chGemini = curl_init($geminiUrl);
    curl_setopt($chGemini, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chGemini, CURLOPT_POST, true);
    curl_setopt($chGemini, CURLOPT_POSTFIELDS, $geminiPayload);
    curl_setopt($chGemini, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-goog-api-key: ' . $geminiApiKey,
    ]);
    $geminiResponse    = curl_exec($chGemini);
    $geminiCurlError   = curl_error($chGemini);
    $geminiHttpCode    = curl_getinfo($chGemini, CURLINFO_HTTP_CODE);
    curl_close($chGemini);

    $geminiTimeMs = round((microtime(true) - $geminiStart) * 1000);
    $geminiData   = json_decode($geminiResponse, true);

    // Cek error dari Google Gemini
    if (isset($geminiData['error'])) {
        $geminiErrorMsg = $geminiData['error']['message'] ?? json_encode($geminiData['error']);
    }

    $aiText = $geminiData['candidates'][0]['content']['parts'][0]['text']
        ?? 'Waduh bro, lagi ada lag nih di sistem. Coba bentar lagi ya!';

    // Log hasil Gemini
    $geminiLog = json_encode([
        "time"            => date('Y-m-d H:i:s'),
        "stage"           => "gemini_response",
        "http_code"       => $geminiHttpCode,
        "curl_error"      => $geminiCurlError ?: null,
        "gemini_error"    => $geminiErrorMsg,
        "ai_text"         => $aiText,
        "latency_ms"      => $geminiTimeMs,
        "raw_response"    => $geminiData,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents('chat_data.json', $geminiLog . PHP_EOL . "---" . PHP_EOL, FILE_APPEND | LOCK_EX);


    // =========================================================
    // 3. SIMPAN MEMORY (hanya jika tidak ada error)
    // =========================================================
    if (!isset($geminiData['error'])) {
        $history[]        = ["role" => "model", "parts" => [["text" => $aiText]]];
        $sessions[$roomId] = $history;
        file_put_contents($sessionFile, json_encode($sessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }


    // =========================================================
    // 4. HIT API QONTAK
    // =========================================================
    $qontakStart   = microtime(true);
    // Masukkan Token Qontak Anda. Sebaiknya menggunakan environment variable atau file config yang diignore git.
    $qontakToken   = 'YOUR_QONTAK_TOKEN'; 
    $qontakUrl     = 'https://service-chat.qontak.com/api/open/v1/messages/whatsapp/bot';
    $qontakPayload = json_encode([
        "room_id" => $roomId,
        "type"    => "text",
        "text"    => $aiText,
    ]);

    $chQontak = curl_init($qontakUrl);
    curl_setopt($chQontak, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chQontak, CURLOPT_POST, true);
    curl_setopt($chQontak, CURLOPT_POSTFIELDS, $qontakPayload);
    curl_setopt($chQontak, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $qontakToken,
        'Content-Type: application/json',
    ]);
    $qontakResponse  = curl_exec($chQontak);
    $qontakCurlError = curl_error($chQontak);
    $qontakHttpCode  = curl_getinfo($chQontak, CURLINFO_HTTP_CODE);
    curl_close($chQontak);

    $qontakTimeMs = round((microtime(true) - $qontakStart) * 1000);

    // Log hasil Qontak
    $qontakLog = json_encode([
        "time"         => date('Y-m-d H:i:s'),
        "stage"        => "qontak_response",
        "http_code"    => $qontakHttpCode,
        "curl_error"   => $qontakCurlError ?: null,
        "raw_response" => json_decode($qontakResponse, true),
        "latency_ms"   => $qontakTimeMs,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents('chat_data.json', $qontakLog . PHP_EOL . "---" . PHP_EOL, FILE_APPEND | LOCK_EX);
}


// =========================================================
// 5. TOTAL LATENCY & LOG RINGKASAN KE data.json (existing)
// =========================================================
$totalTimeMs = round((microtime(true) - $startTime) * 1000);

$logData = json_encode([
    "time"        => date('Y-m-d H:i:s'),
    "event"       => $dataEvent,
    "room_status" => $roomStatus,
    "sender"      => $senderName,
    "text"        => $userText,
    "ai_response" => $aiText,
    "ai_error"    => $geminiErrorMsg,
    "latency"     => [
        "gemini_ms" => $geminiTimeMs,
        "qontak_ms" => $qontakTimeMs,
        "total_ms"  => $totalTimeMs,
    ],
], JSON_UNESCAPED_UNICODE);
file_put_contents('data.json', $logData . PHP_EOL, FILE_APPEND | LOCK_EX);

// Log penutup ke chat_data.json
$finalLog = json_encode([
    "time"       => date('Y-m-d H:i:s'),
    "stage"      => "finished",
    "total_ms"   => $totalTimeMs,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
file_put_contents('chat_data.json', $finalLog . PHP_EOL . "===========================" . PHP_EOL, FILE_APPEND | LOCK_EX);

http_response_code(200);
echo json_encode(["status" => "success", "total_execution_ms" => $totalTimeMs]);
?>