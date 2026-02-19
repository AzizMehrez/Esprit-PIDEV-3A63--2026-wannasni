/**
 * WANNASNI Networking – Front-end controller
 * Handles: tabs, posts, likes, comments, invites, friends,
 *          conversations, messaging (text + voice), search, share, privacy
 */
(function () {
    'use strict';

    const C = window.NET_CONFIG || {};
    const U = C.urls || {};
    let currentConvId = null;
    let currentChatUserId = null;
    let mediaRecorder = null;
    let audioChunks = [];
    let selectedFiles = [];

    // ─── Helpers ──────────────────────────────────────────────────────
    function api(url, opts = {}) {
        const defaults = { headers: {} };
        if (!(opts.body instanceof FormData)) {
            defaults.headers['Content-Type'] = 'application/json';
        }
        return fetch(url, { ...defaults, ...opts })
            .then(r => r.json())
            .catch(err => { console.error('API Error', err); return { error: err.message }; });
    }

    function $$(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }
    function $(sel, ctx) { return (ctx || document).querySelector(sel); }

    function avatarHtml(src, letter, cls) {
        cls = cls || 'net-avatar-sm';
        if (src) return `<div class="${cls}"><img src="${src}" alt=""></div>`;
        return `<div class="${cls}">${(letter || 'U').charAt(0).toUpperCase()}</div>`;
    }

    function timeAgo(dateStr) {
        if (!dateStr) return '';
        return dateStr; // simple for now
    }

    function urlFor(template, id) {
        return template.replace('{id}', id);
    }

    // ─── Tabs ─────────────────────────────────────────────────────────
    $$('.net-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            $$('.net-tab').forEach(t => t.classList.remove('active'));
            $$('.net-tab-content').forEach(tc => tc.classList.remove('active'));
            tab.classList.add('active');
            const target = tab.dataset.tab;
            const panel = $('#tab-' + target);
            if (panel) panel.classList.add('active');

            // Auto-load data for tabs
            if (target === 'friends') { loadPendingInvites(); loadFriends(); }
            if (target === 'messages') { loadConversations(); }
        });
    });

    // Handle hash-based tab activation
    if (window.location.hash) {
        const hashTab = window.location.hash.replace('#', '');
        const tab = $(`.net-tab[data-tab="${hashTab}"]`);
        if (tab) tab.click();
    }

    // ─── Search ───────────────────────────────────────────────────────
    const searchToggle = $('#btnSearchToggle');
    const searchBar = $('#searchBar');
    const searchInput = $('#searchInput');
    const searchResults = $('#searchResults');

    if (searchToggle && searchBar) {
        searchToggle.addEventListener('click', () => {
            searchBar.style.display = searchBar.style.display === 'none' ? 'block' : 'none';
            if (searchBar.style.display === 'block') searchInput.focus();
        });
    }

    let searchTimeout;
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            const q = searchInput.value.trim();
            if (q.length < 2) { searchResults.style.display = 'none'; return; }
            searchTimeout = setTimeout(() => {
                api(U.search + '?q=' + encodeURIComponent(q))
                    .then(data => {
                        if (!data.users || data.users.length === 0) {
                            searchResults.innerHTML = '<div class="net-search-item"><small>Aucun résultat</small></div>';
                        } else {
                            searchResults.innerHTML = data.users.map(u => `
                                <div class="net-search-item" data-user-id="${u.id}" onclick="window.location.href='${urlFor(U.userProfile, u.id)}'">
                                    ${avatarHtml(u.avatar, u.name)}
                                    <div>
                                        <strong>${u.name}</strong>
                                        <small>${u.isConnected ? '✅ Connecté' : (u.isPublic ? '🌐 Public' : '🔒 Privé')}</small>
                                    </div>
                                </div>
                            `).join('');
                        }
                        searchResults.style.display = 'block';
                    });
            }, 300);
        });

        document.addEventListener('click', (e) => {
            if (!searchBar.contains(e.target) && e.target !== searchToggle) {
                searchResults.style.display = 'none';
            }
        });
    }

    // ─── Create Post ──────────────────────────────────────────────────
    const postContent = $('#postContent');
    const mediaImage = $('#mediaImage');
    const mediaVideo = $('#mediaVideo');
    const mediaPreview = $('#mediaPreview');
    const btnCreatePost = $('#btnCreatePost');
    const postType = $('#postType');

    function showMediaPreview() {
        if (!mediaPreview) return;
        mediaPreview.innerHTML = '';
        if (selectedFiles.length === 0) { mediaPreview.style.display = 'none'; return; }
        mediaPreview.style.display = 'flex';
        selectedFiles.forEach((f, i) => {
            const url = URL.createObjectURL(f);
            if (f.type.startsWith('image/')) {
                mediaPreview.innerHTML += `<img src="${url}" alt="preview">`;
            } else if (f.type.startsWith('video/')) {
                mediaPreview.innerHTML += `<video src="${url}" controls style="max-height:120px;"></video>`;
            }
        });
    }

    if (mediaImage) {
        mediaImage.addEventListener('change', (e) => {
            selectedFiles = [...selectedFiles, ...Array.from(e.target.files)];
            showMediaPreview();
        });
    }

    if (mediaVideo) {
        mediaVideo.addEventListener('change', (e) => {
            selectedFiles = [...selectedFiles, ...Array.from(e.target.files)];
            showMediaPreview();
        });
    }

    // Compose error banner (shown when image is rejected by moderation)
    function showPostError(msg) {
        let banner = $('#postComposeBanner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'postComposeBanner';
            banner.style.cssText = [
                'display:flex', 'align-items:flex-start', 'gap:0.75rem',
                'padding:1rem 1.25rem', 'border-radius:12px', 'margin-bottom:0.75rem',
                'background:linear-gradient(135deg,#fff5f5,#fed7d7)',
                'border:2px solid #fc8181', 'color:#c53030',
                'font-size:0.92rem', 'line-height:1.5', 'animation:slideIn 0.3s ease',
            ].join(';');
            // Insert before the textarea inside the post-composer
            const composer = postContent ? postContent.closest('.post-composer') || postContent.parentNode : null;
            if (composer) composer.insertBefore(banner, composer.firstChild);
        }
        banner.innerHTML = `<span style="font-size:1.4rem;flex-shrink:0">🚫</span><span>${msg}</span>`;
        banner.style.display = 'flex';
        // Auto-hide after 8 s
        clearTimeout(banner._hideTimer);
        banner._hideTimer = setTimeout(() => { banner.style.display = 'none'; }, 8000);
    }

    function hidePostError() {
        const banner = $('#postComposeBanner');
        if (banner) banner.style.display = 'none';
    }

    // Comment moderation error banner (shown inline above comment input)
    function showCommentError(postId, msg, strikes) {
        const section = $(`#comments-${postId}`);
        if (!section) return;

        // Remove any previous comment error for this post
        let banner = section.querySelector('.net-comment-mod-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.className = 'net-comment-mod-banner';
            banner.style.cssText = [
                'display:flex', 'align-items:flex-start', 'gap:0.6rem',
                'padding:0.8rem 1rem', 'border-radius:10px', 'margin:0.5rem 0',
                'background:linear-gradient(135deg,#fff5f5,#fed7d7)',
                'border:2px solid #fc8181', 'color:#c53030',
                'font-size:0.85rem', 'line-height:1.45', 'animation:slideIn 0.3s ease',
            ].join(';');
            // Insert before the comment input row
            const inputRow = section.querySelector('.net-comment-input');
            if (inputRow && inputRow.parentNode) {
                inputRow.parentNode.insertBefore(banner, inputRow);
            } else {
                section.prepend(banner);
            }
        }
        const strikesInfo = strikes ? `<br><small style="opacity:0.8">⚠️ Avertissements: ${strikes}</small>` : '';
        banner.innerHTML = `<span style="font-size:1.2rem;flex-shrink:0">🚫</span><span>${msg}${strikesInfo}</span>`;
        banner.style.display = 'flex';

        // Auto-hide after 10 seconds
        clearTimeout(banner._hideTimer);
        banner._hideTimer = setTimeout(() => { banner.style.display = 'none'; }, 10000);
    }

    if (postContent) postContent.addEventListener('input', hidePostError);

    if (btnCreatePost) {
        btnCreatePost.addEventListener('click', () => {
            const content = postContent.value.trim();
            if (!content && selectedFiles.length === 0) return;

            hidePostError();
            btnCreatePost.disabled = true;

            // Show contextual loading text: if images attached, tell user we're scanning
            const hasImages = selectedFiles.some(f => f.type.startsWith('image/'));
            btnCreatePost.textContent = hasImages ? '🔍 Analyse de l\'image en cours...' : 'Publication...';

            const form = new FormData();
            form.append('content', content);
            form.append('type', postType ? postType.value : 'post');
            selectedFiles.forEach(f => form.append('media[]', f));

            // Use raw fetch so we can inspect the status code
            fetch(U.createPost, { method: 'POST', body: form })
                .then(async r => {
                    const data = await r.json().catch(() => ({}));
                    if (r.ok && data.success) {
                        window.location.reload();
                    } else if (r.status === 422 && data.error) {
                        // Content moderation rejection – show a clear, friendly banner
                        const strikesNote = data.strikes ? `\n⚠️ Avertissements: ${data.strikes}` : '';
                        showPostError(data.error + strikesNote);
                        // Clear the rejected media so the user can replace it
                        selectedFiles = [];
                        if (mediaImage) mediaImage.value = '';
                        if (mediaVideo) mediaVideo.value = '';
                        showMediaPreview();
                    } else {
                        showPostError(data.error || 'Erreur lors de la publication. Veuillez réessayer.');
                    }
                })
                .catch(() => {
                    showPostError('Problème de connexion. Veuillez réessayer.');
                })
                .finally(() => {
                    btnCreatePost.disabled = false;
                    btnCreatePost.textContent = 'Publier';
                });
        });
    }

    // ─── Post Actions (Like, Comment, Share, Delete) ──────────────────
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        const action = btn.dataset.action;
        const postId = btn.dataset.postId;

        if (action === 'like') handleLike(btn, postId);
        if (action === 'comment') toggleComments(postId);
        if (action === 'share') openShareModal(postId);
    });

    // Delete post
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.net-post-menu');
        if (!btn) return;
        const postId = btn.dataset.postId;
        if (confirm('Supprimer ce post ?')) {
            api(urlFor(U.deletePost, postId), { method: 'POST' })
                .then(data => {
                    if (data.success) {
                        btn.closest('.net-post').remove();
                    }
                });
        }
    });

    function handleLike(btn, postId) {
        api(urlFor(U.likePost, postId), { method: 'POST' })
            .then(data => {
                if (data.success) {
                    btn.classList.toggle('liked', data.liked);
                    btn.innerHTML = (data.liked ? '❤️' : '🤍') + ' J\'aime';
                    const countEl = $(`#likeCount-${postId}`);
                    if (countEl) countEl.textContent = data.count + ' ❤️';
                }
            });
    }

    function toggleComments(postId) {
        const section = $(`#comments-${postId}`);
        if (!section) return;

        if (section.style.display === 'none') {
            section.style.display = 'block';
            loadComments(postId);
        } else {
            section.style.display = 'none';
        }
    }

    function loadComments(postId) {
        const list = $(`#commentsList-${postId}`);
        if (!list) return;
        list.innerHTML = '<small>Chargement...</small>';

        api(urlFor(U.getComments, postId))
            .then(data => {
                if (!data.comments || data.comments.length === 0) {
                    list.innerHTML = '<small style="color:#999;">Aucun commentaire</small>';
                    return;
                }
                list.innerHTML = data.comments.map(c => `
                    <div class="net-comment-item">
                        ${avatarHtml(c.userAvatar, c.userName)}
                        <div class="net-comment-bubble">
                            <strong>${c.userName}</strong>
                            <p>${c.content}</p>
                            <small>${timeAgo(c.createdAt)}</small>
                        </div>
                    </div>
                `).join('');
            });
    }

    // Comment submission
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.net-comment-send');
        if (!btn) return;
        const postId = btn.dataset.postId;
        const input = $(`.net-comment-input[data-post-id="${postId}"]`);
        if (!input || !input.value.trim()) return;

        const content = input.value.trim();
        input.value = '';
        const sendBtn = btn;
        sendBtn.disabled = true;

        fetch(urlFor(U.commentPost, postId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ content })
        })
        .then(async r => {
            const data = await r.json().catch(() => ({}));
            if (r.ok && data.success) {
                loadComments(postId);
            } else if (r.status === 422 && data.error) {
                // Text moderation rejection – show inline alert near the comment input
                showCommentError(postId, data.error, data.strikes);
            } else if (data.error) {
                showCommentError(postId, data.error);
            }
        })
        .catch(() => {
            showCommentError(postId, 'Problème de connexion. Veuillez réessayer.');
        })
        .finally(() => {
            sendBtn.disabled = false;
        });
    });

    // Enter key for comments
    document.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && e.target.classList.contains('net-comment-input')) {
            const postId = e.target.dataset.postId;
            const btn = $(`.net-comment-send[data-post-id="${postId}"]`);
            if (btn) btn.click();
        }
    });

    // Comment count click → toggle comments
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('net-comment-count')) {
            toggleComments(e.target.dataset.postId);
        }
    });

    // ─── Invitations ──────────────────────────────────────────────────
    // Send invite (from suggestions or profile page)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.net-invite-btn');
        if (!btn) return;
        const userId = btn.dataset.userId;

        btn.disabled = true;
        btn.textContent = 'Envoi...';

        api(urlFor(U.sendInvite, userId), { method: 'POST' })
            .then(data => {
                if (data.success) {
                    btn.textContent = '✅ Envoyée';
                    btn.style.background = '#66bb6a';
                } else {
                    btn.textContent = data.error || 'Erreur';
                    btn.style.background = '#ef5350';
                    setTimeout(() => { btn.textContent = '+ Inviter'; btn.disabled = false; btn.style.background = ''; }, 2000);
                }
            });
    });

    const btnInvites = $('#btnInvites');
    if (btnInvites) {
        btnInvites.addEventListener('click', () => {
            $$('.net-tab').forEach(t => t.classList.remove('active'));
            $$('.net-tab-content').forEach(tc => tc.classList.remove('active'));
            $(`.net-tab[data-tab="friends"]`).classList.add('active');
            $('#tab-friends').classList.add('active');
            loadPendingInvites();
            loadFriends();
        });
    }

    function loadPendingInvites() {
        const container = $('#pendingInvites');
        if (!container) return;
        container.innerHTML = '<small>Chargement...</small>';

        api(U.pendingInvites).then(data => {
            if (!data.invites || data.invites.length === 0) {
                container.innerHTML = '<p class="net-empty" style="padding:1rem;">Aucune invitation en attente</p>';
                return;
            }
            container.innerHTML = data.invites.map(inv => `
                <div class="net-invite-card" data-invite-id="${inv.id}">
                    ${avatarHtml(inv.senderAvatar, inv.senderName)}
                    <div class="net-invite-info">
                        <strong>${inv.senderName}</strong>
                        <small>${inv.senderEmail} · ${timeAgo(inv.createdAt)}</small>
                    </div>
                    <div class="net-invite-actions">
                        <button class="net-accept-btn" data-invite-id="${inv.id}">✅ Accepter</button>
                        <button class="net-reject-btn" data-invite-id="${inv.id}">❌ Refuser</button>
                    </div>
                </div>
            `).join('');
        });
    }

    document.addEventListener('click', (e) => {
        const acceptBtn = e.target.closest('.net-accept-btn');
        const rejectBtn = e.target.closest('.net-reject-btn');

        if (acceptBtn) {
            const invId = acceptBtn.dataset.inviteId;
            api(urlFor(U.acceptInvite, invId), { method: 'POST' }).then(data => {
                if (data.success) {
                    acceptBtn.closest('.net-invite-card').remove();
                    loadFriends();
                }
            });
        }

        if (rejectBtn) {
            const invId = rejectBtn.dataset.inviteId;
            api(urlFor(U.rejectInvite, invId), { method: 'POST' }).then(data => {
                if (data.success) rejectBtn.closest('.net-invite-card').remove();
            });
        }
    });

    // ─── Friends ──────────────────────────────────────────────────────
    function loadFriends() {
        const container = $('#friendsList');
        if (!container) return;
        container.innerHTML = '<small>Chargement...</small>';

        api(U.friends).then(data => {
            if (!data.friends || data.friends.length === 0) {
                container.innerHTML = '<p class="net-empty" style="padding:1rem;">Aucun ami pour le moment</p>';
                return;
            }
            container.innerHTML = data.friends.map(f => `
                <div class="net-friend-card">
                    ${avatarHtml(f.avatar, f.name)}
                    <div class="net-friend-info">
                        <strong>${f.name}</strong>
                        <small>${f.email}</small>
                    </div>
                    <a class="net-msg-friend-btn" onclick="openConversation(${f.id}, '${f.name.replace(/'/g, "\\'")}', '${f.avatar || ''}')">💬</a>
                </div>
            `).join('');
        });
    }

    // ─── Conversations & Messaging ────────────────────────────────────
    const btnMessages = $('#btnMessages');
    if (btnMessages) {
        btnMessages.addEventListener('click', () => {
            $$('.net-tab').forEach(t => t.classList.remove('active'));
            $$('.net-tab-content').forEach(tc => tc.classList.remove('active'));
            $(`.net-tab[data-tab="messages"]`).classList.add('active');
            $('#tab-messages').classList.add('active');
            loadConversations();
        });
    }

    function loadConversations() {
        const container = $('#conversationsList');
        if (!container) return;
        container.innerHTML = '<small style="padding:1rem;display:block;">Chargement...</small>';

        api(U.conversations).then(data => {
            if (!data.conversations || data.conversations.length === 0) {
                container.innerHTML = '<p class="net-empty" style="padding:1rem;">Aucune conversation</p>';
                return;
            }
            container.innerHTML = data.conversations.map(c => `
                <div class="net-conv-item ${c.id === currentConvId ? 'active' : ''}" 
                     data-conv-id="${c.id}" data-user-id="${c.otherUser.id}"
                     data-user-name="${c.otherUser.name}" data-user-avatar="${c.otherUser.avatar || ''}"
                     onclick="openConversationFromList(this)">
                    ${avatarHtml(c.otherUser.avatar, c.otherUser.name)}
                    <div class="net-conv-info">
                        <strong>${c.otherUser.name}</strong>
                        <small>${c.lastMessage ? (c.lastMessage.isMine ? 'Vous: ' : '') + (c.lastMessage.content || c.lastMessage.type) : 'Nouvelle conversation'}</small>
                    </div>
                </div>
            `).join('');
        });
    }

    window.openConversationFromList = function (el) {
        const convId = parseInt(el.dataset.convId);
        const userId = parseInt(el.dataset.userId);
        const name = el.dataset.userName;
        const avatar = el.dataset.userAvatar;

        // Highlight active
        $$('.net-conv-item').forEach(c => c.classList.remove('active'));
        el.classList.add('active');

        currentConvId = convId;
        currentChatUserId = userId;
        loadChatMessages(convId, name, avatar, userId);
    };

    window.openConversation = function (userId, name, avatar) {
        // Switch to messages tab
        $$('.net-tab').forEach(t => t.classList.remove('active'));
        $$('.net-tab-content').forEach(tc => tc.classList.remove('active'));
        const msgTab = $(`.net-tab[data-tab="messages"]`);
        if (msgTab) msgTab.classList.add('active');
        const msgPanel = $('#tab-messages');
        if (msgPanel) msgPanel.classList.add('active');

        currentChatUserId = userId;
        // Try to find existing conversation
        api(U.conversations).then(data => {
            loadConversations();
            const existing = (data.conversations || []).find(c => c.otherUser.id === userId);
            if (existing) {
                currentConvId = existing.id;
                loadChatMessages(existing.id, name, avatar, userId);
            } else {
                currentConvId = null;
                renderChatUI(name, avatar, userId, []);
            }
        });
    };

    function loadChatMessages(convId, name, avatar, userId) {
        api(urlFor(U.getMessages, convId)).then(data => {
            renderChatUI(name, avatar, userId, data.messages || []);
        });
    }

    function renderChatUI(name, avatar, userId, messages) {
        const chat = $('#chatArea');
        if (!chat) return;

        chat.innerHTML = `
            <div class="net-msg-chat-header">
                ${avatarHtml(avatar, name)}
                <strong>${name}</strong>
            </div>
            <div class="net-msg-messages" id="msgList">
                ${messages.map(m => renderMessage(m)).join('')}
            </div>
            <div class="net-msg-input-area">
                <label class="net-msg-attach-btn" title="Joindre">
                    📎
                    <input type="file" id="msgAttachment" accept="image/*,video/*,audio/*" style="display:none;">
                </label>
                <button class="net-msg-voice-btn" id="btnVoice" title="Message vocal">🎤</button>
                <input type="text" class="net-msg-text-input" id="msgTextInput" placeholder="Écrire un message...">
                <button class="net-msg-send-btn" id="btnSendMsg" title="Envoyer">➤</button>
            </div>
        `;

        // Scroll to bottom
        const msgList = $('#msgList');
        if (msgList) msgList.scrollTop = msgList.scrollHeight;

        // Wire events
        $('#btnSendMsg').addEventListener('click', () => sendTextMessage(userId));
        $('#msgTextInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendTextMessage(userId);
        });

        $('#msgAttachment').addEventListener('change', (e) => {
            if (e.target.files[0]) sendFileMessage(userId, e.target.files[0]);
        });

        $('#btnVoice').addEventListener('click', toggleVoiceRecording.bind(null, userId));
    }

    function renderMessage(m) {
        const cls = m.isMine ? 'mine' : 'theirs';
        let content = '';

        if (m.type === 'voice' && m.attachment) {
            content = `<audio controls src="${m.attachment}" style="max-width:200px;"></audio>`;
        } else if (m.type === 'image' && m.attachment) {
            content = `<img src="${m.attachment}" alt="" style="max-width:200px;border-radius:8px;">`;
        } else if (m.type === 'video' && m.attachment) {
            content = `<video controls src="${m.attachment}" style="max-width:200px;"></video>`;
        } else if (m.type === 'post_share' && m.sharedPost) {
            content = `<div style="background:#f5f5f5;padding:0.5rem;border-radius:8px;margin-bottom:0.3rem;">
                <small>📌 ${m.sharedPost.authorName}</small><br>${m.sharedPost.content || 'Post'}
            </div>`;
            if (m.content) content += `<p style="margin:0;">${m.content}</p>`;
        } else {
            content = m.content || '';
        }

        return `<div class="net-msg-bubble ${cls}">
            ${content}
            <small>${timeAgo(m.createdAt)}</small>
        </div>`;
    }

    function sendTextMessage(userId) {
        const input = $('#msgTextInput');
        const text = input.value.trim();
        if (!text) return;
        input.value = '';

        api(urlFor(U.sendMessage, userId), {
            method: 'POST',
            body: JSON.stringify({ content: text })
        }).then(data => {
            if (data.success) {
                if (data.conversationId) currentConvId = data.conversationId;
                appendMessage(data.message);
            }
        });
    }

    function sendFileMessage(userId, file) {
        const form = new FormData();
        form.append('attachment', file);
        form.append('content', '');

        api(urlFor(U.sendMessage, userId), {
            method: 'POST',
            body: form,
            headers: {}
        }).then(data => {
            if (data.success) {
                if (data.conversationId) currentConvId = data.conversationId;
                appendMessage(data.message);
            }
        });
    }

    function appendMessage(m) {
        const list = $('#msgList');
        if (!list) return;
        const div = document.createElement('div');
        div.innerHTML = renderMessage(m);
        list.appendChild(div.firstElementChild);
        list.scrollTop = list.scrollHeight;
    }

    // ─── Voice Recording ──────────────────────────────────────────────
    function toggleVoiceRecording(userId) {
        const btn = $('#btnVoice');
        if (!btn) return;

        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            btn.classList.remove('recording');
            btn.textContent = '🎤';
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(stream => {
                audioChunks = [];
                mediaRecorder = new MediaRecorder(stream);
                mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
                mediaRecorder.onstop = () => {
                    stream.getTracks().forEach(t => t.stop());
                    const blob = new Blob(audioChunks, { type: 'audio/webm' });
                    const file = new File([blob], 'voice_message.webm', { type: 'audio/webm' });
                    sendFileMessage(userId, file);
                };
                mediaRecorder.start();
                btn.classList.add('recording');
                btn.textContent = '⏹️';
            })
            .catch(err => {
                alert('Impossible d\'accéder au microphone');
                console.error(err);
            });
    }

    // ─── Share Post ───────────────────────────────────────────────────
    let sharePostId = null;

    function openShareModal(postId) {
        sharePostId = postId;
        const modal = $('#shareModal');
        if (!modal) return;
        modal.style.display = 'flex';

        const list = $('#shareFriendsList');
        list.innerHTML = '<small>Chargement de vos amis...</small>';

        api(U.friends).then(data => {
            if (!data.friends || data.friends.length === 0) {
                list.innerHTML = '<p>Aucun ami pour le partage</p>';
                return;
            }
            list.innerHTML = data.friends.map(f => `
                <div class="net-share-friend-item">
                    ${avatarHtml(f.avatar, f.name)}
                    <strong>${f.name}</strong>
                    <button class="net-share-send-btn" data-friend-id="${f.id}">Envoyer</button>
                </div>
            `).join('');
        });
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.net-share-send-btn');
        if (!btn || !sharePostId) return;
        const friendId = btn.dataset.friendId;

        btn.disabled = true;
        btn.textContent = '...';

        api(U.sharePost, {
            method: 'POST',
            body: JSON.stringify({ postId: sharePostId, friendId: parseInt(friendId) })
        }).then(data => {
            btn.textContent = data.success ? '✅' : '❌';
        });
    });

    const closeShare = $('#closeShare');
    if (closeShare) {
        closeShare.addEventListener('click', () => {
            $('#shareModal').style.display = 'none';
            sharePostId = null;
        });
    }

    // ─── Settings Modal ───────────────────────────────────────────────
    const btnSettings = $('#btnSettings');
    const settingsModal = $('#settingsModal');
    const closeSettings = $('#closeSettings');

    if (btnSettings && settingsModal) {
        btnSettings.addEventListener('click', () => settingsModal.style.display = 'flex');
    }
    if (closeSettings && settingsModal) {
        closeSettings.addEventListener('click', () => settingsModal.style.display = 'none');
    }

    // Privacy toggle
    const privacyToggle = $('#privacyToggle');
    if (privacyToggle) {
        privacyToggle.addEventListener('change', () => {
            api(U.togglePrivacy, { method: 'POST' }).then(data => {
                if (data.success) {
                    const label = privacyToggle.closest('.net-setting-row').querySelector('span');
                    if (label) label.textContent = 'Profil ' + (data.isPublic ? 'Public' : 'Privé');
                }
            });
        });
    }

    // Close modals on backdrop click
    $$('.net-modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.style.display = 'none';
        });
    });

    // ─── Polling for new messages (simple approach) ───────────────────
    setInterval(() => {
        if (currentConvId) {
            // Light check: could be refined with last-message-id
        }
    }, 10000);

})();
