#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Face Recognition Service for WANNASNI
Uses OpenCV's DNN face detector and a simple histogram-based comparison.

Usage:
    python face_recognition_service.py detect <image_path>
    python face_recognition_service.py encode <image_path>
    python face_recognition_service.py match <image_path> <users_json_path>

Output: JSON to stdout
"""

import sys
import json
import os
import base64
import hashlib
from pathlib import Path

# Force UTF-8 encoding for Windows compatibility
if os.name == 'nt':  # Windows
    import codecs
    sys.stdout = codecs.getwriter('utf-8')(sys.stdout.detach())
    sys.stderr = codecs.getwriter('utf-8')(sys.stderr.detach())

# Suppress OpenCV warning messages
os.environ['OPENCV_LOG_LEVEL'] = 'SILENT'

try:
    import cv2
    import numpy as np
except ImportError:
    print(json.dumps({
        "success": False,
        "error": "OpenCV not installed. Run: pip install opencv-python numpy",
        "code": "MISSING_DEPENDENCY"
    }))
    sys.exit(1)


# OpenCV's Haar Cascade for face detection (built-in)
FACE_CASCADE = None

def get_face_cascade():
    """Get or initialize the face cascade classifier."""
    global FACE_CASCADE
    if FACE_CASCADE is None:
        cascade_path = cv2.data.haarcascades + 'haarcascade_frontalface_default.xml'
        FACE_CASCADE = cv2.CascadeClassifier(cascade_path)
    return FACE_CASCADE


def load_image_from_path(image_path: str):
    """Load image from file path or base64 data URI."""
    # Check if it's a base64 data URI
    if image_path.startswith('data:'):
        # Base64 data URI
        header, data = image_path.split(',', 1)
        # Ensure proper binary handling
        try:
            image_bytes = base64.b64decode(data)
            nparr = np.frombuffer(image_bytes, np.uint8)
            image = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
            if image is None:
                raise ValueError("Could not decode image data")
            return image
        except Exception as e:
            raise ValueError(f"Invalid base64 image data: {str(e)}")
    else:
        # Try loading as a regular image file first (binary JPEG/PNG)
        if os.path.exists(image_path):
            image = cv2.imread(image_path)
            if image is not None:
                return image
            
            # If cv2.imread failed, try reading as text (base64 data URI in file)
            try:
                with open(image_path, 'r', encoding='utf-8', errors='ignore') as f:
                    content = f.read().strip()
                    if content.startswith('data:'):
                        header, data = content.split(',', 1)
                        image_bytes = base64.b64decode(data)
                        nparr = np.frombuffer(image_bytes, np.uint8)
                        image = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
                        return image
            except Exception:
                pass
        
        # Final fallback
        return cv2.imread(image_path)


def detect_faces(image_path: str) -> dict:
    """
    Detect faces in an image using OpenCV's Haar Cascade.
    Returns: {"success": True, "faces_count": N, "face_locations": [...]}
    """
    try:
        image = load_image_from_path(image_path)
        if image is None:
            return {
                "success": False,
                "error": "Could not load image",
                "code": "LOAD_ERROR"
            }
        
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        face_cascade = get_face_cascade()
        
        faces = face_cascade.detectMultiScale(
            gray,
            scaleFactor=1.1,
            minNeighbors=5,
            minSize=(30, 30)
        )
        
        if len(faces) == 0:
            return {
                "success": True,
                "faces_count": 0,
                "face_locations": [],
                "message": "No face detected in the image"
            }
        
        face_locations = []
        for (x, y, w, h) in faces:
            face_locations.append({
                "x": int(x),
                "y": int(y),
                "width": int(w),
                "height": int(h)
            })
        
        return {
            "success": True,
            "faces_count": len(faces),
            "face_locations": face_locations
        }
    except Exception as e:
        return {
            "success": False,
            "error": str(e),
            "code": "DETECTION_ERROR"
        }


def compute_lbp(image, radius=1, n_points=8):
    """
    Compute Local Binary Pattern (LBP) for a grayscale image.
    LBP captures texture/structure rather than raw pixel values.
    """
    rows, cols = image.shape
    lbp = np.zeros_like(image, dtype=np.uint8)
    
    for i in range(radius, rows - radius):
        for j in range(radius, cols - radius):
            center = image[i, j]
            binary = 0
            for k in range(n_points):
                angle = 2 * np.pi * k / n_points
                y = int(round(i + radius * np.sin(angle)))
                x = int(round(j + radius * np.cos(angle)))
                if 0 <= y < rows and 0 <= x < cols:
                    binary |= (1 << k) if image[y, x] >= center else 0
            lbp[i, j] = binary
    
    return lbp


def compute_face_encoding(image, face_rect) -> list:
    """
    Compute a face encoding using LBP histograms for robust face comparison.
    Uses structural features (texture) rather than raw pixels.
    Produces a 1024-dimensional feature vector.
    """
    x, y, w, h = face_rect
    
    # Extract face region with some margin
    margin = int(min(w, h) * 0.15)
    y1 = max(0, y - margin)
    y2 = min(image.shape[0], y + h + margin)
    x1 = max(0, x - margin)
    x2 = min(image.shape[1], x + w + margin)
    
    face_region = image[y1:y2, x1:x2]
    
    # Resize to standard size for consistent encoding
    face_resized = cv2.resize(face_region, (128, 128))
    
    # Convert to grayscale
    if len(face_resized.shape) == 3:
        gray_face = cv2.cvtColor(face_resized, cv2.COLOR_BGR2GRAY)
    else:
        gray_face = face_resized
    
    # Apply histogram equalization for lighting normalization
    gray_face = cv2.equalizeHist(gray_face)
    
    # Compute LBP texture features
    lbp_image = compute_lbp(gray_face, radius=1, n_points=8)
    
    # Divide face into a 4x4 grid and compute histogram per cell
    # Each cell produces a 256-bin histogram => 4*4*256 total but we reduce to 64 bins per cell
    cell_h = lbp_image.shape[0] // 4
    cell_w = lbp_image.shape[1] // 4
    
    histograms = []
    for row in range(4):
        for col in range(4):
            cell = lbp_image[row*cell_h:(row+1)*cell_h, col*cell_w:(col+1)*cell_w]
            hist, _ = np.histogram(cell, bins=64, range=(0, 256), density=True)
            histograms.extend(hist.tolist())
    
    # histograms has 4*4*64 = 1024 dimensions
    encoding = np.array(histograms, dtype=np.float32)
    
    # Normalize the full vector to unit length
    norm = np.linalg.norm(encoding)
    if norm > 0:
        encoding = encoding / norm
    
    return encoding.tolist()


def encode_face(image_path: str) -> dict:
    """
    Detect face and compute encoding.
    Returns: {"success": True, "encoding": [...], "faces_count": N}
    """
    try:
        image = load_image_from_path(image_path)
        if image is None:
            return {
                "success": False,
                "error": "Could not load image",
                "code": "LOAD_ERROR"
            }
        
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        face_cascade = get_face_cascade()
        
        faces = face_cascade.detectMultiScale(
            gray,
            scaleFactor=1.1,
            minNeighbors=5,
            minSize=(30, 30)
        )
        
        if len(faces) == 0:
            return {
                "success": False,
                "error": "No face detected in the image",
                "code": "NO_FACE"
            }
        
        if len(faces) > 1:
            return {
                "success": False,
                "error": "Multiple faces detected. Please ensure only one face is visible.",
                "code": "MULTIPLE_FACES"
            }
        
        # Compute face encoding
        encoding = compute_face_encoding(image, faces[0])
        
        x, y, w, h = faces[0]
        return {
            "success": True,
            "encoding": encoding,
            "faces_count": 1,
            "face_location": {
                "x": int(x),
                "y": int(y),
                "width": int(w),
                "height": int(h)
            }
        }
    except Exception as e:
        return {
            "success": False,
            "error": str(e),
            "code": "ENCODING_ERROR"
        }


def compare_encodings(encoding1: list, encoding2: list, tolerance: float = 0.45) -> tuple:
    """
    Compare two face encodings using cosine similarity on LBP histograms.
    
    For LBP histogram-based encodings:
    - Same person: cosine distance typically < 0.35
    - Different persons: cosine distance typically > 0.5
    - tolerance=0.45 is a good threshold
    
    Returns: (is_match: bool, distance: float)
    """
    arr1 = np.array(encoding1, dtype=np.float32)
    arr2 = np.array(encoding2, dtype=np.float32)
    
    # Ensure both arrays have the same dimensions
    if len(arr1) != len(arr2):
        return False, 1.0
    
    # Cosine similarity
    dot = np.dot(arr1, arr2)
    norm1 = np.linalg.norm(arr1)
    norm2 = np.linalg.norm(arr2)
    
    if norm1 == 0 or norm2 == 0:
        return False, 1.0
    
    cosine_sim = dot / (norm1 * norm2)
    cosine_distance = 1.0 - cosine_sim
    
    # Chi-squared distance (good for histogram comparison)
    # Avoid division by zero
    denom = arr1 + arr2
    mask = denom > 0
    chi2 = np.sum(((arr1[mask] - arr2[mask]) ** 2) / denom[mask]) / 2.0
    # Normalize chi2 to 0-1 range (cap at 1)
    chi2_norm = min(chi2, 1.0)
    
    # Combined distance: weighted average of cosine and chi-squared
    final_distance = 0.5 * cosine_distance + 0.5 * chi2_norm
    
    # A match if distance is below tolerance
    return final_distance < tolerance, float(final_distance)


def match_against_users(image_path: str, users_json_path: str, tolerance: float = 0.45) -> dict:
    """
    Match a face against stored user face encodings.
    
    Args:
        image_path: Path to the image to check
        users_json_path: Path to JSON file with user data [{id, name, email, encoding}, ...]
    
    Returns: {"success": True, "matched": True/False, "user": {...}}
    """
    try:
        # First encode the new face
        encode_result = encode_face(image_path)
        if not encode_result["success"]:
            return encode_result
        
        new_encoding = encode_result["encoding"]
        
        # Load users data
        if not os.path.exists(users_json_path):
            return {
                "success": True,
                "matched": False,
                "encoding": new_encoding,
                "message": "No users to compare against"
            }
        
        with open(users_json_path, 'r') as f:
            users = json.load(f)
        
        if not users:
            return {
                "success": True,
                "matched": False,
                "encoding": new_encoding,
                "message": "No users to compare against"
            }
        
        # Filter users with valid encodings
        valid_users = [u for u in users if u.get("encoding")]
        
        if not valid_users:
            return {
                "success": True,
                "matched": False,
                "encoding": new_encoding,
                "message": "No users with face data to compare against"
            }
        
        # Find best match
        best_match = None
        best_distance = float('inf')
        
        for user in valid_users:
            is_match, distance = compare_encodings(new_encoding, user["encoding"], tolerance)
            if distance < best_distance:
                best_distance = distance
                if is_match:
                    best_match = user
        
        if best_match:
            return {
                "success": True,
                "matched": True,
                "user": {
                    "id": best_match.get("id"),
                    "name": best_match.get("name"),
                    "email": best_match.get("email")
                },
                "distance": float(best_distance),
                "confidence": float(1 - best_distance),
                "encoding": new_encoding
            }
        else:
            return {
                "success": True,
                "matched": False,
                "closest_distance": float(best_distance) if best_distance != float('inf') else None,
                "encoding": new_encoding,
                "message": "No matching face found"
            }
    except Exception as e:
        return {
            "success": False,
            "error": str(e),
            "code": "MATCH_ERROR"
        }


def main():
    if len(sys.argv) < 2:
        print(json.dumps({
            "success": False,
            "error": "Usage: python face_recognition_service.py <command> <args...>",
            "code": "INVALID_ARGS"
        }))
        sys.exit(1)
    
    command = sys.argv[1].lower()
    
    if command == "detect":
        if len(sys.argv) < 3:
            print(json.dumps({"success": False, "error": "Missing image path", "code": "INVALID_ARGS"}))
            sys.exit(1)
        result = detect_faces(sys.argv[2])
    
    elif command == "encode":
        if len(sys.argv) < 3:
            print(json.dumps({"success": False, "error": "Missing image path", "code": "INVALID_ARGS"}))
            sys.exit(1)
        result = encode_face(sys.argv[2])
    
    elif command == "match":
        if len(sys.argv) < 4:
            print(json.dumps({"success": False, "error": "Missing arguments", "code": "INVALID_ARGS"}))
            sys.exit(1)
        result = match_against_users(sys.argv[2], sys.argv[3])
    
    else:
        result = {
            "success": False,
            "error": f"Unknown command: {command}",
            "code": "INVALID_COMMAND"
        }
    
    print(json.dumps(result))
    sys.exit(0 if result.get("success") else 1)


if __name__ == "__main__":
    # Ensure UTF-8 handling for command line arguments on Windows
    if os.name == 'nt':  # Windows
        import locale
        if locale.getpreferredencoding().upper() != 'UTF-8':
            try:
                sys.argv = [arg.encode('utf-8').decode('utf-8', 'ignore') for arg in sys.argv]
            except:
                pass  # Continue with original args if encoding fails
    
    main()
