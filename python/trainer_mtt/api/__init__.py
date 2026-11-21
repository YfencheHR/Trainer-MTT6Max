"""Capa HTTP (Flask): una aplicación que se crea con create_app() y rutas separadas por responsabilidad."""

from flask import Flask

from trainer_mtt.ai.ollama import OllamaClient
from trainer_mtt.ai.streets import default_registry
from trainer_mtt.api.ai import ai_blueprint
from trainer_mtt.api.equity import equity_blueprint
from trainer_mtt.api.system import system_blueprint
from trainer_mtt.equity.simulator import EquitySimulator


def create_app(simulator=None, registry=None, client_factory=OllamaClient):
    """La aplicación con sus dependencias: el simulador de equity, los servicios de calle de la IA y el cliente de Ollama."""
    app = Flask(__name__)
    app.register_blueprint(system_blueprint())
    app.register_blueprint(equity_blueprint(simulator or EquitySimulator()))
    app.register_blueprint(ai_blueprint(registry or default_registry(), client_factory))

    return app
