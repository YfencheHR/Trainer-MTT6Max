"""Formato de la respuesta del modelo: esquema JSON que se le exige y lectura de la distribución de acciones."""

import json

from trainer_mtt.ai.actions import ACTIONS


class ActionAnswer:
    @staticmethod
    def legal_or_all(legal_actions):
        legal = [a for a in (legal_actions or ACTIONS) if a in ACTIONS]
        if not legal:
            raise ValueError('legal_actions has no valid action')

        return legal

    @staticmethod
    def schema(legal_actions, form=None, analysis=False):
        """Esquema JSON que obliga al modelo a dar un número por cada acción permitida y nada más.

        `form` = {campo: [valores posibles]}: las preguntas del cuestionario que el modelo contesta ANTES de las probabilidades
        (en este orden, así razona por el camino). Esos campos se ignoran al leer la distribución.
        `analysis`: un campo de texto libre antes de las probabilidades (razonamiento breve); también se ignora al leer.
        """
        properties = {'analysis': {'type': 'string'}} if analysis else {}
        properties.update({name: {'type': 'string', 'enum': list(options)} for name, options in (form or {}).items()})
        properties.update({a: {'type': 'number', 'minimum': 0, 'maximum': 1} for a in legal_actions})

        return {'type': 'object', 'properties': properties, 'required': list(properties), 'additionalProperties': False}

    @classmethod
    def parse(cls, text, legal_actions):
        """Texto del modelo -> distribución normalizada sobre las acciones permitidas."""
        legal = cls.legal_or_all(legal_actions)
        try:
            raw = json.loads(text)
        except (TypeError, ValueError) as error:
            raise ValueError(f'The model did not return JSON: {error}') from error
        if not isinstance(raw, dict):
            raise ValueError('The model answer is not a JSON object')

        values = {}
        for action in legal:
            value = raw.get(action, 0)
            if isinstance(value, bool) or not isinstance(value, (int, float)) or value != value or value < 0:
                value = 0.0
            values[action] = float(value)
        total = sum(values.values())
        if total <= 0:
            raise ValueError('The model gave no probability to any allowed action')

        return {a: round(v / total, 6) for a, v in values.items() if v > 0}
