/**
 * Nexus AI API Integration
 * OpenRouter API with Web Search, Database, and UI Actions
 */

if (typeof SmartChatAPI === 'undefined') {
class SmartChatAPI {
    constructor() {
        // Validation: Ensure running on server
        if (window.location.protocol === 'file:') {
            alert("⚠️ Please open this file using http://localhost/chat/index.html\n\nThe AI backend requires a server (XAMPP) to function correctly.");
            console.error("CRITICAL: Running on file:// protocol. PHP proxy will not work.");
        }
        // OpenRouter API Configuration (via Symfony proxy)
        this.apiKey = ""; // Handled in Symfony ChatController
        
        // Get current locale from URL (fr, en, ar)
        const currentPath = window.location.pathname;
        const locale = currentPath.match(/^\/([a-z]{2})\//)?.[1] || 'fr';
        this.locale = locale; // store for use in system prompt
        
        this.apiUrl = `/${locale}/api/chat/proxy`; // Dynamic Symfony route
        this.model = "meta-llama/llama-3-8b-instruct:free"; // Try different Llama version
        
        // Working free models (updated list)
        this.freeModels = [
            "openai/gpt-3.5-turbo",
            "anthropic/claude-3-haiku:beta", 
            "google/gemini-flash-1.5",
            "meta-llama/codellama-34b-instruct",
            "microsoft/wizardlm-2-8x22b"
        ];
        
        // Fallback responses for when all APIs fail
        this.fallbackEnabled = true;
        this.currentModelIndex = 0;

        // Database API Configuration (Symfony)
        this.dbApiUrl = `/${locale}/api/chat/db-query`;
        this.userApiUrl = `/${locale}/api/chat/user-context`;
        this.profileUpdateUrl = `/${locale}/api/chat/profile-update`;
        
        // Debug: Log API URLs for troubleshooting
        console.log(`🌐 Chat API URLs initialized for locale '${locale}':`, {
            proxy: this.apiUrl,
            database: this.dbApiUrl,
            userContext: this.userApiUrl
        });

        // Current user context (loaded on init)
        this.currentUser = null;
        this.userSummary = null;

        // Load user context on startup
        this.loadUserContext();

        // System prompt for the AI (dynamic - includes current time + user context)
        this.getSystemPrompt = () => {
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
City: ${u.ville || 'N/A'}
Country: ${u.pays || 'N/A'}
Address: ${u.adresse || 'N/A'}
Postal Code: ${u.code_postal || 'N/A'}
Location: ${u.location || 'N/A'}
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
IMPORTANT: NEVER display or mention the following sensitive fields in your responses: id, password, reset_token, verification_code, face_encoding, face_image_path, senior_id, user_id, coach_id, docteur_id, employe_id, or any internal foreign key IDs. Always omit them silently.
`;
            }

            return `You are Nexus, a powerful and friendly AI assistant for the WANNASNI platform — a senior care management system.
You can: answer questions, analyze images, search the web, control the UI, query the WANNASNI database, analyze files, run code, open websites, and tell the time.
ALWAYS use markdown formatting in your responses (bold, code blocks, lists, headers, tables).

=== LANGUAGE & DIALECT POLICY ===
You are fully multilingual. You understand and speak:
- **English** — standard and informal
- **French** — standard and informal
- **Arabic** (Modern Standard + Tunisian/Darija dialect in Arabic script — e.g. كيفاش، بالله، نحب، وقتاش، شنوا، موش، باهي، برشا)
- **Tunisian dialect in Latin script (Franco-Arab)** — e.g. chnowa, kifech, nheb, wqtesh, moch, bahi, barcha, 3andi, ya kho
- **Mixed-language messages** — understand any combination of the above in a single sentence

RULES:
1. ALWAYS detect the dominant language/dialect of the user's message.
2. ALWAYS reply in the SAME language and dialect the user used. If they write in Tunisian darija, respond in Tunisian darija (matching their script — Arabic or Latin).
3. If the message is mixed, reply in the dominant language with natural blending.
4. NEVER switch to English unless the user writes in English.
5. For Tunisian responses, be warm and friendly — use نحبك (nhebek), باهي (bahi), يزي (yezi), الله يعاونك (allah y3awnek), etc. naturally.
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
- [OPEN:url] - Open any URL the user requests
You can open ANY website. If the user asks to open something not in the list above, figure out the correct URL yourself. For example:
- "open WhatsApp" → [OPEN:https://web.whatsapp.com]
- "open Amazon" → [OPEN:https://www.amazon.com]
- "open my calendar" → [OPEN:https://calendar.google.com]
- "open Canva" → [OPEN:https://www.canva.com]
- "open Discord" → [OPEN:https://discord.com/app]
Always use the full URL (https://...). When the user says "open my mail" or "open mailbox", use Gmail. If they ask to search for something, open Google with the search query: [OPEN:https://www.google.com/search?q=query+here]
Confirm to the user that you're opening it.

=== DATABASE ACTIONS ===
For database operations, include:
- [DB:get_tables] - List tables
- [DB:get_schema] - Get schema
- [DB:get_table_data:tablename] - Get data
- [DB:query:SQL] - Run SELECT
- [DB:execute:SQL] - Run INSERT/UPDATE/DELETE

=== PROFILE COMPLETION ===
When the user asks about their profile, completeness, or missing information:
1. Check the user context above — the profile fields are: First Name, last name, Phone, Date of Birth, Address, City, Postal Code, Country, Location.
2. A field is missing/incomplete if it is null, empty, or "N/A".
3. List clearly which fields are missing with friendly labels:
   - firstName → Prénom (First Name)
   - lastName → Nom (Last Name)
   - phone → Téléphone (Phone)
   - dateNaissance → Date de naissance (Date of Birth) — format YYYY-MM-DD
   - adresse → Adresse (Street Address)
   - ville → Ville (City)
   - codePostal → Code Postal (Postal Code)
   - pays → Pays (Country)
   - location → Localisation (Location)
4. After listing missing fields, say: "Voulez-vous que je complète votre profil? Donnez-moi les informations manquantes et je les enregistrerai pour vous! 😊"
5. When the user provides information, extract the values and include [PROFILE:update:{"field":"value"}] at the END of your response.
   Examples:
   - User says "my first name is John" → [PROFILE:update:{"firstName":"John"}]
   - User says "my city is Paris and phone is 0612345678" → [PROFILE:update:{"ville":"Paris","phone":"0612345678"}]
   - User says "I was born on March 15, 1950" → [PROFILE:update:{"dateNaissance":"1950-03-15"}]
   - Multiple fields: [PROFILE:update:{"firstName":"Marie","lastName":"Dupont","phone":"0612345678","ville":"Lyon","pays":"France"}]
6. AFTER the [PROFILE:update:...] is processed, you'll receive a system result. If complete=true, say "🎉 Votre profil est maintenant complet!". If still missing fields, list what remains.
7. IMPORTANT: Always include exactly ONE [PROFILE:update:{...}] tag — make sure the JSON inside is valid.
8. Do NOT ask for password, email, or image — only the fields listed above.

=== INTERNAL APP NAVIGATION ===
When the user asks to go to a section of the app, navigate using [OPEN:/{_locale_}/path] where {_locale_} is replaced by the current locale: ${locale}.
Navigation map (use the EXACT paths below, replacing {_locale_} with "${locale}"):
- main page / home page / page principale / الرئيسية / الصفحة الرئيسية / الصفحة / ادخل / دخلني / خذني / امشي لـ / page d'accueil / dashboard / tableau de bord → [OPEN:/${locale}/dashboard]
- home / début / الصفحة الأولى → [OPEN:/${locale}/]
- profile / profil / ملف شخصي / البروفايل / بروفيلي / mon profil → [OPEN:/${locale}/profile]
- activities / mes activités / activités / أنشطتي / الأنشطة / نشاطات → [OPEN:/${locale}/my-activities]
- health / journal de santé / santé / صحتي / دفتر الصحة / صحة → [OPEN:/${locale}/health/journal]
- nutrition / régime / alimentation / تغذية / نظام غذائي → [OPEN:/${locale}/nutrition]
- services / mes services / الخدمات / خدماتي → [OPEN:/${locale}/my-services]
- treatment / traitement / علاج / علاجي / دواء → [OPEN:/${locale}/treatment]
- loyalty / fidélité / نقاط الولاء → [OPEN:/${locale}/loyalty]
- networking / réseau / تواصل → [OPEN:/${locale}/networking]
- messages / messagerie / الرسائل / رسائلي → [OPEN:/${locale}/networking/messages]
- subscription / abonnement / اشتراك → [OPEN:/${locale}/subscription]

For Tunisian phrases like "خذني للميان", "امشي للداشبورد", "حب نشوف صحتي", "فتحلي البروفيل", "roh l dashboard", "beh t3addi lel activities" — detect the intent and use the corresponding [OPEN:...] tag.
Always confirm navigation in the same language the user used.

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

        // Try models with automatic fallback
        return await this.tryModelsWithFallback();
    }
    
    async tryModelsWithFallback() {
        try {
            // The Symfony backend handles model selection (Ollama → Gemini fallback)
            // We just need to send the request once
            console.log(`🔄 Sending to backend proxy: ${this.apiUrl}`);
            
            const response = await this.sendWithCurrentModel();
            console.log("✅ Chat response received");
            return response;
            
        } catch (error) {
            console.warn(`❌ Backend failed:`, error.message);
            // Backend unavailable — use intelligent fallback
            console.log("🤖 Backend unavailable, using intelligent fallback system");
            return this.handleWithFallback();
        }
    }
    
    /**
     * Intelligent fallback system when all AI models fail
     */
    handleWithFallback() {
        const lastMessage = this.conversationHistory[this.conversationHistory.length - 1]?.content || "";
        const message = lastMessage.toLowerCase();
        
        // Tunisian / Arabic detection (check early since darija words overlap with AR)
        const tunisianLatinWords = ['chnowa', 'kifech', 'nheb', 'wqtesh', 'bahi', 'barcha', 'moch', '3andi', 'ya kho', 'roh', 'b3id', 'hna', 'hedha', 'famma', 'yezzi', 'chwaya'];
        const isTunisian = tunisianLatinWords.some(w => message.includes(w)) || /[\u0600-\u06FF]/.test(message);

        if (isTunisian || message.includes('مرحبا') || message.includes('اهلا') || message.includes('يسلم') || message.includes('شكرا') || message.includes('بالله')) {
            return this.createFallbackResponse(
                "مرحبا! أنا نكسوس في الخدمة 😊 نقدر نساعدك تتبع صحتك، تشوف النشاطات، وتتصفح قسم التغذية. إيه اللي تحب تعمله؟"
            );
        }

        // Health-related responses
        if (message.includes('health') || message.includes('santé') || message.includes('صحة')) {
            return this.createFallbackResponse(
                "I understand you're asking about health. While I can't access AI right now, I recommend consulting the Health Journal section for tracking your wellness, or contact your healthcare provider for specific medical advice. 🏥"
            );
        }
        
        // Activity-related responses  
        if (message.includes('activity') || message.includes('exercise') || message.includes('activité') || message.includes('نشاط')) {
            return this.createFallbackResponse(
                "For activities and exercise, check out the Activities section where you can find personalized recommendations and track your progress. Regular movement is great for overall health! 🏃‍♂️"
            );
        }
        
        // Service requests
        if (message.includes('service') || message.includes('help') || message.includes('aide') || message.includes('مساعدة')) {
            return this.createFallbackResponse(
                "I'd be happy to help! You can access various services through the Services section, or feel free to ask about specific health topics, activities, or nutrition guidance. 🤝"
            );
        }
        
        // Nutrition-related
        if (message.includes('food') || message.includes('nutrition') || message.includes('diet') || message.includes('nourriture') || message.includes('طعام')) {
            return this.createFallbackResponse(
                "For nutrition guidance, visit the Nutrition section where you can find meal planning tools and dietary recommendations. A balanced diet is key to healthy aging! 🥗"
            );
        }
        
        // Profile-related responses
        if (message.includes('profile') || message.includes('profil') || message.includes('compte') || message.includes('information') || message.includes('complet') || message.includes('manqu')) {
            const u = this.currentUser;
            if (u) {
                const fieldLabels = {
                    first_name: 'Prénom', last_name: 'Nom', phone: 'Téléphone',
                    date_naissance: 'Date de naissance', adresse: 'Adresse',
                    ville: 'Ville', code_postal: 'Code Postal', pays: 'Pays', location: 'Localisation'
                };
                const missing = Object.entries(fieldLabels)
                    .filter(([key]) => !u[key] || u[key] === 'N/A')
                    .map(([, label]) => label);
                if (missing.length === 0) {
                    return this.createFallbackResponse(`🎉 Votre profil est **complet** ! Tous vos informations sont renseignées, ${u.first_name}. ✅`);
                } else {
                    return this.createFallbackResponse(
                        `👤 Bonjour **${u.first_name}** ! Il manque les informations suivantes dans votre profil :\n\n${missing.map(f => `• ❌ **${f}**`).join('\n')}\n\nVoulez-vous que je complète votre profil ? Donnez-moi les informations manquantes et je les enregistrerai pour vous! 😊`
                    );
                }
            }
        }

        // Greetings
        if (message.includes('hello') || message.includes('hi') || message.includes('bonjour') || message.includes('salut') || message.includes('مرحبا')) {
            return this.createFallbackResponse(
                "Hello! Welcome to WANNASNI, your health and wellness companion. I'm here to help with health questions, activities, services, and nutrition guidance. How can I assist you today? 😊"
            );
        }
        
        // Default helpful response
        return this.createFallbackResponse(
            "I understand you're looking for information. While I'm experiencing some connectivity issues right now, you can explore the different sections: Health Journal for wellness tracking, Activities for exercise recommendations, Services for assistance, and Nutrition for dietary guidance. Is there a specific area I can guide you to? 🌟"
        );
    }
    
    createFallbackResponse(text) {
        // Add visual indicator that this is a fallback response
        const fallbackText = `🤖 ${text}\n\n*Note: I'm currently using offline guidance mode. For full AI features, please check your internet connection.*`;
        
        // Add to conversation history
        this.conversationHistory.push({
            role: "assistant", 
            content: fallbackText
        });
        
        return {
            text: fallbackText,
            action: null
        };
    }
    
    async sendWithCurrentModel() {

        const requestBody = {
            model: this.model,
            messages: [
                { role: "system", content: this.getSystemPrompt() },
                ...this.conversationHistory
            ],
            temperature: 0.7,
            max_tokens: 2048
        };
        
        console.log('🔄 Sending chat request:', {
            model: requestBody.model,
            messages: requestBody.messages.length + ' messages',
            url: this.apiUrl
        });
        
        try {
            const response = await fetch(this.apiUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": "Bearer " + this.apiKey
                },
                body: JSON.stringify(requestBody)
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

            // Check for activity_data (structured response from activity engine)
            if (data.activity_data) {
                return {
                    text: aiMessage,
                    action: null,
                    activityData: data.activity_data
                };
            }

            // Parse response for actions
            return this.parseResponse(aiMessage);
        } catch (error) {
            console.error("Model", this.model, "failed:", error.message);
            throw error; // Re-throw for fallback system
        }
    }

    /**
     * Parse AI response for actions and clean the text
     */
    async parseResponse(response) {
        let action = null;
        let cleanText = response;

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

        // Check for PROFILE update commands
        const profileMatch = response.match(/\[PROFILE:update:({[^\]]+})\]/i);
        if (profileMatch) {
            cleanText = cleanText.replace(/\[PROFILE:update:{[^\]]+}\]/gi, '').trim();
            try {
                const fields = JSON.parse(profileMatch[1]);
                const profileResult = await this.updateProfile(fields);
                cleanText += "\n\n" + profileResult;
            } catch (error) {
                console.error("Profile update error:", error);
                cleanText += "\n\n⚠️ Erreur lors de la mise à jour du profil: " + error.message;
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
        // Fields that should never be shown to the user
        const SENSITIVE_FIELDS = new Set([
            'id', 'password', 'reset_token', 'reset_token_expires_at',
            'verification_code', 'face_encoding', 'face_image_path',
            'face_consent_at', 'senior_id', 'user_id', 'coach_id',
            'docteur_id', 'nutritionniste_id', 'employe_id',
            'service_request_id', 'demande_id', 'activity_id'
        ]);

        const sanitizeRow = (row) => {
            const clean = {};
            for (const [key, value] of Object.entries(row)) {
                if (!SENSITIVE_FIELDS.has(key.toLowerCase())) {
                    clean[key] = value;
                }
            }
            return clean;
        };

        switch (command) {
            case 'get_tables':
                return `📋 **Tables in database:**\n${data.map(t => `  • ${t}`).join('\n')}`;

            case 'get_schema':
                let schemaText = '📊 **Database Schema:**\n\n';
                for (const [table, columns] of Object.entries(data)) {
                    schemaText += `**${table}**\n`;
                    columns.forEach(col => {
                        if (!SENSITIVE_FIELDS.has(col.name.toLowerCase())) {
                            schemaText += `  • ${col.name} (${col.type})\n`;
                        }
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
                    const clean = sanitizeRow(row);
                    tableText += `**Row ${idx + 1}:**\n`;
                    for (const [key, value] of Object.entries(clean)) {
                        tableText += `  ${key}: ${value}\n`;
                    }
                    tableText += '\n';
                });

                if (data.length > 5) {
                    tableText += `... and ${data.length - 5} more rows`;
                }
                return tableText;

            case 'execute':
                return `✅ **Success!** Operation completed.`;

            default:
                return JSON.stringify(data, null, 2);
        }
    }

    /**
     * Update user profile fields via the chat profile-update API
     */
    async updateProfile(fields) {
        const response = await fetch(this.profileUpdateUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ fields })
        });

        if (!response.ok) {
            const err = await response.json().catch(() => ({}));
            throw new Error(err.error || 'Profile update failed');
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Could not save profile');
        }

        // Reload user context so the AI has fresh data
        await this.loadUserContext();

        const fieldLabels = {
            firstName: 'Prénom', lastName: 'Nom', phone: 'Téléphone',
            dateNaissance: 'Date de naissance', adresse: 'Adresse',
            ville: 'Ville', codePostal: 'Code Postal', pays: 'Pays', location: 'Localisation'
        };

        const updatedLabels = result.updated.map(f => fieldLabels[f] || f).join(', ');
        let msg = `✅ **Profil mis à jour !** Champs enregistrés: **${updatedLabels}**`;

        if (result.complete) {
            msg += `\n\n🎉 **Votre profil est maintenant complet! Tout est en ordre.** 🌟`;
        } else {
            const missingLabels = result.missing.map(f => fieldLabels[f] || f).join(', ');
            msg += `\n\n📋 Il reste encore des champs à compléter: **${missingLabels}**\nVoulez-vous me les fournir maintenant?`;
        }

        return msg;
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
} // end if typeof SmartChatAPI === 'undefined'
