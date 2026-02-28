/**
 * Nexus AI API Integration
 * OpenRouter API with Web Search, Database, and UI Actions
 */

class SmartChatAPI {
    constructor() {
        // Validation: Ensure running on server
        if (window.location.protocol === 'file:') {
            alert("⚠️ Please open this file using http://localhost/chat/index.html\n\nThe AI backend requires a server (XAMPP) to function correctly.");
            console.error("CRITICAL: Running on file:// protocol. PHP proxy will not work.");
        }
        // OpenRouter API Configuration (via local proxy)
        this.apiKey = ""; // Handled in proxy.php
        this.apiUrl = "./proxy.php"; // Relative path handles localhost/127.0.0.1/IP automatically
        this.model = "openrouter/free";

        // Database API Configuration (requires XAMPP)
        this.dbApiUrl = "./db_api.php";
        this.userApiUrl = "./user_api.php";

        // Current user context (loaded on init)
        this.currentUser = null;
        this.userSummary = null;

        // Load user context on startup
        this.loadUserContext();

        // System prompt for the AI (dynamic - includes current time + user context + ML insights + profile analysis)
        this.getSystemPrompt = (mlInsights = null, profileContext = null) => {
            const now = new Date();
            const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
            const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

            // Build user context string
            let userContext = '';
            if (this.currentUser) {
                const u = this.currentUser;
                userContext = `
=== CURRENT USER (LOGGED IN) ===
Name: ${u.first_name} ${u.last_name}
Email: ${u.email}
User ID: ${u.id}
Phone: ${u.phone || 'N/A'}
Role: ${u.roles}
Status: ${u.status}
Location: ${u.ville || ''}, ${u.pays || ''}
Address: ${u.adresse || 'N/A'}
Date of Birth: ${u.date_naissance || 'N/A'}
Domain: ${u.user_domain || 'N/A'}
Account Created: ${u.created_at}

=== USER DATA SUMMARY ===
Health Journal Entries: ${this.userSummary?.health_entries || 0}
Activity Participations: ${this.userSummary?.participations || 0}
Diet Requests: ${this.userSummary?.diet_requests || 0}
Prescribed Diets: ${this.userSummary?.prescribed_diets || 0}
Service Requests: ${this.userSummary?.service_requests || 0}
Treatments: ${this.userSummary?.treatments || 0}
Unread Notifications: ${this.userSummary?.unread_notifications || 0}

When the user asks about their data, use their User ID (${u.id}) to query the database.
Address the user by their first name: ${u.first_name}.
`;
            }

            return `You are Nexus, a powerful and friendly AI assistant for the WANNASNI platform — a senior care management system.
You can: answer questions, analyze images, search the web, control the UI, query the WANNASNI database, analyze files, run code, open websites, and tell the time.
ALWAYS use markdown formatting in your responses (bold, code blocks, lists, headers, tables).
${userContext}
=== CURRENT DATE & TIME ===
Right now it is: ${timeStr} on ${dateStr}.
When the user asks "what time is it" or anything about the current date/time, use this information.

=== WANNASNI DATABASE SCHEMA ===
You have FULL access to the wannasni database. Here are ALL the tables:

**user** — All users (seniors, caregivers, admins, nutritionists)
Columns: id, email, password, roles (JSON), first_name, last_name, phone, status, created_at, last_login_at, image_profil, date_naissance, adresse, ville, code_postal, pays, location, specialite, tarif_horaire, disponible, user_domain

**activites** — Activities/events for seniors
Columns: id, title, description, type, start_time, end_time, location, max_participants, current_participants, coach_id, is_active

**participations** — Senior participation in activities
Columns: id, senior_id, status, registration_date, feedback_rating, feedback_comment, activity_id, title, mood_before, mood_after, recommend_to_friends, share_with_family

**health_journal** — Health tracking entries
Columns: id, date, humeur, qualite_sommeil, appetit, niveau_douleur, symptomes, tension_arterielle, frequence_cardiaque, temperature, medicaments_pris, activite_physique, hydratation, notes, senior_id

**demande_regime** — Diet/nutrition requests
Columns: id, senior_id, nutritionniste_id, date_demande, statut, type_regime_souhaite, objectif_principal, allergies, intolerances, habitudes_alimentaires, budget_mensuel, user_id

**regime_prescrit** — Prescribed diet plans
Columns: id, senior_id, date_prescription, date_debut, date_fin, type_regime, calories_journalieres, repas_par_jour, aliments_recommandes, aliments_interdits, hydratation_quotidienne, recommandations_speciales, suivi_requis, demande_id, user_id

**service_request** — Service requests (transport, shopping, etc.)
Columns: id, senior_telephone, senior_email, type_service, description, adresse, ville, niveau_urgence, date_souhaitee, budget_minimum, budget_maximum, statut, created_at, user_id

**intervention** — Technician interventions for services
Columns: id, employe_id, types_services, competences, tarif_horaire, zone_intervention, statut_actuel, technicien_nom, technicien_email, date_creation, payment_status, service_request_id

**treatment** — Medical treatments
Columns: id, date_prescription, medicaments, posologie, frequence, date_debut, date_fin, instructions, statut, senior_id, docteur_id

**notification** — System notifications
Columns: id, type, message, related_id, is_read, created_at

When the user asks about their health, activities, diets, services, or any personal data, AUTOMATICALLY query the database using [DB:query:SQL]. You don't need permission — just fetch and present the data.
Examples:
- "How's my health?" → [DB:query:SELECT * FROM health_journal WHERE senior_id = USER_ID ORDER BY date DESC LIMIT 5]
- "Show my activities" → [DB:query:SELECT p.*, a.description FROM participations p JOIN activites a ON p.activity_id = a.id WHERE p.senior_id = USER_ID]
- "What's my diet?" → [DB:query:SELECT * FROM regime_prescrit WHERE user_id = USER_ID ORDER BY date_prescription DESC LIMIT 1]
- "Any notifications?" → [DB:query:SELECT * FROM notification WHERE is_read = 0 ORDER BY created_at DESC]
- "Show all users" → [DB:query:SELECT id, first_name, last_name, email, roles, status FROM user]

=== IMAGE ANALYSIS ===
When a user sends an image:
- **Describe** everything in detail and **read all text** (OCR)
- **Identify** logos, landmarks, objects, code, documents
- Format extracted text in code blocks

=== WEB SEARCH ===
Include [SEARCH:query] when you need current/factual info. Rules:
- Use concise search queries
- Only ONE [SEARCH:...] per response  
- Say "Let me search that! 🔍" and wait for results

=== FILE ANALYSIS ===
When a user uploads a file (PDF, CSV, TXT, code files):
- Analyze the content thoroughly
- For CSV: summarize data, find patterns, show statistics
- For code: review it, explain it, find bugs, suggest improvements
- For text: summarize key points

=== CODE EXECUTION ===
When a user asks you to run code or calculate something:
- Write the code in a fenced code block with the language specified
- Explain what the code does
- If it's simple math/logic, compute the result directly

=== UI ACTIONS ===
Include these for UI changes:
- [ACTION:change_bg] - Change background
- [ACTION:toggle_theme] - Toggle theme
- [ACTION:reset_ui] - Reset appearance

=== OPEN URLs / PC CONTROL ===
When the user asks you to open a website, app, or URL, include:
- [OPEN:https://www.youtube.com] - Open YouTube
- [OPEN:https://www.google.com] - Open Google
- [OPEN:https://mail.google.com] - Open Gmail / mailbox
- [OPEN:https://www.github.com] - Open GitHub
- [OPEN:https://chat.openai.com] - Open ChatGPT
- [OPEN:https://www.twitter.com] - Open Twitter/X
- [OPEN:https://www.instagram.com] - Open Instagram
- [OPEN:https://www.facebook.com] - Open Facebook
- [OPEN:https://www.linkedin.com] - Open LinkedIn
- [OPEN:https://www.reddit.com] - Open Reddit
- [OPEN:https://www.netflix.com] - Open Netflix
- [OPEN:https://www.spotify.com] - Open Spotify
- [OPEN:https://www.twitch.tv] - Open Twitch

=== WANNASNI PLATFORM NAVIGATION ===
For WANNASNI platform pages, use these specific actions:
- [OPEN:/fr/dashboard] - Open Dashboard/Accueil (🏠 Home)
- [OPEN:/fr/my-activities/] - Open Activities/Activités (🎯 Activities page)  
- [OPEN:/fr/my-health/] - Open Health/Santé (❤️ Health page)
- [OPEN:/fr/my-services/] - Open Services (🛎️ Services page)
- [OPEN:/fr/nutrition/] - Open Nutrition (🥗 Nutrition page)
- [OPEN:/fr/profile] - Open Profile/Profil (👤 Profile page)

When the user asks about:
- "show me my activities" or "go to activities" → [OPEN:/fr/my-activities/]
- "show me my health" or "go to health" → [OPEN:/fr/my-health/]  
- "show me services" or "go to services" → [OPEN:/fr/my-services/]
- "show me nutrition" or "go to nutrition" → [OPEN:/fr/nutrition/]
- "go to dashboard" or "go home" → [OPEN:/fr/dashboard]
- "show me my profile" or "go to profile" → [OPEN:/fr/profile]

=== PROFILE ANALYSIS ===
When users ask about themselves, their profile, or missing information, use the [CHECK_PROFILE] action:
- "tell me about myself" → [CHECK_PROFILE] then provide profile analysis
- "what's missing from my profile?" → [CHECK_PROFILE] then list missing fields
- "check my profile" → [CHECK_PROFILE] then show completeness
- "what information do I need to add?" → [CHECK_PROFILE] then provide recommendations

The [CHECK_PROFILE] action will automatically analyze the user's profile at http://127.0.0.1:8000/fr/profile and provide:
- Missing profile fields
- Empty required fields  
- Profile completeness score
- Personalized recommendations for improvement

**IMPORTANT:** When using [OPEN:/fr/profile], the profile page opens in an EMBEDDED PANEL within the chat interface (not a new tab). 
Tell users: "J'ouvre votre profil dans le panneau latéral" or "Profil affiché à droite"
Always offer to open the profile page with [OPEN:/fr/profile] after showing the analysis.

- [OPEN:url] - Open any URL the user requests
You can open ANY website. If the user asks to open something not in the list above, figure out the correct URL yourself. For example:
- "open WhatsApp" → [OPEN:https://web.whatsapp.com]
- "open Amazon" → [OPEN:https://www.amazon.com]
- "open my calendar" → [OPEN:https://calendar.google.com]
- "open Canva" → [OPEN:https://www.canva.com]
- "open Discord" → [OPEN:https://discord.com/app]
Always use the full URL (https://...) for external sites, or relative paths for WANNASNI platform pages. When the user says "open my mail" or "open mailbox", use Gmail. If they ask to search for something, open Google with the search query: [OPEN:https://www.google.com/search?q=query+here]
Confirm to the user that you're opening it.

=== DATABASE ACTIONS ===
For database operations, include:
- [DB:get_tables] - List tables
- [DB:get_schema] - Get schema
- [DB:get_table_data:tablename] - Get data
- [DB:query:SQL] - Run SELECT
- [DB:execute:SQL] - Run INSERT/UPDATE/DELETE

${mlInsights ? `
=== REAL-TIME HEALTH INSIGHTS ===
The ML Engine has provided the following insights for this conversation:

**Health Analysis:**
${mlInsights.health_context ? `
- Overall Health Score: ${mlInsights.health_context.health_summary?.overall_score || 'N/A'}
- Current Mood: ${mlInsights.health_context.health_summary?.mood?.current_level || 'N/A'}/10 (${mlInsights.health_context.health_summary?.mood?.status || 'Unknown'})
- Pain Level: ${mlInsights.health_context.health_summary?.pain?.current_level || 'N/A'}/10 (${mlInsights.health_context.health_summary?.pain?.status || 'Unknown'})` : ''}

**Conversation Insights:**
${mlInsights.conversation_insights ? `
- Sentiment: ${mlInsights.conversation_insights.sentiment?.dominant_emotion || 'neutral'} (${Math.round((mlInsights.conversation_insights.sentiment?.overall_score || 0) * 100)}% confidence)
- Health Concerns Detected: ${mlInsights.conversation_insights.health_concerns?.total_concerns || 0}
- Urgency Level: ${mlInsights.conversation_insights.urgency_assessment?.urgency_level || 'normal'}` : ''}

**Activity Recommendations:**
${mlInsights.activity_recommendations ? mlInsights.activity_recommendations.slice(0, 3).map(rec => `
- ${rec.activity_identifier} (${Math.round(rec.confidence_score * 100)}% match) - Best time: ${rec.optimal_timing?.best_time_of_day || 'anytime'}`).join('') : 'No current recommendations'}

Use this information to provide personalized, health-aware responses. If any urgent health concerns are detected, prioritize addressing them.
` : ''}

${profileContext ? `
=== PROFILE ANALYSIS RESULTS ===
User's profile completeness status:

**Profile Completeness:** ${profileContext.completeness_score}%

${profileContext.missing_fields && profileContext.missing_fields.length > 0 ? `**Missing Fields:**
${profileContext.missing_fields.map(field => `- ${field}`).join('\n')}` : ''}

${profileContext.empty_fields && profileContext.empty_fields.length > 0 ? `**Empty Fields:**
${profileContext.empty_fields.map(field => `- ${field}`).join('\n')}` : ''}

**Auto-Recommendations:**
${profileContext.recommendations ? profileContext.recommendations.join('\n') : 'Profile appears complete'}

When the user asks about their profile or themselves, automatically include [CHECK_PROFILE] in your response to trigger a live profile analysis.
Always offer to help them complete their profile by using [OPEN:/fr/profile] to open their profile page.
` : ''}

Be conversational, helpful, and use emojis! Format code with proper syntax highlighting. Address the user by name when possible. 👋`;
        };

        // Conversation history for context
        this.conversationHistory = [];
        this.dbSchema = null;
    }

