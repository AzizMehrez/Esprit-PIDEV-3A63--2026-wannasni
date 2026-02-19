/**
 * Nexus AI — Smart Chat UI Controller
 * Features: Typing animation, Voice CALL mode, Chat memory, Export, 
 * Dark/Light mode, Suggested prompts, Sound effects, File upload,
 * Screen share, Markdown rendering, Code highlighting
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Nexus AI Chat Loading...');
    
    // ========================
    // DOM ELEMENTS
    // ========================
    const chatContainer = document.getElementById('smart-chat-container');
    const chatWindow = document.getElementById('chat-window');
    const toggleBtn = document.getElementById('chat-toggle');
    const closeBtn = document.getElementById('close-chat');
    const messagesContainer = document.getElementById('chat-messages');
    const userInput = document.getElementById('user-input');
    const sendBtn = document.getElementById('send-btn');
    const typingIndicator = document.getElementById('typing-indicator');
    const badge = document.querySelector('.notification-badge');
    const agentStatus = document.getElementById('agent-status');
    const suggestedPrompts = document.getElementById('suggested-prompts');

    // Debug: Check if critical elements exist
    console.log('Chat elements found:', {
        chatContainer: !!chatContainer,
        toggleBtn: !!toggleBtn,
        sendBtn: !!sendBtn,
        userInput: !!userInput,
        uploadBtn: !!document.getElementById('upload-btn'),
        voiceBtn: !!document.getElementById('voice-btn')
    });

    let isOpen = false;
    let isSpeaking = false;
    let currentAudio = null; // ElevenLabs audio playback tracker

    // ========================
    // SOUND EFFECTS
    // ========================
    function playSound(type) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            gain.gain.value = 0.1;

            switch (type) {
                case 'send':
                    osc.frequency.value = 600;
                    osc.type = 'sine';
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
                    break;
                case 'receive':
                    osc.frequency.value = 800;
                    osc.type = 'sine';
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2);
                    break;
                case 'open':
                    osc.frequency.value = 500;
                    osc.type = 'triangle';
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1);
                    break;
            }
            osc.start();
            osc.stop(ctx.currentTime + 0.25);
        } catch (e) { /* Ignore audio errors */ }
    }

    // ========================
    // TOGGLE CHAT
    // ========================
    const toggleChat = () => {
        isOpen = !isOpen;
        if (isOpen) {
            if (chatWindow) chatWindow.classList.add('open');
            if (toggleBtn) toggleBtn.classList.add('active');
            if (badge) badge.style.display = 'none';
            playSound('open');
            setTimeout(() => {
                if (userInput) userInput.focus();
            }, 400);
        } else {
            if (chatWindow) chatWindow.classList.remove('open');
            if (toggleBtn) toggleBtn.classList.remove('active');
        }
    };

    // Only add event listeners if elements exist
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleChat);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', toggleChat);
    }

    // ========================
    // DARK/LIGHT MODE (Optional)
    // ========================
    const themeToggle = document.getElementById('theme-toggle');
    const moonIcon = document.getElementById('theme-icon-moon');
    const sunIcon = document.getElementById('theme-icon-sun');

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('nexus-theme', theme);
        
        // Only update icons if they exist
        if (moonIcon && sunIcon) {
            if (theme === 'light') {
                moonIcon.style.display = 'none';
                sunIcon.style.display = 'block';
            } else {
                moonIcon.style.display = 'block';
                sunIcon.style.display = 'none';
            }
        }
    }

    setTheme(localStorage.getItem('nexus-theme') || 'dark');

    // Only add event listener if theme toggle exists
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme');
            setTheme(current === 'dark' ? 'light' : 'dark');
        });
    }

    // ========================
    // SUGGESTED PROMPTS
    // ========================
    if (suggestedPrompts) {
        suggestedPrompts.addEventListener('click', (e) => {
            const chip = e.target.closest('.prompt-chip');
            if (chip) {
                if (userInput) userInput.value = chip.dataset.prompt;
                handleSendUnified();
                suggestedPrompts.style.display = 'none';
            }
        });
    }

    // ========================
    // MAIN SEND HANDLER
    // ========================
    async function handleSendUnified() {
        const text = userInput ? userInput.value.trim() : '';
        const imageFile = imageInput ? imageInput.files[0] : null;
        const screenData = screenShareBtn && screenShareBtn._screenshotData ? screenShareBtn._screenshotData : null;
        const fileData = fileUploadBtn && fileUploadBtn._fileData ? fileUploadBtn._fileData : null;

        let messageText = text;
        let imageBase64 = null;

        if (screenData) {
            messageText = text || 'Look at my screen and tell me what you see. Help me with anything you notice.';
            imageBase64 = screenData;
            if (screenShareBtn) screenShareBtn._screenshotData = null;
        } else if (imageFile) {
            imageBase64 = await toBase64(imageFile);
        } else if (fileData) {
            messageText = text ? `${text}\n\n--- FILE: ${fileData.name} ---\n${fileData.content}`
                : `Analyze this file "${fileData.name}":\n\n${fileData.content}`;
            if (fileUploadBtn) fileUploadBtn._fileData = null;
        }

        if (!messageText && !imageBase64) return;

        if (suggestedPrompts) suggestedPrompts.style.display = 'none';

        addMessage(messageText, 'user', imageBase64);
        if (userInput) {
            userInput.value = '';
            userInput.placeholder = 'Type a message...';
        }
        clearImageSelection();
        playSound('send');

        showTyping(true);
        setStatus('Thinking...');
        if (messagesContainer) messagesContainer.scrollTop = messagesContainer.scrollHeight;

        try {
            const response = await window.SmartChatAPI.sendMessage(
                messageText.substring(0, 4000),
                imageBase64
            );

            showTyping(false);
            await addMessageAnimated(response.text, 'bot');
            playSound('receive');
            setStatus('Online & Ready');

            if (response.action) {
                executeAction(response.action);
            }

            saveChat();

        } catch (error) {
            console.error(error);
            showTyping(false);
            addMessage("Sorry, something went wrong. 😔", 'bot');
            setStatus('Online & Ready');
        }
    }

    // Only add event listeners if elements exist
    if (sendBtn) {
        sendBtn.addEventListener('click', handleSendUnified);
    }
    if (userInput) {
        userInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') handleSendUnified();
        });
    }

    // ========================
    // IMAGE UPLOAD
    // ========================
    const imageInput = document.getElementById('image-upload');
    const uploadBtn = document.getElementById('upload-btn');
    const previewContainer = document.getElementById('image-preview-container');
    const previewImage = document.getElementById('image-preview');
    const removeImageBtn = document.getElementById('remove-image');

    // Only add event listeners if elements exist
    if (uploadBtn && imageInput) {
        uploadBtn.addEventListener('click', () => imageInput.click());
    }

    if (imageInput && previewImage && previewContainer) {
        imageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImage.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                    if (userInput) userInput.focus();
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', clearImageSelection);
    }

    function clearImageSelection() {
        if (imageInput) imageInput.value = '';
        if (previewContainer) previewContainer.classList.add('hidden');
        if (previewImage) previewImage.src = '';
        if (fileUploadBtn && fileUploadBtn._fileData) fileUploadBtn._fileData = null;
    }

    function toBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = () => resolve(reader.result);
            reader.onerror = error => reject(error);
        });
    }

    // ========================
    // FILE UPLOAD (PDF, CSV, TXT)
    // ========================
    const fileInput = document.getElementById('file-upload');
    const fileUploadBtn = document.getElementById('upload-btn'); // Fixed ID reference

    // Handle both image and file uploads with the same upload button
    fileUploadBtn.addEventListener('click', () => {
        // Show options for both image and file upload
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*,.pdf,.csv,.txt,.json,.xml,.html,.md';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                if (file.type.startsWith('image/')) {
                    // Handle as image
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        previewImage.src = e.target.result;
                        previewContainer.classList.remove('hidden');
                        userInput.focus();
                    };
                    reader.readAsDataURL(file);
                } else {
                    // Handle as file
                    handleFileUpload(file);
                }
            }
        };
        input.click();
    });

    fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const maxSize = 500 * 1024;
        if (file.size > maxSize) {
            addMessage(`⚠️ File too large (${(file.size / 1024).toFixed(0)}KB). Max 500KB.`, 'bot');
            return;
        }

        try {
            const content = await readFileContent(file);
            fileUploadBtn._fileData = { name: file.name, content: content };

            previewImage.src = '';
            previewContainer.classList.remove('hidden');
            const previewText = document.createElement('span');
            previewText.textContent = `📄 ${file.name}`;
            previewText.style.cssText = 'color: var(--text-main); font-size: 0.8rem;';
            previewContainer.querySelector('img').replaceWith(previewText);

            userInput.placeholder = `Ask about ${file.name}...`;
            userInput.focus();
        } catch (err) {
            addMessage('⚠️ Could not read file. Try a text-based file.', 'bot');
        }

        fileInput.value = '';
    });

    function readFileContent(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                let content = e.target.result;
                if (content.length > 10000) {
                    content = content.substring(0, 10000) + '\n\n... [truncated, file too long]';
                }
                resolve(content);
            };
            reader.onerror = reject;
            reader.readAsText(file);
        });
    }

    // Handle file upload and preview
    async function handleFileUpload(file) {
        const maxSize = 500 * 1024; // 500KB limit
        if (file.size > maxSize) {
            addMessage(`⚠️ File too large (${(file.size / 1024).toFixed(0)}KB). Max 500KB.`, 'bot');
            return;
        }

        try {
            const content = await readFileContent(file);
            fileUploadBtn._fileData = { name: file.name, content: content };

            // Show file preview
            previewImage.src = '';
            previewContainer.classList.remove('hidden');
            const previewText = document.createElement('span');
            previewText.textContent = `📄 ${file.name}`;
            previewText.style.cssText = 'color: var(--text-main); font-size: 0.8rem; padding: 10px;';
            
            // Replace image with text preview
            const img = previewContainer.querySelector('img');
            if (img) {
                img.replaceWith(previewText);
            }

            userInput.placeholder = `Ask about ${file.name}...`;
            userInput.focus();
            
            addMessage(`📎 File "${file.name}" uploaded successfully!`, 'bot');
        } catch (err) {
            addMessage('⚠️ Could not read file. Try a text-based file.', 'bot');
        }
    }

    // ========================
    // SCREEN SHARE (Optional)
    // ========================
    const screenShareBtn = document.getElementById('screen-share-btn');

    // Only add event listener if screen share button exists
    if (screenShareBtn) {
        screenShareBtn.addEventListener('click', async () => {
            try {
                screenShareBtn.classList.add('capturing');
                screenShareBtn.disabled = true;

                if (chatWindow) chatWindow.style.visibility = 'hidden';
                if (toggleBtn) toggleBtn.style.visibility = 'hidden';

            await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));

            const canvas = await html2canvas(document.body, {
                scale: 0.8,
                useCORS: true,
                logging: false,
                backgroundColor: '#0f172a'
            });

            if (chatWindow) chatWindow.style.visibility = 'visible';
            if (toggleBtn) toggleBtn.style.visibility = 'visible';

            const screenshotBase64 = canvas.toDataURL('image/jpeg', 0.7);

            if (previewImage) previewImage.src = screenshotBase64;
            if (previewContainer) previewContainer.classList.remove('hidden');
            screenShareBtn._screenshotData = screenshotBase64;

            screenShareBtn.classList.remove('capturing');
            screenShareBtn.disabled = false;

            if (userInput) {
                userInput.placeholder = 'Ask about your screen... (or just hit send)';
                userInput.focus();
            }

        } catch (error) {
            console.error('Screen capture failed:', error);
            if (chatWindow) chatWindow.style.visibility = 'visible';
            if (toggleBtn) toggleBtn.style.visibility = 'visible';
            screenShareBtn.classList.remove('capturing');
            screenShareBtn.disabled = false;
            addMessage('⚠️ Screen capture failed.', 'bot');
        }
        });
    }

    // ========================
    // VOICE CALL MODE (ChatGPT-style)
    // ========================
    const voiceBtn = document.getElementById('voice-btn');
    const voiceOverlay = document.getElementById('voice-call-overlay');
    const voiceOrb = document.getElementById('voice-orb');
    const voiceStatus = document.getElementById('voice-call-status');
    const voiceTranscript = document.getElementById('voice-transcript');
    const voiceAiText = document.getElementById('voice-ai-text');
    const voiceEndCall = document.getElementById('voice-end-call');
    const voiceMuteBtn = document.getElementById('voice-mute-btn');
    const voiceSpeakerBtn = document.getElementById('voice-speaker-btn');
    const voiceTimerEl = document.getElementById('voice-call-timer');

    let callRecognition = null;
    let callActive = false;
    let callMuted = false;
    let callSpeaker = true;
    let callTimerInterval = null;
    let callStartTime = null;

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

    // Diagnostic: Check voice call setup on load
    console.log('🔍 Voice Call Diagnostics:');
    console.log('  - Voice button:', !!voiceBtn ? '✅' : '❌');
    console.log('  - Voice overlay:', !!voiceOverlay ? '✅' : '❌');
    console.log('  - Voice orb:', !!voiceOrb ? '✅' : '❌');
    console.log('  - Voice status:', !!voiceStatus ? '✅' : '❌');
    console.log('  - Voice transcript:', !!voiceTranscript ? '✅' : '❌');
    console.log('  - Voice AI text:', !!voiceAiText ? '✅' : '❌');
    console.log('  - Voice end call:', !!voiceEndCall ? '✅' : '❌');
    console.log('  - Voice mute btn:', !!voiceMuteBtn ? '✅' : '❌');
    console.log('  - Voice speaker btn:', !!voiceSpeakerBtn ? '✅' : '❌');
    console.log('  - Voice timer:', !!voiceTimerEl ? '✅' : '❌');
    console.log('  - Speech Recognition API:', !!SpeechRecognition ? '✅' : '❌ (Use Chrome/Edge)');

    // Open voice call - only if voice button exists
    if (voiceBtn) {
        voiceBtn.addEventListener('click', () => {
            console.log('🎤 Voice button clicked');
            console.log('Speech recognition available:', !!SpeechRecognition);
            console.log('Voice overlay exists:', !!voiceOverlay);
            
            if (!SpeechRecognition) {
                // Fallback: Simple voice recording simulation
                addMessage('🎤 Voice input started...', 'bot');
                voiceBtn.style.background = 'linear-gradient(45deg, #ef4444, #dc2626)';
                voiceBtn.style.animation = 'pulse 1.5s infinite';
                
                // Simple timeout for demo purposes
                setTimeout(() => {
                    voiceBtn.style.background = '';
                    voiceBtn.style.animation = '';
                    addMessage('🔴 Voice recording stopped. Speech recognition not available in this browser. Try Chrome or Edge for full voice features.', 'bot');
                }, 3000);
                return;
            }
            startVoiceCall();
        });
    } else {
        console.warn('⚠️ Voice button not found in DOM');
    }

    function startVoiceCall() {
        // Check if all required elements exist
        if (!voiceOverlay || !voiceStatus || !voiceTranscript || !voiceAiText || !voiceMuteBtn) {
            console.error('Voice call elements not found in DOM');
            addMessage('❌ Voice call feature not available. Please refresh the page.', 'bot');
            return;
        }

        callActive = true;
        callMuted = false;

        voiceOverlay.classList.add('active');
        voiceOverlay.classList.remove('listening', 'thinking', 'speaking');
        voiceStatus.textContent = 'Connecting...';
        voiceTranscript.textContent = '';
        voiceAiText.textContent = '';
        voiceMuteBtn.classList.remove('muted');

        callStartTime = Date.now();
        callTimerInterval = setInterval(updateCallTimer, 1000);

        playSound('open');

        setTimeout(() => {
            if (callActive) startCallListening();
        }, 800);
    }

    function endVoiceCall() {
        callActive = false;

        if (callRecognition) {
            callRecognition.abort();
            callRecognition = null;
        }

        // Stop ElevenLabs audio if playing
        if (currentAudio) {
            currentAudio.pause();
            currentAudio.src = '';
            currentAudio = null;
        }
        if (window.speechSynthesis) window.speechSynthesis.cancel();

        clearInterval(callTimerInterval);

        if (voiceOverlay) {
            voiceOverlay.classList.remove('active', 'listening', 'thinking', 'speaking');
        }

        playSound('send');
    }

    function startCallListening() {
        if (!callActive || callMuted || !voiceOverlay || !voiceStatus || !voiceTranscript) return;

        callRecognition = new SpeechRecognition();
        callRecognition.continuous = false;
        callRecognition.interimResults = true;
        callRecognition.lang = 'en-US';

        voiceOverlay.classList.remove('thinking', 'speaking');
        voiceOverlay.classList.add('listening');
        voiceStatus.textContent = 'Listening...';
        voiceTranscript.textContent = '';

        callRecognition.onresult = (event) => {
            let transcript = '';
            let isFinal = false;
            for (let i = 0; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
                if (event.results[i].isFinal) isFinal = true;
            }
            if (voiceTranscript) voiceTranscript.textContent = transcript;

            if (isFinal && transcript.trim()) {
                processVoiceInput(transcript.trim());
            }
        };

        callRecognition.onend = () => {
            if (callActive && !callMuted && voiceOverlay && voiceOverlay.classList.contains('listening')) {
                setTimeout(() => {
                    if (callActive && !callMuted) startCallListening();
                }, 300);
            }
        };

        callRecognition.onerror = (e) => {
            console.error('Speech recognition error:', e.error);
            
            if (e.error === 'not-allowed') {
                if (voiceStatus) voiceStatus.textContent = 'Mic access denied';
                addMessage('❌ Microphone access denied. Please allow microphone access and try again.', 'bot');
                setTimeout(endVoiceCall, 2000);
                return;
            }
            if (e.error === 'no-speech') {
                if (voiceStatus) voiceStatus.textContent = 'No speech detected';
                // Continue listening
                if (callActive && !callMuted) {
                    setTimeout(() => {
                        if (callActive) startCallListening();
                    }, 500);
                }
                return;
            }
            if (callActive && !callMuted && e.error !== 'aborted') {
                setTimeout(() => {
                    if (callActive) startCallListening();
                }, 500);
            }
        };

        try {
            callRecognition.start();
            console.log('✅ Speech recognition started');
        } catch (e) {
            console.error('Failed to start speech recognition:', e);
        }
    }

    async function processVoiceInput(text) {
        console.log('🎯 Processing voice input:', text);
        
        if (!callActive) {
            console.warn('Call not active, ignoring input');
            return;
        }

        if (!voiceOverlay || !voiceStatus || !voiceAiText) {
            console.error('Voice UI elements missing');
            return;
        }

        voiceOverlay.classList.remove('listening');
        voiceOverlay.classList.add('thinking');
        voiceStatus.textContent = 'Thinking...';
        voiceAiText.textContent = '';

        if (callRecognition) {
            callRecognition.abort();
            callRecognition = null;
        }

        addMessage(text, 'user');

        try {
            console.log('📡 Sending message to AI...');
            const response = await window.SmartChatAPI.sendMessage(text);
            console.log('✅ AI response received');

            if (!callActive) return;

            const aiText = response.text
                .replace(/\*\*(.+?)\*\*/g, '$1')
                .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
                .replace(/```[\s\S]*?```/g, '')
                .replace(/[#*_~`]/g, '')
                .replace(/\n+/g, ' ')
                .replace(/[\u{1F600}-\u{1F64F}\u{1F300}-\u{1F5FF}\u{1F680}-\u{1F6FF}\u{1F1E0}-\u{1F1FF}\u{2600}-\u{27BF}\u{2B50}\u{2B55}\u{231A}-\u{23F3}\u{23E9}-\u{23EF}\u{25AA}-\u{25FE}\u{2934}-\u{2935}\u{200D}\u{20E3}\u{FE0F}\u{E0020}-\u{E007F}\u{FE00}-\u{FEFF}\u{D83C}-\u{DBFF}\u{DC00}-\u{DFFF}]/gu, '')
                .replace(/\s{2,}/g, ' ')
                .trim();

            console.log('🗣️ AI text for voice:', aiText.substring(0, 100) + '...');
            
            voiceAiText.textContent = aiText.length > 200 ? aiText.substring(0, 200) + '...' : aiText;

            addMessage(response.text, 'bot');
            saveChat();

            if (response.action) executeAction(response.action);

            // Speak the response
            if (callSpeaker && callActive) {
                voiceOverlay.classList.remove('thinking');
                voiceOverlay.classList.add('speaking');
                voiceStatus.textContent = 'Speaking...';

                console.log('🔊 Speaking response...');
                await speakTextAsync(aiText.substring(0, 500));
                console.log('✅ Finished speaking');
            }

            // Resume listening
            if (callActive) {
                voiceOverlay.classList.remove('speaking', 'thinking');
                if (voiceTranscript) voiceTranscript.textContent = '';
                console.log('👂 Resuming listening...');
                startCallListening();
            }

        } catch (error) {
            console.error('❌ Voice call error:', error);
            if (callActive) {
                voiceStatus.textContent = 'Error, retrying...';
                voiceAiText.textContent = 'Something went wrong 😔';
                setTimeout(() => {
                    if (callActive) startCallListening();
                }, 1500);
            }
        }
    }

    // ========================
    // SMART VOICE SELECTION
    // ========================
    // Picks the best natural-sounding voice available in the browser
    let cachedVoice = null;

    function getBestVoice() {
        if (cachedVoice) return cachedVoice;

        const voices = window.speechSynthesis.getVoices();
        if (!voices.length) return null;

        // Priority list: best natural voices (ranked)
        const preferredVoices = [
            // Microsoft Edge premium voices (very natural)
            'Microsoft Guy Online',
            'Microsoft Aria Online',
            'Microsoft Jenny Online',
            'Microsoft Ryan Online',
            'Microsoft Christopher Online',
            'Microsoft Eric Online',
            'Microsoft Steffan Online',
            // Google Chrome voices (good quality)
            'Google US English',
            'Google UK English Male',
            'Google UK English Female',
            // macOS premium voices
            'Samantha',
            'Alex',
            'Daniel',
            // General fallbacks with "natural" or "online" in name
        ];

        // Try exact matches first
        for (const preferred of preferredVoices) {
            const found = voices.find(v => v.name === preferred);
            if (found) { cachedVoice = found; return found; }
        }

        // Try partial matches (voices with "Online", "Natural", or "Neural" in name)
        const naturalVoice = voices.find(v =>
            v.lang.startsWith('en') && (
                v.name.includes('Online') ||
                v.name.includes('Natural') ||
                v.name.includes('Neural')
            )
        );
        if (naturalVoice) { cachedVoice = naturalVoice; return naturalVoice; }

        // Try any English voice
        const englishVoice = voices.find(v => v.lang.startsWith('en'));
        if (englishVoice) { cachedVoice = englishVoice; return englishVoice; }

        // Last resort: first voice
        cachedVoice = voices[0];
        return voices[0];
    }

    // Pre-load voices (some browsers load them async)
    if (window.speechSynthesis) {
        window.speechSynthesis.onvoiceschanged = () => { cachedVoice = null; };
        window.speechSynthesis.getVoices(); // trigger loading
    }

    // Promisified TTS for call mode — smart voice selection
    function speakTextAsync(text) {
        return new Promise((resolve) => {
            if (!window.speechSynthesis || !text) { resolve(); return; }

            // Stop any currently playing audio
            if (currentAudio) {
                currentAudio.pause();
                currentAudio.src = '';
                currentAudio = null;
            }
            window.speechSynthesis.cancel();

            const utterance = new SpeechSynthesisUtterance(text);

            // Select the best voice
            const bestVoice = getBestVoice();
            if (bestVoice) {
                utterance.voice = bestVoice;
                console.log('🎙️ Using voice:', bestVoice.name);
            }

            utterance.rate = 1.0;
            utterance.pitch = 1.0;
            utterance.volume = 1.0;

            const timeout = setTimeout(() => {
                window.speechSynthesis.cancel();
                resolve();
            }, 60000);

            utterance.onend = () => { clearTimeout(timeout); resolve(); };
            utterance.onerror = () => { clearTimeout(timeout); resolve(); };

            window.speechSynthesis.speak(utterance);
        });
    }

    // Call controls - only add listeners if elements exist
    if (voiceEndCall) {
        voiceEndCall.addEventListener('click', endVoiceCall);
    }

    if (voiceMuteBtn) {
        voiceMuteBtn.addEventListener('click', () => {
            callMuted = !callMuted;
            voiceMuteBtn.classList.toggle('muted', callMuted);
            if (callMuted) {
                if (callRecognition) callRecognition.abort();
                if (voiceOverlay) voiceOverlay.classList.remove('listening');
                if (voiceStatus) voiceStatus.textContent = 'Muted';
            } else {
                startCallListening();
            }
        });
    }

    if (voiceSpeakerBtn) {
        voiceSpeakerBtn.addEventListener('click', () => {
            callSpeaker = !callSpeaker;
            voiceSpeakerBtn.classList.toggle('off', !callSpeaker);
        });
    }

    // Tap orb to interrupt AI speaking
    if (voiceOrb) {
        voiceOrb.addEventListener('click', () => {
            if (!callActive || !voiceOverlay) return;
            if (voiceOverlay.classList.contains('speaking')) {
                // Stop ElevenLabs audio
                if (currentAudio) {
                    currentAudio.pause();
                    currentAudio.src = '';
                    currentAudio = null;
                }
                if (window.speechSynthesis) window.speechSynthesis.cancel();
                voiceOverlay.classList.remove('speaking');
                startCallListening();
            }
        });
    }

    function updateCallTimer() {
        if (!callStartTime) return;
        const elapsed = Math.floor((Date.now() - callStartTime) / 1000);
        const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
        const secs = String(elapsed % 60).padStart(2, '0');
        voiceTimerEl.textContent = `${mins}:${secs}`;
    }

    // ========================
    // UI HELPER FUNCTIONS
    // ========================

    function setStatus(text) {
        if (agentStatus) {
            agentStatus.textContent = text;
        }
    }

    function addMessage(text, sender, image = null) {
        if (!messagesContainer) return null;
        
        const div = document.createElement('div');
        div.classList.add('message', `${sender}-message`);

        if (image) {
            const img = document.createElement('img');
            img.src = image;
            div.appendChild(img);
        }

        if (text) {
            const p = document.createElement('p');
            if (sender === 'bot') {
                p.innerHTML = renderMarkdown(text);
            } else {
                p.textContent = text.length > 200 ? text.substring(0, 200) + '...' : text;
            }
            div.appendChild(p);
        }

        const time = document.createElement('span');
        time.classList.add('timestamp');
        time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        div.appendChild(time);

        messagesContainer.appendChild(div);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        return div;
    }

    async function addMessageAnimated(text, sender) {
        if (!messagesContainer) return null;
        
        const div = document.createElement('div');
        div.classList.add('message', `${sender}-message`);

        const p = document.createElement('p');
        div.appendChild(p);

        const time = document.createElement('span');
        time.classList.add('timestamp');
        time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        div.appendChild(time);

        messagesContainer.appendChild(div);

        const words = text.split(' ');
        let currentText = '';
        const chunkSize = 3;

        for (let i = 0; i < words.length; i += chunkSize) {
            const chunk = words.slice(i, i + chunkSize).join(' ');
            currentText += (currentText ? ' ' : '') + chunk;
            p.innerHTML = renderMarkdown(currentText);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            await sleep(30);
        }

        p.innerHTML = renderMarkdown(text);

        div.querySelectorAll('pre code').forEach(block => {
            if (window.Prism) Prism.highlightElement(block);
        });

        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        return div;
    }

    function sleep(ms) {
        return new Promise(r => setTimeout(r, ms));
    }

    function renderMarkdown(text) {
        if (typeof marked !== 'undefined') {
            marked.setOptions({
                breaks: true,
                gfm: true,
                highlight: function (code, lang) {
                    if (window.Prism && lang && Prism.languages[lang]) {
                        return Prism.highlight(code, Prism.languages[lang], lang);
                    }
                    return code;
                }
            });
            try {
                return marked.parse(text);
            } catch (e) {
                return text.replace(/\n/g, '<br>');
            }
        }
        return text
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>')
            .replace(/\n/g, '<br>');
    }

    function showTyping(show) {
        if (typingIndicator) {
            typingIndicator.classList.toggle('visible', show);
        }
    }

    // ========================
    // MULTI-SESSION CHAT STORAGE
    // ========================
    const STORAGE_KEY = 'nexus-sessions';
    let currentSessionId = null;

    function generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2, 6);
    }

    function getAllSessions() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        } catch { return []; }
    }

    function saveSessions(sessions) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(sessions));
        } catch { /* Storage full */ }
    }

    function getSession(id) {
        return getAllSessions().find(s => s.id === id) || null;
    }

    function createNewSession(switchTo = true) {
        const session = {
            id: generateId(),
            title: 'New Chat',
            messages: [],
            createdAt: new Date().toISOString()
        };
        const sessions = getAllSessions();
        sessions.unshift(session);
        saveSessions(sessions);

        if (switchTo) {
            currentSessionId = session.id;
            if (messagesContainer) messagesContainer.innerHTML = '';
            window.SmartChatAPI.clearHistory();
            if (suggestedPrompts) suggestedPrompts.style.display = 'flex';
            addMessage("Hello! I'm **Nexus** 🤖 How can I help you?", 'bot');
            updateSidebar();
        }
        return session;
    }

    function saveChat() {
        if (!currentSessionId || !messagesContainer) return;
        try {
            const messages = [];
            messagesContainer.querySelectorAll('.message').forEach(msg => {
                const isBot = msg.classList.contains('bot-message');
                const p = msg.querySelector('p');
                if (p) {
                    messages.push({
                        sender: isBot ? 'bot' : 'user',
                        html: p.innerHTML,
                        text: p.textContent,
                        time: msg.querySelector('.timestamp')?.textContent || ''
                    });
                }
            });

            const sessions = getAllSessions();
            const idx = sessions.findIndex(s => s.id === currentSessionId);
            if (idx !== -1) {
                sessions[idx].messages = messages.slice(-50);
                // Auto-title from first user message
                if (sessions[idx].title === 'New Chat') {
                    const firstUser = messages.find(m => m.sender === 'user');
                    if (firstUser) {
                        sessions[idx].title = firstUser.text.substring(0, 40) + (firstUser.text.length > 40 ? '...' : '');
                    }
                }
                saveSessions(sessions);
                updateSidebar();
            }
        } catch { /* Error saving */ }
    }

    function loadSession(id) {
        const session = getSession(id);
        if (!session) return;

        currentSessionId = id;
        if (messagesContainer) messagesContainer.innerHTML = '';
        window.SmartChatAPI.clearHistory();

        if (session.messages.length === 0) {
            if (suggestedPrompts) suggestedPrompts.style.display = 'flex';
            addMessage("Hello! I'm **Nexus** 🤖 How can I help you?", 'bot');
        } else {
            if (suggestedPrompts) suggestedPrompts.style.display = 'none';
            session.messages.forEach(msg => {
                const div = document.createElement('div');
                div.classList.add('message', `${msg.sender}-message`);

                const p = document.createElement('p');
                p.innerHTML = msg.html;
                div.appendChild(p);

                const time = document.createElement('span');
                time.classList.add('timestamp');
                time.textContent = msg.time;
                div.appendChild(time);

                messagesContainer.appendChild(div);

                // Rebuild API history for context
                if (msg.sender === 'user') {
                    window.SmartChatAPI.conversationHistory.push({ role: 'user', content: msg.text });
                } else {
                    window.SmartChatAPI.conversationHistory.push({ role: 'assistant', content: msg.text });
                }
            });
            // Keep only last 10 for API
            if (window.SmartChatAPI.conversationHistory.length > 10) {
                window.SmartChatAPI.conversationHistory = window.SmartChatAPI.conversationHistory.slice(-10);
            }
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        updateSidebar();
    }

    function deleteSession(id) {
        let sessions = getAllSessions();
        sessions = sessions.filter(s => s.id !== id);
        saveSessions(sessions);

        if (id === currentSessionId) {
            if (sessions.length > 0) {
                loadSession(sessions[0].id);
            } else {
                createNewSession();
            }
        }
        updateSidebar();
    }

    // ========================
    // SIDEBAR UI (OPTIONAL FEATURE)
    // ========================
    // Note: Sidebar elements are optional. If not present, chat works normally without conversation history UI.
    const sidebar = document.getElementById('chat-sidebar');
    const sidebarList = document.getElementById('sidebar-list');
    const historyToggle = document.getElementById('history-toggle');
    const sidebarNewChat = document.getElementById('sidebar-new-chat');

    // Only add event listeners if sidebar elements exist
    if (historyToggle && sidebar) {
        historyToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    } else if (historyToggle) {
        console.log('ℹ️ Chat sidebar feature not available (elements not in template)');
    }

    // Close sidebar when clicking outside (on overlaid content)
    if (sidebar) {
        sidebar.addEventListener('click', (e) => {
            if (e.target === sidebar) {
                sidebar.classList.remove('open');
            }
        });
    }

    if (sidebarNewChat && sidebar) {
        sidebarNewChat.addEventListener('click', () => {
            createNewSession();
            sidebar.classList.remove('open');
        });
    }

    function updateSidebar() {
        // Check if sidebar elements exist
        if (!sidebarList) {
            console.log('ℹ️ Sidebar not available (elements not found)');
            return;
        }

        const sessions = getAllSessions();
        sidebarList.innerHTML = '';

        if (sessions.length === 0) {
            sidebarList.innerHTML = `
                <div class="sidebar-empty">
                    <span class="sidebar-empty-icon">💬</span>
                    No conversations yet
                </div>`;
            return;
        }

        sessions.forEach(session => {
            const item = document.createElement('div');
            item.classList.add('sidebar-item');
            if (session.id === currentSessionId) item.classList.add('active');

            const dateStr = new Date(session.createdAt).toLocaleDateString([], {
                month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            item.innerHTML = `
                <span class="sidebar-item-icon">💬</span>
                <div class="sidebar-item-info">
                    <span class="sidebar-item-title">${session.title}</span>
                    <span class="sidebar-item-date">${dateStr}</span>
                </div>
                <button class="sidebar-item-delete" title="Delete">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>`;

            // Click to switch session
            item.addEventListener('click', (e) => {
                if (e.target.closest('.sidebar-item-delete')) return;
                loadSession(session.id);
                sidebar.classList.remove('open');
            });

            // Delete button
            item.querySelector('.sidebar-item-delete').addEventListener('click', (e) => {
                e.stopPropagation();
                deleteSession(session.id);
            });

            sidebarList.appendChild(item);
        });
    }

    // Initialize: load last session or create first one
    function initSessions() {
        const sessions = getAllSessions();

        // Migrate old single-session data
        const oldData = localStorage.getItem('nexus-chat-history');
        if (oldData && sessions.length === 0) {
            try {
                const oldMessages = JSON.parse(oldData);
                if (oldMessages.length > 0) {
                    const session = {
                        id: generateId(),
                        title: 'Previous Chat',
                        messages: oldMessages.map(m => ({
                            ...m,
                            text: m.text || ''
                        })),
                        createdAt: new Date().toISOString()
                    };
                    saveSessions([session]);
                    localStorage.removeItem('nexus-chat-history');
                    loadSession(session.id);
                    return;
                }
            } catch { /* ignore */ }
        }

        if (sessions.length > 0) {
            loadSession(sessions[0].id);
        } else {
            createNewSession();
        }
    }

    initSessions();

    // ========================
    // EXPORT CHAT
    // ========================
    const exportBtn = document.getElementById('export-chat');

    exportBtn.addEventListener('click', () => {
        let exportText = '=== Nexus AI Chat Export ===\n';
        exportText += `Date: ${new Date().toLocaleString()}\n`;
        exportText += '================================\n\n';

        messagesContainer.querySelectorAll('.message').forEach(msg => {
            const isBot = msg.classList.contains('bot-message');
            const sender = isBot ? '🤖 Nexus' : '👤 You';
            const p = msg.querySelector('p');
            const time = msg.querySelector('.timestamp');
            const text = p ? p.textContent : '';

            exportText += `[${time?.textContent || ''}] ${sender}:\n${text}\n\n`;
        });

        const blob = new Blob([exportText], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `nexus-chat-${new Date().toISOString().slice(0, 10)}.txt`;
        a.click();
        URL.revokeObjectURL(url);

        addMessage('✅ Chat exported successfully!', 'bot');
    });

    // ========================
    // CLEAR / NEW CHAT
    // ========================
    const clearBtn = document.getElementById('clear-chat');

    clearBtn.addEventListener('click', () => {
        createNewSession();
    });

    // ========================
    // INTEGRATION ACTIONS (with PC Control)
    // ========================
    function executeAction(action) {
        console.log("Executing Action:", action);

        const blob1 = document.querySelector('.blob-1');
        const blob2 = document.querySelector('.blob-2');

        // Check if it's an open_url action
        if (action.startsWith('open_url:')) {
            const url = action.replace('open_url:', '').trim();
            if (url) {
                window.location.href = url;
                return;
            }
        }

        switch (action) {
            case 'change_bg':
                document.body.style.transition = 'background 1s ease';
                document.body.style.background = '#2e1065';
                break;
            case 'toggle_theme':
                if (blob1) blob1.style.background = '#22c55e';
                if (blob2) blob2.style.background = '#eab308';
                document.body.style.background = '#0f1729';
                break;
            case 'reset_ui':
                document.body.style.background = '';
                if (blob1) blob1.style.background = 'var(--primary)';
                if (blob2) blob2.style.background = 'var(--accent)';
                break;
        }
    }
});
