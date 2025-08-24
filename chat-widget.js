jQuery(document).ready(function($) {
    class OmionAIChat {
        constructor() {
            this.messages = [];
            this.isDarkMode = this.loadDarkModePreference();
            this.soundEnabled = this.loadSoundPreference();
            this.chatHistory = this.loadChatHistory();
            this.currentConversationId = this.generateConversationId();
            
            // Lead capture system
            this.leadData = {
                id: this.generateLeadId(),
                email: '',
                name: '',
                phone: '',
                score: 0,
                status: 'visitor',
                source: window.location.href,
                created: Date.now(),
                lastActivity: Date.now(),
                interactions: [],
                interests: [],
                user_agent: navigator.userAgent,
                screen_resolution: `${screen.width}x${screen.height}`,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                referrer: document.referrer,
                conversation_messages: 0,
                captureAttempts: 0,
                captured: false
            };
            
            this.setupEventListeners();
            this.applyCustomStyles();
            this.initializeDarkMode();
            this.loadPersistedConversation();
            this.initializeSoundSystem();
            this.initializeLeadTracking();
        }

        // Lead Capture System
        generateLeadId() {
            return 'lead_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        initializeLeadTracking() {
            // Track page views and time spent
            this.startTime = Date.now();
            this.trackPageInteraction();
            
            // Load existing lead data if available
            const existingLead = localStorage.getItem('omion-lead-data');
            if (existingLead) {
                try {
                    this.leadData = { ...this.leadData, ...JSON.parse(existingLead) };
                } catch (error) {
                    console.error('Error loading lead data:', error);
                }
            }
            
            // Auto-save lead data periodically
            setInterval(() => {
                this.saveLeadData();
            }, 30000);

            // Track interactions
            this.trackUserEngagement();
        }

        trackUserEngagement() {
            // Track scroll behavior
            let scrolled = false;
            $(window).on('scroll', () => {
                if (!scrolled && $(window).scrollTop() > 100) {
                    scrolled = true;
                    this.updateLeadScore(5, 'page_scroll');
                }
            });

            // Track time on page
            setTimeout(() => {
                this.updateLeadScore(10, 'time_spent_30s');
            }, 30000);

            setTimeout(() => {
                this.updateLeadScore(15, 'time_spent_60s');
            }, 60000);

            // Track multiple page views
            if (this.leadData.interactions.length > 0) {
                this.updateLeadScore(8, 'return_visitor');
            }
        }

        trackPageInteraction() {
            this.leadData.interactions.push({
                type: 'page_view',
                url: window.location.href,
                title: document.title,
                timestamp: Date.now()
            });
            this.leadData.lastActivity = Date.now();
        }

        updateLeadScore(points, reason) {
            this.leadData.score += points;
            this.leadData.lastActivity = Date.now();
            
            console.log(`Lead score updated: +${points} for ${reason} (Total: ${this.leadData.score})`);
            
            // Update visual indicator if exists
            this.updateLeadScoreDisplay();
            
            // Check if should trigger capture
            this.checkLeadCaptureConditions();
            
            this.saveLeadData();
        }

        updateLeadScoreDisplay() {
            const badge = $('.omion-lead-score-badge');
            if (badge.length) {
                badge.text(this.leadData.score);
            }
        }

        checkLeadCaptureConditions() {
            const options = omionAIChat.options || {};
            const threshold = parseInt(options.lead_score_threshold) || 30;
            const maxAttempts = parseInt(options.max_capture_attempts) || 3;
            
            // Don't show if already captured or max attempts reached
            if (this.leadData.captured || this.leadData.captureAttempts >= maxAttempts) {
                return;
            }

            // Check conditions
            const shouldCapture = 
                this.leadData.score >= threshold ||
                this.leadData.conversation_messages >= 3 ||
                this.containsBuyingIntent();

            if (shouldCapture) {
                this.showLeadCaptureForm();
            }
        }

        containsBuyingIntent() {
            const buyingKeywords = [
                'price', 'cost', 'buy', 'purchase', 'order', 'quote', 
                'demo', 'trial', 'contact', 'call', 'email', 'info',
                'interested', 'want', 'need', 'help', 'service'
            ];
            
            const recentMessages = this.messages.slice(-3);
            const messageText = recentMessages
                .filter(msg => msg.role === 'user')
                .map(msg => msg.content.toLowerCase())
                .join(' ');
            
            return buyingKeywords.some(keyword => messageText.includes(keyword));
        }

        showLeadCaptureForm() {
            // Prevent multiple shows
            if ($('.omion-email-capture-form').length > 0) {
                return;
            }

            this.leadData.captureAttempts++;
            this.saveLeadData();

            const formHtml = `
                <div class="omion-email-capture-form" id="omionLeadCaptureForm">
                    <div class="omion-lead-score-badge">${this.leadData.score}</div>
                    
                    <div class="omion-capture-header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                        Get Personalized Help
                    </div>
                    
                    <div class="omion-lead-progress">
                        <div class="omion-lead-progress-bar" style="width: ${Math.min(this.leadData.score, 100)}%"></div>
                    </div>
                    
                    <div class="omion-capture-content">
                        <input type="email" 
                               class="omion-capture-input" 
                               id="omionCaptureEmail" 
                               placeholder="Enter your email address" 
                               required>
                        
                        <input type="text" 
                               class="omion-capture-input" 
                               id="omionCaptureName" 
                               placeholder="Your name (optional)">
                        
                        <input type="tel" 
                               class="omion-capture-input" 
                               id="omionCapturePhone" 
                               placeholder="Phone number (optional)">
                        
                        <div class="omion-capture-buttons">
                            <button class="omion-capture-submit" id="omionCaptureSubmit">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                                </svg>
                                Get Help Now
                            </button>
                            <button class="omion-capture-skip" id="omionCaptureSkip">
                                Maybe Later
                            </button>
                        </div>
                        
                        <div class="omion-capture-privacy">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <circle cx="12" cy="16" r="1"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            Your information is secure and will not be shared
                        </div>
                    </div>
                </div>
            `;

            const messagesContainer = $('#omionChatMessages');
            messagesContainer.append(formHtml);
            this.forceScrollBottom();

            // Add event listeners for the form
            this.setupLeadCaptureEvents();
            this.playSound('notification');
        }

        setupLeadCaptureEvents() {
            // Submit handler
            $('#omionCaptureSubmit').on('click', () => {
                this.submitLeadCapture();
            });

            // Skip handler
            $('#omionCaptureSkip').on('click', () => {
                this.skipLeadCapture();
            });

            // Enter key handler
            $('.omion-capture-input').on('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.submitLeadCapture();
                }
            });

            // Form validation
            $('.omion-capture-input').on('input', () => {
                this.validateCaptureForm();
            });
        }

        validateCaptureForm() {
            const email = $('#omionCaptureEmail').val().trim();
            const submitBtn = $('#omionCaptureSubmit');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (email && emailRegex.test(email)) {
                submitBtn.prop('disabled', false).css('opacity', '1');
            } else {
                submitBtn.prop('disabled', true).css('opacity', '0.6');
            }
        }

        async submitLeadCapture() {
            const email = $('#omionCaptureEmail').val().trim();
            const name = $('#omionCaptureName').val().trim();
            const phone = $('#omionCapturePhone').val().trim();

            // Validate email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email || !emailRegex.test(email)) {
                this.showCaptureError('Please enter a valid email address');
                return;
            }

            // Update lead data
            this.leadData.email = email;
            this.leadData.name = name;
            this.leadData.phone = phone;
            this.leadData.status = 'lead';
            this.leadData.captured = true;

            // Show loading state
            const form = $('#omionLeadCaptureForm');
            form.addClass('omion-capture-loading');

            try {
                // Send to server
                const response = await $.ajax({
                    url: omionAIChat.ajaxurl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'omion_capture_lead',
                        lead_data: JSON.stringify(this.leadData)
                    }
                });

                if (response.success) {
                    this.showCaptureSuccess();
                    this.updateLeadScore(50, 'email_captured');
                    this.playSound('notification');
                } else {
                    throw new Error(response.data || 'Failed to capture lead');
                }
            } catch (error) {
                console.error('Lead capture error:', error);
                this.showCaptureError('Sorry, there was an error. Please try again.');
                form.removeClass('omion-capture-loading');
            }

            this.saveLeadData();
        }

        skipLeadCapture() {
            $('#omionLeadCaptureForm').fadeOut(300, function() {
                $(this).remove();
            });
            
            // Add slight delay before next attempt
            this.leadData.lastSkip = Date.now();
            this.saveLeadData();
        }

        showCaptureSuccess() {
            const form = $('#omionLeadCaptureForm');
            form.removeClass('omion-capture-loading').addClass('omion-capture-success');
            
            form.html(`
                <div class="omion-capture-header">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22,4 12,14.01 9,11.01"/>
                    </svg>
                    Thank You!
                </div>
                <div style="text-align: center; padding: 10px; color: #059669; font-weight: 600;">
                    We'll be in touch soon with personalized assistance.
                </div>
            `);

            setTimeout(() => {
                form.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }

        showCaptureError(message) {
            // Remove existing error
            $('.omion-capture-error').remove();
            
            const errorHtml = `<div class="omion-capture-error">${message}</div>`;
            $('.omion-capture-content').prepend(errorHtml);
            
            setTimeout(() => {
                $('.omion-capture-error').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        saveLeadData() {
            try {
                localStorage.setItem('omion-lead-data', JSON.stringify(this.leadData));
            } catch (error) {
                console.error('Error saving lead data:', error);
            }
        }

        // Sound System
        initializeSoundSystem() {
            // Create audio context and sounds
            this.sounds = {
                messageSent: this.createSound('messageSent'),
                messageReceived: this.createSound('messageReceived'),
                notification: this.createSound('notification'),
                typing: this.createSound('typing')
            };
        }

        createSound(type) {
            // Using Web Audio API for better performance
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                
                const soundConfigs = {
                    messageSent: { frequency: 800, duration: 0.1, volume: 0.3 },
                    messageReceived: { frequency: 600, duration: 0.15, volume: 0.4 },
                    notification: { frequency: 1000, duration: 0.2, volume: 0.5 },
                    typing: { frequency: 400, duration: 0.05, volume: 0.2 }
                };

                const config = soundConfigs[type];
                
                return () => {
                    if (!this.soundEnabled) return;
                    
                    try {
                        const oscillator = audioContext.createOscillator();
                        const gainNode = audioContext.createGain();
                        
                        oscillator.connect(gainNode);
                        gainNode.connect(audioContext.destination);
                        
                        oscillator.frequency.setValueAtTime(config.frequency, audioContext.currentTime);
                        oscillator.type = 'sine';
                        
                        gainNode.gain.setValueAtTime(0, audioContext.currentTime);
                        gainNode.gain.linearRampToValueAtTime(config.volume, audioContext.currentTime + 0.01);
                        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + config.duration);
                        
                        oscillator.start(audioContext.currentTime);
                        oscillator.stop(audioContext.currentTime + config.duration);
                    } catch (error) {
                        console.log('Sound play error:', error);
                    }
                };
            } catch (error) {
                console.log('Audio context not available');
                return () => {}; // Return empty function
            }
        }

        playSound(soundType) {
            if (this.sounds[soundType] && this.soundEnabled) {
                this.sounds[soundType]();
            }
        }

        loadSoundPreference() {
            const stored = localStorage.getItem('omion-chat-sound-enabled');
            return stored !== null ? stored === 'true' : true; // Default to enabled
        }

        saveSoundPreference(enabled) {
            localStorage.setItem('omion-chat-sound-enabled', enabled.toString());
        }

        toggleSound() {
            this.soundEnabled = !this.soundEnabled;
            this.saveSoundPreference(this.soundEnabled);
            this.updateSoundToggle();
            
            // Play test sound if enabling
            if (this.soundEnabled) {
                this.playSound('notification');
            }
        }

        updateSoundToggle() {
            const toggle = $('#omionSoundToggle');
            const iconHtml = this.soundEnabled ? 
                `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
                </svg>` :
                `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                    <line x1="23" y1="9" x2="17" y2="15"/>
                    <line x1="17" y1="9" x2="23" y2="15"/>
                </svg>`;
            
            toggle.html(iconHtml);
        }

        // Chat History System
        generateConversationId() {
            return 'conv_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        loadChatHistory() {
            try {
                const history = localStorage.getItem('omion-chat-history');
                return history ? JSON.parse(history) : {};
            } catch (error) {
                console.error('Error loading chat history:', error);
                return {};
            }
        }

        saveChatHistory() {
            try {
                // Keep only last 10 conversations to prevent storage bloat
                const conversations = Object.keys(this.chatHistory);
                if (conversations.length > 10) {
                    conversations
                        .sort((a, b) => this.chatHistory[b].lastUpdated - this.chatHistory[a].lastUpdated)
                        .slice(10)
                        .forEach(convId => delete this.chatHistory[convId]);
                }
                
                localStorage.setItem('omion-chat-history', JSON.stringify(this.chatHistory));
            } catch (error) {
                console.error('Error saving chat history:', error);
            }
        }

        saveCurrentConversation() {
            if (this.messages.length > 0) {
                this.chatHistory[this.currentConversationId] = {
                    messages: this.messages,
                    lastUpdated: Date.now(),
                    title: this.generateConversationTitle()
                };
                this.saveChatHistory();
            }
        }

        generateConversationTitle() {
            const firstUserMessage = this.messages.find(msg => msg.role === 'user');
            if (firstUserMessage) {
                return firstUserMessage.content.substring(0, 30) + (firstUserMessage.content.length > 30 ? '...' : '');
            }
            return 'New Conversation';
        }

        loadPersistedConversation() {
            // Load the most recent conversation if exists
            const conversations = Object.keys(this.chatHistory);
            if (conversations.length > 0) {
                const mostRecent = conversations
                    .sort((a, b) => this.chatHistory[b].lastUpdated - this.chatHistory[a].lastUpdated)[0];
                
                const conversation = this.chatHistory[mostRecent];
                if (conversation && conversation.messages) {
                    this.messages = conversation.messages;
                    this.currentConversationId = mostRecent;
                    this.displayPersistedMessages();
                }
            }
        }

        displayPersistedMessages() {
            const messagesContainer = $('#omionChatMessages');
            
            // Clear welcome message
            messagesContainer.find('.omion-welcome-message').remove();
            
            // Add conversation restored indicator
            const restoreIndicator = $(`
                <div class="omion-conversation-restored">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
                        <path d="M21 3v5h-5"/>
                        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
                        <path d="M3 21v-5h5"/>
                    </svg>
                    Conversation restored
                </div>
            `);
            messagesContainer.append(restoreIndicator);
            
            // Display persisted messages
            this.messages.forEach(message => {
                if (message.role !== 'system') {
                    const role = message.role === 'user' ? 'user' : 'bot';
                    this.displayMessage(role, message.content, false); // false = don't save to history
                }
            });
            
            this.forceScrollBottom();
        }

        startNewConversation() {
            // Save current conversation
            this.saveCurrentConversation();
            
            // Reset for new conversation
            this.messages = [];
            this.currentConversationId = this.generateConversationId();
            
            // Clear messages and show welcome
            const messagesContainer = $('#omionChatMessages');
            messagesContainer.empty();
            messagesContainer.append(`
                <div class="omion-welcome-message">
                    👋 Hello! How can I assist you today?
                </div>
            `);
            
            this.playSound('notification');
        }

        showConversationHistory() {
            const conversations = Object.keys(this.chatHistory)
                .sort((a, b) => this.chatHistory[b].lastUpdated - this.chatHistory[a].lastUpdated);
            
            if (conversations.length === 0) {
                this.addSystemMessage('No previous conversations found.');
                return;
            }
            
            let historyHtml = '<div class="omion-conversation-list">';
            historyHtml += '<div class="omion-history-header">Recent Conversations</div>';
            
            conversations.slice(0, 5).forEach(convId => {
                const conv = this.chatHistory[convId];
                const date = new Date(conv.lastUpdated).toLocaleDateString();
                const isActive = convId === this.currentConversationId;
                
                historyHtml += `
                    <div class="omion-conversation-item ${isActive ? 'active' : ''}" data-conv-id="${convId}">
                        <div class="omion-conv-content">
                            <div class="omion-conv-title">${conv.title}</div>
                            <div class="omion-conv-date">${date}</div>
                        </div>
                        <button class="omion-conv-delete" data-conv-id="${convId}" title="Delete conversation">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3,6 5,6 21,6"/>
                                <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                            </svg>
                        </button>
                    </div>
                `;
            });
            
            historyHtml += '</div>';
            
            const messagesContainer = $('#omionChatMessages');
            messagesContainer.append(historyHtml);
            
            // Handle conversation selection
            $('.omion-conversation-item').on('click', (e) => {
                if (!$(e.target).hasClass('omion-conv-delete')) {
                    const convId = $(e.currentTarget).data('conv-id');
                    this.loadConversation(convId);
                }
            });
            
            // Handle conversation deletion
            $('.omion-conv-delete').on('click', (e) => {
                e.stopPropagation();
                const convId = $(e.currentTarget).data('conv-id');
                this.deleteConversation(convId);
            });
            
            this.forceScrollBottom();
        }

        loadConversation(conversationId) {
            if (this.chatHistory[conversationId]) {
                // Save current conversation
                this.saveCurrentConversation();
                
                // Load selected conversation
                this.messages = this.chatHistory[conversationId].messages;
                this.currentConversationId = conversationId;
                
                // Clear and redisplay
                const messagesContainer = $('#omionChatMessages');
                messagesContainer.empty();
                this.displayPersistedMessages();
                
                this.playSound('notification');
            }
        }

        deleteConversation(conversationId) {
            if (confirm('Are you sure you want to delete this conversation?')) {
                delete this.chatHistory[conversationId];
                this.saveChatHistory();
                
                // If deleting current conversation, start new one
                if (conversationId === this.currentConversationId) {
                    this.startNewConversation();
                } else {
                    // Refresh history display
                    $('.omion-conversation-list').remove();
                    this.showConversationHistory();
                }
                
                this.playSound('notification');
            }
        }

        addSystemMessage(text) {
            const messagesContainer = $('#omionChatMessages');
            const systemMessage = $(`
                <div class="omion-system-message">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    ${text}
                </div>
            `);
            messagesContainer.append(systemMessage);
            this.forceScrollBottom();
        }

        setupEventListeners() {
            // Chat Toggle
            $('#omionChatButton').on('click', () => {
                this.toggleChat();
                setTimeout(() => this.forceScrollBottom(), 300);
            });
            
            $('#omionChatClose').on('click', () => this.toggleChat());

            // Dark Mode Toggle
            $(document).on('click', '#omionDarkModeToggle', () => this.toggleDarkMode());

            // Sound Toggle
            $(document).on('click', '#omionSoundToggle', () => this.toggleSound());

            // History Controls
            $(document).on('click', '#omionNewConversation', () => this.startNewConversation());
            $(document).on('click', '#omionShowHistory', () => this.showConversationHistory());

            // Form Toggle
            $('#omionInquiryBtn').on('click', () => this.showForm());
            $('#omionReturnChat').on('click', () => this.showChat());

            // Message Handling
            $('#omionChatInput').on('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            $('#omionSendMessage').on('click', () => this.sendMessage());
            
            // Quick Questions
            $('.omion-quick-question').on('click', (e) => {
                const question = $(e.target).text();
                $('#omionChatInput').val(question);
                this.sendMessage();
            });

            // Window Resize
            $(window).on('resize', () => this.forceScrollBottom());

            // Input Typing Detection
            let typingTimer;
            $('#omionChatInput').on('input', () => {
                clearTimeout(typingTimer);
                this.showUserTyping();
                this.playSound('typing');
                typingTimer = setTimeout(() => {
                    this.hideUserTyping();
                }, 1000);
            });

            // Auto-save conversation periodically
            setInterval(() => {
                this.saveCurrentConversation();
            }, 30000); // Save every 30 seconds

            // Save on page unload
            $(window).on('beforeunload', () => {
                this.saveCurrentConversation();
                this.saveLeadData();
            });
        }

        // Dark Mode Functions
        loadDarkModePreference() {
            const stored = localStorage.getItem('omion-chat-dark-mode');
            if (stored !== null) {
                return stored === 'true';
            }
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        }

        saveDarkModePreference(isDark) {
            localStorage.setItem('omion-chat-dark-mode', isDark.toString());
        }

        toggleDarkMode() {
            this.isDarkMode = !this.isDarkMode;
            this.saveDarkModePreference(this.isDarkMode);
            this.applyDarkMode();
            
            const toggle = $('#omionDarkModeToggle');
            toggle.addClass('omion-toggle-animate');
            setTimeout(() => toggle.removeClass('omion-toggle-animate'), 300);
        }

        initializeDarkMode() {
            this.applyDarkMode();
            this.updateSoundToggle();
            
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                    if (localStorage.getItem('omion-chat-dark-mode') === null) {
                        this.isDarkMode = e.matches;
                        this.applyDarkMode();
                    }
                });
            }
        }

        applyDarkMode() {
            const container = $('.omion-chat-widget-container');
            
            if (this.isDarkMode) {
                container.addClass('omion-dark-mode');
            } else {
                container.removeClass('omion-dark-mode');
            }

            this.updateDarkModeToggle();
        }

        updateDarkModeToggle() {
            const toggle = $('#omionDarkModeToggle');
            const iconHtml = this.isDarkMode ? 
                `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"/>
                    <line x1="12" y1="1" x2="12" y2="3"/>
                    <line x1="12" y1="21" x2="12" y2="23"/>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                    <line x1="1" y1="12" x2="3" y2="12"/>
                    <line x1="21" y1="12" x2="23" y2="12"/>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>` :
                `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>`;
            
            toggle.html(iconHtml);
        }

        // Enhanced Typing Indicators
        showUserTyping() {
            const header = $('.omion-chat-header h3');
            if (!header.hasClass('user-typing')) {
                header.addClass('user-typing').attr('data-original', header.text()).text('You are typing...');
            }
        }

        hideUserTyping() {
            const header = $('.omion-chat-header h3');
            if (header.hasClass('user-typing')) {
                header.removeClass('user-typing').text(header.attr('data-original'));
            }
        }

        showBotTypingIndicator() {
            const typingHtml = `
                <div class="omion-typing-indicator omion-message bot">
                    <div class="omion-typing-content">
                        <div class="omion-typing-avatar">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2l3.09 6.26L22 9l-5.91 5.74L18 22l-6-3.27L6 22l1.91-7.26L2 9l6.91-.74L12 2z"/>
                            </svg>
                        </div>
                        <div class="omion-typing-dots">
                            <div class="omion-typing-dot"></div>
                            <div class="omion-typing-dot"></div>
                            <div class="omion-typing-dot"></div>
                        </div>
                        <div class="omion-typing-text">AI is thinking...</div>
                    </div>
                </div>`;
            
            $('#omionChatMessages').append(typingHtml);
            this.forceScrollBottom();

            setTimeout(() => {
                $('.omion-typing-dot').each((index, dot) => {
                    setTimeout(() => {
                        $(dot).addClass('omion-typing-dot-active');
                    }, index * 200);
                });
            }, 100);
        }

        hideBotTypingIndicator() {
            $('.omion-typing-indicator').fadeOut(200, function() {
                $(this).remove();
            });
        }

        showForm() {
            $('#omionChatMessages, .omion-chat-input-container, .omion-quick-questions, .omion-custom-buttons, .omion-history-controls').hide();
            $('.omion-form-container').show();
        }

        showChat() {
            $('.omion-form-container').hide();
            $('#omionChatMessages, .omion-chat-input-container, .omion-quick-questions, .omion-custom-buttons, .omion-history-controls').show();
            this.forceScrollBottom();
        }

        toggleChat() {
            const chatWindow = $('#omionChatWindow');
            if (chatWindow.is(':visible')) {
                chatWindow.fadeOut(300);
                this.showChat();
            } else {
                chatWindow.css('display', 'flex').fadeIn(300);
                $('#omionChatInput').focus();
                this.forceScrollBottom();
                this.playSound('notification');
            }
        }

        forceScrollBottom() {
            const messagesContainer = document.getElementById('omionChatMessages');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }

        cleanMessage(text) {
            return text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\n/g, '<br>');
        }

        displayMessage(role, content, saveToHistory = true) {
            const messagesContainer = $('#omionChatMessages');
            const timestamp = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            const messageDiv = $(`
                <div class="omion-message ${role}" data-timestamp="${timestamp}">
                    <div class="omion-message-content">${this.cleanMessage(content)}</div>
                    <div class="omion-message-time">${timestamp}</div>
                </div>
            `);
            
            messagesContainer.append(messageDiv);
            this.forceScrollBottom();

            if (role === 'bot') {
                setTimeout(() => this.addMessageReactions(messageDiv), 500);
            }

            if (saveToHistory) {
                this.saveCurrentConversation();
            }
        }

        addMessage(role, content) {
            this.displayMessage(role, content, true);
            
            // Play appropriate sound
            if (role === 'user') {
                this.playSound('messageSent');
            } else {
                this.playSound('messageReceived');
            }
        }

        addMessageReactions(messageElement) {
            const reactionsHtml = `
                <div class="omion-message-reactions">
                    <button class="omion-reaction-btn" data-reaction="👍" title="Helpful">
                        <span class="omion-reaction-icon">👍</span>
                    </button>
                    <button class="omion-reaction-btn" data-reaction="👎" title="Not helpful">
                        <span class="omion-reaction-icon">👎</span>
                    </button>
                    <button class="omion-reaction-btn omion-copy-btn" title="Copy message">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                </div>
            `;
            
            messageElement.append(reactionsHtml);

            messageElement.find('.omion-reaction-btn').on('click', (e) => {
                const btn = $(e.currentTarget);
                const reaction = btn.data('reaction');
                
                if (btn.hasClass('omion-copy-btn')) {
                    this.copyMessage(messageElement.find('.omion-message-content').text());
                    this.showCopyFeedback(btn);
                } else {
                    this.handleReaction(messageElement, reaction, btn);
                }
            });
        }

        handleReaction(messageElement, reaction, button) {
            messageElement.find('.omion-reaction-btn').removeClass('omion-reaction-active');
            button.addClass('omion-reaction-active');
            
            console.log('Message reaction:', reaction);
            this.showReactionFeedback(reaction);
            this.playSound('notification');
        }

        copyMessage(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            } else {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
            }
            this.playSound('notification');
        }

        showCopyFeedback(button) {
            const originalHtml = button.html();
            button.html(`
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20,6 9,17 4,12"/>
                </svg>
            `).addClass('omion-copy-success');
            
            setTimeout(() => {
                button.html(originalHtml).removeClass('omion-copy-success');
            }, 2000);
        }

        showReactionFeedback(reaction) {
            const feedback = reaction === '👍' ? 'Thanks for the feedback!' : 'We\'ll try to improve!';
            
            const feedbackElement = $(`
                <div class="omion-reaction-feedback">
                    ${feedback}
                </div>
            `);
            
            $('#omionChatMessages').append(feedbackElement);
            
            setTimeout(() => {
                feedbackElement.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 2000);
            
            this.forceScrollBottom();
        }

        async sendMessage() {
            const input = $('#omionChatInput');
            const message = input.val().trim();
            
            if (!message) return;
            
            input.val('');
            this.addMessage('user', message);
            this.showBotTypingIndicator();

            // Update lead tracking
            this.leadData.conversation_messages++;
            this.updateLeadScore(10, 'sent_message');
            
            // Analyze message for interests
            this.analyzeMessageForInterests(message);

            const currentMessages = [...this.messages, { role: 'user', content: message }];

            try {
                const response = await $.ajax({
                    url: omionAIChat.ajaxurl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'omion_chat_request',
                        messages: JSON.stringify(currentMessages),
                        current_url: window.location.href,
                        page_title: document.title
                    }
                });

                this.hideBotTypingIndicator();
                
                if (response.success && response.data.choices && response.data.choices[0].message) {
                    const aiResponse = response.data.choices[0].message.content;
                    this.addMessage('bot', aiResponse);
                    this.messages = [...currentMessages, { role: 'assistant', content: aiResponse }];
                    
                    // Update lead score for receiving response
                    this.updateLeadScore(5, 'received_response');
                } else {
                    throw new Error('Invalid API response format');
                }
            } catch (error) {
                console.error('Chat error:', error);
                this.hideBotTypingIndicator();
                this.addMessage('bot', 'Sorry, I encountered an error. Please try again.');
            }
            
            this.forceScrollBottom();
        }

        analyzeMessageForInterests(message) {
            const interestKeywords = {
                'pricing': ['price', 'cost', 'expensive', 'cheap', 'budget', 'quote'],
                'services': ['service', 'help', 'support', 'assist', 'offer'],
                'products': ['product', 'feature', 'buy', 'purchase', 'order'],
                'contact': ['contact', 'call', 'email', 'reach', 'phone'],
                'demo': ['demo', 'trial', 'test', 'try', 'preview'],
                'technical': ['how', 'technical', 'setup', 'install', 'configure']
            };

            const messageLower = message.toLowerCase();
            
            Object.keys(interestKeywords).forEach(interest => {
                const keywords = interestKeywords[interest];
                if (keywords.some(keyword => messageLower.includes(keyword))) {
                    if (!this.leadData.interests.includes(interest)) {
                        this.leadData.interests.push(interest);
                        this.updateLeadScore(8, `interest_${interest}`);
                    }
                }
            });
        }

        applyCustomStyles() {
            if (omionAIChat.options.primary_color) {
                const styleTag = `
                    .omion-chat-widget-button:not(.omion-dark-mode .omion-chat-widget-button), 
                    .omion-chat-header:not(.omion-dark-mode .omion-chat-header), 
                    .omion-message.user:not(.omion-dark-mode .omion-message.user), 
                    .omion-chat-send:not(.omion-dark-mode .omion-chat-send),
                    .omion-return-chat:not(.omion-dark-mode .omion-return-chat),
                    .omion-capture-submit:not(.omion-dark-mode .omion-capture-submit) {
                        background: ${omionAIChat.options.primary_color} !important;
                    }
                    .omion-quick-question:hover:not(.omion-dark-mode .omion-quick-question:hover),
                    .omion-capture-input:focus:not(.omion-dark-mode .omion-capture-input:focus) {
                        border-color: ${omionAIChat.options.primary_color} !important;
                        color: ${omionAIChat.options.primary_color} !important;
                    }
                    .omion-lead-progress-bar {
                        background: linear-gradient(90deg, ${omionAIChat.options.primary_color}, ${omionAIChat.options.primary_color}dd) !important;
                    }
                `;
                $('<style>').text(styleTag).appendTo('head');
            }
        }
    }

    // Initialize the chat widget
    window.omionChatInstance = new OmionAIChat();
});