    /**
     * Load current user context from the backend
     */
    async loadUserContext() {
        try {
            const res = await fetch(this.userApiUrl);
            const data = await res.json();
            if (data.success) {
                this.currentUser = data.user;
                this.userSummary = data.summary;
                console.log('👤 User context loaded:', data.user.first_name, data.user.last_name);
            }
        } catch (e) {
            console.log('ℹ️ User context not available (XAMPP may not be running)');
        }
    }

    /**
     * Get ML insights from the ML Engine for enhanced health-aware responses
     */
    async getMLInsights(message) {
        const userId = this.currentUser?.id || 1;
        
        try {
            // Get chat enhancement insights
            const chatResponse = await fetch('http://127.0.0.1:5000/api/chat/enhance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    message: message
                })
            });

            // Get activity recommendations
            const activityResponse = await fetch('http://127.0.0.1:5000/api/activities/recommend', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    limit: 3
                })
            });

            const chatData = chatResponse.ok ? await chatResponse.json() : null;
            const activityData = activityResponse.ok ? await activityResponse.json() : null;

            return {
                health_context: chatData?.health_context || null,
                conversation_insights: chatData?.conversation_insights || null,
                activity_recommendations: activityData?.recommendations || null
            };
        } catch (error) {
            console.warn('ML Engine not available:', error);
            return null;
        }
    }

    /**
     * Check user profile at http://127.0.0.1:8000/fr/profile for missing information
     */
    async checkUserProfile() {
        try {
            const response = await fetch('http://127.0.0.1:8000/fr/profile', {
                method: 'GET',
                credentials: 'include', // Include cookies for authentication
            });

            if (!response.ok) {
                throw new Error(`Profile fetch failed: ${response.status}`);
            }

            const profileHtml = await response.text();
            
            // Analyze the profile page to identify missing information
            const missingFields = [];
            const emptyFields = [];
            
            // Check for common profile fields that might be missing
            const fieldChecks = {
                'date_naissance': { pattern: /(date.{0,20}naissance|birth.{0,20}date)/i, label: 'Date de naissance' },
                'phone': { pattern: /(téléphone|phone|mobile)/i, label: 'Numéro de téléphone' },
                'adresse': { pattern: /(adresse|address|domicile)/i, label: 'Adresse' },
                'ville': { pattern: /(ville|city)/i, label: 'Ville' },
                'code_postal': { pattern: /(code.postal|zip.code|postal)/i, label: 'Code postal' },
                'pays': { pattern: /(pays|country)/i, label: 'Pays' },
                'image_profil': { pattern: /(photo|image|avatar|profilepicture)/i, label: 'Photo de profil' },
                'specialite': { pattern: /(spécialité|specialty|expertise)/i, label: 'Spécialité médicale' },
                'emergency_contact': { pattern: /(urgence|emergency|contact.urgence)/i, label: 'Contact d\'urgence' }
            };

            // Check if fields appear to be empty or missing
            for (const [field, check] of Object.entries(fieldChecks)) {
                if (check.pattern.test(profileHtml)) {
                    // Field exists in form, check if it's empty
                    const emptyPatterns = [
                        new RegExp(`value=""[^>]*name="[^"]*${field}`, 'i'),
                        new RegExp(`name="[^"]*${field}"[^>]*value=""`, 'i'),
                        new RegExp(`placeholder="[^"]*${check.label}[^"]*"[^>]*value=""`, 'i'),
                        new RegExp(`<input[^>]*placeholder="[^"]*${check.label}[^"]*"[^>]*>`, 'i')
                    ];
                    
                    if (emptyPatterns.some(pattern => pattern.test(profileHtml))) {
                        emptyFields.push(check.label);
                    }
                } else {
                    missingFields.push(check.label);
                }
            }

            // Check for empty form inputs and required fields
            const emptyInputMatches = profileHtml.match(/value=""[^>]*required|required[^>]*value=""/g);
            const placeholderFields = profileHtml.match(/placeholder="([^"]+)"/g);
            
            if (placeholderFields) {
                placeholderFields.forEach(match => {
                    const placeholderText = match.match(/placeholder="([^"]+)"/)[1];
                    if (placeholderText && !emptyFields.includes(placeholderText)) {
                        emptyFields.push(placeholderText);
                    }
                });
            }

            return {
                status: 'success',
                profile_url: 'http://127.0.0.1:8000/fr/profile',
                missing_fields: missingFields,
                empty_fields: emptyFields,
                completeness_score: Math.max(0, 100 - (missingFields.length + emptyFields.length) * 10),
                recommendations: this.generateProfileRecommendations(missingFields, emptyFields)
            };
            
        } catch (error) {
            console.warn('Profile check failed:', error);
            return {
                status: 'error',
                error: error.message,
                profile_url: 'http://127.0.0.1:8000/fr/profile',
                note: 'Unable to check profile. Please ensure you are logged in.'
            };
        }
    }

    /**
     * Generate recommendations based on missing profile information
     */
    generateProfileRecommendations(missingFields, emptyFields) {
        const recommendations = [];
        
        if (missingFields.length > 0 || emptyFields.length > 0) {
            recommendations.push('📋 **Complétez votre profil** pour bénéficier de services personnalisés');
            
            if (emptyFields.includes('Date de naissance') || emptyFields.includes('date_naissance')) {
                recommendations.push('🎂 Ajoutez votre date de naissance pour des recommandations d\'âge appropriées');
            }
            
            if (emptyFields.includes('Numéro de téléphone') || emptyFields.includes('phone')) {
                recommendations.push('📞 Renseignez votre numéro de téléphone pour les services d\'urgence');
            }
            
            if (emptyFields.includes('Adresse') || emptyFields.includes('adresse')) {
                recommendations.push('🏠 Complétez votre adresse pour les services à domicile');
            }
            
            if (emptyFields.includes('Photo de profil') || emptyFields.includes('image_profil')) {
                recommendations.push('📸 Ajoutez une photo de profil pour personnaliser votre expérience');
            }
            
            if (emptyFields.includes('Contact d\'urgence') || emptyFields.includes('emergency_contact')) {
                recommendations.push('🚨 Définissez un contact d\'urgence pour votre sécurité');
            }
        } else {
            recommendations.push('✅ Votre profil semble complet ! Bravo !');
        }
        
        return recommendations;
    }




    /**
     * Send a message to the AI API
     */
    async sendMessage(message, imageBase64 = null) {
        // Construct User Message Content
        let userContent = message;

        if (imageBase64) {
            userContent = [
                { type: "text", text: message || "Describe this image in detail. Identify everything you see, and if there is any text in the image, read and transcribe it completely." },
                {
                    type: "image_url",
                    image_url: {
                        url: imageBase64,
                        detail: "high"
                    }
                }
            ];
        }

        // Add user message to history
        this.conversationHistory.push({
            role: "user",
            content: userContent
        });

        // Keep only last 10 messages to avoid token limits
        if (this.conversationHistory.length > 10) {
            this.conversationHistory = this.conversationHistory.slice(-10);
        }

        // Get ML insights for health-related messages
        let mlInsights = null;
        try {
            mlInsights = await this.getMLInsights(message);
        } catch (error) {
            console.log("ML Engine not available:", error.message);
        }

        // Check if user is asking about their profile or themselves
        let profileContext = null;
        const profileKeywords = [
            'tell me about myself', 'about me', 'who am i', 'my profile', 
            'my information', 'what information', 'missing information', 
            'complete my profile', 'profile completeness', 'what do i need',
            'personal information', 'check my profile', 'analyze my profile',
            'what\'s missing', 'incomplete profile', 'profile status'
        ];
        
        const isProfileQuery = profileKeywords.some(keyword => 
            message.toLowerCase().includes(keyword.toLowerCase())
        );
        
        if (isProfileQuery) {
            try {
                profileContext = await this.checkUserProfile();
                console.log("Profile analysis triggered for user query");
            } catch (error) {
                console.log("Profile analysis failed:", error.message);
            }
        }

        try {
            const response = await fetch(this.apiUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": "Bearer " + this.apiKey
                },
                body: JSON.stringify({
                    model: this.model,
                    messages: [
                        { role: "system", content: this.getSystemPrompt(mlInsights, profileContext) },
                        ...this.conversationHistory
                    ],
                    temperature: 0.7,
                    max_tokens: 2048
                })
            });

            if (!response.ok) {
                const errorData = await response.text();
                console.error("API Error:", response.status, errorData);
                throw new Error(`API request failed: ${response.status}`);
            }

            const data = await response.json();
            const aiMessage = data.choices[0].message.content;

            // Add AI response to history
            this.conversationHistory.push({
                role: "assistant",
                content: aiMessage
            });

            // Parse response for actions
            return this.parseResponse(aiMessage);

        } catch (error) {
            console.error("Error calling API:", error);
            const errorMessage = error.message || "Unknown error";
            let userMsg = "I'm having trouble connecting right now. 🔄";

            if (errorMessage.includes("401")) userMsg = "Authentication failed. Please check your API key. 🔑";
            if (errorMessage.includes("402")) userMsg = "Insufficient credit. Please top up your API account. 💳";
            if (errorMessage.includes("429")) userMsg = "Too many requests. Please wait a moment! ⏳";
            if (errorMessage.includes("500") || errorMessage.includes("503")) userMsg = "DeepSeek AI service is currently down. �";

            return {
                text: `${userMsg} (Error: ${errorMessage})`,
                action: null
            };
        }
    }

    /**
     * Parse AI response for actions and clean the text
     */
    async parseResponse(response) {
        let action = null;
        let cleanText = response;

        // Check for PROFILE CHECK action
        const profileCheckMatch = response.match(/\[CHECK_PROFILE\]/i);
        if (profileCheckMatch) {
            cleanText = cleanText.replace(/\[CHECK_PROFILE\]/gi, '').trim();
            
            try {
                const profileAnalysis = await this.checkUserProfile();
                
                if (profileAnalysis.status === 'success') {
                    const { missing_fields, empty_fields, completeness_score, recommendations } = profileAnalysis;
                    
                    let profileReport = `\n\n## 📋 **Analyse de votre profil**\n\n`;
                    profileReport += `**Score de complétude:** ${completeness_score}%\n\n`;
                    
                    if (missing_fields.length > 0) {
                        profileReport += `### ❌ **Champs manquants:**\n`;
                        missing_fields.forEach(field => profileReport += `- ${field}\n`);
                        profileReport += `\n`;
                    }
                    
                    if (empty_fields.length > 0) {
                        profileReport += `### ⚠️ **Champs vides:**\n`;
                        empty_fields.forEach(field => profileReport += `- ${field}\n`);
                        profileReport += `\n`;
                    }
                    
                    if (recommendations.length > 0) {
                        profileReport += `### 💡 **Recommandations:**\n`;
                        recommendations.forEach(rec => profileReport += `${rec}\n`);
                        profileReport += `\n`;
                    }
                    
                    profileReport += `### 🔧 **Actions rapides:**\n`;
                    profileReport += `- [Ouvrir mon profil](${profileAnalysis.profile_url}) → [OPEN:/fr/profile]\n`;
                    profileReport += `- [Aller au tableau de bord](/fr/dashboard) → [OPEN:/fr/dashboard]\n\n`;
                    
                    cleanText += profileReport;
                } else {
                    cleanText += `\n\n⚠️ **Impossible d'analyser votre profil**\n\n`;
                    cleanText += `${profileAnalysis.note}\n\n`;
                    cleanText += `Vous pouvez essayer d'[ouvrir votre profil](${profileAnalysis.profile_url}) directement → [OPEN:/fr/profile]`;
                }
            } catch (error) {
                console.error("Profile check error:", error);
                cleanText += `\n\n⚠️ **Erreur lors de l'analyse du profil**\n\nVeuillez [ouvrir votre profil](http://127.0.0.1:8000/fr/profile) manuellement → [OPEN:/fr/profile]`;
            }
        }

        // Check for UI action commands
        const actionMatch = response.match(/\[ACTION:(\w+)\]/i);
        if (actionMatch) {
            action = actionMatch[1];
            cleanText = cleanText.replace(/\[ACTION:\w+\]/gi, '').trim();
        }

        // Check for OPEN URL commands (PC Control)
        const openMatch = response.match(/\[OPEN:([^\]]+)\]/i);
        if (openMatch) {
            const url = openMatch[1].trim();
            action = 'open_url:' + url;
            cleanText = cleanText.replace(/\[OPEN:[^\]]+\]/gi, '').trim();
        }

        // Check for SEARCH action — this triggers web search
        const searchMatch = response.match(/\[SEARCH:([^\]]+)\]/i);
        if (searchMatch) {
            const query = searchMatch[1].trim();
            cleanText = cleanText.replace(/\[SEARCH:[^\]]+\]/gi, '').trim();

            try {
                // Show the "searching" message first, then fetch results
                const searchResults = await this.webSearch(query);

                if (searchResults && searchResults.length > 0) {
                    // Feed results back to AI for a natural summary
                    const summary = await this.summarizeSearchResults(query, searchResults);
                    cleanText += "\n\n" + summary;
                } else {
                    cleanText += `\n\n🔍 I couldn't find specific results for "${query}". You can try searching directly: [Google Search](https://www.google.com/search?q=${encodeURIComponent(query)})`;
                }
            } catch (error) {
                console.error("Search error:", error);
                cleanText += `\n\n⚠️ Search failed. Try searching manually: [Google Search](https://www.google.com/search?q=${encodeURIComponent(query)})`;
            }
        }

        // Check for database action commands
        const dbMatch = response.match(/\[DB:([^\]]+)\]/i);
        if (dbMatch) {
            const dbAction = dbMatch[1];
            cleanText = cleanText.replace(/\[DB:[^\]]+\]/gi, '').trim();

            try {
                const dbResult = await this.executeDatabaseAction(dbAction);
                cleanText += "\n\n" + dbResult;
            } catch (error) {
                console.error("Database action error:", error);
                cleanText += "\n\n⚠️ Database error: " + error.message;
            }
        }

        return {
            text: cleanText,
            action: action
        };
    }

    // ========================
    // WEB SEARCH METHODS
    // ========================

    /**
     * Search the web using Wikipedia API (free, CORS-friendly)
     * Returns an array of search results with titles and summaries
     */
    async webSearch(query) {
        console.log("🔍 Searching for:", query);
        const results = [];

        try {
            // Step 1: Search Wikipedia for matching articles
            const searchUrl = `https://en.wikipedia.org/w/api.php?action=query&list=search&srsearch=${encodeURIComponent(query)}&srlimit=3&format=json&origin=*`;
            const searchResponse = await fetch(searchUrl);
            const searchData = await searchResponse.json();

            if (!searchData.query || !searchData.query.search || searchData.query.search.length === 0) {
                return results;
            }

            // Step 2: Get summaries for the top results
            const titles = searchData.query.search.map(r => r.title);

            for (const title of titles) {
                try {
                    const summaryUrl = `https://en.wikipedia.org/api/rest_v1/page/summary/${encodeURIComponent(title)}`;
                    const summaryResponse = await fetch(summaryUrl);

                    if (summaryResponse.ok) {
                        const summaryData = await summaryResponse.json();
                        results.push({
                            title: summaryData.title,
                            summary: summaryData.extract || "No summary available.",
                            url: summaryData.content_urls?.desktop?.page || `https://en.wikipedia.org/wiki/${encodeURIComponent(title)}`,
                            thumbnail: summaryData.thumbnail?.source || null
                        });
                    }
                } catch (e) {
                    console.warn("Failed to get summary for:", title, e);
                }
            }
        } catch (error) {
            console.error("Wikipedia search failed:", error);
        }

        return results;
    }

    /**
     * Feed search results back to the AI for a natural language summary
     */
    async summarizeSearchResults(query, results) {
        const resultsText = results.map((r, i) =>
            `[${i + 1}] "${r.title}": ${r.summary}`
        ).join('\n\n');

        const summaryPrompt = `Based on the following search results for "${query}", provide a helpful, 
concise summary. Include relevant facts and figures. Add source links at the end.
Be conversational and use emojis.

SEARCH RESULTS:
${resultsText}

SOURCES:
${results.map((r, i) => `[${i + 1}] ${r.title}: ${r.url}`).join('\n')}

Respond naturally as if you found this information for the user. Include the source links formatted nicely at the end.`;

        try {
            const response = await fetch(this.apiUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": "Bearer " + this.apiKey
                },
                body: JSON.stringify({
                    model: this.model,
                    messages: [
                        { role: "system", content: "You are a helpful assistant that summarizes search results concisely and naturally. Use emojis and be friendly." },
                        { role: "user", content: summaryPrompt }
                    ],
                    temperature: 0.5,
                    max_tokens: 1024
                })
            });

            if (!response.ok) {
                throw new Error(`Summary API failed: ${response.status}`);
            }

            const data = await response.json();
            return data.choices[0].message.content;

        } catch (error) {
            console.error("Summary generation failed:", error);

            // Fallback: format results manually
            let fallback = `🔍 **Search Results for "${query}":**\n\n`;
            results.forEach((r, i) => {
                fallback += `**${i + 1}. ${r.title}**\n${r.summary.substring(0, 200)}...\n🔗 ${r.url}\n\n`;
            });
            return fallback;
        }
    }

    // ========================
    // DATABASE METHODS
    // ========================

    /**
     * Execute database actions
     */
    async executeDatabaseAction(dbAction) {
        const parts = dbAction.split(':');
        const command = parts[0];
        const param = parts.slice(1).join(':');

        let requestBody = {};

        switch (command) {
            case 'get_schema':
                requestBody = { action: 'get_schema' };
                break;
            case 'get_tables':
                requestBody = { action: 'get_tables' };
                break;
            case 'get_table_data':
                requestBody = { action: 'get_table_data', table: param };
                break;
            case 'query':
                requestBody = { action: 'query', sql: param };
                break;
            case 'execute':
                requestBody = { action: 'execute', sql: param };
                break;
            default:
                throw new Error(`Unknown database command: ${command}`);
        }

        const response = await fetch(this.dbApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestBody)
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Database request failed');
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Database operation failed');
        }

        return this.formatDatabaseResult(command, result.data);
    }

    /**
     * Format database results for display
     */
    formatDatabaseResult(command, data) {
        switch (command) {
            case 'get_tables':
                return `📋 **Tables in database:**\n${data.map(t => `  • ${t}`).join('\n')}`;

            case 'get_schema':
                let schemaText = '📊 **Database Schema:**\n\n';
                for (const [table, columns] of Object.entries(data)) {
                    schemaText += `**${table}**\n`;
                    columns.forEach(col => {
                        schemaText += `  • ${col.name} (${col.type})${col.key === 'PRI' ? ' 🔑' : ''}\n`;
                    });
                    schemaText += '\n';
                }
                return schemaText;

            case 'get_table_data':
            case 'query':
                if (!data || data.length === 0) {
                    return '📭 No data found.';
                }

                let tableText = `📊 **Results (${data.length} rows):**\n\n`;
                const displayData = data.slice(0, 5);
                displayData.forEach((row, idx) => {
                    tableText += `**Row ${idx + 1}:**\n`;
                    for (const [key, value] of Object.entries(row)) {
                        tableText += `  ${key}: ${value}\n`;
                    }
                    tableText += '\n';
                });

                if (data.length > 5) {
                    tableText += `... and ${data.length - 5} more rows`;
                }
                return tableText;

            case 'execute':
                return `✅ **Success!** Affected ${data.affected_rows} row(s)${data.insert_id ? `, Insert ID: ${data.insert_id}` : ''}`;

            default:
                return JSON.stringify(data, null, 2);
        }
    }

    /**
     * Clear conversation history
     */
    clearHistory() {
        this.conversationHistory = [];
    }
}

// Export a global instance
window.SmartChatAPI = new SmartChatAPI();
