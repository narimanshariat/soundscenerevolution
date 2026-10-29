# Speech/music segmentation for the transcript filter (inaSpeechSegmenter, CPU only).
# 0.8.x requires CUDA packages with no arm64 build; 0.7.14 runs on TensorFlow 2.15 (Keras 2).
FROM python:3.11-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Its own requirements pull in tensorflow[and-cuda] and onnxruntime-gpu, so install it
# without them and pin what it actually uses.
RUN pip install --no-cache-dir tensorflow==2.15.1 "numpy<2" pandas scikit-image matplotlib \
        pyannote.core Pyro4 pytextgrid soundfile \
    && pip install --no-cache-dir --no-deps inaSpeechSegmenter==0.7.14

# Fetch the model weights at build time rather than on every run.
RUN python -c "from inaSpeechSegmenter import Segmenter; Segmenter(vad_engine='smn', detect_gender=False)"

WORKDIR /app
ENTRYPOINT ["python", "scripts/find-music.py"]
