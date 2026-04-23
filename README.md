# Mekari Qontak WhatsApp Chatbot with PHP & Gemini AI

This repository demonstrates how to build and integrate a WhatsApp Chatbot using the Mekari Qontak Open API, utilizing Google's Gemini AI to dynamically generate conversational responses. 

## Features
- **Webhook Receiver:** Captures incoming messages (`receive_message_from_customer`) via Mekari Qontak Webhook.
- **Contextual Memory:** Maintains short-term conversation history for each chat group/room using a local JSON file (`sessions.json`), allowing the AI to understand the ongoing context of the chat.
- **AI Integration (Google Gemini):** Uses Gemini 2.5 Flash API with a custom system prompt to provide tailored responses for the domain (e.g., an e-commerce electronics / gaming store).
- **Automated WhatsApp Replies:** Delivers the AI's response straight to the user using Qontak's Open API.
- **Extensive Logging:** Records all inputs, routing outcomes, AI completions, latency, and debug messages into JSON log files (`chat_data.json` and `data.json`).

## Architecture & Sequence Diagram

Below is a detailed sequence diagram highlighting the workflow from the moment a user sends a message to when they receive an AI response.

![Sequence Diagram](http://code.iqbalmind.id/uploads/sequence_diagram.png)

```mermaid
sequenceDiagram
    autonumber
    actor User as WhatsApp User
    participant Qontak as Mekari Qontak
    participant Webhook as Webhook server (PHP)
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

## Setup Instructions

1. **Clone the Repository**
   ```bash
   git clone https://github.com/yourusername/your-repo-name.git
   cd your-repo-name
   ```

2. **Configuration Settings**
   In `api/webhook.php`, you need to set up your specific API keys:
   - Identify `$geminiApiKey = 'YOUR_GEMINI_API_KEY';` and replace it with your active Google Gemini Token.
   - Identify `$qontakToken = 'YOUR_QONTAK_TOKEN';` and replace it with your Mekari Qontak API Token.
   - **Important Security Note:** Because this code will be on GitHub, *do not hardcode* your actual tokens into your public repository. We have set `.gitignore` to skip files like `.env`, so you can transition these values to environment variables in your production environment.

3. **Log & Session Files Access**
   Make sure the `api/` directory has write permissions so the script can save `sessions.json`, `data.json`, and `chat_data.json` safely.

4. **Integration with Mekari Qontak**
   Provide the public URL of your `webhook.php` to the Mekari Qontak Webhook Integration settings under "Receive new message". You might use tools like `ngrok` if you are testing locally.
