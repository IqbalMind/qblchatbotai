<div align="center">

<!-- Banner -->
<img src="https://capsule-render.vercel.app/api?type=waving&color=0:25D366,100:128C7E&height=200&section=header&text=Qontak%20WhatsApp%20Chatbot&fontSize=40&fontColor=ffffff&fontAlignY=35&desc=PHP%20%2B%20Google%20Gemini%20AI&descAlignY=55&descSize=20" width="100%"/>

# 🤖 Mekari Qontak WhatsApp Chatbot

### Powered by PHP & Google Gemini AI

[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Gemini AI](https://img.shields.io/badge/Gemini-2.5%20Flash-4285F4?style=for-the-badge&logo=google&logoColor=white)](https://ai.google.dev/)
[![WhatsApp](https://img.shields.io/badge/WhatsApp-API-25D366?style=for-the-badge&logo=whatsapp&logoColor=white)](https://whatsapp.com)
[![Mekari](https://img.shields.io/badge/Mekari-Qontak-FF6B35?style=for-the-badge&logo=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAMAAABEpIrGAAAALVBMVEVHcEwpef8rfv8pev8qfP8qe/8pev8qe/8pev8qe/8pev8pev8qe/8pev8pef8FFd0qAAAADnRSTlMA9Q3XMFjoeY9xpalMqnCpGC4AAACiSURBVDiNzVLLEsQgCBPwUduu//+5nZYRFOzM3nZzgyAJSAh/hnQQAH3SC701wbagI7QR0fJnMzA60fK2h/QHVVr5qxgC1oVTzmTkCLNtkTiBPUbrc3/ioi9Y5ZCY5ga9BZkZ3FTwfYGT4IxKOJO8Wd2lHfO2WcoYzotaoK+6vJboF4Fg9LT67tm1P5jb0yRiTs4XTEfLqM5q2imLx+z5X+ECpf8QUhVXcRYAAAAASUVORK5CYII=&logoColor=white)](https://qontak.com)
[![License](https://img.shields.io/badge/License-MIT-yellow?style=for-the-badge)](LICENSE)

<br/>

> A production-ready WhatsApp chatbot that combines **Mekari Qontak Open API** with **Google Gemini AI** to deliver intelligent, context-aware automated responses — built entirely in PHP.

<br/>

</div>

---

## 📋 Table of Contents

- [✨ Features](#-features)
- [🏗️ Architecture](#️-architecture)
- [🚀 Setup Instructions](#-setup-instructions)
- [⚙️ Configuration](#️-configuration)
- [📁 File Structure](#-file-structure)
- [🔍 Logging](#-logging)
- [🌐 Going Public](#-going-public)
- [🤝 Contributing](#-contributing)

---

## ✨ Features

<div align="center">

| Feature | Description |
|---|---|
| 📥 **Webhook Receiver** | Captures incoming messages via `receive_message_from_customer` event |
| 🧠 **Contextual Memory** | Maintains short-term conversation history per room using `sessions.json` |
| 🤖 **Gemini AI Integration** | Uses Gemini 2.5 Flash with a custom domain-specific system prompt |
| 📤 **Automated Replies** | Delivers AI responses directly to users via Qontak Open API |
| 📊 **Extensive Logging** | Logs inputs, routing, AI completions, latency & debug info to JSON files |

</div>

---

## 🏗️ Architecture

Below is a detailed sequence diagram highlighting the complete workflow — from the moment a user sends a WhatsApp message to when they receive an AI-generated response.

```mermaid
sequenceDiagram
    autonumber
    actor User as WhatsApp User
    participant Qontak as Mekari Qontak
    participant Webhook as Webhook Server (PHP)
    participant Gemini as Google Gemini API

    User->>Qontak: Send "Halo / Tanya harga"
    Qontak->>Webhook: POST /api/webhook.php (Event: receive_message_from_customer)

    rect rgb(240, 248, 255)
        Note over Webhook: Incoming Request Validation & Routing
        Webhook->>Webhook: Parse Payload JSON
        Webhook->>Webhook: Check Room Status (unassigned)
        Webhook->>Webhook: Load History from sessions.json
    end

    Webhook->>Gemini: POST /models/gemini-2.5-flash:generateContent (with history & prompt)
    Gemini-->>Webhook: Return AI Response Text (JSON)

    Webhook->>Webhook: Append AI response to sessions.json

    Webhook->>Qontak: POST /api/open/v1/messages/whatsapp/bot
    Qontak-->>Webhook: HTTP 200 OK (Message sent)

    Webhook-->>Qontak: Return 200 OK Response
    Qontak-->>User: Bot message delivered to WhatsApp
```

---

## 🚀 Setup Instructions

### 1. Clone the Repository

```bash
git clone https://github.com/IqbalMind/qblchatbotai.git
cd qblchatbotai
```

### 2. Configure API Keys

Open `api/webhook.php` and replace the placeholder values with your actual credentials:

```php
// Replace with your Google Gemini API Key
$geminiApiKey = 'YOUR_GEMINI_API_KEY';

// Replace with your Mekari Qontak API Token
$qontakToken  = 'YOUR_QONTAK_TOKEN';
```

> 🔑 **Get your keys:**
> - [Google Gemini API Key →](https://ai.google.dev/)
> - [Mekari Qontak API Token →](https://qontak.com)

### 3. Set Directory Permissions

Ensure the `api/` directory has write permissions so the webhook script can create and update the session and log files:

```bash
chmod 755 api/
```

The following files will be auto-created on first run:

```
api/
├── sessions.json    # Conversation history per room
├── chat_data.json   # Raw chat logs
└── data.json        # Debug & routing logs
```

### 4. Integrate with Mekari Qontak

1. Log in to your **Mekari Qontak** dashboard.
2. Navigate to **Settings → Webhook Integration**.
3. Under **"Receive new message"**, provide the public URL to your `webhook.php`:

```
https://yourdomain.com/api/webhook.php
```

> ⚠️ **Important:** Qontak requires a **publicly accessible HTTPS URL**. For local development, use a tunneling tool:

<div align="center">

| Tool | Command |
|---|---|
| **Ngrok** | `ngrok http 80` |
| **Cloudflare Tunnel** | `cloudflared tunnel --url http://localhost:80` |

</div>

---

## ⚙️ Configuration

| Variable | File | Description |
|---|---|---|
| `$geminiApiKey` | `api/webhook.php` | Your Google Gemini API key |
| `$qontakToken` | `api/webhook.php` | Your Mekari Qontak Bearer token |
| System Prompt | `api/webhook.php` | Customize the AI persona & domain |

---

## 📁 File Structure

```
qblchatbotai/
│
├── api/
│   ├── webhook.php          # 🎯 Main webhook handler & AI logic
│   ├── sessions.json        # 💬 Per-room conversation memory
│   ├── chat_data.json       # 📋 Chat log output
│   └── data.json            # 🐛 Debug & routing log
│
└── README.md
```

---

## 🔍 Logging

The system writes structured JSON logs to help with debugging and monitoring:

| File | Contents |
|---|---|
| `sessions.json` | Short-term conversation history keyed by room/group ID |
| `chat_data.json` | Full message payload, AI response, and timestamps |
| `data.json` | Routing decisions, latency metrics, and debug events |

---

## 🌐 Going Public

For production deployments, make sure you have:

- [x] A **public domain** with **HTTPS/SSL** enabled
- [x] Proper **write permissions** on the `api/` directory
- [x] Webhook URL registered in the Mekari Qontak dashboard
- [x] API keys stored securely (consider using environment variables)

---

## 🤝 Contributing

Contributions are welcome! Feel free to open an issue or submit a pull request.

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m 'Add some feature'`
4. Push to the branch: `git push origin feature/my-feature`
5. Open a Pull Request

---

<div align="center">

<img src="https://capsule-render.vercel.app/api?type=waving&color=0:128C7E,100:25D366&height=100&section=footer" width="100%"/>

Made with ❤️ by [IqbalMind](https://github.com/IqbalMind)

⭐ **Star this repo if you found it helpful!**

</div>