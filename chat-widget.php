<div class="omion-chat-widget-container">
    <button class="omion-chat-widget-button" id="omionChatButton">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
        </svg>
    </button>
    
    <div class="omion-chat-widget-window" id="omionChatWindow">
        <div class="omion-chat-header">
            <h3><?php echo esc_html($options['brand_name']); ?></h3>
            
            <!-- Sound Toggle Button -->
            <button id="omionSoundToggle" title="Toggle sound notifications">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
                </svg>
            </button>
            
            <!-- Dark Mode Toggle Button -->
            <button id="omionDarkModeToggle" title="Toggle dark mode">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>
            
            <button class="omion-chat-close" id="omionChatClose">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        
        <!-- History Controls -->
        <div class="omion-history-controls">
            <button class="omion-history-btn" id="omionNewConversation" title="Start new conversation">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                New Chat
            </button>
            <button class="omion-history-btn" id="omionShowHistory" title="Show conversation history">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
                    <path d="M21 3v5h-5"/>
                    <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
                    <path d="M3 21v-5h5"/>
                </svg>
                History
            </button>
        </div>
        
        <div class="omion-chat-messages" id="omionChatMessages">
            <div class="omion-welcome-message">
                👋 Hello! How can I assist you today?
            </div>
        </div>

        <div class="omion-form-container" style="display: none;">
            <?php 
            if (!empty($options['inquiry_form_shortcode'])) {
                echo do_shortcode($options['inquiry_form_shortcode']); 
            }
            ?>
            <button class="omion-return-chat" id="omionReturnChat">Return to Chat</button>
        </div>

   

    

        <div class="omion-chat-input-container">
            <div class="omion-chat-input-wrapper">
                <input type="text" class="omion-chat-input" id="omionChatInput" placeholder="Type your message...">
                <button class="omion-chat-send" id="omionSendMessage">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Auto-save Indicator -->
    <div class="omion-autosave-indicator" id="omionAutosaveIndicator">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
            <path d="M21 3v5h-5"/>
        </svg>
        Saved
    </div>
</div>