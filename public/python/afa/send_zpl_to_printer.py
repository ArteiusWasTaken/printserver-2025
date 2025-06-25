import sys
import socket

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
        print("Uso: send_zpl_to_printer.py "ZPL_STRING" IP_IMPRESORA")
        sys.exit(1)

    zpl = sys.argv[1]
    ip = sys.argv[2]

    enviar_a_impresora(zpl, ip)

if __name__ == "__main__":
    main()
