from pdf2image import convert_from_path
from PIL import Image
import sys
import zpl

pdf_path = sys.argv[1]

# Convertimos PDF a imagen
pages = convert_from_path(pdf_path, dpi=203)
image_path = pdf_path.replace('.pdf', '.png')
pages[0].save(image_path, 'PNG')

# Convertimos imagen a blanco y negro
img = Image.open(image_path).convert("1")

# Generamos el ZPL
label = zpl.Label(100, 150)
label.origin(0, 0)
label.image(img, 0, 0, True)
print(label.dumpZPL())
