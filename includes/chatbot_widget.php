

<!-- Chatbot Widget -->
<div id="chatbotWidget" class="fixed bottom-6 right-6 z-50">
    <!-- Collapsed Chat Button -->
    <button id="chatButton" class="bg-primary text-white rounded-full w-16 h-16 flex items-center justify-center shadow-lg hover:bg-blue-700 transition-all duration-300 focus:outline-none">
        <i id="chatIcon" class="fas fa-comment-dots text-2xl"></i>
    </button>
    
    <!-- Expanded Chat Window -->
    <div id="chatWindow" class="hidden bg-white rounded-xl shadow-lg overflow-hidden transition-all duration-300 w-80 md:w-96 h-[500px] max-h-[80vh] flex flex-col">
        <!-- Chat Header -->
        <div class="bg-primary text-white p-4 flex justify-between items-center">
            <div class="flex items-center">
                <div class="h-8 w-8 rounded-full bg-white bg-opacity-20 flex items-center justify-center mr-3">
                    <i class="fas fa-robot"></i>
                </div>
                <h3 class="font-medium">Property Assistant</h3>
            </div>
            <button id="minimizeChat" class="text-white hover:text-gray-200 focus:outline-none">
                <i class="fas fa-minus"></i>
            </button>
        </div>
        
        <!-- Chat Messages -->
        <div id="chatMessages" class="flex-1 overflow-y-auto p-4 space-y-4">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center">
                        <i class="fas fa-robot text-white"></i>
                    </div>
                </div>
                <div class="ml-3 bg-blue-100 rounded-lg py-2 px-4 max-w-[80%]">
                    <p class="text-sm text-gray-800">
                        Hello <?php echo htmlspecialchars($firstName); ?>! I'm your property assistant. How can I help you today?
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Chat Input -->
        <div class="border-t border-gray-200 p-4">
            <form id="chatForm" class="flex items-center">
                <input 
                    type="text" 
                    id="messageInput" 
                    class="flex-1 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" 
                    placeholder="Type your message here..."
                    required
                >
                <button 
                    type="submit" 
                    class="ml-3 bg-blue-500 text-white rounded-lg px-4 py-2 hover:bg-blue-600 transition"
                >
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Chat functionality
document.addEventListener('DOMContentLoaded', function() {
    const chatButton = document.getElementById('chatButton');
    const chatWindow = document.getElementById('chatWindow');
    const minimizeChat = document.getElementById('minimizeChat');
    const chatMessages = document.getElementById('chatMessages');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const chatIcon = document.getElementById('chatIcon');
    
    let conversationId = null;
    let isUnreadMessage = false;
    
    // Toggle chat window
    chatButton.addEventListener('click', function() {
        if (chatWindow.classList.contains('hidden')) {
            // Open chat window
            chatWindow.classList.remove('hidden');
            chatButton.classList.add('hidden');
            
            // Reset unread indicator
            isUnreadMessage = false;
            chatIcon.classList.remove('animate-pulse');
            
            // Scroll to bottom
            scrollToBottom();
            
            // Focus input
            messageInput.focus();
        }
    });
    
    // Minimize chat window
    minimizeChat.addEventListener('click', function() {
        chatWindow.classList.add('hidden');
        chatButton.classList.remove('hidden');
    });
    
// Load conversation history if available
function loadConversationHistory() {
    try {
        fetch('../api/chatbot.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Received conversation history:', data);
                if (data.messages && data.messages.length > 0) {
                    conversationId = data.conversation_id;
                    
                    // Clear welcome message
                    chatMessages.innerHTML = '';
                    
                    // Add messages to chat
                    data.messages.forEach(message => {
                        addMessageToChat(message.text, message.sender === 'bot', message.id);
                    });
                    
                    // Scroll to bottom
                    scrollToBottom();
                }
            })
            .catch(error => {
                console.error('Error loading chat history:', error);
                // Continue with empty chat history - don't let this break the chat functionality
            });
    } catch (error) {
        console.error('Error in loadConversationHistory:', error);
        // Continue with empty chat history
    }
}

    // Load history on page load
    loadConversationHistory();
    
    // Handle form submission
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        if (!message) return;
        
        // Add user message to chat
        addMessageToChat(message, false);
        
        // Clear input
        messageInput.value = '';
        
        // Send message to server
        sendMessage(message);
        
        // Scroll to bottom
        scrollToBottom();
    });
    
    // Send message to server
    function sendMessage(message) {
        // Show typing indicator
        showTypingIndicator();
        
        fetch('../api/chatbot.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: message,
                conversation_id: conversationId
            })
        })
        .then(response => response.json())
        .then(data => {
            // Remove typing indicator
            hideTypingIndicator();
            
            // Update conversation ID if new
            if (data.conversation_id) {
                conversationId = data.conversation_id;
            }
            
            // Add bot response to chat
            addMessageToChat(data.message, true, data.message_id);
            
            // If chat is minimized, show unread indicator
            if (chatWindow.classList.contains('hidden')) {
                isUnreadMessage = true;
                chatIcon.classList.add('animate-pulse');
            }
            
            // Scroll to bottom
            scrollToBottom();
        })
        .catch(error => {
            console.error('Error sending message:', error);
            hideTypingIndicator();
            addMessageToChat('Sorry, there was an error processing your request. Please try again.', true);
            scrollToBottom();
        });
    }
    
    // Add message to chat
    function addMessageToChat(message, isBot, messageId = null) {
        const messageElement = document.createElement('div');
        messageElement.className = 'flex items-start';
        
        if (isBot) {
            messageElement.innerHTML = `
                <div class="flex-shrink-0">
                    <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center">
                        <i class="fas fa-robot text-white"></i>
                    </div>
                </div>
                <div class="ml-3 bg-blue-100 rounded-lg py-2 px-4 max-w-[80%]">
                    <p class="text-sm text-gray-800">${escapeHtml(message)}</p>
                    ${messageId ? `
                    <div class="mt-2 flex items-center justify-end space-x-2">
                        <button class="text-xs text-gray-500 hover:text-green-500" onclick="rateChatbotResponse(${messageId}, true)">
                            <i class="fas fa-thumbs-up"></i> Helpful
                        </button>
                        <button class="text-xs text-gray-500 hover:text-red-500" onclick="rateChatbotResponse(${messageId}, false)">
                            <i class="fas fa-thumbs-down"></i> Not Helpful
                        </button>
                    </div>
                    ` : ''}
                </div>
            `;
        } else {
            messageElement.innerHTML = `
                <div class="ml-auto flex items-start">
                    <div class="bg-gray-200 rounded-lg py-2 px-4 max-w-[80%]">
                        <p class="text-sm text-gray-800">${escapeHtml(message)}</p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                            <i class="fas fa-user text-white"></i>
                        </div>
                    </div>
                </div>
            `;
        }
        
        chatMessages.appendChild(messageElement);
    }
    
    // Show typing indicator
    function showTypingIndicator() {
        const typingElement = document.createElement('div');
        typingElement.id = 'typingIndicator';
        typingElement.className = 'flex items-start';
        typingElement.innerHTML = `
            <div class="flex-shrink-0">
                <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center">
                    <i class="fas fa-robot text-white"></i>
                </div>
            </div>
            <div class="ml-3 bg-blue-100 rounded-lg py-2 px-4">
                <p class="text-sm text-gray-800">
                    <span class="typing-dot">.</span>
                    <span class="typing-dot">.</span>
                    <span class="typing-dot">.</span>
                </p>
            </div>
        `;
        
        chatMessages.appendChild(typingElement);
        scrollToBottom();
    }
    
    // Hide typing indicator
    function hideTypingIndicator() {
        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }
    
    // Scroll to bottom of chat
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});

