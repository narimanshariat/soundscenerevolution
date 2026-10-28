# Internet Archive CLI. Credentials live in the ia-config named volume, never in the repo.
FROM python:3.12-slim

RUN pip install --no-cache-dir internetarchive==5.12.1

WORKDIR /app
ENTRYPOINT ["ia"]
