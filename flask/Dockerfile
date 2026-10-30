# The documented Books API (Flask + Flasgger).
FROM python:3.12-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py .

EXPOSE 5000
# gunicorn is a production web server for Python apps. Flask's built-in server
# (flask run) is meant for development only and says so in its log.
CMD ["gunicorn", "--bind", "0.0.0.0:5000", "--workers", "2", "--access-logfile", "-", "app:app"]
