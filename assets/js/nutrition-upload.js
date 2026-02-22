
/**
 * NUTRITION UPLOAD JS - VERSION 4.0 (ADVANCED ML ANALYSIS)
 * Date: 2026-02-17
 */
console.log("CHARGEMENT DU SCRIPT NUTRITION-UPLOAD V4.1 ACTIVÉ");

// ============================================================================
// TEXT-TO-SPEECH (TTS) Module - Lecture vocale des infos nutritionnelles
// Uses the Web Speech API (built into modern browsers)
// ============================================================================
window.NutritionTTS = (function () {
    let currentUtterance = null;
    let activeBtn = null;

    function getVoice() {
        const voices = window.speechSynthesis.getVoices();
        // Prefer French voices
        const frVoice = voices.find(v => v.lang.startsWith('fr') && v.localService)
            || voices.find(v => v.lang.startsWith('fr'))
            || voices.find(v => v.lang.startsWith('en'));
        return frVoice || voices[0] || null;
    }

    function setButtonState(btn, speaking) {
        if (!btn) return;
        const icon = btn.querySelector('i');
        if (speaking) {
            btn.classList.add('tts-speaking');
            if (icon) icon.className = 'fas fa-stop';
            // Show stop button if exists
            document.querySelectorAll('.tts-stop-btn').forEach(b => b.style.display = 'inline-block');
        } else {
            btn.classList.remove('tts-speaking');
            if (icon) {
                icon.className = btn.classList.contains('tts-listen-all')
                    ? 'fas fa-headphones me-2'
                    : 'fas fa-volume-up';
            }
            document.querySelectorAll('.tts-stop-btn').forEach(b => b.style.display = 'none');
        }
    }

    function speak(text, btn) {
        if (!window.speechSynthesis) {
            console.warn('TTS not supported in this browser');
            return;
        }

        // If same button clicked while speaking -> stop
        if (currentUtterance && activeBtn === btn) {
            stop();
            return;
        }

        // Stop any ongoing speech
        stop();

        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'fr-FR';
        utterance.rate = 0.95;
        utterance.pitch = 1.0;
        utterance.volume = 1.0;

        const voice = getVoice();
        if (voice) utterance.voice = voice;

        activeBtn = btn;
        currentUtterance = utterance;
        setButtonState(btn, true);

        utterance.onend = function () {
            setButtonState(activeBtn, false);
            currentUtterance = null;
            activeBtn = null;
        };
        utterance.onerror = function () {
            setButtonState(activeBtn, false);
            currentUtterance = null;
            activeBtn = null;
        };

        window.speechSynthesis.speak(utterance);
    }

    function stop() {
        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
        if (activeBtn) {
            setButtonState(activeBtn, false);
        }
        currentUtterance = null;
        activeBtn = null;
    }

    // Preload voices (some browsers load them async)
    if (window.speechSynthesis) {
        window.speechSynthesis.getVoices();
        window.speechSynthesis.onvoiceschanged = function () {
            window.speechSynthesis.getVoices();
        };
    }

    return { speak, stop };
})();

