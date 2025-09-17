import sys
import os
import zpl
import uuid
import textwrap
import unicodedata

def clean_text(text):
    return unicodedata.normalize("NFKD", text).encode("ascii", "ignore").decode("ascii")


sku = clean_text(str(sys.argv[1]))
desc = clean_text(str(sys.argv[2]))
serie = str(sys.argv[3])
qty = str(sys.argv[4])
extra = clean_text(str(sys.argv[5]))
dpi = int(sys.argv[6]) if len(sys.argv) > 6 else 300  # por defecto 300 dpi

# Escala según DPI
if dpi == 203:
    DPI_SCALE = 1  # nativo 203 dpi
else:
    DPI_SCALE = 300 / 203  # escalado para 300 dpi

sku_length = len(sku)
serie_length = len(serie)

# Ajustar el ancho de los códigos de barras para reducir el espacio horizontal
barcode_width_length = 0.12 * DPI_SCALE if sku_length <= 10 else 0.099 * DPI_SCALE  # Ancho del código de barras
barcode_start_cords_x = 7 * DPI_SCALE if sku_length <= 12 else 7 * DPI_SCALE

serie_width_length = 0.12 * DPI_SCALE if serie_length <= 10 else 0.1 * DPI_SCALE  # Ancho del código de barras
serie_start_cords_x = 5 * DPI_SCALE if serie_length <= 12 else 6 * DPI_SCALE

# Crear la etiqueta con tamaño ajustado para 300 dpi
l = zpl.Label(50.8 * DPI_SCALE, 25.4 * DPI_SCALE)

# Ajustar el texto
wrapped_desc = textwrap.wrap(desc, width=40)

start_y = 2 * DPI_SCALE  # Ajuste en Y
for i, line in enumerate(wrapped_desc[:2]):
    l.origin(int(5 * DPI_SCALE), int(start_y + (i * 3 * DPI_SCALE)))
    l.write_text(line, char_height=1.5 * DPI_SCALE, char_width=1 * DPI_SCALE, line_width=30 * DPI_SCALE, justification='L')
    l.endorigin()

# Ajustar código de barras y texto
l.origin(int(barcode_start_cords_x - (sku_length / 2) * DPI_SCALE), int(7 * DPI_SCALE))
l.barcode_field_default(module_width=barcode_width_length, bar_width_ratio=2, height=25 * DPI_SCALE)
l.barcode(height=int(25 * DPI_SCALE), barcode_type='C', code=sku, check_digit='N')
l.write_text(sku)
l.endorigin()

# Ajuste para el código de barras del serie
l.origin(int(serie_start_cords_x - (serie_length / 2) * DPI_SCALE if serie_length <= 12 else 0.1 * DPI_SCALE ), int(12 * DPI_SCALE))
l.barcode_field_default(module_width=serie_width_length, bar_width_ratio=2, height=25 * DPI_SCALE)
l.barcode(height=int(30 * DPI_SCALE), barcode_type='C', code=serie, check_digit='N')
l.write_text(serie)
l.endorigin()

# Si hay extra, ajustar tamaño y posición
if extra != "":
    l.origin(int(2 * DPI_SCALE), int(14 * DPI_SCALE))
    l.write_text(extra[:50], char_height=2 * DPI_SCALE, char_width=1.2 * DPI_SCALE, line_width=40 * DPI_SCALE)
    l.endorigin()

# Generar el código ZPL final con cantidad ajustada
zpl_code = l.dumpZPL()[:-3]
zpl_code += "^PQ" + qty + ",0,1,Y^XZ"

print(zpl_code)
