# 🎤 Voice Call Troubleshooting Guide

## Quick Checklist

### 1. Browser Compatibility
- ✅ **Chrome** (recommended)
- ✅ **Edge** (recommended)
- ❌ **Firefox** (limited support)
- ❌ **Safari** (limited support)

### 2. Check Browser Console
1. Press **F12** to open Developer Tools
2. Click on **Console** tab
3. Look for the "🔍 Voice Call Diagnostics" section
4. All items should show ✅ green checkmarks

### 3. Microphone Permissions

#### How to Check/Allow Mic Access:

**Chrome/Edge:**
1. Click the **🔒 lock icon** or **camera icon** in the address bar
2. Find "Microphone" setting
3. Select **"Allow"**
4. Refresh the page (F5)

**Alternative:**
1. Go to `chrome://settings/content/microphone` (Chrome)
2. Or `edge://settings/content/microphone` (Edge)
3. Make sure your site is in the "Allowed" list

### 4. Test Microphone
Open your system sound settings and verify:
- Microphone is plugged in/enabled
- Input level bars move when you speak
- Default recording device is set correctly

---

## Step-by-Step Voice Call Test

### Expected Behavior:
1. ✅ Click microphone button 🎤
2. ✅ See overlay with animated blue orb
3. ✅ Browser asks for microphone permission → **Click "Allow"**
4. ✅ Status changes to "Listening..."
5. ✅ Speak: "Hello, how are you?"
6. ✅ See your words appear in transcript
7. ✅ Orb turns orange = AI thinking
8. ✅ Orb turns green = AI speaking back
9. ✅ Hear AI response through speakers
10. ✅ Orb turns blue again = listening for your next message

---

## Common Issues & Solutions

### ❌ "Speech recognition not available"
**Problem:** Browser doesn't support the Web Speech API  
**Solution:** Use **Chrome** or **Edge** browser

---

### ❌ "Mic access denied"
**Problem:** You blocked microphone permissions  
**Solutions:**
1. Click the 🔒 icon in address bar → Allow microphone
2. Settings → Privacy → Microphone → Allow this site
3. Refresh page (F5) and try again

---

### ❌ Overlay appears but nothing happens
**Problem:** Voice elements not loading  
**Solutions:**
1. **Hard refresh:** Ctrl + Shift + R (Windows) or Cmd + Shift + R (Mac)
2. **Clear cache:** Ctrl + Shift + Delete → Clear cached images/files
3. Check browser console (F12) for errors

---

### ❌ Can hear transcript but no AI response
**Problem:** API issues or network  
**Check:**
1. Browser console for errors (F12)
2. Is the chat working in text mode?
3. Check your internet connection
4. Look at Symfony logs: `var/log/dev.log`

---

### ❌ No voice output from AI
**Problem:** Text-to-speech not working or speaker muted  
**Solutions:**
1. Check speaker button in call overlay (should be blue, not dimmed)
2. Check system volume is not muted
3. Try clicking speaker button to toggle it on
4. Some voices may take time to load - wait a few seconds

---

### ❌ "No speech detected" keeps appearing
**Problem:** Microphone sensitivity too low or wrong mic selected  
**Solutions:**
1. Speak louder and closer to microphone
2. Check system settings → select correct microphone device
3. Test mic in Windows "Sound Settings" → Recording tab
4. Try another microphone if available

---

### ❌ Voice recognition stops after first message
**Problem:** Speech recognition session ended unexpectedly  
**Solutions:**
1. Check console logs (F12) for errors
2. Try ending call and starting again
3. Check if microphone is still allowed in permissions
4. Some browsers limit continuous recognition - this is normal

---

## Debug Mode

### Enable Detailed Logging:
The voice call now logs everything to console automatically!

Open **Developer Tools (F12)** and watch for:
- 🎤 Voice button clicked
- ✅ Speech recognition started  
- 🎯 Processing voice input: [your text]
- 📡 Sending message to AI...
- ✅ AI response received
- 🗣️ AI text for voice: [response]
- 🔊 Speaking response...
- ✅ Finished speaking
- 👂 Resuming listening...

If any of these don't appear, that's where the problem is!

---

## Still Not Working?

### 1. Check voice elements exist:
Open console (F12) and type:
```javascript
document.getElementById('voice-call-overlay')
```
Should return: `<div class="voice-call-overlay" id="voice-call-overlay">`  
If it returns `null`, the HTML is missing - refresh page!

### 2. Test speech recognition directly:
```javascript
const recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
recognition.start();
```
If this throws an error, your browser doesn't support it.

### 3. Check logs:
```bash
# In your project directory:
tail -f var/log/dev.log
```
Look for errors when you try to use voice call.

---

## Browser-Specific Issues

### Chrome:
- May ask for permission repeatedly if you're on `http://` instead of `https://`
- Solution: Use `localhost` or proper SSL certificate

### Edge:
- Same as Chrome (uses Chromium engine)

### Firefox:
- Speech Recognition API available but requires manual enable
- Go to `about:config` → search `media.webspeech.recognition.enable` → set to `true`
- Still may not work perfectly

### Safari:
- Very limited Web Speech API support
- macOS 14+ has some support but unreliable
- Best to use Chrome/Edge instead

---

## Contact/Report Issues

If voice call still doesn't work after trying all solutions:
1. **Note your browser version:** Help → About
2. **Copy console errors:** F12 → Console → Right-click errors → Copy
3. **Check if text chat works:** Try typing a message normally
4. **Report with details:** Browser, OS, exact error message

---

## Quick Reference Commands

### For Developers:

**View all voice elements:**
```javascript
console.table({
  voiceBtn: !!document.getElementById('voice-btn'),
  voiceOverlay: !!document.getElementById('voice-call-overlay'),
  voiceOrb: !!document.getElementById('voice-orb'),
  voiceStatus: !!document.getElementById('voice-call-status'),
  voiceTranscript: !!document.getElementById('voice-transcript'),
  voiceAiText: !!document.getElementById('voice-ai-text'),
  voiceEndCall: !!document.getElementById('voice-end-call'),
  voiceMuteBtn: !!document.getElementById('voice-mute-btn'),
  voiceSpeakerBtn: !!document.getElementById('voice-speaker-btn'),
  voiceTimerEl: !!document.getElementById('voice-call-timer')
});
```

**Test speech recognition:**
```javascript
const rec = new webkitSpeechRecognition();
rec.onresult = (e) => console.log('You said:', e.results[0][0].transcript);
rec.start();
```

**Force reload JS:**
```javascript
location.reload(true);
```

---

**Last Updated:** {{ "now"|date("Y-m-d H:i") }}
