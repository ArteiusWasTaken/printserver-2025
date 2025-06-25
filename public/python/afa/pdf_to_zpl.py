import os
import sys
from zebrafy import ZebrafyPDF

def convert_pdf_to_zpl(pdf_path, label_width=812, label_height=1624, dpi=203, invert=False):
    with open(pdf_path, "rb") as pdf:
        zpl_string = ZebrafyPDF(
            pdf.read(),
            format="Z64",
            invert=invert,
            dither=False,
            threshold=128,
            dpi=dpi,
            width=label_width,
            height=label_height,
            pos_x=0,
            pos_y=0,
            rotation=0,
            string_line_break=80,
            complete_zpl=True,
            split_pages=True,
        ).to_zpl()

    # Guardar archivo (opcional)
    with open("output.zpl", "wb") as zpl:
        zpl.write(zpl_string.encode('utf-8'))

    # Retorna bytes, no string
    return zpl_string.encode('utf-8')


if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.exit("Uso: python pdf_to_zpl.py <ruta/relativa/al/pdf>")

    relative_path = sys.argv[1]
    project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
    pdf_file = os.path.abspath(os.path.join(project_root, relative_path))

    if not os.path.exists(pdf_file):
        sys.exit(f"Archivo no encontrado: {pdf_file}")

    zpl_code = convert_pdf_to_zpl(pdf_file, label_width=812, label_height=1624, invert=True)
    clean_zpl = zpl_code.decode('utf-8').replace('\n', '').strip()
    print(clean_zpl)

