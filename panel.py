from flask import Flask, request, render_template_string
import subprocess

app = Flask(__name__)
ffmpeg_process = None

HTML = """
<h2>🔥 SmartStream Panel</h2>

<form method="post">
URL Web o M3U8:<br>
<input name="url" size="80"><br><br>

RTMP Output:<br>
<input name="rtmp" size="80" value="rtmp://YOUR_OWNCAST_IP/live/STREAMKEY"><br><br>

<button type="submit" name="action" value="start">▶ START STREAM</button>
<button type="submit" name="action" value="stop">⛔ STOP STREAM</button>
</form>

{% if result %}
<h3>Status:</h3>
<pre>{{ result }}</pre>
{% endif %}
"""

@app.route("/", methods=["GET", "POST"])
def index():
    global ffmpeg_process
    result = ""

    if request.method == "POST":
        url = request.form["url"]
        rtmp = request.form["rtmp"]
        action = request.form["action"]

        if action == "stop":
            if ffmpeg_process:
                ffmpeg_process.kill()
                ffmpeg_process = None
                result = "Stream detenido"
            else:
                result = "No hay stream activo"

        if action == "start":
            if ffmpeg_process:
                result = "Ya hay un stream activo"
            else:
                # Detectar si es m3u8 directo
                if ".m3u8" in url:
                    stream_url = url
                else:
                    # Extraer con yt-dlp
                    try:
                        cmd = ["yt-dlp", "-f", "best", "-g", url]
                        stream_url = subprocess.check_output(cmd, text=True).strip()
                    except:
                        return render_template_string(HTML, result="❌ No se pudo extraer M3U8")

                # Lanzar FFmpeg
                ffmpeg_cmd = [
                    "ffmpeg", "-re", "-i", stream_url,
                    "-c", "copy", "-f", "flv", rtmp
                ]

                ffmpeg_process = subprocess.Popen(ffmpeg_cmd)
                result = f"✅ Stream iniciado\nFuente:\n{stream_url}"

    return render_template_string(HTML, result=result)

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8080)
