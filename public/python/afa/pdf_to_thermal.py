import sys
from pdf2image import convert_from_path
from PIL import Image
import socket

def image_to_zpl(image: Image.Image, dpi=203) -> str:
    image = image.convert("1")  # Blanco y negro
    width, height = image.size
    bytes_per_row = (width + 7) // 8
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

    label_width = 4 * dpi  # Ancho: 812 puntos (4" x 203 dpi)
    label_height = 8 * dpi # Alto: 1624 puntos (8" x 203 dpi)

    zpl = f"""
^XA
^PW{label_width}
^LL{label_height}
^FO0,0^GFA,{len(bitmap)},{len(bitmap)},{bytes_per_row},{hex_data}
^XZ
""".strip()
    return zpl

def enviar_a_impresora(zpl, ip, puerto=9100):
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.connect((ip, puerto))
        s.sendall(zpl.encode('utf-8'))

def main(pdf_path, zoom, printer_ip):
    dpi = 203  # DPI ajustado a estándar Zebra
    images = convert_from_path(pdf_path, dpi=dpi)
    img = images[0]

    # Asegura tamaño EXACTO 4"x8"
    img = img.resize((4*dpi, 8*dpi), Image.LANCZOS)

    zpl = image_to_zpl(img, dpi)

    enviar_a_impresora(zpl, printer_ip)
    print("Impresión enviada correctamente a:", printer_ip)

if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("Uso: pdf_to_thermal.py archivo.pdf zoom IP_IMPRESORA")
        sys.exit(1)
    main(sys.argv[1], sys.argv[2],sys.argv[3])
