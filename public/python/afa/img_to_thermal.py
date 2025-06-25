import sys
from PIL import Image
import socket

def image_to_zpl(image: Image.Image, dpi=203) -> str:
    image = image.convert("1")  # Convertir a blanco y negro
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

    label_width = 4 * dpi  # 812 puntos para 4"
    label_height = 8 * dpi # 1624 puntos para 8"

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

def main(img_path, zoom, printer_ip):
    dpi = 203  # DPI estándar Zebra
    img = Image.open(img_path)

    if int(zoom) == 0:
        img = img.resize((4*dpi, 8*dpi), Image.LANCZOS)

    zpl = image_to_zpl(img, dpi)

    enviar_a_impresora(zpl, printer_ip)

    print("Impresión enviada correctamente a:", printer_ip)

if _name_ == "_main_":
    if len(sys.argv) < 4:
        print("Uso: image_to_thermal.py imagen.png zoom IP_IMPRESORA")
        sys.exit(1)
    main(sys.argv[1], sys.argv[2], sys.argv[3])