document.addEventListener('DOMContentLoaded', function () {
    const dropZone = document.getElementById('dropZone');
    const mealPhoto = document.getElementById('mealPhoto');
    const imagePreview = document.getElementById('imagePreview');
    const submitBtn = document.getElementById('submitBtn');
    const uploadForm = document.getElementById('uploadForm');
    const loading = document.getElementById('loading');
    const resultsContainer = document.getElementById('resultsContainer');
    const loadingText = document.getElementById('loadingText');
    const notDetectedResult = document.getElementById('notDetectedResult');
    const regimeSelect = document.getElementById('regimeSelect');

    if (!dropZone) return;

    // Update regime data when selector changes
    if (regimeSelect) {
        regimeSelect.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            if (uploadForm) {
                uploadForm.dataset.regime = this.value;
                uploadForm.dataset.limit = selected.dataset.limit || 2000;
            }
            const limitEl = document.getElementById('regimeLimit');
            const repasEl = document.getElementById('regimeRepas');
            if (limitEl) limitEl.textContent = selected.dataset.limit || 2000;
            if (repasEl) repasEl.textContent = selected.dataset.repas || 3;
        });
    }

    // Trigger click on input when clicking drop zone
    dropZone.addEventListener('click', () => mealPhoto.click());

    mealPhoto.addEventListener('change', function () {
        handleFiles(this.files);
    });

    function handleFiles(files) {
        if (files.length > 0) {
            const file = files[0];
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                    dropZone.style.display = 'none';
                    submitBtn.style.display = 'inline-block';
                    if (notDetectedResult) notDetectedResult.style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        }
    }

    uploadForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        submitBtn.style.display = 'none';
        loading.style.display = 'block';
        if (notDetectedResult) notDetectedResult.style.display = 'none';
        resultsContainer.innerHTML = document.getElementById('resultsTemplate').innerHTML;

        const baseUrl = window.location.pathname.replace('/upload', '');
        const regime = uploadForm.dataset.regime || 'Normal';

        try {
            console.log("V3.0: Démarrage analyse multi-étapes (régime: " + regime + ")...");

            // STEP 1: Detect Foods
            updateStep(1, 'active');
            const formData = new FormData(uploadForm);
            const res1 = await fetch(baseUrl + '/step1-detect', { method: 'POST', body: formData });

            if (!res1.ok) {
                const err1 = await res1.json().catch(() => ({}));
                throw new Error(err1.message || "Erreur lors de la détection (Etape 1)");
            }

            const data1 = await res1.json();
            console.log("V3.0 - Step 1 Results:", data1);

            // *** STRICT HANDLING: NOT DETECTED ***
            if (data1.status === 'not_detected') {
                loading.style.display = 'none';
                if (notDetectedResult) {
                    const msgEl = document.getElementById('notDetectedMessage');
                    if (msgEl) msgEl.textContent = data1.message || "L'IA n'a pas pu identifier l'aliment.";
                    notDetectedResult.style.display = 'block';
                } else {
                    alert(data1.message || "Aliment non détecté. Réessayez avec une meilleure photo.");
                    submitBtn.style.display = 'inline-block';
                }
                return; // STOP HERE - no fake data
            }

            if (data1.status !== 'success') throw new Error(data1.message || "Échec de détection");

            document.getElementById('resultImage').src = '/uploads/meals/' + data1.photo;
            document.getElementById('resultImage').style.display = 'inline-block';
            updateStep(1, 'done');

            // STEP 2: Nutrition & Compliance (pass regime)
            updateStep(2, 'active');
            const formData2 = new FormData();
            formData2.append('foods', JSON.stringify(data1.foods || []));
            formData2.append('regime', regime);
            const res2 = await fetch(baseUrl + '/step2-nutrition', { method: 'POST', body: formData2 });

            if (!res2.ok) {
                const err2 = await res2.json().catch(() => ({}));
                throw new Error(err2.message || "Erreur lors de l'analyse nutritionnelle (Etape 2)");
            }

            const data2 = await res2.json();
            console.log("V3.0 - Step 2 Results:", data2);
            if (data2.status !== 'success') throw new Error(data2.message || "Échec analyse nutritionnelle");

            renderNutrition(data2);
            updateStep(2, 'done');

            // STEP 3: Recipes (pass regime) + STEP 2b: Advanced ML Analysis (parallel)
            updateStep(3, 'active');
            const limit = uploadForm.dataset.limit || 2000;
            const consumed = uploadForm.dataset.consumed || 0;
            const formData3 = new FormData();
            formData3.append('calories', data2.total_calories || 0);
            formData3.append('limit', limit);
            formData3.append('consumed', consumed);
            formData3.append('regime', regime);

            const formDataAdv = new FormData();
            formDataAdv.append('photo', data1.photo);
            formDataAdv.append('foods', JSON.stringify(data2.aliments || data1.foods || []));

            // Fire both requests in parallel
            const [res3, resAdv] = await Promise.all([
                fetch(baseUrl + '/step3-recipes', { method: 'POST', body: formData3 }),
                fetch(baseUrl + '/step2b-advanced', { method: 'POST', body: formDataAdv }).catch(e => {
                    console.warn("V4.0: Advanced analysis failed:", e);
                    return null;
                })
            ]);

            if (!res3.ok) {
                const err3 = await res3.json().catch(() => ({}));
                throw new Error(err3.message || "Erreur lors des recettes (Etape 3)");
            }

            const data3 = await res3.json();
            console.log("V4.0 - Step 3 Results:", data3);
            if (data3 && data3.status === 'success') {
                renderRecipes(data3.suggestions || []);
            }

            // Parse advanced analysis results
            let advancedData = null;
            if (resAdv && resAdv.ok) {
                try {
                    advancedData = await resAdv.json();
                    console.log("V4.0 - Advanced Analysis Results:", advancedData);
                    if (advancedData.status === 'success') {
                        renderAdvancedResults(advancedData);
                    }
                } catch (e) {
                    console.warn("V4.0: Failed to parse advanced results:", e);
                }
            }
            updateStep(3, 'done');

            // STEP 4: Alerts & Finalize
            updateStep(4, 'active');
            const formData4 = new FormData();
            formData4.append('calories', data2.total_calories || 0);
            formData4.append('compliance', JSON.stringify(data2.compliance || {}));
            formData4.append('limit', limit);
            formData4.append('consumed', consumed);
            formData4.append('photo', data1.photo);
            formData4.append('foods', JSON.stringify(data2.aliments || []));

            // Append advanced ML data for DB saving
            if (advancedData && advancedData.status === 'success') {
                formData4.append('portions_estimees', JSON.stringify(advancedData.portions || {}));
                formData4.append('mode_cuisson', (advancedData.cooking || {}).methode || '');
                formData4.append('score_risque', (advancedData.risk_score || {}).score || 0);
                formData4.append('analyse_texture', JSON.stringify(advancedData.texture || {}));
                formData4.append('details_nutriments', JSON.stringify(advancedData.nutriments || {}));
            }

            console.log("V4.0: Envoi vers l'étape 4 (Finalisation)...");
            const res4 = await fetch(baseUrl + '/step4-finalize', { method: 'POST', body: formData4 });

            if (!res4.ok) {
                const errData = await res4.json().catch(() => ({}));
                console.error("V4.0 ERROR 500 DETAILS:", errData);
                throw new Error(errData.message || `Erreur serveur Step 4 (Status: ${res4.status})`);
            }

            const data4 = await res4.json();
            console.log("V4.0 - Step 4 Raw Data:", data4);

            if (data4.status !== 'success') throw new Error(data4.message || "Échec finalisation");

            renderFinal(data4);
            updateStep(4, 'done');

            // Finish
            loading.style.display = 'none';
            resultsContainer.style.display = 'block';

        } catch (err) {
            console.error("V4.0 CRASH ANALYSE:", err);
            loading.style.display = 'none';
            submitBtn.style.display = 'inline-block';

            // Friendly error display — never show raw cURL/HTTP messages
            const isServiceDown = err.message.includes('Failed to connect') ||
                err.message.includes('cURL') ||
                err.message.includes('500') ||
                err.message.includes('unavailable');

            const errMsg = isServiceDown
                ? "Le service d'analyse IA est momentanément indisponible. Veuillez réessayer dans un instant."
                : (err.message || "Une erreur est survenue lors de l'analyse.");

            resultsContainer.innerHTML = `
                <div class="alert alert-warning rounded-4 shadow-sm d-flex align-items-start gap-3 p-4">
                    <div style="font-size:2rem;">⚠️</div>
                    <div>
                        <strong>Analyse interrompue</strong>
                        <p class="mb-2 mt-1">${errMsg}</p>
                        <button onclick="submitBtn.click()" class="btn btn-outline-warning btn-sm rounded-pill">
                            <i class="fas fa-redo me-1"></i>Réessayer
                        </button>
                    </div>
                </div>`;
            resultsContainer.style.display = 'block';
        }
    });

    function updateStep(stepNum, state) {
        const stepEl = document.getElementById('step' + stepNum);
        if (!stepEl) return;
        if (state === 'active') {
            stepEl.classList.remove('text-muted');
            stepEl.classList.add('text-primary', 'fw-bold');
            const icon = stepEl.querySelector('i');
            if (icon) icon.className = 'fas fa-spinner fa-spin me-2';
        } else if (state === 'done') {
            stepEl.classList.remove('text-primary');
            stepEl.classList.add('text-success');
            const icon = stepEl.querySelector('i');
            if (icon) icon.className = 'fas fa-check-circle me-2';
        }
    }

    function renderNutrition(data) {
        if (!data) return;
        const badge = document.getElementById('complianceBadge');
        if (badge && data.compliance) {
            badge.innerText = data.compliance.conforme ? '✓ Conforme' : '✗ Non Conforme';
            badge.className = 'badge p-2 rounded-pill ' + (data.compliance.conforme ? 'bg-success' : 'bg-danger');
        }

        const list = document.getElementById('foodsList');
        if (!list) return;

        const aliments = Array.isArray(data.aliments) ? data.aliments : [];

        try {
            list.innerHTML = aliments.map((a, idx) => {
                const nom = (a.nom || '').replace(/_/g, ' ');
                const bienfaits = a.bienfaits || '';
                const speechText = `${nom}. ${Math.round(a.calories || 0)} kilocalories. ${bienfaits}`;
                const escapedText = speechText.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                return `
                <div class="list-group-item border-0 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <strong class="text-capitalize">${nom}</strong>
                            <br><small class="text-muted bienfaits-text">${bienfaits}</small>
                        </div>
                        <div class="d-flex align-items-center gap-2 ms-2">
                            <span class="badge bg-light text-success p-2">${Math.round(a.calories || 0)} kcal</span>
                            <button class="btn btn-sm btn-outline-success tts-btn rounded-circle" 
                                    onclick="window.NutritionTTS.speak('${escapedText}', this)" 
                                    title="Écouter les infos nutritionnelles"
                                    style="width:36px;height:36px;padding:0;display:inline-flex;align-items:center;justify-content:center;">
                                <i class="fas fa-volume-up"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
            }).join('');

            // Add "Listen All" button after food list (remove existing to avoid duplicates)
            const existingListenDiv = list.parentNode.querySelector('.tts-listen-all-wrapper');
            if (existingListenDiv) existingListenDiv.remove();

            if (aliments.length > 0) {
                const allText = aliments.map(a => {
                    const nom = (a.nom || '').replace(/_/g, ' ');
                    return `${nom}: ${Math.round(a.calories || 0)} kilocalories. ${a.bienfaits || ''}`;
                }).join('. Aliment suivant: ');
                const totalCals = aliments.reduce((sum, a) => sum + Math.round(a.calories || 0), 0);
                const fullSpeech = `R\u00e9sum\u00e9 nutritionnel de votre repas. ${allText}. Total du repas: ${totalCals} kilocalories.`;

                const listenAllDiv = document.createElement('div');
                listenAllDiv.className = 'text-center mt-3 mb-2 tts-listen-all-wrapper';
                listenAllDiv.innerHTML = `
                    <button class="btn btn-success btn-sm rounded-pill px-4 tts-listen-all" 
                            onclick="window.NutritionTTS.speak(this.dataset.speech, this)"
                            data-speech="${fullSpeech.replace(/"/g, '&quot;')}">
                        <i class="fas fa-headphones me-2"></i>Écouter le r\u00e9sum\u00e9 vocal
                    </button>
                    <button class="btn btn-outline-danger btn-sm rounded-pill px-3 ms-2 tts-stop-btn" 
                            onclick="window.NutritionTTS.stop()" style="display:none;">
                        <i class="fas fa-stop me-1"></i>Arr\u00eater
                    </button>
                `;
                list.parentNode.insertBefore(listenAllDiv, list.nextSibling);
            }
        } catch (e) {
            console.error("CRASH renderNutrition map:", e);
        }
    }

    function renderRecipes(suggestions) {
        const recipesList = document.getElementById('recipesList');
        const recipesSection = document.getElementById('recipesSection');
        if (!recipesList || !recipesSection) return;

        const items = Array.isArray(suggestions) ? suggestions : [];
        if (items.length === 0) {
            recipesSection.style.display = 'none';
            return;
        }

        recipesSection.style.display = 'block';
        try {
            recipesList.innerHTML = items.map(s => `
                <div class="col-md-6">
                    <div class="p-3 border rounded-4 bg-light h-100">
                        <h4 class="h6 mb-2">${s.nom || s.name || ''}</h4>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-warning text-dark">${s.calories || s.estimated_calories || 0} kcal</span>
                            <span class="text-muted small"><i class="fas fa-signal me-1"></i> ${s.difficulte || ''}</span>
                        </div>
                        ${s.ingredients ? `<p class="text-muted small mt-2 mb-0">${Array.isArray(s.ingredients) ? s.ingredients.slice(0, 5).join(', ') : ''}</p>` : ''}
                    </div>
                </div>
            `).join('');
        } catch (e) {
            console.error("CRASH renderRecipes map:", e);
        }
    }

    function renderFinal(data) {
        if (!data) return;

        try {
            const totalDayEl = document.getElementById('totalDayCals');
            if (totalDayEl) totalDayEl.innerText = Math.round(data.total_day || 0);

            const limit = parseInt(uploadForm.dataset.limit || 2000);
            const percent = Math.min(100, Math.round(((data.total_day || 0) / limit) * 100));
            const bar = document.getElementById('calorieBar');
            if (bar) {
                bar.style.width = percent + '%';
                bar.className = 'progress-bar ' + (percent >= 100 ? 'bg-danger' : 'bg-success');
            }

            const alertsBox = document.getElementById('alertsContainer');
            if (alertsBox) {
                const alerts = (data && Array.isArray(data.alerts)) ? data.alerts : [];

                alertsBox.innerHTML = alerts.map(a => {
                    if (!a) return '';
                    return `
                        <div class="alert alert-${a.type || 'info'} d-flex align-items-center shadow-sm">
                            <i class="fas fa-exclamation-triangle me-3"></i>
                            <span>${a.message || ''}</span>
                        </div>
                    `;
                }).join('');
            }

            const finalMsgContainer = document.getElementById('finalMessageContainer');
            const finalMsg = document.getElementById('finalMessage');
            if (finalMsgContainer && finalMsg) {
                finalMsgContainer.style.display = 'block';
                finalMsg.innerText = data.message || 'Analyse terminée.';
            }
        } catch (e) {
            console.error("CRASH renderFinal:", e);
        }
    }

    /**
     * Render advanced ML analysis results (portions, cooking, risk, texture)
     */
    function renderAdvancedResults(data) {
        if (!data) return;
        try {
            // === Portions ===
            const portionsSection = document.getElementById('portionsSection');
            const portionsList = document.getElementById('portionsList');
            if (portionsSection && portionsList && data.portions) {
                const portions = Array.isArray(data.portions) ? data.portions : [];
                if (portions.length > 0) {
                    portionsSection.style.display = 'block';
                    portionsList.innerHTML = portions.map(p => `
                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 py-2">
                            <span class="text-capitalize fw-semibold">${(p.food || '').replace(/_/g, ' ')}</span>
                            <div>
                                <span class="badge bg-primary rounded-pill me-1">${Math.round(p.portion_g || 0)}g</span>
                                <span class="badge bg-light text-muted">${p.densite || ''}</span>
                            </div>
                        </div>
                    `).join('');
                }
            }

            // === Cooking Method ===
            const cookingSection = document.getElementById('cookingSection');
            const cookingInfo = document.getElementById('cookingInfo');
            if (cookingSection && cookingInfo && data.cooking) {
                const c = data.cooking;
                if (c.methode && c.methode !== 'non_detecte') {
                    cookingSection.style.display = 'block';
                    const multiplierColor = (c.calorie_multiplier || 1) > 1.1 ? '#ef4444' : '#22c55e';
                    cookingInfo.innerHTML = `
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-4">
                            <div style="width:50px;height:50px;border-radius:12px;background:${multiplierColor}20;display:flex;align-items:center;justify-content:center;">
                                <span style="font-size:1.5rem;">🍳</span>
                            </div>
                            <div>
                                <div class="fw-bold text-capitalize fs-5">${c.label || c.methode}</div>
                                <div class="text-muted small">
                                    Multiplicateur calorique: <strong style="color:${multiplierColor}">×${(c.calorie_multiplier || 1).toFixed(1)}</strong>
                                </div>
                                ${c.conseil ? `<div class="text-muted small mt-1">💡 ${c.conseil}</div>` : ''}
                            </div>
                        </div>
                    `;
                }
            }

            // === Risk Score ===
            const riskScoreSection = document.getElementById('riskScoreSection');
            if (riskScoreSection && data.risk_score) {
                const rs = data.risk_score;
                const score = rs.score || 0;
                if (score > 0) {
                    riskScoreSection.style.display = 'block';
                    const scoreColor = score < 30 ? '#22c55e' : score < 60 ? '#f59e0b' : '#ef4444';
                    const riskGauge = riskScoreSection.querySelector('.risk-gauge') || document.createElement('div');
                    riskGauge.className = 'risk-gauge text-center p-3';
                    riskGauge.innerHTML = `
                        <div style="position:relative;width:100px;height:100px;margin:0 auto;">
                            <svg viewBox="0 0 36 36" style="transform:rotate(-90deg);width:100%;height:100%;">
                                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                      fill="none" stroke="#e5e7eb" stroke-width="3"/>
                                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                      fill="none" stroke="${scoreColor}" stroke-width="3"
                                      stroke-dasharray="${score}, 100"/>
                            </svg>
                            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:1.3rem;font-weight:700;color:${scoreColor};">
                                ${score}
                            </div>
                        </div>
                        <div class="mt-2 fw-semibold text-capitalize" style="color:${scoreColor};">${rs.niveau || ''}</div>
                        ${(rs.details || []).length > 0 ? `
                            <div class="text-start mt-3">
                                ${rs.details.map(d => `<div class="small text-muted mb-1">• ${d}</div>`).join('')}
                            </div>
                        ` : ''}
                        ${(rs.recommandations || []).length > 0 ? `
                            <div class="text-start mt-2">
                                ${rs.recommandations.map(r => `
                                    <div class="alert alert-warning py-1 px-2 small mb-1">${r}</div>
                                `).join('')}
                            </div>
                        ` : ''}
                    `;
                    // Insert after the section title
                    const existingGauge = riskScoreSection.querySelector('.risk-gauge');
                    if (existingGauge) {
                        existingGauge.replaceWith(riskGauge);
                    } else {
                        riskScoreSection.appendChild(riskGauge);
                    }
                }
            }

            // === Texture Analysis ===
            const textureSection = document.getElementById('textureSection');
            if (textureSection && data.texture) {
                const tex = data.texture;
                const hasContent = tex.aliments_risque?.length > 0 || tex.recommandations?.length > 0 || tex.conseil_general;
                if (hasContent) {
                    textureSection.style.display = 'block';
                    let html = '';

                    if (tex.adapte === false) {
                        html += `<div class="alert alert-danger d-flex align-items-center py-2 mb-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>⚠️ Certains aliments peuvent présenter un risque de déglutition</strong>
                        </div>`;
                    } else {
                        html += `<div class="alert alert-success d-flex align-items-center py-2 mb-2">
                            <i class="fas fa-check-circle me-2"></i>
                            Texture adaptée — aucun risque détecté
                        </div>`;
                    }

                    if (tex.aliments_risque && tex.aliments_risque.length > 0) {
                        html += `<div class="mb-2">
                            <strong class="small text-danger">Aliments à risque :</strong>
                            <ul class="mb-0 small">
                                ${tex.aliments_risque.map(a => `<li>${a.aliment || a} — ${a.risque || 'risque modéré'}</li>`).join('')}
                            </ul>
                        </div>`;
                    }

                    if (tex.recommandations && tex.recommandations.length > 0) {
                        html += `<div class="mb-2">
                            ${tex.recommandations.map(r => `<div class="alert alert-info py-1 px-2 small mb-1">💡 ${r}</div>`).join('')}
                        </div>`;
                    }

                    if (tex.conseil_general) {
                        html += `<div class="text-muted small mt-1">📋 ${tex.conseil_general}</div>`;
                    }

                    const textureContent = textureSection.querySelector('.texture-content') || document.createElement('div');
                    textureContent.className = 'texture-content';
                    textureContent.innerHTML = html;
                    const existingContent = textureSection.querySelector('.texture-content');
                    if (existingContent) {
                        existingContent.replaceWith(textureContent);
                    } else {
                        textureSection.appendChild(textureContent);
                    }
                }
            }

        } catch (e) {
            console.error("CRASH renderAdvancedResults:", e);
        }
    }
});
