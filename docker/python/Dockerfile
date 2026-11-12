FROM python:3.11-slim

WORKDIR /app

# Instalar dependencias
RUN apt-get update && apt-get install -y \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Copiar requirements
COPY python/requirements.txt /app/requirements.txt

# Instalar Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Copiar código
COPY python /app

EXPOSE 5000

CMD ["python", "-m", "flask", "run", "--host=0.0.0.0"]
