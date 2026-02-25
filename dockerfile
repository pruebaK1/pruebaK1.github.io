FROM python:3.11-slim
RUN apt-get update && apt-get install -y ffmpeg curl && \
    curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp && \
    chmod +x /usr/local/bin/yt-dlp && \
    pip install flask
WORKDIR /app
COPY panel.py index.html ./
EXPOSE 8080
CMD ["python", "panel.py"]
