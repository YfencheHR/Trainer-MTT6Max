from flask import Flask, jsonify, request
from math import isfinite
from equity_simulator import EquitySimulator

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
        "defense_range_percent": 20
    }
    """
    try:
        stack_depth_bb, defense_percent = _table_parameters(
            request.get_json(silent=True), 'defense_range_percent', 20,
        )

        result = simulator.generate_attack_table(stack_depth_bb, defense_percent)

        # Filtrar solo manos que entran en rango
        opening_range = [hand for hand, info in result.items() if info['include']]

        return jsonify({
            'status': 'success',
            'stack_depth_bb': stack_depth_bb,
            'defense_range_percent': defense_percent,
            'opening_range': opening_range,
            'range_percent': round(len(opening_range) / 169 * 100, 2),
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
        "attack_range_percent": 15
    }
    """
    try:
        stack_depth_bb, attack_percent = _table_parameters(
            request.get_json(silent=True), 'attack_range_percent', 15,
        )

        result = simulator.generate_defense_table(stack_depth_bb, attack_percent)

        # Filtrar solo manos que entran en rango
        defending_range = [hand for hand, info in result.items() if info['include']]

        return jsonify({
            'status': 'success',
            'stack_depth_bb': stack_depth_bb,
            'attack_range_percent': attack_percent,
            'defending_range': defending_range,
            'range_percent': round(len(defending_range) / 169 * 100, 2),
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

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)
