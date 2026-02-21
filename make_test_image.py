from PIL import Image
import numpy as np

# Create a simple test image
img_array = np.random.randint(0, 255, (224, 224, 3), dtype=np.uint8)
img = Image.fromarray(img_array)
img.save('test_image.jpg')
print("Test image created: test_image.jpg")
