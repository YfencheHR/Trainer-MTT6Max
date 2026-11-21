"""Rutas de la IA de los bots: estado de Ollama, calles cubiertas y decisión."""

from flask import Blueprint, jsonify, request

from trainer_mtt.ai.ollama import OllamaClient


def ai_blueprint(registry, client_factory=OllamaClient):
    """`client_factory` crea el cliente de Ollama en cada petición (así se lee el entorno al momento y los tests lo sustituyen)."""
    blueprint = Blueprint('ai', __name__, url_prefix='/ai')

    @blueprint.get('/health')
    def health():
        """¿Está Ollama en marcha y qué modelos tiene descargados?"""
        try:
            return jsonify({'status': 'ok', 'models': client_factory().models()})
        except ConnectionError as error:
            return jsonify({'status': 'unavailable', 'message': str(error)}), 503

    @blueprint.get('/streets')
    def streets():
        """Calles que ya decide la IA (una por servicio)."""
        return jsonify({'streets': registry.available()})

    @blueprint.post('/decide')
    def decide():
        """Estado + perfil -> distribución de probabilidad de cada acción. El sorteo lo hace quien llama (con su semilla)."""
        data = request.get_json(silent=True)
        if not isinstance(data, dict) or not isinstance(data.get('state'), dict) or not isinstance(data.get('model'), str):
            return jsonify({'status': 'error', 'message': 'state (object) and model (string) are required'}), 400
        street = data['state'].get('street')
        service = registry.service_for(street)
        if service is None:
            return jsonify({'status': 'not_available', 'message': f"No AI service for street '{street}' yet", 'streets': registry.available()}), 501
        try:
            result = service.decide(data['state'], data['model'], data.get('legal_actions'), client_factory(), data.get('seed'))
        except ValueError as error:
            return jsonify({'status': 'error', 'message': str(error)}), 422
        except ConnectionError as error:
            return jsonify({'status': 'unavailable', 'message': str(error)}), 503

        return jsonify({'status': 'success', **result})

    return blueprint
