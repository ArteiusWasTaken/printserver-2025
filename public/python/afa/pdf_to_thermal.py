import sys
from pdf2image import convert_from_path
from PIL import Image
import socket
import math

def image_to_zpl(image: Image.Image, dpi=203) -> str:
    # Asegurar 1-bit (blanco/negro) sin caracteres raros
    image = image.convert("1")  # dither por defecto; si prefieres, usa convert("1", dither=Image.NONE)
    width, height = image.size

    # Cálculos binarios por línea
    bytes_per_row = (width + 7) // 8
    total_bytes = bytes_per_row * height

    # Construir bitmap 1-bit (negro=1)
    bitmap = bytearray()
    for y in range(height):
        row_byte = 0
        bit = 0
        for x in range(width):
            # En modo "1", píxel negro suele ser 0 → invertimos: consideraremos 0=blanco, 1=negro
            is_black = (image.getpixel((x, y)) == 0)
            if is_black:
                row_byte |= (1 << (7 - bit))
            bit += 1
            if bit == 8:
                bitmap.append(row_byte)
                row_byte = 0
                bit = 0
        if bit > 0:
            bitmap.append(row_byte)

    # HEX en mayúsculas
    hex_data = bitmap.hex().upper()

    # Validaciones fuertes (evita ZPL inválido silencioso)
    if len(bitmap) != total_bytes:
        raise ValueError(f"Bitmap size inconsistente: len(bitmap)={len(bitmap)} vs total_bytes={total_bytes}")
    if len(hex_data) != total_bytes * 2:
        raise ValueError(f"HEX length inconsistente: len(hex_data)={len(hex_data)} vs {total_bytes*2}")

    # Tamaño de etiqueta en puntos (usa el tamaño real de la imagen ya escalada/rotada)
    label_width = width           # puntos
    label_height = height         # puntos
    rows = height                 # filas = alto en px

    # ^GFA,total,bytes_per_row,rows,hex
    zpl = (
        "^XA\n"
        f"^PW{label_width}\n"
        f"^LL{label_height}\n"
        f"^FO0,0^GFA,{total_bytes},{bytes_per_row},{rows},{hex_data}\n"
        "^XZ\n"
    )
    return zpl

def enviar_a_impresora(zpl: str, ip: str, puerto: int = 9100, timeout: int = 10):
    # Enviar en ASCII puro
    payload = zpl.encode("ascii", errors="ignore")
    with socket.create_connection((ip, puerto), timeout=timeout) as s:
        s.sendall(payload)

def main(pdf_path: str, zoom: str, printer_ip: str):
    dpi = 203  # Zebra estándar 203 dpi
    images = convert_from_path(pdf_path, dpi=dpi)
    img = images[0]

    # Transformaciones por "zoom" como en tu lógica original
    try:
        zoom_value = float(zoom)
        if 0 < zoom_value < 1:
            # recorte franja izquierda
            w, h = img.size
            img = img.crop((0, 0, int(w * zoom_value), h))
        elif zoom_value > 1:
            # cortar en bloques horizontales (toma el primero) y rotar
            w, h = img.size
            guia_height = int(h / zoom_value)
            img = img.crop((0, 0, w, guia_height))
            img = img.rotate(90, expand=True)
        # zoom == 1 → sin recorte
    except Exception:
        pass

    # Escalar (ajusta a tu tamaño real de guía; muchas son 4x6 → 4*dpi x 6*dpi)
    # Tú usabas 4x8; si tu guía es 4x6, cambia 8*dpi por 6*dpi
    img = img.resize((4 * dpi, 8 * dpi), Image.LANCZOS)

    # Generar ZPL válido
    zpl = image_to_zpl(img, dpi)

    # Enviar a la impresora
    enviar_a_impresora(zpl, printer_ip)
    print("Impresión enviada correctamente a:", printer_ip)

if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("Uso: pdf_to_thermal.py archivo.pdf zoom IP_IMPRESORA")
        sys.exit(1)
    main(sys.argv[1], sys.argv[2], sys.argv[3])
