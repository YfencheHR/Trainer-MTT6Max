"""Punto de entrada del servicio (flask run busca la variable `app` en este archivo)."""

from trainer_mtt.api import create_app

app = create_app()

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)