// Rate chatbot response
function rateChatbotResponse(messageId, helpful) {
    fetch('../api/chatbot.php', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            message_id: messageId,
            helpful: helpful
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show feedback received message
            alert(helpful ? 'Thank you for your positive feedback!' : 'Thank you for your feedback. We\'ll work to improve our responses.');
            
            // Disable the feedback buttons for this message
            const buttons = document.querySelectorAll(`button[onclick="rateChatbotResponse(${messageId}, true)"], button[onclick="rateChatbotResponse(${messageId}, false)"]`);
            buttons.forEach(button => {
                button.disabled = true;
                button.classList.add('opacity-50');
                button.classList.remove('hover:text-green-500', 'hover:text-red-500');
            });
        }
    })
    .catch(error => {
        console.error('Error submitting feedback:', error);
        alert('Sorry, there was an error submitting your feedback.');
    });
}
</script>

<style>
/* Typing indicator animation */
.typing-dot {
    animation: typingAnimation 1.4s infinite;
    display: inline-block;
}

.typing-dot:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typingAnimation {
    0% { opacity: 0.2; }
    20% { opacity: 1; }
    100% { opacity: 0.2; }
}

/* Chat button pulse animation */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.animate-pulse {
    animation: pulse 2s infinite;
}

/* Chat window transition */
#chatWindow {
    transform-origin: bottom right;
    transition: transform 0.3s ease, opacity 0.3s ease;
}

#chatWindow.hidden {
    transform: scale(0.95);
    opacity: 0;
    pointer-events: none;
}

#chatButton {
    transition: transform 0.3s ease, opacity 0.3s ease;
}

#chatButton.hidden {
    transform: scale(0.95);
    opacity: 0;
    pointer-events: none;
}
</style>


