from flask import Flask, jsonify, request
from math import isfinite
from equity_simulator import EquitySimulator
import ai_decision
import streets

app = Flask(__name__)
simulator = EquitySimulator()


def _equity_request(data):
    """Validate the shared preflop equity request used by analysis and prediction."""
    if not isinstance(data, dict):
        raise ValueError('A JSON object is required')

    hero_hand = data.get('hero_hand')
    opponent_ranges = data.get('opponent_ranges')
    villain_range = data.get('villain_range')
    iterations = data.get('iterations', 5000)

    if not isinstance(hero_hand, str) or not hero_hand:
        raise ValueError('hero_hand is required')
    if opponent_ranges is None:
        if not isinstance(villain_range, list) or not villain_range:
            raise ValueError('villain_range or opponent_ranges is required')
        opponent_ranges = [villain_range]
    if not isinstance(opponent_ranges, list) or not opponent_ranges:
        raise ValueError('opponent_ranges must be a non-empty list of ranges')
    if type(iterations) is not int or not 100 <= iterations <= 50000:
        raise ValueError('iterations must be an integer from 100 to 50000')

    return hero_hand, opponent_ranges, iterations


def _table_parameters(data, percent_key, default_percent):
    if not isinstance(data, dict):
        raise ValueError('A JSON object is required')

    stack_depth = data.get('stack_depth_bb', 50)
    range_percent = data.get(percent_key, default_percent)
    if (isinstance(stack_depth, bool) or not isinstance(stack_depth, (int, float))
            or not isfinite(stack_depth) or stack_depth <= 0):
        raise ValueError('stack_depth_bb must be a positive number')
    if (isinstance(range_percent, bool) or not isinstance(range_percent, (int, float))
            or not isfinite(range_percent) or not 0 < range_percent <= 100):
        raise ValueError(f'{percent_key} must be greater than 0 and at most 100')

    return stack_depth, range_percent

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        'status': 'ok',
        'service': 'trainer-mtt-equity',
        'version': '1.0.0'
    })

@app.route('/', methods=['GET'])
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

@app.route('/api/equity/simulate', methods=['POST'])
def simulate_equity():
    """
    Calcula equity preflop heads-up o contra varios rangos.

    Request:
    {
        "hero_hand": "AKs",
        "opponent_ranges": [["AA", "KK"], ["QQ", "JJ"]],
        "iterations": 5000
    }
    """
    data = request.get_json(silent=True)
    if not isinstance(data, dict):
        return jsonify({'error': 'A JSON object is required'}), 400

    hero_hand = data.get('hero_hand')
    iterations = data.get('iterations', 5000)
    opponent_ranges = data.get('opponent_ranges')
    villain_range = data.get('villain_range')

    if not hero_hand or (opponent_ranges is None and not villain_range):
        return jsonify({'error': 'Missing hero_hand or opponent range(s)'}), 400
    if not isinstance(iterations, int) or not 100 <= iterations <= 50000:
        return jsonify({'error': 'iterations must be an integer from 100 to 50000'}), 400

    try:
        if opponent_ranges is not None:
            result = simulator.simulate_multiway_equity(hero_hand, opponent_ranges, iterations)
            range_sizes = [len(hand_range) for hand_range in opponent_ranges]
        else:
            result = simulator.simulate_equity(hero_hand, villain_range, iterations)
            range_sizes = [len(villain_range)]
    except (TypeError, ValueError) as error:
        return jsonify({'error': str(error)}), 400

    response = {
        'status': 'success',
        'hero_hand': hero_hand,
        'result': result
    }
    if opponent_ranges is None:
        response['villain_range_size'] = range_sizes[0]
    else:
        response['opponent_range_sizes'] = range_sizes

    return jsonify(response)

@app.route('/api/tables/generate/attack', methods=['POST'])
def generate_attack_table():
    """
    Genera tabla de ataque para un stack depth

    Request:
    {
        "stack_depth_bb": 50,
        "defense_range_percent": 20,
        "defense_range_strs": ["AA", "KK", "AKs"],  # Optional
        "equity_threshold": 0.5,  # Optional
        "iterations": 1000  # Optional (optimized from 2000)
    }
    """
    try:
        data = request.get_json(silent=True)
        stack_depth_bb, defense_percent = _table_parameters(
            data, 'defense_range_percent', 20,
        )

        defense_range_strs = data.get('defense_range_strs')
        equity_threshold = data.get('equity_threshold', 0.5)
        iterations = data.get('iterations', 1000)

        result = simulator.generate_attack_table(
            stack_depth_bb,
            defense_percent,
            defense_range_strs=defense_range_strs,
            equity_threshold=equity_threshold,
            iterations=iterations
        )

        opening_range = [hand for hand, info in result.items() if info['include']]
        cache_stats = simulator.get_cache_stats()

        return jsonify({
            'status': 'success',
            'stack_depth_bb': stack_depth_bb,
            'opening_range': opening_range,
            'range_percent': round(len(opening_range) / 169 * 100, 2),
            'cache_stats': cache_stats,
            'tables': result
        })

    except (TypeError, ValueError) as e:
        return jsonify({'status': 'error', 'message': str(e)}), 400
    except Exception as e:
        return jsonify({'status': 'error', 'message': str(e)}), 500

