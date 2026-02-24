FROM debian:12

RUN apt update && apt install -y ffmpeg python3 python3-pip yt-dlp curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY panel.py /app/panel.py

RUN pip3 install flask --break-system-packages

EXPOSE 8080
CMD ["python3", "panel.py"]
