#!/usr/bin/env python3
"""
CAPTCHA Generation Service for WANNASNI Login
Generates a random text captcha image with visual distortion using Pillow.

Usage:
    python captcha_service.py generate
Output:
    JSON {"answer": "AB3XY", "image_base64": "data:image/png;base64,..."}
"""

import json
import sys
import random
import string
import base64
import io

try:
    from PIL import Image, ImageDraw, ImageFont, ImageFilter
except ImportError:
    print(json.dumps({"error": "Pillow library not installed. Run: pip install Pillow"}))
    sys.exit(1)


def generate_captcha(length=5, width=280, height=90):
    """Generate a captcha image with random text and distortion."""
    # Characters excluding confusing ones (0/O, 1/I/l)
    chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
    answer = ''.join(random.choices(chars, k=length))

    # Create image
    image = Image.new('RGB', (width, height))
    draw = ImageDraw.Draw(image)

    # Background with random color blocks
    for x in range(0, width, 10):
        for y in range(0, height, 10):
            color = (
                random.randint(210, 245),
                random.randint(210, 245),
                random.randint(210, 245)
            )
            draw.rectangle([x, y, x + 10, y + 10], fill=color)

    # Try to use a system font, fall back to default
    font_size = 42
    font = None
    for font_name in ["arial.ttf", "Arial.ttf", "DejaVuSans-Bold.ttf", "FreeSansBold.ttf"]:
        try:
            font = ImageFont.truetype(font_name, font_size)
            break
        except (IOError, OSError):
            continue

    if font is None:
        try:
            font = ImageFont.load_default(size=font_size)
        except TypeError:
            font = ImageFont.load_default()

    # Draw each character with random position and rotation
    x_start = 15
    for char in answer:
        char_color = (
            random.randint(10, 100),
            random.randint(10, 100),
            random.randint(10, 100)
        )
        # Create a small transparent image for each character
        char_img = Image.new('RGBA', (55, 65), (0, 0, 0, 0))
        char_draw = ImageDraw.Draw(char_img)
        char_draw.text((5, 5), char, font=font, fill=char_color)

        # Random rotation
        angle = random.randint(-25, 25)
        char_img = char_img.rotate(angle, expand=True, resample=Image.BICUBIC)

        # Paste onto main image
        y_pos = random.randint(5, 20)
        image.paste(char_img, (x_start, y_pos), char_img)
        x_start += random.randint(42, 52)

    # Add noise lines
    for _ in range(random.randint(5, 8)):
        line_color = (
            random.randint(80, 180),
            random.randint(80, 180),
            random.randint(80, 180)
        )
        x1, y1 = random.randint(0, width), random.randint(0, height)
        x2, y2 = random.randint(0, width), random.randint(0, height)
        draw.line([(x1, y1), (x2, y2)], fill=line_color, width=random.randint(1, 3))

    # Add noise dots
    for _ in range(random.randint(150, 300)):
        dot_color = (
            random.randint(80, 200),
            random.randint(80, 200),
            random.randint(80, 200)
        )
        x, y = random.randint(0, width - 1), random.randint(0, height - 1)
        draw.point((x, y), fill=dot_color)

    # Add arced lines for extra distortion
    for _ in range(random.randint(2, 4)):
        arc_color = (
            random.randint(60, 160),
            random.randint(60, 160),
            random.randint(60, 160)
        )
        x1 = random.randint(-20, width // 2)
        y1 = random.randint(-20, height // 2)
        x2 = random.randint(width // 2, width + 20)
        y2 = random.randint(height // 2, height + 20)
        start_angle = random.randint(0, 180)
        end_angle = start_angle + random.randint(90, 270)
        draw.arc([x1, y1, x2, y2], start_angle, end_angle, fill=arc_color, width=2)

    # Apply slight blur for smoothing
    image = image.filter(ImageFilter.SMOOTH)

    # Convert to base64 PNG
    buffer = io.BytesIO()
    image.save(buffer, format='PNG', optimize=True)
    img_base64 = base64.b64encode(buffer.getvalue()).decode('utf-8')

    return {
        "answer": answer,
        "image_base64": f"data:image/png;base64,{img_base64}"
    }


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Usage: python captcha_service.py generate"}))
        sys.exit(1)

    command = sys.argv[1]

    if command == 'generate':
        result = generate_captcha()
        print(json.dumps(result))
    else:
        print(json.dumps({"error": f"Unknown command: {command}"}))
        sys.exit(1)
