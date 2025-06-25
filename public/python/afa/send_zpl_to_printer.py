import sys
import socket
import os

def leer_archivo_zpl(path: str) -> str:
    if not os.path.isfile(path):
        raise FileNotFoundError(f"No se encontró el archivo: {path}")
    with open(path, "r", encoding="utf-8") as f:
        return f.read()

def es_zpl_valido(zpl: str) -> bool:
    return "^XA" in zpl and "^XZ" in zpl and zpl.index("^XA") < zpl.index("^XZ")

def enviar_a_impresora(zpl: str, ip: str, puerto: int = 9100):
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            s.connect((ip, puerto))
            s.sendall(zpl.encode('utf-8'))
        print(f"✅ Impresión enviada a {ip}:{puerto}")
    except Exception as e:
        print(f"❌ Error al enviar impresión: {e}")

def main():
    if len(sys.argv) < 3:
        print("Uso: send_zpl_to_printer.py /ruta/archivo.zpl IP_IMPRESORA")
        sys.exit(1)

    ruta = sys.argv[1]
    ip = sys.argv[2]

    try:
        zpl = leer_archivo_zpl(ruta)
    except Exception as e:
        print(f"❌ Error leyendo archivo: {e}")
        sys.exit(1)

    if not es_zpl_valido(zpl):
        print("❌ ZPL inválido: debe comenzar con ^XA y terminar con ^XZ.")
        sys.exit(1)

    enviar_a_impresora(zpl, ip)

if __name__ == "__main__":
    main()
