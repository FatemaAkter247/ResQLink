(function () {
    const cfg = window.RESQLINK_CHATBOT || {};
    const chatWindow = document.getElementById('chatWindow');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');

    function scrollBottom() {
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    function addMessage(type, text) {
        const row = document.createElement('div');
        row.className = 'message ' + type;

        if (type === 'bot') {
            const icon = document.createElement('div');
            icon.className = 'message-icon';
            icon.innerHTML = '<i class="fa-solid fa-robot"></i>';
            row.appendChild(icon);
        }

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.textContent = text;
        row.appendChild(bubble);

        chatWindow.appendChild(row);
        scrollBottom();

        return row;
    }

    function addTyping() {
        const row = document.createElement('div');
        row.className = 'message bot';
        row.id = 'typingMessage';

        const icon = document.createElement('div');
        icon.className = 'message-icon';
        icon.innerHTML = '<i class="fa-solid fa-robot"></i>';

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.innerHTML = '<span class="typing-dots"><span></span><span></span><span></span></span>';

        row.appendChild(icon);
        row.appendChild(bubble);

        chatWindow.appendChild(row);
        scrollBottom();
    }

    function removeTyping() {
        const typing = document.getElementById('typingMessage');

        if (typing) {
            typing.remove();
        }
    }

    async function sendMessage(message) {
        const clean = String(message || '').trim();

        if (!clean) {
            return;
        }

        addMessage('user', clean);

        input.value = '';
        sendBtn.disabled = true;

        addTyping();

        try {
            const response = await fetch(cfg.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message: clean,
                    csrf: cfg.csrf
                })
            });

            const data = await response.json();

            removeTyping();

            if (data && data.reply) {
                addMessage('bot', data.reply);
            } else {
                addMessage('bot', 'Sorry, I could not generate a reply. Please try again.');
            }
        } catch (error) {
            removeTyping();
            addMessage('bot', 'Connection error. Check your local server, internet, and API key.');
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        sendMessage(input.value);
    });

    document.querySelectorAll('.quick-buttons button').forEach(function (button) {
        button.addEventListener('click', function () {
            sendMessage(button.getAttribute('data-msg'));
        });
    });

    input.focus();
    scrollBottom();
})();