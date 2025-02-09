import requests

url = "https://example.com/api/datos"
response = requests.get()

if response.status_code == 200:
    datos = response.json()
    print(datos)
else:
    print("Error al recopilar datos")
