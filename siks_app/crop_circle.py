from PIL import Image, ImageDraw, ImageOps
import sys
import os

def crop_to_circle(input_path, output_path):
    try:
        # Open image
        img = Image.open(input_path).convert("RGBA")
        
        # Make it square
        size = min(img.size)
        img = ImageOps.fit(img, (size, size), centering=(0.5, 0.5))
        
        # Create mask
        mask = Image.new('L', (size, size), 0)
        draw = ImageDraw.Draw(mask)
        draw.ellipse((0, 0, size, size), fill=255)
        
        # Apply mask
        output = Image.new('RGBA', (size, size), (0, 0, 0, 0))
        output.paste(img, (0, 0), mask=mask)
        
        # Save output
        output.save(output_path, format="PNG")
        print("Success!")
    except Exception as e:
        print(f"Error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python crop_circle.py input output")
        sys.exit(1)
    
    crop_to_circle(sys.argv[1], sys.argv[2])
