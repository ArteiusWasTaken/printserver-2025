import sys
import uuid
from pdf2image import convert_from_path
from PIL import Image
import os

def image_to_zpl(image: Image.Image) -> str:
    image = image.convert("1")  # Convertir a blanco y negro
    width, height = image.size
    bytes_per_row = (width + 7) // 8
    total_bytes = bytes_per_row * height
    bitmap = bytearray()

    for y in range(height):
        row = 0
        bit = 0
        for x in range(width):
            if image.getpixel((x, y)) == 0:
                row |= (1 << (7 - bit))
            bit += 1
            if bit == 8:
                bitmap.append(row)
                row = 0
                bit = 0
        if bit > 0:
            bitmap.append(row)

    hex_data = bitmap.hex().upper()
    zpl = f"^XA^FO0,0^GFA,{len(bitmap)},{len(bitmap)},{bytes_per_row},{hex_data}^XZ"
    return zpl

def main(pdf_path, zoom):
    images = convert_from_path(pdf_path, dpi=203)
    output_path = str(uuid.uuid4()) + ".zpl"

    # Solo tomamos la primera página
    img = images[0]

    if int(zoom) == 0:
        img = img.resize((576, 288))  # Ajuste opcional

    zpl = image_to_zpl(img)
    with open(output_path, "w") as f:
        f.write(zpl)

    print(output_path)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Uso: pdf_to_thermal.py archivo.pdf zoom")
        sys.exit(1)
    main(sys.argv[1], sys.argv[2])
