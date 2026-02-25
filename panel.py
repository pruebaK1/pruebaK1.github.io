from flask import Flask, request
import subprocess, threading, uuid, json, datetime, re

app = Flask(__name__)
streams = {}

def get_url(url):
    if any(x in url for x in ['.m3u8', '.m3u', 'rtmp://', 'rtmps://']):
        return url
    # Intentar extraer m3u8 del HTML de la pagina
    try:
        html = subprocess.check_output(['curl','-s','-L','--max-time','15',url], text=True)
        m = re.search(r'https?://[^\s"\']+\.m3u8[^\s"\']*', html)
        if m:
            return m.group(0)
    except:
        pass
    # Intentar con yt-dlp
    try:
        return subprocess.check_output(['yt-dlp','-f','best','-g','--no-playlist',url], text=True, timeout=30).strip().split('\n')[0]
    except:
        return None

@app.route('/')
def index():
    return open('/app/index.html').read(), 200, {'Content-Type': 'text/html'}

@app.route('/api/streams', methods=['GET'])
def list_streams():
    return json.dumps([{'id':sid,'name':s['name'],'source':s['source'],'rtmp':s['rtmp'],'status':s['status'],'startedAt':s.get('startedAt'),'logs':s.get('logs',[])[-50:]} for sid,s in streams.items()])

@app.route('/api/streams', methods=['POST'])
def add_stream():
    d = request.get_json()
    sid = str(uuid.uuid4())[:8]
    streams[sid] = {'id':sid,'name':d.get('name','Stream'),'source':d['source'],'rtmp':d['rtmp'],'status':'stopped','proc':None,'logs':[]}
    return json.dumps({'ok':True,'id':sid})

@app.route('/api/streams/<sid>/start', methods=['POST'])
def start(sid):
    s = streams.get(sid)
    if not s or s['status'] == 'running':
        return json.dumps({'error':'no disponible'})
    def run():
        s['status'] = 'extracting'
        s['logs'] = ['[INFO] Extrayendo fuente...']
        url = get_url(s['source'])
        if not url:
            s['status'] = 'error'
            s['logs'].append('[ERROR] No se pudo obtener la URL')
            return
        s['logs'].append('[INFO] URL obtenida OK')
        s['logs'].append('[INFO] Iniciando FFmpeg...')
        s['status'] = 'running'
        s['startedAt'] = datetime.datetime.utcnow().isoformat()
        proc = subprocess.Popen(
            ['ffmpeg','-re','-i',url,'-c','copy','-f','flv',s['rtmp']],
            stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True
        )
        s['proc'] = proc
        for line in proc.stdout:
            if line.strip():
                s['logs'] = s['logs'][-150:] + [line.rstrip()]
        proc.wait()
        s['status'] = 'stopped' if proc.returncode == 0 else 'error'
        s['proc'] = None
    threading.Thread(target=run, daemon=True).start()
    return json.dumps({'ok':True})

@app.route('/api/streams/<sid>/stop', methods=['POST'])
def stop(sid):
    s = streams.get(sid)
    if s and s['proc']:
        s['proc'].kill()
        s['proc'] = None
    if s:
        s['status'] = 'stopped'
    return json.dumps({'ok':True})

@app.route('/api/streams/<sid>', methods=['DELETE'])
def delete(sid):
    s = streams.pop(sid, None)
    if s and s['proc']:
        s['proc'].kill()
    return json.dumps({'ok':True})

@app.route('/api/streams/<sid>/logs', methods=['GET'])
def logs(sid):
    s = streams.get(sid)
    return json.dumps({'logs': s.get('logs',[]) if s else []})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8080)