@app.route('/api/tables/generate/defense', methods=['POST'])
def generate_defense_table():
    """
    Genera tabla de defensa para un stack depth

    Request:
    {
        "stack_depth_bb": 50,
        "attack_range_percent": 15,
        "attack_range_strs": ["AKs", "AKo"],  # Optional
        "equity_threshold": 0.45,  # Optional
        "iterations": 500  # Optional (optimized from 1000)
    }
    """
    try:
        data = request.get_json(silent=True)
        stack_depth_bb, attack_percent = _table_parameters(
            data, 'attack_range_percent', 15,
        )

        attack_range_strs = data.get('attack_range_strs')
        equity_threshold = data.get('equity_threshold', 0.45)
        iterations = data.get('iterations', 500)

        result = simulator.generate_defense_table(
            stack_depth_bb,
            attack_percent,
            attack_range_strs=attack_range_strs,
            equity_threshold=equity_threshold,
            iterations=iterations
        )

        defending_range = [hand for hand, info in result.items() if info['include']]
        cache_stats = simulator.get_cache_stats()

        return jsonify({
            'status': 'success',
            'stack_depth_bb': stack_depth_bb,
            'defending_range': defending_range,
            'range_percent': round(len(defending_range) / 169 * 100, 2),
            'cache_stats': cache_stats,
            'tables': result
        })

    except (TypeError, ValueError) as e:
        return jsonify({'status': 'error', 'message': str(e)}), 400
    except Exception as e:
        return jsonify({'status': 'error', 'message': str(e)}), 500


@app.route('/api/predict', methods=['POST'])
def predict():
    """Give a preflop call/fold estimate from Monte Carlo equity and pot odds."""
    data = request.get_json(silent=True)
    try:
        hero_hand, opponent_ranges, iterations = _equity_request(data)
        pot = data.get('pot')
        call_amount = data.get('call_amount')
        if (isinstance(pot, bool) or not isinstance(pot, (int, float)) or pot < 0
                or isinstance(call_amount, bool) or not isinstance(call_amount, (int, float)) or call_amount < 0):
            raise ValueError('pot and call_amount must be non-negative numbers')

        result = simulator.simulate_multiway_equity(hero_hand, opponent_ranges, iterations)
        equity = result['equity']
        pot_odds = call_amount / (pot + call_amount) if call_amount else 0.0
        call_ev = equity * (pot + call_amount) - call_amount
        decision = 'CHECK' if call_amount == 0 else ('CALL' if equity >= pot_odds else 'FOLD')

        return jsonify({
            'status': 'success',
            'message': 'Preflop estimate calculated from Monte Carlo equity and pot odds.',
            'hero_hand': hero_hand,
            'decision': decision,
            'equity': equity,
            'pot_odds': round(pot_odds, 4),
            'call_ev': round(call_ev, 4),
            'decision_margin': round(abs(equity - pot_odds), 4),
            'iterations': result['iterations'],
            'opponents': result['opponents'],
        })
    except (TypeError, ValueError) as error:
        return jsonify({'status': 'error', 'message': str(error)}), 400


@app.route('/api/analyze', methods=['POST'])
def analyze():
    """Return real Monte Carlo equity and outcome counts for preflop ranges."""
    data = request.get_json(silent=True)
    try:
        hero_hand, opponent_ranges, iterations = _equity_request(data)
        result = simulator.simulate_multiway_equity(hero_hand, opponent_ranges, iterations)
        return jsonify({
            'status': 'success',
            'message': 'Preflop Monte Carlo analysis completed.',
            'analysis': {
                'street': 'preflop',
                'hero_hand': hero_hand,
                'opponent_range_sizes': [len(hand_range) for hand_range in opponent_ranges],
                **result,
            },
        })
    except (TypeError, ValueError) as error:
        return jsonify({'status': 'error', 'message': str(error)}), 400


@app.route('/api/cache/stats', methods=['GET'])
def cache_stats():
    """Devuelve estadísticas del caché de equity."""
    stats = simulator.get_cache_stats()
    return jsonify({
        'status': 'success',
        'cache_stats': stats
    })


@app.route('/api/cache/clear', methods=['POST'])
def cache_clear():
    """Limpia el caché de equity."""
    simulator.clear_cache()
    return jsonify({
        'status': 'success',
        'message': 'Cache cleared',
        'cache_stats': simulator.get_cache_stats()
    })

def _ollama():
    return ai_decision.OllamaClient()


@app.route('/ai/health', methods=['GET'])
def ai_health():
    """¿Está Ollama en marcha y qué modelos tiene descargados?"""
    try:
        return jsonify({'status': 'ok', 'models': _ollama().models()})
    except ConnectionError as error:
        return jsonify({'status': 'unavailable', 'message': str(error)}), 503


@app.route('/ai/streets', methods=['GET'])
def ai_streets():
    """Calles que ya decide la IA (una por servicio)."""
    return jsonify({'streets': streets.available()})


@app.route('/ai/decide', methods=['POST'])
def ai_decide():
    """Estado + perfil -> distribución de probabilidad de cada acción. El sorteo lo hace quien llama (con su semilla)."""
    data = request.get_json(silent=True)
    if not isinstance(data, dict) or not isinstance(data.get('state'), dict) or not isinstance(data.get('model'), str):
        return jsonify({'status': 'error', 'message': 'state (object) and model (string) are required'}), 400
    street = data['state'].get('street')
    service = streets.service_for(street)
    if service is None:
        return jsonify({'status': 'not_available', 'message': f"No AI service for street '{street}' yet", 'streets': streets.available()}), 501
    try:
        result = service.decide(data['state'], data['model'], data.get('legal_actions'), _ollama(), data.get('seed'))
    except ValueError as error:
        return jsonify({'status': 'error', 'message': str(error)}), 422
    except ConnectionError as error:
        return jsonify({'status': 'unavailable', 'message': str(error)}), 503
    return jsonify({'status': 'success', **result})


if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)
