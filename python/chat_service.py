"""
WANNASNI Chat Service
=====================
FastAPI proxy that routes chat requests to Ollama (local LLM).
No cloud API keys needed - everything runs locally.

Endpoint:
- POST /v1/chat/completions : OpenAI-compatible chat completions

Usage:
    python python/chat_service.py
    # Runs on http://localhost:8002
"""

import sys
import os
import io
import time
import uuid
import json
import logging

# UTF-8 for Windows console
if sys.platform == "win32":
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace")
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8", errors="replace")

from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
import httpx

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------
OLLAMA_BASE = os.environ.get("OLLAMA_BASE_URL", "http://localhost:11434")
PREFERRED_MODEL = os.environ.get("CHAT_MODEL", "qwen2.5:1.5b")
FALLBACK_MODELS = ["llama3.2:1b", "phi3:mini", "tinyllama"]

WANNASNI_SYSTEM = (
    "You are Nexus, the friendly AI assistant for WANNASNI - a platform that helps "
    "seniors manage their health, activities, nutrition, and social connections. "
    "You understand and reply in English, French, Arabic, and Tunisian dialect "
    "(both Arabic and Latin scripts like 'chnowa', 'kifech', 'bahi'). "
    "Always answer in the SAME language the user writes in. "
    "Be warm, patient, and concise. Use simple vocabulary suitable for elderly users."
)

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("chat_service")

# ---------------------------------------------------------------------------
# FastAPI app
# ---------------------------------------------------------------------------
app = FastAPI(title="WANNASNI Chat Service", version="1.0")
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_methods=["*"], allow_headers=["*"])

_active_model: str | None = None


async def _ollama_ok() -> bool:
    try:
        async with httpx.AsyncClient(timeout=3) as c:
            return (await c.get(f"{OLLAMA_BASE}/api/tags")).status_code == 200
    except Exception:
        return False


async def _list_models() -> list[str]:
    try:
        async with httpx.AsyncClient(timeout=5) as c:
            r = await c.get(f"{OLLAMA_BASE}/api/tags")
            if r.status_code == 200:
                return [m["name"] for m in r.json().get("models", [])]
    except Exception:
        pass
    return []


async def _pull(name: str) -> bool:
    logger.info(f"Pulling '{name}' - may take a few minutes on first run...")
    try:
        async with httpx.AsyncClient(timeout=600) as c:
            r = await c.post(f"{OLLAMA_BASE}/api/pull", json={"name": name, "stream": False}, timeout=600)
            return r.status_code == 200
    except Exception as e:
        logger.error(f"Pull failed for '{name}': {e}")
        return False


async def _ensure_model() -> str | None:
    global _active_model
    if _active_model:
        return _active_model

    local = await _list_models()
    logger.info(f"Local models: {local}")

    # Check preferred
    for m in local:
        if PREFERRED_MODEL in m:
            _active_model = m
            return m

    # Check fallbacks
    for fb in FALLBACK_MODELS:
        for m in local:
            if fb in m:
                _active_model = m
                return m

    # Pull preferred
    if await _pull(PREFERRED_MODEL):
        _active_model = PREFERRED_MODEL
        return PREFERRED_MODEL

    # Pull fallbacks
    for fb in FALLBACK_MODELS:
        if await _pull(fb):
            _active_model = fb
            return fb

    return None


def _openai_response(text: str, model: str = "local", usage: dict | None = None) -> dict:
    return {
        "id": f"chatcmpl-{uuid.uuid4().hex[:12]}",
        "object": "chat.completion",
        "created": int(time.time()),
        "model": model,
        "choices": [{"index": 0, "message": {"role": "assistant", "content": text}, "finish_reason": "stop"}],
        "usage": usage or {"prompt_tokens": 0, "completion_tokens": 0, "total_tokens": 0},
    }


# ---------------------------------------------------------------------------
# Endpoints
# ---------------------------------------------------------------------------

@app.get("/")
async def root():
    ok = await _ollama_ok()
    return {"status": "online" if ok else "waiting_for_ollama", "service": "WANNASNI Chat Service v1.0", "ollama": ok, "model": _active_model or PREFERRED_MODEL}


@app.post("/v1/chat/completions")
async def chat_completions(request: Request):
    data = await request.json()
    messages = data.get("messages", [])

    # Inject system prompt if missing
    if not any(m.get("role") == "system" for m in messages):
        messages.insert(0, {"role": "system", "content": WANNASNI_SYSTEM})

    # Check Ollama
    if not await _ollama_ok():
        return JSONResponse(
            {"error": {"message": "Ollama is not running. Start it with: ollama serve", "type": "server_error"}},
            status_code=503,
        )

    model = await _ensure_model()
    if not model:
        return JSONResponse(
            {"error": {"message": "No model available. Run: ollama pull qwen2.5:1.5b", "type": "server_error"}},
            status_code=503,
        )

    payload = {
        "model": model,
        "messages": messages,
        "stream": False,
        "options": {"temperature": data.get("temperature", 0.7), "num_predict": data.get("max_tokens", 2048)},
    }

    try:
        async with httpx.AsyncClient(timeout=120) as c:
            r = await c.post(f"{OLLAMA_BASE}/api/chat", json=payload, timeout=120)

        if r.status_code != 200:
            return JSONResponse({"error": {"message": f"Ollama error: {r.text[:200]}", "type": "api_error"}}, status_code=r.status_code)

        resp = r.json()
        text = resp.get("message", {}).get("content", "")
        usage = {
            "prompt_tokens": resp.get("prompt_eval_count", 0),
            "completion_tokens": resp.get("eval_count", 0),
            "total_tokens": resp.get("prompt_eval_count", 0) + resp.get("eval_count", 0),
        }
        logger.info(f"[OK] {model} - {usage['total_tokens']} tokens")
        return JSONResponse(_openai_response(text, model, usage))

    except httpx.TimeoutException:
        return JSONResponse({"error": {"message": "Model took too long to respond", "type": "timeout"}}, status_code=504)
    except Exception as e:
        logger.error(f"Error: {e}")
        return JSONResponse({"error": {"message": str(e), "type": "server_error"}}, status_code=500)


if __name__ == "__main__":
    import uvicorn

    port = int(os.environ.get("CHAT_SERVICE_PORT", "8002"))
    logger.info(f"WANNASNI Chat Service starting on port {port}")
    logger.info(f"Ollama: {OLLAMA_BASE} | Model: {PREFERRED_MODEL}")
    uvicorn.run(app, host="0.0.0.0", port=port, log_level="info")
