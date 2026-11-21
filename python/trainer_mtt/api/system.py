"""Rutas de sistema: comprobación de estado e índice."""

from flask import Blueprint, jsonify


def system_blueprint():
    blueprint = Blueprint('system', __name__)

    @blueprint.get('/health')
    def health():
        return jsonify({
            'status': 'ok',
            'service': 'trainer-mtt-equity',
            'version': '1.0.0'
        })

    @blueprint.get('/')
    def index():
        return jsonify({
            'message': 'Trainer MTT - equity simulation and range analysis service',
            'endpoints': {
                '/health': 'Health check',
                '/api/equity/simulate': 'Calculate equity (POST)',
                '/api/tables/generate/attack': 'Generate attack table (POST)',
                '/api/tables/generate/defense': 'Generate defense table (POST)',
                '/api/predict': 'Preflop call/fold estimate using equity and pot odds (POST)',
                '/api/analyze': 'Preflop Monte Carlo equity analysis (POST)',
            }
        })

    return blueprint
