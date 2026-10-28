# Speech-to-text for episode transcripts (CPU only; Docker on macOS has no GPU access).
FROM python:3.12-slim

# Pin av: 19.x rejects the metadata_errors argument faster-whisper 1.2.1 passes; 16.1.0 accepts it.
RUN pip install --no-cache-dir faster-whisper==1.2.1 av==16.1.0

WORKDIR /app
ENTRYPOINT ["python", "scripts/transcribe.py"]
