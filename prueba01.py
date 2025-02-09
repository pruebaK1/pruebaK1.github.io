import requests

url = "https://pruebak1.github.io/prueba.html"
response = requests.get(https://pruebak1.github.io/dominio.json)

if response.status_code == 200:
    datos = response.json()
    print(datos)
else:
    print("Error al recopilar datos")
