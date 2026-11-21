"""Rutas de equity preflop: simulación, tablas de ataque y defensa, predicción, análisis y caché."""

from flask import Blueprint, jsonify, request

from trainer_mtt.api.validation import EquityRequest, table_parameters
from trainer_mtt.equity.tables import PreflopTables


def equity_blueprint(simulator):
    blueprint = Blueprint('equity', __name__)
    tables = PreflopTables(simulator)

    @blueprint.post('/api/equity/simulate')
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

    @blueprint.post('/api/tables/generate/attack')
    def generate_attack_table():
        """
        Genera tabla de ataque para un stack depth

        Request:
        {
            "stack_depth_bb": 50,
            "defense_range_percent": 20,
            "defense_range_strs": ["AA", "KK", "AKs"],  # Optional
            "equity_threshold": 0.5,  # Optional
            "iterations": 1000  # Optional
        }
        """
        try:
            data = request.get_json(silent=True)
            stack_depth_bb, defense_percent = table_parameters(data, 'defense_range_percent', 20)

            result = tables.attack(
                stack_depth_bb,
                defense_percent,
                defense_range_strs=data.get('defense_range_strs'),
                equity_threshold=data.get('equity_threshold', 0.5),
                iterations=data.get('iterations', 1000),
            )

            opening_range = [hand for hand, info in result.items() if info['include']]

            return jsonify({
                'status': 'success',
                'stack_depth_bb': stack_depth_bb,
                'opening_range': opening_range,
                'range_percent': round(len(opening_range) / 169 * 100, 2),
                'cache_stats': simulator.get_cache_stats(),
                'tables': result
            })

        except (TypeError, ValueError) as e:
            return jsonify({'status': 'error', 'message': str(e)}), 400
        except Exception as e:
            return jsonify({'status': 'error', 'message': str(e)}), 500

    @blueprint.post('/api/tables/generate/defense')
    def generate_defense_table():
        """
        Genera tabla de defensa para un stack depth

        Request:
        {
            "stack_depth_bb": 50,
            "attack_range_percent": 15,
            "attack_range_strs": ["AKs", "AKo"],  # Optional
            "equity_threshold": 0.45,  # Optional
            "iterations": 500  # Optional
        }
        """
        try:
            data = request.get_json(silent=True)
            stack_depth_bb, attack_percent = table_parameters(data, 'attack_range_percent', 15)

            result = tables.defense(
                stack_depth_bb,
                attack_percent,
                attack_range_strs=data.get('attack_range_strs'),
                equity_threshold=data.get('equity_threshold', 0.45),
                iterations=data.get('iterations', 500),
            )

            defending_range = [hand for hand, info in result.items() if info['include']]

            return jsonify({
                'status': 'success',
                'stack_depth_bb': stack_depth_bb,
                'defending_range': defending_range,
                'range_percent': round(len(defending_range) / 169 * 100, 2),
                'cache_stats': simulator.get_cache_stats(),
                'tables': result
            })

        except (TypeError, ValueError) as e:
            return jsonify({'status': 'error', 'message': str(e)}), 400
        except Exception as e:
            return jsonify({'status': 'error', 'message': str(e)}), 500

    @blueprint.post('/api/predict')
    def predict():
        """Give a preflop call/fold estimate from Monte Carlo equity and pot odds."""
        data = request.get_json(silent=True)
        try:
            parsed = EquityRequest.parse(data)
            pot = data.get('pot')
            call_amount = data.get('call_amount')
            if (isinstance(pot, bool) or not isinstance(pot, (int, float)) or pot < 0
                    or isinstance(call_amount, bool) or not isinstance(call_amount, (int, float)) or call_amount < 0):
                raise ValueError('pot and call_amount must be non-negative numbers')

            result = simulator.simulate_multiway_equity(parsed.hero_hand, parsed.opponent_ranges, parsed.iterations)
            equity = result['equity']
            pot_odds = call_amount / (pot + call_amount) if call_amount else 0.0
            call_ev = equity * (pot + call_amount) - call_amount
            decision = 'CHECK' if call_amount == 0 else ('CALL' if equity >= pot_odds else 'FOLD')

            return jsonify({
                'status': 'success',
                'message': 'Preflop estimate calculated from Monte Carlo equity and pot odds.',
                'hero_hand': parsed.hero_hand,
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

    @blueprint.post('/api/analyze')
    def analyze():
        """Return real Monte Carlo equity and outcome counts for preflop ranges."""
        data = request.get_json(silent=True)
        try:
            parsed = EquityRequest.parse(data)
            result = simulator.simulate_multiway_equity(parsed.hero_hand, parsed.opponent_ranges, parsed.iterations)

            return jsonify({
                'status': 'success',
                'message': 'Preflop Monte Carlo analysis completed.',
                'analysis': {
                    'street': 'preflop',
                    'hero_hand': parsed.hero_hand,
                    'opponent_range_sizes': [len(hand_range) for hand_range in parsed.opponent_ranges],
                    **result,
                },
            })
        except (TypeError, ValueError) as error:
            return jsonify({'status': 'error', 'message': str(error)}), 400

    @blueprint.get('/api/cache/stats')
    def cache_stats():
        """Devuelve estadísticas del caché de equity."""
        return jsonify({'status': 'success', 'cache_stats': simulator.get_cache_stats()})

    @blueprint.post('/api/cache/clear')
    def cache_clear():
        """Limpia el caché de equity."""
        simulator.clear_cache()

        return jsonify({'status': 'success', 'message': 'Cache cleared', 'cache_stats': simulator.get_cache_stats()})

    return blueprint
